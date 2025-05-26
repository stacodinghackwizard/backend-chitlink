<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use App\Models\User;
use App\Models\Merchant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use App\Mail\VerificationMail;
use Illuminate\Support\Str;

class VerificationController extends Controller
{
    public function sendVerification(Request $request)
    {
        $request->validate([
            'email' => 'required|string|email|exists:users,email',
        ]);

        $user = User::where('email', $request->email)->first();
        $verificationCode = rand(100000, 999999);
        $user->email_verification_code = $verificationCode;
        $user->email_verification_code_expires_at = now()->addMinutes(10);
        $user->save();

        // Send OTP via email
        Mail::to($user->email)->send(new VerificationMail($user, $verificationCode));

        return response()->json(['status' => 'success', 'message' => 'Verification code sent.']);
    }

    public function resendEmailVerification(Request $request)
    {
        return $this->sendVerification($request);
    }

    public function verifyUser(Request $request)
    {
        $request->validate([
            'email' => 'required|string|email',
            'code' => 'required|string|size:6',
        ]);

        // Check if the user is a merchant
        $merchant = Merchant::where('email', $request->email)->first();
        if ($merchant) {
            // Verify merchant
            if ($merchant->email_verification_code === $request->code) {
                $merchant->email_verified_at = now();
                $merchant->email_verification_code = null;
                $merchant->save();

                return response()->json(['message' => 'Merchant email verified successfully.']);
            }
            return response()->json(['message' => 'Invalid verification code for merchant.'], 400);
        }

        // Check if the user is a regular user
        $user = User::where('email', $request->email)->first();
        if ($user) {
            // Verify user
            if ($user->email_verification_code === $request->code) {
                $user->email_verified_at = now();
                $user->email_verification_code = null;
                $user->save();

                return response()->json(['message' => 'User email verified successfully.']);
            }
            return response()->json(['message' => 'Invalid verification code for user.'], 400);
        }

        return response()->json(['message' => 'User or merchant not found.'], 404);
    }

    public function verifyPasswordReset(Request $request)
    {
        $request->validate([
            'user_type' => 'required|in:user,merchant',
            'contact' => 'required|string',
            'code' => 'required|string|size:4',
        ]);

        $userType = $request->user_type;
        $contact = $request->contact;

        // Find the user or merchant
        $user = $userType === 'merchant' ? Merchant::where('email', $contact)->orWhere('phone_number', $contact)->first() : User::where('email', $contact)->orWhere('phone_number', $contact)->first();

        if (!$user || $user->password_reset_code !== $request->code) {
            return response()->json(['status' => 'error', 'message' => 'Invalid verification code.'], 400);
        }

        if (is_null($user->password_reset_expires_at) || Carbon::now()->isAfter($user->password_reset_expires_at)) {
            // Handle expired token or missing expiration
            return response()->json(['message' => 'The password reset token is invalid or has expired.'], 400);
        }

        // Generate a unique token
        $token = Str::random(60); 
        $user->password_reset_token = $token; // Add this field to your User and Merchant models
        $user->password_reset_expires_at = now()->addMinutes(10); // Optional: Set expiration for the token
        $user->save();

        return response()->json(['status' => 'success', 'token' => $token, 'message' => 'Verification successful. You can now reset your password.']);
    }
}
