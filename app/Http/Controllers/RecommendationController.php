<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\RecommendationService;
use Illuminate\Support\Facades\Http;

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

    /**
     * Run AI recommendations and return JSON
     */
    public function aiInterpret(Request $request)
    {
        $products = $request->input('products', []);

        if (empty($products)) {
            return response()->json([
                'success' => false,
                'message' => 'No products provided.'
            ]);
        }

        try {
            $apiKey = config('services.ollama.api_key');

            $messages = [
                [
                    'role' => 'user',
                    'content' =>
                        $prompt = "
                    You are a sales analyst writing for a sales manager.

                        TASK:
                        Interpret the product recommendation scores below.

                        STYLE & FORMAT RULES:
                        - Keep it brief and non-technical
                        - Use business-friendly language
                        - Return VALID HTML ONLY
                        - DO NOT use markdown
                        - DO NOT use code blocks
                        - Use semantic HTML and Tailwind CSS classes

                        LAYOUT REQUIREMENTS:

                        <h3 class='text-xl font-bold text-blue-600 mb-3'>Section title</h3>

                        <p class='text-gray-700 mb-4'>Short explanation</p>

                        TABLE STYLE:
                        <table class='w-full text-sm border border-gray-200 rounded-xl overflow-hidden mb-6'>
                        <thead class='bg-gray-100 text-gray-700'>
                        <tr>
                        <th class='px-4 py-2 text-left'>...</th>
                        </tr>
                        </thead>
                        <tbody>
                        <tr class='hover:bg-gray-50'>
                        <td class='px-4 py-2 border-t'>...</td>
                        </tr>
                        </tbody>
                        </table>

                        LIST STYLE:
                        <ul class='list-disc pl-6 text-gray-700 space-y-1 mb-6'>
                        <li>...</li>
                        </ul>

                        STRUCTURE:
                        1. Quick Insights (short paragraph)
                        2. Top Products (table)
                        3. Key Trends (bullet list)
                        4. Recommended Actions (bullet list)

                        IMPORTANT:
                        - Highlight strong products in GREEN text
                        - Highlight weak products in RED text
                        - Emphasize product names using <strong>

                        DATA:
                    " . json_encode($products, JSON_PRETTY_PRINT)
                ]
            ];

            $response = Http::withHeaders([
                'Authorization' => "Bearer {$apiKey}",
                'Content-Type'  => 'application/json',
            ])->post('https://ollama.com/api/chat', [
                'model'    => 'gpt-oss:120b',
                'messages' => $messages,
                'stream'   => false
            ]);

            $result = $response->json();

            // ✅ Correct for Ollama Cloud
            $insight = $result['message']['content'] ?? 'AI did not return any insight.';

            return response()->json([
                'success' => true,
                'insight' => $insight
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error calling Ollama Cloud API: ' . $e->getMessage()
            ]);
        }
    }

}


