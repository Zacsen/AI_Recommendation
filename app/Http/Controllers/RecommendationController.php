<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\RecommendationService;

class RecommendationController extends Controller
{
    protected RecommendationService $service;

    public function __construct(RecommendationService $service)
    {
        $this->service = $service;
    }

    /**
     * Show the dashboard
     */
    public function index()
    {
        return view('recommender.index'); // adjust path if needed
    }

    /**
     * Run recommendations and return JSON
     */
    public function run(Request $request)
    {
        $focus = $request->input('focus', 'all'); // Online / OTC / all

        try {
            $results = $this->service->computeAll($focus);

            // Map results to include paired products per main product
            $mappedResults = [];
            foreach ($results as $product) {
                $mappedResults[] = [
                    'id' => $product['id'],
                    'name' => $product['name'],
                    'final_score' => $product['final_score'],
                    'components' => $product['components'],
                    'pairs' => array_map(fn($p) => [
                        'name' => $p['name'],
                        'score' => $p['score']
                    ], $product['pairs'] ?? [])
                ];
            }

            return response()->json([
                'success' => true,
                'results' => $mappedResults
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
