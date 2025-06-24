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
        // Route::post('/contacts/add-users', [ContactController::class, 'addUserToContacts']); // Add users to contacts
        // Route::delete('/contacts', [ContactController::class, 'destroy']);
        // Route::delete('/contacts/sequential/{id}', [ContactController::class, 'destroyBySequentialId']);

        Route::post('/contacts', [ContactController::class, 'store']);
        Route::put('/contacts/{id}', [ContactController::class, 'update']);
        Route::post('/contact/delete', [ContactController::class, 'destroy']);


        // Bulk operations
        Route::post('/contacts/bulk-delete', [ContactController::class, 'bulkDestroy']);
        Route::post('/contacts/add-users', [ContactController::class, 'addUserToContacts']);
        
        // Group management routes
        Route::prefix('contact-groups')->group(function () {
            // Group CRUD
            Route::get('/', [ContactController::class, 'getGroups']);
            Route::post('/', [ContactController::class, 'createGroup']);
            Route::put('/{id}', [ContactController::class, 'updateGroup']);
            Route::delete('/{id}', [ContactController::class, 'deleteGroup']);
            
            // Group membership management
            Route::get('/{id}/contacts', [ContactController::class, 'getGroupContacts']);
            Route::post('/{id}/contacts', [ContactController::class, 'addContactsToGroup']);
            Route::delete('/{id}/contacts', [ContactController::class, 'removeContactsFromGroup']);
        });

       
    });

     // Thrift Package routes
     Route::middleware(['auth:sanctum'])
        ->prefix('thrift-packages')
        ->group(function () {
            Route::get('/', [\App\Http\Controllers\ThriftPackageController::class, 'index']);
            Route::post('/', [\App\Http\Controllers\ThriftPackageController::class, 'store']);
            Route::get('{id}', [\App\Http\Controllers\ThriftPackageController::class, 'show']);
            Route::put('{id}/terms', [\App\Http\Controllers\ThriftPackageController::class, 'updateTerms']);
            Route::post('{id}/contributors', [\App\Http\Controllers\ThriftPackageController::class, 'addContributors']);
            Route::get('{id}/contributors', [\App\Http\Controllers\ThriftPackageController::class, 'getContributors']);
            Route::post('{id}/contributors/confirm', [\App\Http\Controllers\ThriftPackageController::class, 'confirmContributors']);
            Route::post('{id}/generate-slots', [\App\Http\Controllers\ThriftPackageController::class, 'generateSlots']);
            Route::get('{id}/transactions', [\App\Http\Controllers\ThriftPackageController::class, 'transactions']);
            Route::post('{id}/payout', [\App\Http\Controllers\ThriftPackageController::class, 'payout']);
            Route::post('{id}/slots/request', [\App\Http\Controllers\ThriftPackageController::class, 'requestSlot']);
            Route::post('{id}/slots/{slotNo}/accept', [\App\Http\Controllers\ThriftPackageController::class, 'acceptSlotRequest']);
            Route::post('{id}/slots/{slotNo}/decline', [\App\Http\Controllers\ThriftPackageController::class, 'declineSlotRequest']);
            Route::post('{id}/add-admin', [\App\Http\Controllers\ThriftPackageController::class, 'addAdmin']);
        });

    Route::controller(AuthController::class)->group(function() {

        Route::post('/change-password', 'changePassword');
        Route::post('/logout','logout');
    });





   
});



?>