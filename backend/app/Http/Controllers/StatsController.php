<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\Support\Facades\DB;

class StatsController extends Controller
{
    public function index()
    {
        $revenueByDay = Order::where('created_at', '>=', now()->subDays(13)->startOfDay())
            ->selectRaw('DATE(created_at) as day, SUM(total_amount) as revenue')
            ->groupBy('day')
            ->orderBy('day')
            ->get();

        $ordersByStatus = Order::selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $topProducts = OrderItem::selectRaw('product_id, SUM(quantity) as units_sold, SUM(quantity * price) as revenue')
            ->groupBy('product_id')
            ->orderByDesc('units_sold')
            ->take(5)
            ->with('product:id,name')
            ->get()
            ->map(fn (OrderItem $item) => [
                'product_id' => $item->product_id,
                'name' => $item->product?->name ?? 'Product #'.$item->product_id,
                'units_sold' => (int) $item->units_sold,
                'revenue' => (float) $item->revenue,
            ]);

        return response()->json([
            'total_revenue' => (float) Order::sum('total_amount'),
            'total_orders' => Order::count(),
            'total_products' => Product::count(),
            'total_customers' => DB::table('users')->where('role', 'customer')->count(),
            'revenue_by_day' => $revenueByDay,
            'orders_by_status' => $ordersByStatus,
            'top_products' => $topProducts,
        ]);
    }
}
