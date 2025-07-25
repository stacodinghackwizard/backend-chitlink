<?php

namespace App\Http;

use Illuminate\Foundation\Http\Kernel as HttpKernel;



class Kernel extends HttpKernel
{
    /**
     * The application's global HTTP middleware stack.
     */

   

     
    protected $middleware = [
        // \App\Http\Middleware\TrustHosts::class,
        \App\Http\Middleware\TrustProxies::class,
        \Illuminate\Http\Middleware\HandleCors::class,
        \App\Http\Middleware\PreventRequestsDuringMaintenance::class,
        \Illuminate\Foundation\Http\Middleware\ValidatePostSize::class,
        \App\Http\Middleware\TrimStrings::class,
        \Illuminate\Foundation\Http\Middleware\ConvertEmptyStringsToNull::class,
      
    ];

    /**
     * The application's route middleware groups.
     */
    protected $middlewareGroups = [
        'web' => [
            \App\Http\Middleware\EncryptCookies::class,
            \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
            \Illuminate\Session\Middleware\StartSession::class,
            \Illuminate\View\Middleware\ShareErrorsFromSession::class,
            \App\Http\Middleware\VerifyCsrfToken::class,
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
        ],

        'api' => [
            'throttle:api',
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
        ],
    ];

    /**
     * The application's route middleware.
     */
    protected $routeMiddleware = [
        'auth' => \App\Http\Middleware\Authenticate::class,
        'auth.basic' => \Illuminate\Auth\Middleware\AuthenticateWithBasicAuth::class,
        'auth.session' => \Illuminate\Session\Middleware\AuthenticateSession::class,
        'cache.headers' => \Illuminate\Http\Middleware\SetCacheHeaders::class,
        'can' => \Illuminate\Auth\Middleware\Authorize::class,
        'guest' => \App\Http\Middleware\RedirectIfAuthenticated::class,
        'password.confirm' => \Illuminate\Auth\Middleware\RequirePassword::class,
        'signed' => \App\Http\Middleware\ValidateSignature::class,
        'throttle' => \Illuminate\Routing\Middleware\ThrottleRequests::class,
        'verified' => \Illuminate\Auth\Middleware\EnsureEmailIsVerified::class,
        'check.kyc' => \App\Http\Middleware\CheckKyc::class,
        'kyc' => \App\Http\Middleware\CheckKyc::class,
        'thrift.admin_or_merchant' => \App\Http\Middleware\CheckThriftAdminOrMerchant::class,
        // 'update.token.activity' => \App\Http\Middleware\UpdateTokenActivity::class,
        // 'handle.expired.tokens' => \App\Http\Middleware\HandleExpiredTokens::class,
    ];

    /**
     * The application's route middleware aliases.
     *
     * @var array<string, class-string|string>
     */
    // protected $middlewareAliases = [
    //     'update.token.activity' => \App\Http\Middleware\UpdateTokenActivity::class,
    //     'handle.expired.tokens' => \App\Http\Middleware\HandleExpiredTokens::class,
    // ];


      public function __construct(...$args)
     {
         file_put_contents(storage_path('kernel-debug.txt'), 'Kernel loaded at '.now().PHP_EOL, FILE_APPEND);
         parent::__construct(...$args);
     }


}