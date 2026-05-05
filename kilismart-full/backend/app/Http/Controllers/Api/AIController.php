<?php
// ============================================================
//  KiliSmart — AI API Controller
//  All AI-powered endpoints
// ============================================================

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AI\{SmartSavingsAdvisor, ProductRecommender, NaturalLanguageSearch, PriceTrendPredictor, CustomerSupportAI};
use App\Models\{Product, User};
use Illuminate\Http\{Request, JsonResponse};

class AIController extends Controller
{
    public function __construct(
        protected SmartSavingsAdvisor  $advisor,
        protected ProductRecommender   $recommender,
        protected NaturalLanguageSearch $nlpSearch,
        protected PriceTrendPredictor  $pricePredictor,
        protected CustomerSupportAI    $supportAI,
    ) {}

    // POST /api/v1/ai/savings-advice
    // Body: { product_id: 1 }
    public function savingsAdvice(Request $request): JsonResponse
    {
        $user    = $request->user();
        $product = Product::findOrFail($request->product_id);

        $advice = $this->advisor->advise($user, $product->retail_price);

        return response()->json(['success' => true, 'data' => $advice]);
    }

    // GET /api/v1/ai/recommendations
    public function recommendations(Request $request): JsonResponse
    {
        $user    = $request->user();
        $results = $this->recommender->recommend($user, limit: 6);

        return response()->json(['success' => true, 'data' => $results]);
    }

    // GET /api/v1/ai/search?q=simu ya bei nafuu
    public function nlpSearchEndpoint(Request $request): JsonResponse
    {
        $query   = $request->get('q', '');
        $results = $this->nlpSearch->search($query);

        return response()->json(['success' => true, 'data' => $results]);
    }

    // GET /api/v1/ai/price-trend/{productId}
    public function priceTrend(int $productId): JsonResponse
    {
        $product    = Product::findOrFail($productId);
        $prediction = $this->pricePredictor->predict($product);

        return response()->json(['success' => true, 'data' => $prediction]);
    }

    // POST /api/v1/ai/chat
    // Body: { message: "Ninawezaje kutoa pesa?" }
    public function chat(Request $request): JsonResponse
    {
        $user     = $request->user();
        $message  = $request->get('message', '');
        $response = $this->supportAI->respond($message, $user);

        return response()->json(['success' => true, 'data' => $response]);
    }

    // POST /api/v1/ai/chat (public — no auth required)
    public function chatPublic(Request $request): JsonResponse
    {
        $message  = $request->get('message', '');
        $response = $this->supportAI->respond($message);

        return response()->json(['success' => true, 'data' => $response]);
    }
}
