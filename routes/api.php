<?php
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\UserController;
use App\Http\Controllers\MerchantController;
use App\Http\Controllers\VerificationController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ContactController;
use Illuminate\Auth\Events\Verified;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful;

// Unified Authentication Routes



Route::controller(AuthController::class)->group(function() {

    Route::post('/register',  'register');
    Route::post('/login',  'login');
    Route::post('/reset-password',  'resetPassword');
    Route::post('/forgot-password', 'forgotPassword');

});

Route::post('/verify-password-reset', [VerificationController::class, 'verifyPasswordReset']);


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




// KYC Route (requires authentication)
Route::middleware(['auth:sanctum'])->group(function () {
    Route::post('/kyc', [AuthController::class, 'completeKyc']);
});

// User and Merchant Routes (KYC check applied)
Route::middleware(['auth:sanctum',  \App\Http\Middleware\CheckKyc::class])->group(function () {
    // User Routes
    Route::prefix('users')->group(function () {
        Route::controller(UserController::class)->group(function() {

            Route::get('/profile', 'profile');
            Route::put('/profile',  'updateProfile');
            Route::post('/profile/image',  'updateProfileImage');
        });
        
    });

    // Merchant Routes
    Route::prefix('merchants')->group(function () {
        Route::controller(MerchantController::class)->group(function() {

            Route::get('/profile', 'profile');
            Route::put('/profile', 'updateProfile');
            Route::post('/profile/image', 'updateProfileImage');
        });
        Route::apiResource('/contacts', ContactController::class)->only(['index', 'store', 'update', 'destroy']);
        // Route::delete('/contacts/sequential/{id}', [ContactController::class, 'destroyBySequentialId']);
        // Add these routes to your routes file
        Route::get('/contacts', [ContactController::class, 'index']);
        Route::get('/contacts/users', [ContactController::class, 'getUsers']); // Get all users to add
        Route::post('/contacts/add-users', [ContactController::class, 'addUserToContacts']); // Add users to contacts
        Route::delete('/contacts', [ContactController::class, 'destroy']);
        Route::delete('/contacts/sequential/{id}', [ContactController::class, 'destroyBySequentialId']);
    });

    Route::controller(AuthController::class)->group(function() {

        Route::post('/change-password', 'changePassword');
        Route::post('/logout','logout');
    });

   
});



?>