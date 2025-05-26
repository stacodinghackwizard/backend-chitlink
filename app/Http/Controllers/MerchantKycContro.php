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
use Illuminate\Support\Facades\Http;

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
                'message' => '"Merchant registration was successful. Please check your email to verify your account."',
                'merchant' => $merchant
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Registration failed: ' . $e->getMessage()
            ], 500);
        }
    }

    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|string|email',
            'password' => 'required|string',
        ]);

        $merchant = Merchant::where('email', $request->email)->first();

        if (!$merchant) {
            return response()->json(['message' => 'Email not found'], 404);
        }

        if (!Hash::check($request->password, $merchant->password)) {
            return response()->json(['message' => 'Invalid credentials'], 401);
        }

        // if (!$merchant->email_verified_at) {
        //     return response()->json(['message' => 'Email not verified'], 403);
        // }
        // **Check if email is verified**
        if ($request->email && is_null($merchant->email_verified_at)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Please verify your email before logging in.'
            ], 403);
        }

        // Log the merchant in and create a token
        $token = $merchant->createToken('API TOKEN')->plainTextToken;

        // return response()->json(['merchant' => $merchant, 'token' => $token], 200);
        return response()->json([
            'status' => 'success',
            'message' => 'Login successful',
            'merchant' => $merchant,
            'authorization' => [
                'token' => $token,
                'type' => 'bearer',
            ]
        ]);
    }

    public function completeKyc(MerchantKycRequest $request)
    {
        $merchant = Auth::user(); // Get the authenticated merchant

        // Retrieve the merchant from the database using the authenticated user's ID
        $merchant = Merchant::find($merchant->id);

        // --- External Verification Logic ---

        // Example: Verify NIN (Replace with actual API call)
        $ninVerificationResponse = Http::post('EXTERNAL_NIN_VERIFICATION_API_URL', [
            'nin' => $request->nin,
            // Include any required API keys or credentials here
            'api_key' => env('EXTERNAL_VERIFICATION_API_KEY'),
        ]);

        if ($ninVerificationResponse->successful()) {
            $ninVerificationResult = $ninVerificationResponse->json();
            // Process the result, check if NIN is valid
            // Example: if ($ninVerificationResult['status'] === 'valid') { ... }
            Log::info('NIN Verification Result:', $ninVerificationResult);

            // Example: Verify BVN (Replace with actual API call)
            $bvnVerificationResponse = Http::post('EXTERNAL_BVN_VERIFICATION_API_URL', [
                'bvn' => $request->bvn,
                // Include any required API keys or credentials here
                'api_key' => env('EXTERNAL_VERIFICATION_API_KEY'),
            ]);

            if ($bvnVerificationResponse->successful()) {
                $bvnVerificationResult = $bvnVerificationResponse->json();
                // Process the result, check if BVN is valid and matches the NIN holder
                // Example: if ($bvnVerificationResult['status'] === 'valid' && $bvnVerificationResult['name'] === $ninVerificationResult['name']) { ... }
                Log::info('BVN Verification Result:', $bvnVerificationResult);

                // If both NIN and BVN are successfully verified:
                $utilityBillPath = null;
                if ($request->hasFile('utility_bill')) {
                    $utilityBillPath = $request->file('utility_bill')->store('utility_bills');
                }

                $merchant->update([
                    'nin' => $request->nin,
                    'bvn' => $request->bvn,
                    'utility_bill_path' => $utilityBillPath,
                    // Update a status field, e.g., 'kyc_status' => 'verified'
                ]);

                return response()->json([
                    'status' => 'success',
                    'message' => 'KYC information updated and verified successfully.',
                    'merchant' => $merchant,
                ], 200);

            } else {
                 // Handle BVN verification failure
                 Log::error('BVN Verification Failed:', ['status' => $bvnVerificationResponse->status(), 'body' => $bvnVerificationResponse->body()]);
                 return response()->json([
                     'status' => 'error',
                     'message' => 'BVN verification failed. Please check the number and try again.'
                 ], $bvnVerificationResponse->status()); // Use the actual status code from the external API
            }

        } else {
            // Handle NIN verification failure
            Log::error('NIN Verification Failed:', ['status' => $ninVerificationResponse->status(), 'body' => $ninVerificationResponse->body()]);
            return response()->json([
                'status' => 'error',
                'message' => 'NIN verification failed. Please check the number and try again.'
            ], $ninVerificationResponse->status()); // Use the actual status code from the external API
        }

        // --- End of External Verification Logic ---

        // Fallback or alternative logic if external verification is not used or fails for other reasons
        // ...
    }
}
