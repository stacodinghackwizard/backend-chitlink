<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use App\Models\Merchant;
use App\Http\Requests\MerchantRegisterRequest;
use App\Http\Requests\MerchantKycRequest;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Auth\Events\Registered;
use App\Models\User;

class MerchantController extends Controller
{
    public function register(MerchantRegisterRequest $request)
    {
        Log::info($request->all()); // Log the request data
        $path = null; // Initialize path as null

        if ($request->hasFile('cac_certificate')) {
            $path = $request->file('cac_certificate')->store('cac_certificates'); 
        }

        try {
            $merchant = Merchant::create([
                'business_name' => $request->business_name,
                'email' => $request->email,
                'phone_number' => $request->phone_number,
                'address' => $request->address,
                'reg_number' => $request->reg_number,
                'cac_certificate' => $path, // Save the file path or null
                'password' => Hash::make($request->password),
            ]);

            // Send verification email
            event(new Registered($merchant)); // This will trigger the email verification

            return response()->json([
                'status' => 'success',
                'message' => 'Merchant registration was successful. Please check your email to verify your account.',
                'merchant' => $merchant
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Registration failed: ' . $e->getMessage()
            ], 500);
        }
    }

    // public function login(Request $request)
    // {
    //     $request->validate([
    //         'email' => 'required|string|email',
    //         'password' => 'required|string',
    //     ]);

    //     $merchant = Merchant::where('email', $request->email)->first();

    //     if (!$merchant) {
    //         return response()->json(['message' => 'Email not found'], 404);
    //     }

    //     if (!Hash::check($request->password, $merchant->password)) {
    //         return response()->json(['message' => 'Invalid credentials'], 401);
    //     }

    //     // if (!$merchant->email_verified_at) {
    //     //     return response()->json(['message' => 'Email not verified'], 403);
    //     // }
    //     // **Check if email is verified**
    //     if ($request->email && is_null($merchant->email_verified_at)) {
    //         return response()->json([
    //             'status' => 'error',
    //             'message' => 'Please verify your email before logging in.'
    //         ], 403);
    //     }

    //     // Log the merchant in and create a token
    //     $token = $merchant->createToken('API TOKEN')->plainTextToken;

    //     // return response()->json(['merchant' => $merchant, 'token' => $token], 200);
    //     return response()->json([
    //         'status' => 'success',
    //         'message' => 'Login successful',
    //         'merchant' => $merchant,
    //         'authorization' => [
    //             'token' => $token,
    //             'type' => 'bearer',
    //         ]
    //     ]);
    // }

    public function completeKyc(MerchantKycRequest $request)
    {
        $merchant = Auth::user(); 

        // Retrieve the merchant from the database using the authenticated user's ID
        $merchant = Merchant::find($merchant->id);

        $utilityBillPath = null;
        if ($request->hasFile('utility_bill')) {
            $utilityBillPath = $request->file('utility_bill')->store('utility_bills');
        }

        $merchant->update([
            'nin' => $request->nin,
            'bvn' => $request->bvn,
            'utility_bill_path' => $utilityBillPath,
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'KYC information updated successfully.',
            'merchant' => $merchant,
        ], 200);
    }

    // Get Merchant Profile
    public function profile()
    {
        
        $merchant = Auth::guard('merchant')->user();
        // $merchant = Merchant::find($merchant->id);

       
        if (!$merchant) {
            return response()->json(['message' => 'User not authenticated.'], 401);
        }

       
        return response()->json([
            'message' => 'Merchant profile retrieved successfully.',
            'merchant' => $merchant,
        ], 200);
    }

    // Update Merchant Profile
    public function updateProfile(Request $request)
    {
        // Get the authenticated merchant
        $merchant = Auth::guard('merchant')->user();

        // Check if the merchant is authenticated
        if (!$merchant) {
            return response()->json(['message' => 'User not authenticated.'], 401);
        }

        // Validate the request
        $request->validate([
            'business_name' => 'sometimes|string|max:255',
            'email' => 'sometimes|email',
            'phone_number' => 'sometimes|string',
            'address' => 'sometimes|string',
            'reg_number' => 'sometimes|string',
            // Add other fields as necessary
        ]);

        // Update only the fields provided
        $merchant->update($request->only('business_name', 'email', 'phone_number', 'address', 'reg_number'));

        return response()->json(['message' => 'Profile updated successfully.', 'merchant' => $merchant], 200);
    }
}
