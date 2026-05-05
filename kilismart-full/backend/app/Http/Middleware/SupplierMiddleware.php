<?php
namespace App\Http\Middleware;
use Closure;
use Illuminate\Http\Request;
class SupplierMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        if (!$user || !in_array($user->role, ['supplier', 'admin', 'superadmin'])) {
            return response()->json(['message' => 'Supplier access required.'], 403);
        }
        return $next($request);
    }
}
