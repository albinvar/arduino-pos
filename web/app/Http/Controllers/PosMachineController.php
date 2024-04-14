<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PosMachineController extends Controller
{
    // Check if the POS machine is operational
    public function checkIfOperational(): JsonResponse
    {
        // Perform some checks to determine if the POS machine is operational

        // Check if the user is a campus entity
        if (!auth()->user()->hasRole('campus entity')) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized access',
            ], 403);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'POS machine is operational',
        ], 200);
    }

    // Balance Inquiry
    public function balanceInquiry(): JsonResponse
    {
        // Perform balance inquiry logic here

        // Check if the user is a campus entity
        if (!auth()->user()->hasRole('campus entity')) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized access',
            ], 403);
        }

        return response()->json([
            'status' => 'success',
            'balance' => auth()->user()->balanceFloat,
        ], 200);
    }

    // Get user from rfid tag
    public function getUserFromRfid(Request $request): JsonResponse
    {
        // Check if the user is a campus entity
        if (!auth()->user()->hasRole('campus entity')) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized access',
            ], 403);
        }

        // Perform logic to get user from RFID tag
        // check if the rfid tag is in the request
        if (!$request->has('card_no')) {
            return response()->json([
                'status' => 'error',
                'message' => 'RFID tag not found in request',
            ], 400);
        }

        // Get the user from the RFID tag
        $user = User::where('card', $request->card_no)->first();

        if (!$user) {
            return response()->json([
                'status' => 'error',
                'message' => 'User not found',
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'balance' => $user->balanceFloat,
            'card' => $user->card,
            'pin_status' => $user->enable_pin,
        ], 200);
    }

    // Validate pin
    public function validatePin(Request $request): JsonResponse
    {
        // Check if the user is a campus entity
        if (!auth()->user()->hasRole('campus entity')) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized access',
            ], 403);
        }

        // Perform logic to validate the pin
        // check if the pin and card number are in the request
        if (!$request->has('card_no') || !$request->has('pin')) {
            return response()->json([
                'status' => 'error',
                'message' => 'Card number and pin not found in request',
            ], 400);
        }

        // Get the user from the request
        $user = User::where('card', $request->card_no)->first();

        if (!$user) {
            return response()->json([
                'status' => 'error',
                'message' => 'User not found',
            ], 404);
        }

        // Check if the pin matches
        if ($request->pin !== $user->pin) {
            return response()->json([
                'status' => 'error',
                'message' => 'Invalid pin',
            ], 400);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Pin is valid',
        ], 200);

    }

    // Create a new transaction
    public function createTransaction(Request $request): JsonResponse
    {
        // Check if the user is a campus entity
        if (!auth()->user()->hasRole('campus entity')) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized access',
            ], 403);
        }

        // Perform logic to create a new transaction
        // check if the card number, pin and amount are in the request
        if (!$request->has('card_no') || !$request->has('amount')) {
            return response()->json([
                'status' => 'error',
                'message' => 'Card number and amount not found in request',
            ], 400);
        }

        // Get the user from the request
        $user = User::where('card', $request->card_no)->first();

        if (!$user) {
            return response()->json([
                'status' => 'error',
                'message' => 'User not found',
            ], 404);
        }

        // Check if the user has a pin enabled
        if ($user->enable_pin) {
            // check if the pin is in the request
            if (!$request->has('pin')) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Pin not found in request',
                ], 400);
            }

            // Check if the pin matches
            if ($request->pin !== $user->pin) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Invalid pin',
                ], 400);
            }
        }


        // Perform transaction logic here
        // Deduct the amount from the user's balance and transfer it to the campus entity
        try {
            $user->transfer(auth()->user(), $request->amount, ['description' => 'Payment for '. auth()->user()->name, 'type' => 'payment']);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 400);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Transaction successful',
            'user_id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'balance' => $user->balanceFloat,
        ], 200);
    }
}
