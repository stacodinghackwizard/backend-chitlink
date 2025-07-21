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
use Illuminate\Validation\Rule;
use GuzzleHttp\Client;

class AuthController extends Controller
{
    public function register(UnifiedRegisterRequest $request)
    {
        Log::info('Unified Registration Attempt:', $request->all());

        // Step 1: Validate user_type first
        $request->validate([
            'user_type' => 'required|in:user,merchant',
            'password' => 'required|string|min:6',
        ]);

        // Treat empty strings as null for validation
        $request->merge([
            'email' => $request->email ?: null,
            'phone_number' => $request->phone_number ?: null,
        ]);

        $userType = $request->user_type;
        $password = Hash::make($request->password);
        $path = null;

        // Step 2: Now validate the rest based on user_type
        if ($userType === 'merchant') {
            $rules = [
                'email' => [
                    'required_without:phone_number',
                    'nullable',
                    'email',
                    'unique:merchants,email',
                ],
                'phone_number' => [
                    'required_without:email',
                    'nullable',
                    'string',
                    'unique:merchants,phone_number',
                    'regex:/^\\+?[0-9]{10,15}$/',
                ],
                'business_name' => 'required|string|max:255',
                'address' => 'required|string',
                'reg_number' => 'required|string', // Now required
                'cac_certificate' => 'required|file', // Now required
            ];
        } else {
            $rules = [
                'email' => 'required|email|unique:users,email',
                'phone_number' => 'required|string',
            ];
        }

        $validated = $request->validate($rules);

        if (
            $userType === 'merchant' &&
            $request->email &&
            Merchant::where('email', $request->email)->exists()
        ) {
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
                    'name' => $request->email ? explode('@', $request->email)[0] : $request->phone_number,
                ];
                
                Log::info('Creating merchant with data:', $creatableData);
                $user = Merchant::create($creatableData);
            } else { 
                $creatableData = [
                    'name' => explode('@', $request->email)[0],
                    'email' => $request->email,
                    'phone_number' => $request->phone_number,
                    'password' => $password,
                ];
                $user = User::create($creatableData);
            }

            if ($user->email) {
            $this->sendOtp($user);
            }
           
            $responseUser = [
                'id' => $user->id,
                'email' => $user->email,
                'phone_number' => $user->phone_number,
                'created_at' => $user->created_at,
                'updated_at' => $user->updated_at,
                'email_verified_at' => $user->email_verified_at,
            ];
            
            if ($userType === 'user') {
                $responseUser['user_id'] = $user->user_id;
            }
            
            if ($userType === 'merchant') {
                $responseUser['mer_id'] = $user->mer_id;
                $responseUser['business_name'] = $user->business_name;
                $responseUser['address'] = $user->address;
                $responseUser['reg_number'] = $user->reg_number;
                $responseUser['cac_certificate'] = $user->cac_certificate;
            }

            $message = ucfirst($userType) . ' registration was successful. ';
            if ($user->email) {
                $message .= 'Please check your email to verify your account.';
            } elseif ($user->phone_number) {
                $message .= 'Please check your phone for your OTP.';
            }

            // Only return a token if the user is already verified (for some reason)
            // $authorization = null;
            $token = $user->createToken('KYC TOKEN', ['kyc'], now()->addHour())->plainTextToken;
            $authorization = [
                'token' => $token,
                'type' => 'bearer',
                'expires_at' => now()->addHour()->toISOString(),
            ];
           

            return response()->json([
                'status' => 'success',
                'message' => $message,
                'user' => $responseUser,
                'authorization' => $authorization, 
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

        
        if ($userType === 'merchant') {
            $user = Merchant::where('email', $request->email)->first();
        } else {
            $user = User::where('email', $request->email)->first();
        }

       
        if ($user && Hash::check($request->password, $user->password)) {
           
            if (method_exists($user, 'hasVerifiedEmail') && !$user->hasVerifiedEmail()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Please verify your email before logging in.'
                ], 403);
            }

            $token = $user->createToken('API TOKEN', ['full_access'], now()->addHour())->plainTextToken;

            // Prepare response without sensitive information
            $responseUser = [
                'id' => $user->id,
                'email' => $user->email,
                'phone_number' => $user->phone_number,
                'created_at' => $user->created_at,
                'updated_at' => $user->updated_at,
                'email_verified_at' => $user->email_verified_at, 
            ];

           
            if ($userType === 'user') {
                $responseUser['user_id'] = $user->user_id;
            }

            
            if ($userType === 'merchant') {
                $responseUser['mer_id'] = $user->mer_id;
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
                    'expires_at' => now()->addHour()->toISOString(),
                ]
            ]);
        }

        return response()->json(['message' => 'Invalid credentials'], 401);
    }

    private function sendOtp($user)
    {
        // Generate a 4-character alphanumeric code (A-Z, a-z, 0-9)
        $characters = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $otp = '';
        for ($i = 0; $i < 4; $i++) {
            $otp .= $characters[random_int(0, strlen($characters) - 1)];
        }
        $hasUppercase = preg_match('/[A-Z]/', $otp) ? true : false;
        $user->email_verification_code = $otp;
        $user->email_verification_code_expires_at = now()->addMinutes(15);
        $user->save();

        if ($user->email) {
            Mail::to($user->email)->send(new VerificationMail($user, $otp, $hasUppercase));
        } elseif ($user->phone_number) {
            $this->sendSmsOtp($user->phone_number, $otp);
        }
    }

    private function sendSmsOtp($phone, $code)
    {
        $apiKey = env('TERMII_API_KEY');
        $senderId = env('TERMII_SENDER_ID', 'N-Alert'); // Use 'N-Alert' if your sender ID is not approved yet
        $message = "Your OTP is: $code";
        $url = "https://v3.api.termii.com/api/sms/send";

        // Convert phone to international format if needed
        if (preg_match('/^0[0-9]{10}$/', $phone)) {
            $phone = '234' . substr($phone, 1);
        }

        $client = new \GuzzleHttp\Client();
        try {
            $response = $client->post($url, [
                'json' => [
                    'to' => $phone,
                    'from' => $senderId,
                    'sms' => $message,
                    'type' => 'plain',
                    'channel' => 'generic',
                    'api_key' => $apiKey,
                ]
            ]);
            $body = json_decode($response->getBody(), true);
            Log::info('Termii SMS response:', $body);
        } catch (\Exception $e) {
            Log::error('Termii SMS error: ' . $e->getMessage());
        }
    }

    public function completeKyc(KycRequest $request)
    {
        $user = Auth::user(); 

        // Only allow users (not merchants) to submit KYC
        if ($user instanceof 
            \App\Models\Merchant) {
            return response()->json([
                'status' => 'error',
                'message' => 'Merchants do not require KYC submission.'
            ], 403);
        }

        // Check if user is verified (email or phone)
        $isVerified = false;
        if (isset($user->email_verified_at) && $user->email_verified_at) {
            $isVerified = true;
        }
        if (isset($user->phone_verified_at) && $user->phone_verified_at) {
            $isVerified = true;
        }
        if (!$isVerified) {
            return response()->json([
                'status' => 'error',
                'message' => 'Account must be verified before submitting KYC.'
            ], 403);
        }
        
        if (!$user) {
            return response()->json(['status' => 'error', 'message' => 'Unauthenticated.'], 401);
        }
        
        $user->update([
            'nin' => $request->nin,
            'bvn' => $request->bvn,
            'utility_bill_path' => $request->file('utility_bill')->store('utility_bills'),
        ]);

        // Revoke/delete the current KYC token after successful KYC submission
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'KYC information updated successfully. Please login to access your account.',
            'user' => $user,
        ], 200);
    }

    public function forgotPassword(Request $request)
    {
        $request->validate([
            'user_type' => 'required|in:user,merchant',
            'contact' => 'required|string', 
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

        
        $otp = Str::random(4);

       
        $user->password_reset_code = $otp;
        $user->password_reset_expires_at = now()->addMinutes(10);
        $user->save();

        
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
            'current_password' => 'required|string',
            'new_password' => 'required|string|min:8|confirmed',
        ]);

        $userType = $request->user_type;
        if ($userType === 'merchant') {
            $user = Auth::guard('merchant')->user();
        } else {
            $user = Auth::user();
        }

        if(!$user){
            return response()->json(['message' => 'User not found.'], 404);
        }

        if(!Hash::check($request->current_password, $user->password)){
            return response()->json(['message' => 'Current password is incorrect.'], 403);
        }

        if ($user instanceof \Illuminate\Database\Eloquent\Model) {
            $user->password = Hash::make($request->new_password);
            $user->save();
        } else {
            return response()->json(['message' => 'User model error.'], 500);
        }

        return response()->json(['message' => 'Password changed successfully.']);
    }

    public function logout(Request $request)
    {
        $request->validate([
            'user_type' => 'required|in:user,merchant',
        ]);

        $userType = $request->user_type;
        if ($userType === 'merchant') {
            $user = Auth::guard('merchant')->user();
        } else {
            $user = Auth::user(); // Use Sanctum's default guard for users
        }

        if (!$user) {
            return response()->json(['message' => 'User not authenticated.'], 401);
        }
     
        $user->tokens()->delete();

        return response()->json(['message' => ucfirst($userType) . ' logged out successfully.']);
    }

    public function checkTokenStatus(Request $request)
    {
        $user = Auth::user();
        
        if (!$user) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthenticated.'
            ], 401);
        }

        $token = $request->user()->currentAccessToken();
        
        if (!$token) {
            return response()->json([
                'status' => 'error',
                'message' => 'No active token found.'
            ], 401);
        }

        $now = now();
        $expiresAt = $token->expires_at;
        $lastUsedAt = $token->last_used_at;
        
        $isExpired = $expiresAt && $now->isAfter($expiresAt);
        $isInactive = $lastUsedAt && $now->diffInHours($lastUsedAt) >= 1;
        
        $remainingMinutes = $expiresAt ? $now->diffInMinutes($expiresAt, false) : null;
        $lastActivityMinutes = $lastUsedAt ? $now->diffInMinutes($lastUsedAt) : null;

        return response()->json([
            'status' => 'success',
            'token_status' => [
                'is_expired' => $isExpired,
                'is_inactive' => $isInactive,
                'expires_at' => $expiresAt ? $expiresAt->toISOString() : null,
                'last_used_at' => $lastUsedAt ? $lastUsedAt->toISOString() : null,
                'remaining_minutes' => $remainingMinutes,
                'last_activity_minutes' => $lastActivityMinutes,
                'user_type' => $user instanceof \App\Models\Merchant ? 'merchant' : 'user',
            ]
        ]);
    }
}