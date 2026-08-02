<?php

namespace App\Http\Controllers;

use App\Support\CardValidator;
use Illuminate\Http\Request;

class AccountController extends Controller
{
    public function show(Request $request)
    {
        return response()->json($request->user());
    }

    public function updateCard(Request $request)
    {
        $validated = $request->validate([
            'card_name' => ['required', 'string', 'max:255'],
            'card_number' => ['required', 'string'],
            'card_expiry' => ['required', 'string', 'regex:/^(0[1-9]|1[0-2])\/\d{2}$/'],
            'card_cvv' => ['required', 'digits_between:3,4'],
        ]);

        $cardNumber = CardValidator::digitsOnly($validated['card_number']);

        if (! CardValidator::passesLuhnCheck($cardNumber)) {
            return response()->json(['message' => 'Your card number is invalid.'], 422);
        }

        if (CardValidator::isExpired($validated['card_expiry'])) {
            return response()->json(['message' => 'Your card has expired.'], 422);
        }

        $user = $request->user();
        $user->card_holder_name = $validated['card_name'];
        $user->card_last_four = substr($cardNumber, -4);
        $user->card_brand = CardValidator::detectBrand($cardNumber);
        $user->card_expiry = $validated['card_expiry'];
        $user->save();

        return response()->json($user);
    }

    public function destroyCard(Request $request)
    {
        $user = $request->user();
        $user->card_holder_name = null;
        $user->card_last_four = null;
        $user->card_brand = null;
        $user->card_expiry = null;
        $user->save();

        return response()->json(null, 204);
    }
}
