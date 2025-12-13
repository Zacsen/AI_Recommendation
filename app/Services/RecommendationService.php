<?php

namespace App\Services;

use Phpml\Association\Apriori;
use Illuminate\Support\Facades\DB;

class RecommendationService
{
    protected float $minSupport = 0.01;
    protected float $minConfidence = 0.1;


    protected function normalizeText(string $s): string
        {
            $s = mb_strtolower($s, 'UTF-8');
            $s = preg_replace('/[^\p{L}\p{N}\s]+/u', ' ', $s);
            $s = preg_replace('/\s+/u', ' ', $s);
            return trim($s);
        }

        protected function tokenize(string $s): array
        {
            if ($s === '') return [];
            return preg_split('/\s+/u', $s, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        }

        protected function cosine(array $a, array $b): float
        {
            $len = max(count($a), count($b));
            $dot = 0.0; $na = 0.0; $nb = 0.0;
            for ($i = 0; $i < $len; $i++) {
                $ai = $a[$i] ?? 0.0;
                $bi = $b[$i] ?? 0.0;
                $dot += $ai * $bi;
                $na += $ai * $ai;
                $nb += $bi * $bi;
            }
            if ($na == 0.0 || $nb == 0.0) return 0.0;
            return $dot / (sqrt($na) * sqrt($nb));
        }
    /**
     * Compute all recommendations
     */
    public function computeAll(string $focus = 'all'): array
    {
        // 1. Load all products
        $products = DB::table('products')->pluck('name', 'id')->toArray();

        // 2. Prepare transactions for MBA
        $transactions = $this->getTransactions();

        // 3. Compute MBA
        $mbaResult = $this->computeMBA($transactions);

        // 4. Compute other components
        $contentSim = $this->computeContentSimilarity();
        $collabSim = $this->computeItemCollabSimilarity();
        $season = $this->computeSeasonality();
        $trend = $this->computeTrend();
        $forecast = $this->computeForecast();
        $stock = $this->computeStockFactor();
        $channel = $this->computeChannelSales();

        $results = [];

        foreach ($products as $id => $name) {

            $baseScore =
                ($mbaResult['scores'][$id] ?? 0) * 0.3
                + ($contentSim[$id] ?? 0) * 0.2
                + ($collabSim[$id] ?? 0) * 0.2
                + ($season[$id] ?? 0) * 0.05
                + ($trend[$id] ?? 0) * 0.05
                + ($forecast[$id] ?? 0) * 0.1
                + ($stock[$id] ?? 0) * 0.05;

            // Boost score according to channel focus
           $channelBoost = 0;

            if ($focus === 'Online') {
                $channelBoost = ($channel[$id]['online_ratio'] ?? 0) * 0.1;
            } elseif ($focus === 'OTC') {
                $channelBoost = ($channel[$id]['otc_ratio'] ?? 0) * 0.1;
            } else {
                $channelBoost =
                    (($channel[$id]['online_ratio'] ?? 0) * 0.05)
                + (($channel[$id]['otc_ratio'] ?? 0) * 0.05);
            }

            $totalSales = $channel[$id]['total_sales'] ?? 0;
            // Sales priority logic
            if ($totalSales === 0) {
                $salesMultiplier = 0.6;       // 🚫 no sales → strong penalty
            } elseif ($totalSales < 5) {
                $salesMultiplier = 0.85;      // ⚠ low sales → mild penalty
            } else {
                $salesMultiplier = 1.0;       // ✅ healthy sales
            }

            // Prepare pairs for this product
            $pairs = [];
            foreach ($mbaResult['pairs'][$id] ?? [] as $pid => $score) {
                // Optional: filter pairs by focus
                if ($focus === 'Online' && ($channel[$pid]['online_ratio'] ?? 0) < 0.5) continue;
                if ($focus === 'OTC' && ($channel[$pid]['otc_ratio'] ?? 0) < 0.5) continue;

                if (isset($products[$pid])) {
                    $pairs[] = [
                        'name' => $products[$pid],
                        'score' => $score, // normalize to 0..1
                    ];
                }
            }

            $finalScore = ($baseScore + $channelBoost) * $salesMultiplier;

            $results[] = [
                'id' => $id,
                'name' => $name,
                'final_score' => round($finalScore, 4),
                'components' => [
                    'mba' => $mbaResult['scores'][$id] ?? 0,
                    'content' => $contentSim[$id] ?? 0,
                    'collab' => $collabSim[$id] ?? 0,
                    'season' => $season[$id] ?? 0,
                    'trend' => $trend[$id] ?? 0,
                    'forecast' => $forecast[$id] ?? 0,
                    'stock_multiplier' => $stock[$id] ?? 0,
                    'online_ratio' => $channel[$id]['online_ratio'] ?? 0,
                    'otc_ratio' => $channel[$id]['otc_ratio'] ?? 0,
                    'total_sales' => $totalSales,
                ],
                'pairs' => $pairs,
            ];
        }

        // Sort by final score descending
        usort($results, fn($a, $b) => $b['final_score'] <=> $a['final_score']);

        return $results;
    }

    /**
     * Fetch all transactions from OTC and Online
     */
    protected function getTransactions(): array
    {
        $transactions = [];

        // OTC
        $sales = DB::table('sale_items')->select('sale_id', 'product_id')->get()->groupBy('sale_id');
        foreach ($sales as $tx) {
            $transactions[] = $tx->pluck('product_id')->toArray();
        }

        // Online
        $orders = DB::table('order_items')->select('order_id', 'product_id')->get()->groupBy('order_id');
        foreach ($orders as $tx) {
            $transactions[] = $tx->pluck('product_id')->toArray();
        }

        return $transactions;
    }

    /**
     * Compute MBA (Apriori)
     */
    protected function computeMBA(array $transactions): array
    {
        if (empty($transactions)) {
            return ['scores' => [], 'pairs' => []];
        }

        // Convert transactions to Apriori format
        $samples = [];
        foreach ($transactions as $tx) {
            $samples[] = array_map(fn($id) => "p_{$id}", $tx);
        }

        // Train Apriori
        $apriori = new Apriori($this->minSupport, $this->minConfidence);
        $apriori->train($samples, []);
        $rules = $apriori->getRules();

        // MAIN product scores
        $scores = [];

        // CLEAN paired products (no duplicates)
        // Format: $pairsPerProduct[mainProduct][pairedProduct] = score
        $pairsPerProduct = [];

        foreach ($rules as $rule) {

            $antecedent = $rule['antecedent'] ?? [];
            $consequent = $rule['consequent'] ?? [];
            $support = $rule['support'] ?? 0;
            $confidence = $rule['confidence'] ?? 0;

            // Raw MBA strength
            $value = $support * $confidence;

            // ADD score only to ANTECEDENT (fixes inflated scores)
            foreach ($antecedent as $lbl) {
                $id = (int) str_replace('p_', '', $lbl);
                $scores[$id] = ($scores[$id] ?? 0) + $value;
            }

            // SAVE paired relationships (Antecedent → Consequent ONLY)
            foreach ($antecedent as $aLbl) {
                $mainId = (int) str_replace('p_', '', $aLbl);

                foreach ($consequent as $cLbl) {
                    $pairId = (int) str_replace('p_', '', $cLbl);

                    // Keep strongest score between same pairs
                    $pairsPerProduct[$mainId][$pairId] = max(
                        $pairsPerProduct[$mainId][$pairId] ?? 0,
                        $value
                    );
                }
            }
        }

        // Normalize main MBA scores to 0..1
        $maxScore = max($scores ?: [1]);
        foreach ($scores as $id => $v) {
            $scores[$id] = $maxScore > 0 ? ($v / $maxScore) : 0.0;
        }

        // Normalize paired product scores 0..1 for each MAIN product
        foreach ($pairsPerProduct as $mainId => $pairs) {
            $maxPairScore = max($pairs ?: [1]);

            foreach ($pairs as $pairId => $v) {
                $pairsPerProduct[$mainId][$pairId] =
                    $maxPairScore > 0 ? ($v / $maxPairScore) : 0.0;
            }
        }

        return [
            'scores' => $scores,
            'pairs' => $pairsPerProduct
        ];
    }

    /**
     * Compute channel ratios (Online vs OTC)
     */
    protected function computeChannelSales(): array
        {
            $products = DB::table('products')->pluck('id')->toArray();

            $otc = DB::table('sale_items')
                ->select('product_id', DB::raw('SUM(quantity) as total'))
                ->groupBy('product_id')->pluck('total', 'product_id')->toArray();

            $online = DB::table('order_items')
                ->select('product_id', DB::raw('SUM(quantity) as total'))
                ->groupBy('product_id')->pluck('total', 'product_id')->toArray();

            $scores = [];
            foreach ($products as $id) {
                $otcQty = $otc[$id] ?? 0;
                $onlineQty = $online[$id] ?? 0;
                $total = $otcQty + $onlineQty;

                if ($total == 0) {
                    $scores[$id] = ['otc_ratio'=>0, 'online_ratio'=>0];
                } else {
                    $scores[$id] = [
                        'otc_ratio' => $otcQty / $total,
                        'online_ratio' => $onlineQty / $total,
                        'total_sales' => $total
                    ];
                }
            }
            return $scores;
        }

        /**
         * Stock factor (low stock → high score)
         */
        protected function computeStockFactor(): array
    {
        // Get warning thresholds
        $thresholds = DB::table('products')
            ->pluck('low_stock_warning_threshold', 'id')
            ->toArray();

        // Get total stock per product
        $stockQty = DB::table('stocks')
            ->select('product_id', DB::raw('SUM(quantity) as total'))
            ->groupBy('product_id')
            ->pluck('total', 'product_id')
            ->toArray();

        $scores = [];

        foreach ($thresholds as $id => $threshold) {
            $qty = $stockQty[$id] ?? 0;

            // No stock at all → do not recommend
            if ($qty <= 0) {
                $scores[$id] = 0.0;
                continue;
            }

            // Safety fallback if threshold is missing or zero
            if ($threshold <= 0) {
                $scores[$id] = 0.3;
                continue;
            }

            /*
            * Ratio-based scoring:
            * qty == threshold → 1.0
            * qty == 2x threshold → 0.5
            * qty >= 3x threshold → ~0.2
            */
            $ratio = $qty / $threshold;

            if ($ratio <= 1) {
                $scores[$id] = 1.0;               // critical stock
            } elseif ($ratio <= 2) {
                $scores[$id] = 0.7;
            } elseif ($ratio <= 3) {
                $scores[$id] = 0.4;
            } else {
                $scores[$id] = 0.2;               // overstocked
            }
        }

        return $scores;
    }


    // Placeholder methods for other components (replace with real calculations)
    protected function computeContentSimilarity(): array
    {
        // load name + description
        $rows = DB::table('products')->select('id', 'name', 'description')->get();

        $ids = [];
        $docs = [];
        foreach ($rows as $r) {
            $ids[] = $r->id;
            $docs[] = $this->normalizeText(($r->name ?? '') . ' ' . ($r->description ?? ''));
        }

        if (count($docs) <= 1) {
            $out = [];
            foreach ($ids as $id) $out[$id] = 0.0;
            return $out;
        }

        // Build vocabulary and term frequencies
        $vocab = [];
        $tfs = [];
        foreach ($docs as $docIdx => $doc) {
            $tokens = $this->tokenize($doc);
            $counts = [];
            foreach ($tokens as $t) {
                $counts[$t] = ($counts[$t] ?? 0) + 1;
                $vocab[$t] = true;
            }
            $tfs[$docIdx] = $counts;
        }

        $vocabList = array_keys($vocab);
        $vocabIndex = array_flip($vocabList);
        $N = count($docs);

        // document frequency
        $df = array_fill(0, count($vocabList), 0);
        foreach ($tfs as $counts) {
            foreach ($counts as $term => $_) {
                $df[$vocabIndex[$term]]++;
            }
        }

        // build tf-idf vectors
        $vectors = [];
        foreach ($tfs as $i => $counts) {
            $vec = array_fill(0, count($vocabList), 0.0);
            $maxTf = $counts ? max($counts) : 1;
            foreach ($counts as $term => $c) {
                $idx = $vocabIndex[$term];
                $tf = $c / $maxTf;
                $idf = log(1 + ($N / max(1, $df[$idx])));
                $vec[$idx] = $tf * $idf;
            }
            $vectors[$i] = $vec;
        }

        // compute average cosine similarity per doc
        $scores = [];
        $n = count($vectors);
        for ($i = 0; $i < $n; $i++) {
            $sum = 0.0; $count = 0;
            for ($j = 0; $j < $n; $j++) {
                if ($i === $j) continue;
                $sum += $this->cosine($vectors[$i], $vectors[$j]);
                $count++;
            }
            $avg = $count ? ($sum / $count) : 0.0;
            $scores[$ids[$i]] = $avg;
        }

        // normalize 0..1
        $max = max($scores ?: [1]);
        foreach ($scores as $k => $v) $scores[$k] = $max > 0 ? ($v / $max) : 0.0;

        return $scores;
    }

    protected function computeItemCollabSimilarity(): array
    {
        // build transaction list (reuse getTransactions if desired)
        $transactions = $this->getTransactions(); // array of arrays of product ids

        // collect product ids from DB to ensure consistent ordering
        $productRows = DB::table('products')->select('id')->get();
        $productIds = array_map(fn($r) => $r->id, $productRows->toArray());
        if (empty($productIds)) return [];

        $nTx = count($transactions);
        $vectors = [];
        // init zero vectors
        foreach ($productIds as $pid) $vectors[$pid] = array_fill(0, $nTx, 0);

        foreach ($transactions as $tIdx => $tx) {
            foreach ($tx as $pid) {
                if (isset($vectors[$pid])) $vectors[$pid][$tIdx] = 1;
            }
        }

        // compute average of top-K similarities per product
        $scores = array_fill_keys($productIds, 0.0);
        foreach ($productIds as $pid) {
            $sims = [];
            foreach ($productIds as $other) {
                if ($pid === $other) continue;
                $sims[] = $this->cosine($vectors[$pid], $vectors[$other]);
            }
            rsort($sims);
            $topk = array_slice($sims, 0, min(5, count($sims)));
            $scores[$pid] = $topk ? (array_sum($topk) / count($topk)) : 0.0;
        }

        // normalize
        $max = max($scores ?: [1]);
        foreach ($scores as $k => $v) $scores[$k] = $max > 0 ? ($v / $max) : 0.0;

        return $scores;
    }



    protected function computeSeasonality(): array
    {
        // aggregate monthly sales (OTC + Online)
        $rows1 = DB::table('sale_items')
            ->selectRaw("sale_items.product_id, YEAR(sales.datetime_sold) as y, MONTH(sales.datetime_sold) as m, SUM(sale_items.quantity) as qty")
            ->join('sales','sale_items.sale_id','=','sales.id')
            ->groupBy('sale_items.product_id','y','m')
            ->get();

        $rows2 = DB::table('order_items')
            ->selectRaw("order_items.product_id, YEAR(orders.datetime_order) as y, MONTH(orders.datetime_order) as m, SUM(order_items.quantity) as qty")
            ->join('orders','order_items.order_id','=','orders.id')
            ->groupBy('order_items.product_id','y','m')
            ->get();

        $monthly = [];
        foreach (array_merge($rows1->toArray(), $rows2->toArray()) as $r) {
            $pid = $r->product_id;
            $ym = sprintf('%04d-%02d', $r->y, $r->m);
            $monthly[$pid][$ym] = ($monthly[$pid][$ym] ?? 0) + (float)$r->qty;
        }

        $seasonality = [];
        $now = date('Y-m');
        foreach ($monthly as $pid => $series) {
            $vals = array_values($series);
            $mean = count($vals) ? array_sum($vals) / count($vals) : 0.0;
            $variance = 0.0;
            foreach ($vals as $v) $variance += ($v - $mean) * ($v - $mean);
            $std = count($vals) ? sqrt($variance / count($vals)) : 0.0;
            $current = $series[$now] ?? 0;
            $z = $std > 0 ? (($current - $mean) / $std) : ($current > 0 ? 1.0 : 0.0);
            $seasonality[$pid] = 1.0 / (1.0 + exp(-$z)); // logistic mapping to 0..1
        }

        // for products with no history, set 0
        $allProducts = DB::table('products')->pluck('id')->toArray();
        foreach ($allProducts as $pid) if (!isset($seasonality[$pid])) $seasonality[$pid] = 0.0;

        return $seasonality;
    }


   protected function computeTrend(): array
{
        // Reuse monthly aggregation (same as seasonality)
        $rows1 = DB::table('sale_items')
            ->selectRaw("sale_items.product_id, YEAR(sales.datetime_sold) as y, MONTH(sales.datetime_sold) as m, SUM(sale_items.quantity) as qty")
            ->join('sales','sale_items.sale_id','=','sales.id')
            ->groupBy('sale_items.product_id','y','m')
            ->get();

        $rows2 = DB::table('order_items')
            ->selectRaw("order_items.product_id, YEAR(orders.datetime_order) as y, MONTH(orders.datetime_order) as m, SUM(order_items.quantity) as qty")
            ->join('orders','order_items.order_id','=','orders.id')
            ->groupBy('order_items.product_id','y','m')
            ->get();

        $monthly = [];
        foreach (array_merge($rows1->toArray(), $rows2->toArray()) as $r) {
            $pid = $r->product_id;
            $ym = sprintf('%04d-%02d', $r->y, $r->m);
            $monthly[$pid][$ym] = ($monthly[$pid][$ym] ?? 0) + (float)$r->qty;
        }

        $trend = [];
        foreach ($monthly as $pid => $series) {
            ksort($series);
            $keys = array_keys($series);
            rsort($keys); // newest first
            $recentKeys = array_slice($keys, 0, 4); // last up to 4 months
            $recentVals = array_map(fn($k) => $series[$k] ?? 0, $recentKeys);
            $last = $recentVals[0] ?? 0;
            $prevAvg = count($recentVals) > 1 ? (array_sum(array_slice($recentVals, 1)) / max(1, count($recentVals)-1)) : ($last ?: 1);
            $growth = $prevAvg > 0 ? (($last - $prevAvg) / max(1, $prevAvg)) : 0.0;
            $trend[$pid] = 1.0 / (1.0 + exp(-$growth)); // map to 0..1
        }

        // fill missing products with 0
        $allProducts = DB::table('products')->pluck('id')->toArray();
        foreach ($allProducts as $pid) if (!isset($trend[$pid])) $trend[$pid] = 0.0;

        return $trend;
    }


        protected function computeForecast(): array
    {
        // aggregate monthly quantities
        $rows1 = DB::table('sale_items')
            ->selectRaw("sale_items.product_id, YEAR(sales.datetime_sold) as y, MONTH(sales.datetime_sold) as m, SUM(sale_items.quantity) as qty")
            ->join('sales','sale_items.sale_id','=','sales.id')
            ->groupBy('sale_items.product_id','y','m')
            ->get();

        $rows2 = DB::table('order_items')
            ->selectRaw("order_items.product_id, YEAR(orders.datetime_order) as y, MONTH(orders.datetime_order) as m, SUM(order_items.quantity) as qty")
            ->join('orders','order_items.order_id','=','orders.id')
            ->groupBy('order_items.product_id','y','m')
            ->get();

        $monthly = [];
        foreach (array_merge($rows1->toArray(), $rows2->toArray()) as $r) {
            $pid = $r->product_id;
            $ym = sprintf('%04d-%02d', $r->y, $r->m);
            $monthly[$pid][$ym] = ($monthly[$pid][$ym] ?? 0) + (float)$r->qty;
        }

        // predict next month using simple linear regression
        $preds = [];
        foreach ($monthly as $pid => $series) {
            ksort($series);
            $xs = []; $ys = []; $i = 0;
            foreach ($series as $ym => $qty) {
                $xs[] = $i++;
                $ys[] = $qty;
            }

            if (count($xs) < 3) {
                // fallback to last-month or small growth
                $last = end($ys) ?: 0.0;
                $preds[$pid] = $last;
                continue;
            }

            $n = count($xs);
            $sumX = array_sum($xs);
            $sumY = array_sum($ys);
            $sumXX = 0.0; $sumXY = 0.0;
            for ($k=0;$k<$n;$k++) {
                $sumXX += $xs[$k] * $xs[$k];
                $sumXY += $xs[$k] * $ys[$k];
            }
            $den = ($n * $sumXX - $sumX * $sumX);
            if (abs($den) < 1e-9) {
                $pred = $ys[$n-1];
            } else {
                $b = ($n * $sumXY - $sumX * $sumY) / $den;
                $a = ($sumY - $b * $sumX) / $n;
                $nextX = $n; // next timestep
                $pred = $a + $b * $nextX;
            }
            $preds[$pid] = max(0.0, (float)$pred);
        }

        // normalize predictions to 0..1
        $max = max($preds ?: [1]);
        $scores = [];
        foreach ($preds as $pid => $v) $scores[$pid] = $max > 0 ? ($v / $max) : 0.0;

        // ensure all products present
        $allProducts = DB::table('products')->pluck('id')->toArray();
        foreach ($allProducts as $pid) if (!isset($scores[$pid])) $scores[$pid] = 0.0;

        return $scores;
    }

}
