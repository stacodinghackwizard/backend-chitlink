<?php
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\UserController;
use App\Http\Controllers\MerchantController;
use App\Http\Controllers\VerificationController;
use App\Http\Controllers\AuthController;
use Illuminate\Auth\Events\Verified;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful;

// Unified Authentication Routes
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);



// Email verification routes
Route::prefix('verify')->group(function () {
    Route::post('email/send', [VerificationController::class, 'sendVerification'])->name('verification.send');
    Route::post('email/resend', [VerificationController::class, 'resendEmailVerification'])->name('verification.resend');
    Route::post('user', [VerificationController::class, 'verifyUser'])->name('verification.verify');
});

// Route for email verification link
// Route::get('/email/verify/{id}/{hash}', function (EmailVerificationRequest $request) {
//     $request->fulfill();
//     return response()->json(['message' => 'Email verified successfully!']);
// })->middleware(['auth:sanctum', 'signed'])->name('verification.verify');

Route::middleware([EnsureFrontendRequestsAreStateful::class, 'auth:sanctum'])->group(function () {
    // Protected routes
});



Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
Route::post('/verify-password-reset', [VerificationController::class, 'verifyPasswordReset']);
Route::post('/reset-password', [AuthController::class, 'resetPassword']);

Route::middleware(['auth:sanctum'])->group(function () {
    // User Routes
    Route::prefix('users')->group(function () {
        Route::get('/profile', [UserController::class, 'profile']);
        Route::put('/profile', [UserController::class, 'updateProfile']);
    });

    // Merchant Routes
    Route::prefix('merchants')->group(function () {
        Route::get('/profile', [MerchantController::class, 'profile']);
        Route::put('/profile', [MerchantController::class, 'updateProfile']);
    });

    Route::post('/kyc', [AuthController::class, 'completeKyc']);
    Route::post('/change-password', [AuthController::class, 'changePassword']);
    Route::post('/logout', [AuthController::class, 'logout']);
    // Route::get('/merchants/profile', [MerchantController::class, 'profile']);
});



?>