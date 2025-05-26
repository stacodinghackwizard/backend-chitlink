<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Requests\UnifiedRegisterRequest;
use App\Http\Requests\KycRequest;
use App\Models\User;
use App\Models\Merchant;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Mail;
use App\Mail\VerificationMail;
use Illuminate\Support\Str;
use App\Mail\ForgotPasswordMail;

class AuthController extends Controller
{
    public function register(UnifiedRegisterRequest $request)
    {
        Log::info('Unified Registration Attempt:', $request->all());

        $userType = $request->user_type;
        $password = Hash::make($request->password);
        $path = null;

        // Check if the email already exists
        if ($userType === 'merchant' && Merchant::where('email', $request->email)->exists()) {
            return response()->json([
                'status' => 'error',
                'message' => 'The email has already been taken.'
            ], 422);
        }

        try {
            if ($userType === 'merchant') {
                if ($request->hasFile('cac_certificate')) {
                    $path = $request->file('cac_certificate')->store('cac_certificates');
                }

                $creatableData = [
                    'business_name' => $request->business_name,
                    'email' => $request->email,
                    'phone_number' => $request->phone_number,
                    'address' => $request->address,
                    'reg_number' => $request->reg_number,
                    'password' => $password,
                    'cac_certificate' => $path,
                    'name' => explode('@', $request->email)[0],
                ];

                Log::info('Creating merchant with data:', $creatableData);
                $user = Merchant::create($creatableData);
            } else { // Default to 'user'
                $creatableData = [
                    'name' => explode('@', $request->email)[0],
                    'email' => $request->email,
                    'phone_number' => $request->phone_number,
                    'password' => $password,
                ];
                $user = User::create($creatableData);
            }

           
            $this->sendOtp($user);

            // Prepare response with relevant fields
            $responseUser = [
                'id' => $user->id,
                'email' => $user->email,
                'phone_number' => $user->phone_number,
                'created_at' => $user->created_at,
                'updated_at' => $user->updated_at,
                'email_verified_at' => $user->email_verified_at,
            ];

            // Include merchant-specific fields if user type is merchant
            if ($userType === 'merchant') {
                $responseUser['business_name'] = $user->business_name;
                $responseUser['address'] = $user->address;
                $responseUser['reg_number'] = $user->reg_number;
                $responseUser['cac_certificate'] = $user->cac_certificate;
            }

            return response()->json([
                'status' => 'success',
                'message' => ucfirst($userType) . ' registration was successful. Please check your email to verify your account.',
                'user' => $responseUser,
            ], 201);

        } catch (\Exception $e) {
            Log::error('Registration Failed:', ['error' => $e->getMessage(), 'request' => $request->all()]);
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
            'user_type' => ['required', 'in:user,merchant'],
        ]);

        $userType = $request->user_type;
        $credentials = $request->only('email', 'password');

        // Attempt to authenticate the user based on user type
        if ($userType === 'merchant') {
            $user = Merchant::where('email', $request->email)->first();
        } else {
            $user = User::where('email', $request->email)->first();
        }

        // Check if user exists and verify password
        if ($user && Hash::check($request->password, $user->password)) {
            // Check for email verification (if applicable)
            if (method_exists($user, 'hasVerifiedEmail') && !$user->hasVerifiedEmail()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Please verify your email before logging in.'
                ], 403);
            }

            $token = $user->createToken('API TOKEN')->plainTextToken;

            // Prepare response without sensitive information
            $responseUser = [
                'id' => $user->id,
                'email' => $user->email,
                'phone_number' => $user->phone_number,
                'created_at' => $user->created_at,
                'updated_at' => $user->updated_at,
                'email_verified_at' => $user->email_verified_at, 
            ];

            // Include merchant-specific fields if user type is merchant
            if ($userType === 'merchant') {
                $responseUser['business_name'] = $user->business_name;
                $responseUser['address'] = $user->address;
                $responseUser['reg_number'] = $user->reg_number;
                $responseUser['cac_certificate'] = $user->cac_certificate;
            }

            return response()->json([
                'status' => 'success',
                'message' => ucfirst($userType) . ' login successful',
                'user' => $responseUser,
                'authorization' => [
                    'token' => $token,
                    'type' => 'bearer',
                ]
            ]);
        }

        return response()->json(['message' => 'Invalid credentials'], 401);
    }

    private function sendOtp($user)
    {
        $verificationCode = rand(100000, 999999);
        $user->email_verification_code = $verificationCode;
        $user->email_verification_code_expires_at = now()->addMinutes(10);
        $user->save();

        Mail::to($user->email)->send(new VerificationMail($user, $verificationCode));
    }

    public function completeKyc(KycRequest $request)
    {
        $user = Auth::user(); 

        // Ensure the user is an instance of the correct model
        if (!$user) {
            return response()->json(['status' => 'error', 'message' => 'Unauthenticated.'], 401);
        }

        // Update KYC information for both users and merchants
        $user->update([
            'nin' => $request->nin,
            'bvn' => $request->bvn,
            'utility_bill_path' => $request->file('utility_bill')->store('utility_bills'),
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'KYC information updated successfully.',
            'user' => $user,
        ], 200);
    }

    public function forgotPassword(Request $request)
    {
        $request->validate([
            'user_type' => 'required|in:user,merchant',
            'contact' => 'required|string', // This can be either email or phone number
        ]);

        $userType = $request->user_type;
        $contact = $request->contact;

        // Determine if the contact is an email or phone number
        if (filter_var($contact, FILTER_VALIDATE_EMAIL)) {
            $user = $userType === 'merchant' ? Merchant::where('email', $contact)->first() : User::where('email', $contact)->first();
        } else {
            $user = $userType === 'merchant' ? Merchant::where('phone_number', $contact)->first() : User::where('phone_number', $contact)->first();
        }

        if (!$user) {
            return response()->json(['status' => 'error', 'message' => 'User or merchant not found.'], 404);
        }

        // Generate a 4-digit alphanumeric OTP
        $otp = Str::random(4);

        // Save the OTP and its expiration time
        $user->password_reset_code = $otp;
        $user->password_reset_expires_at = now()->addMinutes(10);
        $user->save();

        // Send OTP via email
        Mail::to($user->email)->send(new ForgotPasswordMail($user, $otp));

        return response()->json(['status' => 'success', 'message' => 'Verification code sent.']);
    }

    public function resetPassword(Request $request)
    {
        $request->validate([
            'user_type' => 'required|in:user,merchant',
            'contact' => 'required|string', 
            'new_password' => 'required|string|min:8|confirmed', 
            'token' => 'required|string', 
        ]);

        $userType = $request->user_type;
        $contact = $request->contact;

       
        $user = $userType === 'merchant' ? Merchant::where('email', $contact)->orWhere('phone_number', $contact)->first() : User::where('email', $contact)->orWhere('phone_number', $contact)->first();

        if (!$user || $user->password_reset_token !== $request->token || now()->isAfter($user->password_reset_expires_at)) {
            return response()->json(['status' => 'error', 'message' => 'Invalid or expired token.'], 400);
        }

       
        $user->password = Hash::make($request->new_password);
        $user->password_reset_token = null; 
        $user->password_reset_expires_at = null; 
        $user->save();

        return response()->json(['status' => 'success', 'message' => 'Password has been reset successfully.']);
    }

    public function changePassword(Request $request)
    {
        $request->validate([
            'user_type' => 'required|in:user,merchant',
            'email' => 'required|email',
            'current_password' => 'required|string',
            'new_password' => 'required|string|min:8|confirmed',
        ]);

        $userType = $request->user_type;
        $credentials = [
            'email' => $request->email,
            'password' => $request->current_password,
        ];

        if($userType === 'merchant'){
            $user = Merchant::where('email', $request->email)->first();
        }else{
            $user = User::where('email', $request->email)->first();
        }

        if(!$user){
            return response()->json(['message' => 'User not found.'], 404);
        }

        if(!Hash::check($request->current_password, $user->password)){
            return response()->json(['message' => 'Current password is incorrect.'], 403);
        }

        if($userType === 'merchant'){
            $user->password = Hash::make($request->new_password);
            $user->save();
        }else{
            $user->password = Hash::make($request->new_password);
            $user->save();
        }


       
        
        $user->save();

        return response()->json(['message' => 'Password changed successfully.']);
    }

    public function logout(Request $request)
    {
        $request->validate([
            'user_type' => 'required|in:user,merchant',
        ]);

        $userType = $request->user_type;
        $guard = $userType === 'merchant' ? 'merchant' : 'web';

        $user = Auth::guard($guard)->user();

        if (!$user) {
            return response()->json(['message' => 'User not authenticated.'], 401);
        }

        // Revoke all tokens for the user
        $user->tokens()->delete();

        return response()->json(['message' => ucfirst($userType) . ' logged out successfully.']);
    }
}
