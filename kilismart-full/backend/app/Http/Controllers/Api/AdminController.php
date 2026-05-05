<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Models\{SavingPlan, User, Product, WithdrawalRequest, FulfillmentOrder, Transaction};
use App\Services\WalletService;
use Illuminate\Http\{Request, JsonResponse};
use Illuminate\Support\Facades\{DB, Log, Storage};

class AdminController extends Controller
{
    public function __construct(protected WalletService $wallet) {}

    public function overview(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => [
                'total_customers'    => User::where('role', 'customer')->count(),
                'active_plans'       => SavingPlan::where('status', 'active')->count(),
                'total_saved'        => SavingPlan::sum('amount_saved'),
                'pending_withdrawals'=> WithdrawalRequest::where('status', 'pending')->count(),
                'fulfillment_queue'  => FulfillmentOrder::where('status', 'queued')->count(),
                'revenue_this_month' => Transaction::where('type', 'withdrawal_fee')->whereMonth('created_at', now()->month)->sum('amount'),
            ]
        ]);
    }

    public function customers(Request $request): JsonResponse
    {
        $customers = User::where('role', 'customer')
            ->with(['wallet', 'savingPlans'])
            ->when($request->search, fn($q) => $q->where('full_name', 'like', "%{$request->search}%")->orWhere('phone', 'like', "%{$request->search}%"))
            ->orderByDesc('created_at')
            ->paginate(20);
        return response()->json(['success' => true, 'data' => $customers]);
    }

    public function products(Request $request): JsonResponse
    {
        $products = Product::with('category', 'supplier')
            ->when($request->search, fn($q) => $q->where('name', 'like', "%{$request->search}%"))
            ->orderByDesc('created_at')
            ->paginate(20);
        return response()->json(['success' => true, 'data' => $products]);
    }

    public function createProduct(Request $request): JsonResponse
    {
        $v = \Illuminate\Support\Facades\Validator::make($request->all(), [
            'name'         => 'required|string|max:255',
            'name_sw'      => 'required|string|max:255',
            'category_id'  => 'required|integer',
            'supplier_id'  => 'required|integer',
            'retail_price' => 'required|integer|min:1000',
            'cost_price'   => 'required|integer|min:500',
            'description'  => 'nullable|string',
        ]);
        if ($v->fails()) return response()->json(['success' => false, 'message' => $v->errors()->first()], 422);

        $product = Product::create([
            'name'         => $request->name,
            'name_sw'      => $request->name_sw,
            'category_id'  => $request->category_id,
            'supplier_id'  => $request->supplier_id,
            'retail_price' => $request->retail_price,
            'cost_price'   => $request->cost_price,
            'description'  => $request->description,
            'status'        => 'active',
            'badge'         => $request->badge,
        ]);

        return response()->json(['success' => true, 'data' => $product, 'message' => 'Bidhaa imeongezwa!']);
    }

    public function updateProduct(Request $request, int $id): JsonResponse
    {
        $product = Product::findOrFail($id);
        $product->update($request->only(['name','name_sw','retail_price','cost_price','description','badge','status','delivery_days','delivery_fee']));
        return response()->json(['success' => true, 'data' => $product]);
    }

    public function uploadProductImages(Request $request, int $id): JsonResponse
    {
        $product = Product::findOrFail($id);
        $images = [];
        foreach (['image_1','image_2','image_3','image_4'] as $key) {
            if ($request->hasFile($key)) {
                $path = $request->file($key)->store("products/{$id}", 'public');
                $images[] = Storage::url($path);
            }
        }
        if (!empty($images)) {
            $existing = $product->images ?? [];
            $product->update(['images' => array_merge($existing, $images)]);
        }
        return response()->json(['success' => true, 'images' => $product->fresh()->images]);
    }

    public function withdrawals(Request $request): JsonResponse
    {
        $withdrawals = WithdrawalRequest::with('user', 'savingPlan.product')
            ->when($request->status, fn($q) => $q->where('status', $request->status))
            ->orderByDesc('created_at')
            ->paginate(20);
        return response()->json(['success' => true, 'data' => $withdrawals]);
    }

    public function approveWithdrawal(int $id): JsonResponse
    {
        $wr = WithdrawalRequest::findOrFail($id);
        try {
            $result = $this->wallet->approveWithdrawal($wr);
            return response()->json(['success' => true, 'message' => $result['message']]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function rejectWithdrawal(Request $request, int $id): JsonResponse
    {
        $wr = WithdrawalRequest::where('status', 'pending')->findOrFail($id);
        $wr->update(['status' => 'rejected', 'rejection_reason' => $request->reason]);
        // Refund amount back to wallet
        $wr->user->wallet?->increment('balance', $wr->amount_requested);
        $wr->savingPlan?->increment('amount_saved', $wr->amount_requested);
        return response()->json(['success' => true, 'message' => 'Ombi limekataliwa na pesa imerudishwa.']);
    }

    public function fulfillmentQueue(Request $request): JsonResponse
    {
        $queue = FulfillmentOrder::with('user', 'product', 'savingPlan')
            ->when($request->status, fn($q) => $q->where('status', $request->status))
            ->orderByDesc('created_at')
            ->paginate(20);
        return response()->json(['success' => true, 'data' => $queue]);
    }

    public function updateFulfillmentStatus(Request $request, int $id): JsonResponse
    {
        $order = FulfillmentOrder::findOrFail($id);
        $order->update(['status' => $request->status, 'notes' => $request->notes]);
        return response()->json(['success' => true, 'message' => 'Status imesasishwa.']);
    }

    public function financialReport(): JsonResponse
    {
        $month = now()->month;
        $year  = now()->year;
        return response()->json(['success' => true, 'data' => [
            'total_deposits'  => Transaction::where('type','deposit')->whereMonth('created_at',$month)->whereYear('created_at',$year)->sum('amount'),
            'total_withdrawn' => Transaction::where('type','withdrawal_fee')->whereMonth('created_at',$month)->whereYear('created_at',$year)->sum('amount'),
            'wallet_balance'  => DB::table('wallets')->sum('balance'),
            'active_savers'   => SavingPlan::where('status','active')->count(),
        ]]);
    }

    // Stubs for remaining endpoints
    public function categories(): JsonResponse { return response()->json(['success'=>true,'data'=> DB::table('categories')->get()]); }
    public function createCategory(Request $request): JsonResponse { $cat=DB::table('categories')->insertGetId(['name'=>$request->name,'name_sw'=>$request->name_sw,'slug'=>\Illuminate\Support\Str::slug($request->name),'icon'=>$request->icon??'📦','created_at'=>now(),'updated_at'=>now()]); return response()->json(['success'=>true,'id'=>$cat]); }
    public function updateCategory(Request $request, int $id): JsonResponse { DB::table('categories')->where('id',$id)->update($request->only(['name','name_sw','icon','status'])); return response()->json(['success'=>true]); }
    public function suppliers(): JsonResponse { return response()->json(['success'=>true,'data'=> DB::table('suppliers')->get()]); }
    public function createSupplier(Request $request): JsonResponse { $id=DB::table('suppliers')->insertGetId(['name'=>$request->name,'phone'=>$request->phone,'email'=>$request->email,'area'=>$request->area,'created_at'=>now(),'updated_at'=>now()]); return response()->json(['success'=>true,'id'=>$id]); }
    public function updateSupplier(Request $request, int $id): JsonResponse { DB::table('suppliers')->where('id',$id)->update($request->only(['name','phone','email','area','status'])); return response()->json(['success'=>true]); }
    public function customerDetail(int $id): JsonResponse { $u=User::with(['wallet','savingPlans.product','transactions'])->findOrFail($id); return response()->json(['success'=>true,'data'=>$u]); }
    public function updateCustomerStatus(Request $request, int $id): JsonResponse { User::findOrFail($id)->update(['status'=>$request->status]); return response()->json(['success'=>true]); }
    public function plans(): JsonResponse { return response()->json(['success'=>true,'data'=>SavingPlan::with('user','product')->orderByDesc('created_at')->paginate(20)]); }
    public function inactivePlans(): JsonResponse { return response()->json(['success'=>true,'data'=>SavingPlan::with('user','product')->where('status','active')->where(fn($q)=>$q->whereNull('last_deposit_at')->orWhere('last_deposit_at','<',now()->subDays(21)))->paginate(20)]); }
    public function deliveries(): JsonResponse { return response()->json(['success'=>true,'data'=>FulfillmentOrder::with('user','product')->whereIn('status',['sourced','dispatched'])->paginate(20)]); }
    public function hideProduct(int $id): JsonResponse { Product::findOrFail($id)->update(['status'=>'hidden']); return response()->json(['success'=>true]); }
    public function uploadQualityPhoto(Request $request, int $id): JsonResponse { $path=$request->file('photo')->store("fulfillment/{$id}",'public'); FulfillmentOrder::findOrFail($id)->update(['quality_photo'=>Storage::url($path)]); return response()->json(['success'=>true,'photo'=>Storage::url($path)]); }
    public function sendBulkWhatsapp(Request $request): JsonResponse { Log::info('Bulk WhatsApp requested',['count'=>$request->count]); return response()->json(['success'=>true,'message'=>'Bulk WhatsApp queued.']); }
    public function monthlyReport(): JsonResponse { return response()->json(['success'=>true,'data'=>[]]); }
}
