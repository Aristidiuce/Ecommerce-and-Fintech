<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Models\{Product, Category};
use Illuminate\Http\{Request, JsonResponse};

class ProductController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $products = Product::with('category')
            ->where('status', 'active')
            ->when($request->search, fn($q) => $q->where('name', 'like', "%{$request->search}%")->orWhere('name_sw', 'like', "%{$request->search}%"))
            ->when($request->category, fn($q) => $q->whereHas('category', fn($cq) => $cq->where('slug', $request->category)))
            ->orderByDesc('created_at')
            ->paginate(20);
        return response()->json(['success' => true, 'data' => $products]);
    }

    public function show(int $id): JsonResponse
    {
        $product = Product::with('category', 'supplier')->where('status', 'active')->findOrFail($id);
        return response()->json(['success' => true, 'data' => $product]);
    }

    public function featured(): JsonResponse
    {
        $products = Product::with('category')->where('status', 'active')->where('badge', 'hot')->limit(8)->get();
        return response()->json(['success' => true, 'data' => $products]);
    }

    public function byCategory(string $slug): JsonResponse
    {
        $products = Product::with('category')->whereHas('category', fn($q) => $q->where('slug', $slug))->where('status', 'active')->paginate(20);
        return response()->json(['success' => true, 'data' => $products]);
    }

    public function related(int $id): JsonResponse
    {
        $product = Product::findOrFail($id);
        $related = Product::where('category_id', $product->category_id)->where('id', '!=', $id)->where('status', 'active')->limit(4)->get();
        return response()->json(['success' => true, 'data' => $related]);
    }

    public function categories(): JsonResponse
    {
        $cats = Category::where('status', 'active')->orderBy('sort_order')->get();
        return response()->json(['success' => true, 'data' => $cats]);
    }
}
