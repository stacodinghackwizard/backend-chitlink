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
    Route::get('/token/status', [AuthController::class, 'checkTokenStatus']);
});

// User Routes (KYC required)
Route::middleware(['auth:sanctum', 'handle.expired.tokens', 'update.token.activity', \App\Http\Middleware\CheckKyc::class])->group(function () {
    Route::prefix('users')->group(function () {
        Route::controller(UserController::class)->group(function() {
            Route::get('/profile', 'profile');
            Route::put('/profile',  'updateProfile');
            Route::post('/profile/image',  'updateProfileImage');
        });
    });
    // ... any other user routes that require KYC ...
});

// Merchant Routes (NO KYC required, just auth)
Route::middleware(['auth:sanctum', 'handle.expired.tokens', 'update.token.activity'])->group(function () {
    Route::prefix('merchants')->group(function () {
        Route::controller(MerchantController::class)->group(function() {
            Route::get('/profile', 'profile');
            Route::put('/profile', 'updateProfile');
            Route::post('/profile/image', 'updateProfileImage');
        });
        // ... all other merchant routes ...
        Route::apiResource('/contacts', ContactController::class)->only(['index', 'store', 'update', 'destroy']);
       
        Route::get('/contacts/users', [ContactController::class, 'getUsers']);
        Route::get('/users/public', [ContactController::class, 'publicUsers']);
        Route::post('/contacts', [ContactController::class, 'store']);
        Route::put('/contacts/{id}', [ContactController::class, 'update']);
        Route::post('/contact/delete', [ContactController::class, 'destroy']);
        Route::post('/contacts/import', [ContactController::class, 'importExcel']);
        Route::get('/contacts/download-excel', [ContactController::class, 'downloadSampleExcel']);
        Route::post('/contacts/search', [ContactController::class, 'searchContacts']);
        Route::post('/contact-groups/search', [ContactController::class, 'searchContactGroups']);
        Route::post('/contacts/bulk-delete', [ContactController::class, 'bulkDestroy']);
        Route::post('/contacts/add-users', [ContactController::class, 'addUserToContacts']);
        Route::prefix('contact-groups')->group(function () {
            Route::get('/', [ContactController::class, 'getGroups']);
            Route::post('/', [ContactController::class, 'createGroup']);
            Route::put('/{id}', [ContactController::class, 'updateGroup']);
            Route::delete('/{id}', [ContactController::class, 'deleteGroup']);
            Route::get('/{id}/contacts', [ContactController::class, 'getGroupContacts']);
            Route::post('/{id}/contacts', [ContactController::class, 'addContactsToGroup']);
            Route::delete('/{id}/contacts', [ContactController::class, 'removeContactsFromGroup']);
        });
    });
    // ... any other merchant-only routes ...

    // Thrift Package routes
    Route::prefix('thrift-packages')->group(function () {
        Route::post('/save-progress', [\App\Http\Controllers\ThriftPackageController::class, 'saveProgress']);
        Route::get('/public', [\App\Http\Controllers\ThriftPackageController::class, 'listPublicPackages']);
        Route::get('/public/{id}', [\App\Http\Controllers\ThriftPackageController::class, 'showPublicPackage']);
        Route::get('/{id}', [\App\Http\Controllers\ThriftPackageController::class, 'show']);
        Route::get('/', [\App\Http\Controllers\ThriftPackageController::class, 'index']);
        Route::post('/', [\App\Http\Controllers\ThriftPackageController::class, 'store']);
        Route::put('/{id}/terms', [\App\Http\Controllers\ThriftPackageController::class, 'updateTerms']);
        Route::post('/{id}/contributors', [\App\Http\Controllers\ThriftPackageController::class, 'addContributors']);
        Route::get('/{id}/contributors', [\App\Http\Controllers\ThriftPackageController::class, 'getContributors']);
        Route::post('/{id}/contributors/confirm', [\App\Http\Controllers\ThriftPackageController::class, 'confirmContributors']);
        Route::post('/{id}/generate-slots', [\App\Http\Controllers\ThriftPackageController::class, 'generateSlots']);
        Route::get('/{id}/transactions', [\App\Http\Controllers\ThriftPackageController::class, 'transactions']);
        Route::post('/{id}/payout', [\App\Http\Controllers\ThriftPackageController::class, 'payout']);
        Route::post('/{id}/slots/request', [\App\Http\Controllers\ThriftPackageController::class, 'requestSlot']);
        Route::post('/{id}/slots/{slotNo}/accept', [\App\Http\Controllers\ThriftPackageController::class, 'acceptSlotRequest']);
        Route::post('/{id}/slots/{slotNo}/decline', [\App\Http\Controllers\ThriftPackageController::class, 'declineSlotRequest']);
        Route::post('/{id}/add-admin', [\App\Http\Controllers\ThriftPackageController::class, 'addAdmin']);

        // Paystack: Initialize contribution payment with metadata
        Route::post('/{packageId}/initialize-contribution-payment', [\App\Http\Controllers\ThriftPackageController::class, 'initializeContributionPayment']);
        // Paystack: Verify contribution payment and update wallet
        Route::post('/verify-contribution-payment', [\App\Http\Controllers\ThriftPackageController::class, 'verifyContributionPayment']);
        // Paystack: Get wallet transaction history
        Route::get('/wallet-transactions', [\App\Http\Controllers\ThriftPackageController::class, 'walletTransactions']);
        // Paystack: Withdraw from wallet
        Route::post('/{id}/payout', [\App\Http\Controllers\ThriftPackageController::class, 'payout']);
        // Paystack: View a particular wallet transaction (receipt)
        Route::get('/wallet-transactions/{transactionId}', [\App\Http\Controllers\ThriftPackageController::class, 'showWalletTransaction']);
    });

    Route::controller(AuthController::class)->group(function() {
        Route::post('/change-password', 'changePassword');
        Route::post('/logout','logout');
    });

    // Thrift Package Invite & Application Endpoints
    Route::post('/thrift-packages/{id}/invite', [\App\Http\Controllers\ThriftPackageController::class, 'inviteUser']);
    Route::get('/users/thrift-invites', [\App\Http\Controllers\UserController::class, 'listThriftInvites']);
    Route::post('/thrift-invites/{invite_id}/respond', [\App\Http\Controllers\ThriftPackageController::class, 'respondToInvite']);
    Route::post('/thrift-packages/{id}/apply', [\App\Http\Controllers\ThriftPackageController::class, 'applyToPackage']);
    Route::get('/thrift-packages/{id}/applications', [\App\Http\Controllers\ThriftPackageController::class, 'listApplications']);
    Route::post('/thrift-applications/{application_id}/respond', [\App\Http\Controllers\ThriftPackageController::class, 'respondToApplication']);
    Route::get('/users/thrift-applications', [\App\Http\Controllers\UserController::class, 'listThriftApplications']);
    Route::get('/users/thrift-rejected', [\App\Http\Controllers\UserController::class, 'listRejectedPackages']);
});


?>