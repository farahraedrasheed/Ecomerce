<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderItem;
use App\Support\CardValidator;
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
            'card_name' => ['required', 'string', 'max:255'],
            'card_number' => ['required', 'string'],
            'card_expiry' => ['required', 'string', 'regex:/^(0[1-9]|1[0-2])\/\d{2}$/'],
            'card_cvv' => ['required', 'digits_between:3,4'],
        ]);

        $cartItems = $request->user()->cartItems()->with('product')->get();

        if ($cartItems->isEmpty()) {
            return response()->json(['message' => 'Your cart is empty.'], 422);
        }

        $cardNumber = CardValidator::digitsOnly($validated['card_number']);

        if (! CardValidator::passesLuhnCheck($cardNumber)) {
            return response()->json(['message' => 'Your card number is invalid.'], 422);
        }

        if (CardValidator::isExpired($validated['card_expiry'])) {
            return response()->json(['message' => 'Your card has expired.'], 422);
        }

        // Mock payment gateway: this well-known test number is treated as a decline
        // so the "payment failed" path can be demoed without a real processor.
        if ($cardNumber === '4000000000000002') {
            return response()->json(['message' => 'Your card was declined.'], 402);
        }

        $order = DB::transaction(function () use ($request, $validated, $cartItems, $cardNumber) {
            $total = $cartItems->sum(fn ($item) => $item->quantity * $item->product->price);

            $order = Order::create([
                'user_id' => $request->user()->id,
                'status' => 'pending',
                'total_amount' => $total,
                'shipping_address' => $validated['shipping_address'],
                'payment_status' => 'paid',
                'payment_method' => 'card',
                'card_last_four' => substr($cardNumber, -4),
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
