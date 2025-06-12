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
       
        $merchant = Auth::guard('merchant')->user();

       
        if (!$merchant) {
            return response()->json(['message' => 'User not authenticated.'], 401);
        }

       
        $request->validate([
            'business_name' => 'sometimes|string|max:255',
            'email' => 'sometimes|email',
            'phone_number' => 'sometimes|string',
            'address' => 'sometimes|string',
            'reg_number' => 'sometimes|string',
            'name' => 'sometimes|string'
            // Add other fields as necessary
        ]);

       
        $merchant->update($request->only('name', 'business_name', 'email', 'phone_number', 'address', 'reg_number'));

        return response()->json(['message' => 'Profile updated successfully.', 'merchant' => $merchant], 200);
    }

    public function updateProfileImage(Request $request)
{
    $merchant = Auth::guard('merchant')->user();

    if (!$merchant) {
        return response()->json(['message' => 'User not authenticated.'], 401);
    }

    // Debug: Log all request data
    Log::info('Request data:', [
        'all_data' => $request->all(),
        'files' => $request->allFiles(),
        'has_profile_image_file' => $request->hasFile('profile_image'),
        'content_type' => $request->header('Content-Type'),
        'method' => $request->method()
    ]);

    // Check if file exists before validation
    if (!$request->hasFile('profile_image')) {
        return response()->json([
            'message' => 'No profile image file found in request.',
            'debug_info' => [
                'files_received' => $request->allFiles(),
                'all_input' => $request->all()
            ]
        ], 422);
    }

    // Validate the request
    $validator = Validator::make($request->all(), [
        'profile_image' => 'required|image|mimes:jpeg,png,jpg,gif|max:2048',
    ]);

    if ($validator->fails()) {
        return response()->json([
            'message' => 'Validation failed',
            'errors' => $validator->errors(),
            'debug_info' => [
                'files_received' => $request->allFiles(),
                'has_file' => $request->hasFile('profile_image')
            ]
        ], 422);
    }

    try {
        // Store the image
        $path = $request->file('profile_image')->store('profile_images');

        // Update the merchant's profile image path
        $merchant->profile_image = $path;
        $merchant->save();

        return response()->json([
            'message' => 'Profile image updated successfully.',
            'merchant' => $merchant
        ], 200);
    } catch (\Exception $e) {
        return response()->json([
            'message' => 'Failed to update profile image: ' . $e->getMessage()
        ], 500);
    }
}
}