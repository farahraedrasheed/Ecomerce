<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{
    public function index(Request $request)
    {
        $query = Order::with('items.product')->latest();

        if (! $request->user()->isAdmin()) {
            $query->where('user_id', $request->user()->id);
        }

        return response()->json($query->get());
    }

    public function show(Request $request, Order $order)
    {
        if (! $request->user()->isAdmin() && $order->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Not found.'], 404);
        }

        return response()->json($order->load('items.product'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'shipping_address' => ['required', 'string'],
        ]);

        $cartItems = $request->user()->cartItems()->with('product')->get();

        if ($cartItems->isEmpty()) {
            return response()->json(['message' => 'Your cart is empty.'], 422);
        }

        $order = DB::transaction(function () use ($request, $validated, $cartItems) {
            $total = $cartItems->sum(fn ($item) => $item->quantity * $item->product->price);

            $order = Order::create([
                'user_id' => $request->user()->id,
                'status' => 'pending',
                'total_amount' => $total,
                'shipping_address' => $validated['shipping_address'],
            ]);

            foreach ($cartItems as $item) {
                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $item->product_id,
                    'quantity' => $item->quantity,
                    'price' => $item->product->price,
                ]);

                $item->product->decrement('stock', $item->quantity);
            }

            $request->user()->cartItems()->delete();

            return $order;
        });

        return response()->json($order->load('items.product'), 201);
    }

    public function updateStatus(Request $request, Order $order)
    {
        $validated = $request->validate([
            'status' => ['required', 'in:pending,processing,shipped,delivered,cancelled'],
        ]);

        $order->update($validated);

        return response()->json($order);
    }
}
