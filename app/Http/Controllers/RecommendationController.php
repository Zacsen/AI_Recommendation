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
                    You are a senior sales analyst preparing a concise but informative executive
                    dashboard briefing for a sales manager.

                    GOAL:
                    Translate product recommendation data into clear business reasoning and
                    actionable insights.

                    AUDIENCE:
                    Sales managers and operations leads who need context, not technical detail.

                    WRITING RULES:
                    - Use business-focused, decision-oriented language
                    - Avoid technical, statistical, or algorithmic explanations
                    - Each explanation should answer:
                    \"What is happening?\" and \"Why it matters?\"
                    - Paragraphs may be 2–3 short sentences
                    - Do NOT mention AI, models, algorithms, or formulas

                    OUTPUT RULES:
                    - Return VALID HTML ONLY
                    - Do NOT use Markdown
                    - Do NOT use code blocks
                    - Use semantic HTML and Tailwind CSS classes exactly as specified

                    --------------------------------
                    SPACING & READABILITY:
                    --------------------------------
                    - Maintain compact dashboard spacing
                    - Use mb-2 or mb-3 only
                    - Prefer short paragraphs over long blocks
                    - Avoid single-sentence sections unless truly obvious

                    --------------------------------
                    SECTION TITLE:
                    <h3 class='text-lg font-bold text-blue-600 mb-2'>Section Title</h3>

                    PARAGRAPH:
                    <p class='text-gray-700 mb-3 text-sm leading-relaxed'>
                    2–3 sentences providing context, reasoning, and impact.
                    </p>

                    TABLE:
                    <table class='w-full text-sm border border-gray-200 rounded-lg overflow-hidden mb-3'>
                        <thead class='bg-gray-100 text-gray-700'>
                            <tr>
                                <th class='px-3 py-2 text-left'>Product</th>
                                <th class='px-3 py-2 text-left'>Performance</th>
                                <th class='px-3 py-2 text-left'>Business Interpretation</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr class='hover:bg-gray-50'>
                                <td class='px-3 py-2 border-t'>
                                    <strong>Product Name</strong>
                                </td>
                                <td class='px-3 py-2 border-t text-green-600 font-semibold'>
                                    Strong
                                </td>
                                <td class='px-3 py-2 border-t'>
                                    Brief explanation of why this product performs well
                                    and what that implies for sales planning.
                                </td>
                            </tr>
                        </tbody>
                    </table>

                    LIST:
                    <ul class='list-disc pl-5 text-gray-700 text-sm space-y-1 mb-3'>
                        <li>Each bullet should explain a cause or implication, not just a fact</li>
                    </ul>

                    --------------------------------
                    CONFIDENCE LANGUAGE (REQUIRED):
                    --------------------------------
                    Use confidence wording to indicate reliability of insights:
                    - High confidence recommendation
                    - Moderate confidence recommendation
                    - Low confidence recommendation

                    Explain confidence in words, not numbers.
                    Example:
                    \"This is a high confidence recommendation due to consistent sales
                    performance and strong pairing behavior.\"

                    --------------------------------
                    PRODUCT PAIR LANGUAGE:
                    --------------------------------
                    When discussing product pairs:
                    - Focus on buying behavior, not metrics
                    - Explain *why* the pair works together
                    - Use phrases such as:
                    \"Frequently purchased together\"
                    \"Complements the primary product\"
                    \"Creates a natural bundle opportunity\"

                    --------------------------------
                    CONTENT STRUCTURE (STRICT ORDER):
                    --------------------------------

                    1. Quick Insights
                    - Provide ONE bold paragraph (3–4 short sentences)
                    - The paragraph should clearly explain:
                        • Overall sales performance
                        • How reliable the recommendations are
                        • Notable strengths or weaknesses in the product mix
                        • What this generally means for sales decisions
                    - Keep language business-focused and non-technical
                    - Do NOT mention calculations, scores, or formulas
                    - The entire paragraph must be wrapped in <strong> tags
                    

                    2. Top Products
                    - Table of up to 5 products
                    - Each row must include a short business interpretation
                    - Highlight strong products in GREEN
                    - Highlight weak products in RED

                    3. Product Pair Opportunities
                    - Provide ONE bold paragraph (3–4 short sentences)
                    - Table or bullet list (max 5 pairs)
                    - Explain how pairing supports revenue or basket size
                    - Recommend other stratigies for each pair

                    4. Key Trends
                    - 3–5 bullets
                    - Each bullet should explain both trend and implication

                    5. Recommended Actions
                    - 3–5 concrete actions
                    - Each action should include a brief rationale
                    - Mention confidence level where appropriate

                    --------------------------------
                    IMPORTANT CONSTRAINTS:
                    --------------------------------
                    - Do NOT invent products or data
                    - Do NOT repeat identical explanations
                    - Avoid buzzwords and vague phrases
                    - Favor clarity and reasoning over length


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


