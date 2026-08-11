<?php

namespace App\Providers;

use App\Auth\TenantAwareUserProvider;
use App\Contracts\PaymentGatewayInterface;
use App\Models\AkunPendaftar;
use App\Notifications\Channels\WhatsAppChannel;
use App\Services\Finance\Gateway\BriSnapGateway;
use App\Services\Finance\Gateway\MockPaymentGateway;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Auth\Middleware\RedirectIfAuthenticated;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(PaymentGatewayInterface::class, function ($app) {
            $gatewayConfig = config('services.bri.gateway', 'mock');
            
            if ($gatewayConfig === 'snap') {
                return new BriSnapGateway();
            }

            return new MockPaymentGateway();
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Auth::provider('tenant-aware', function ($app, array $config) {
            return new TenantAwareUserProvider($app['hash'], $config['model']);
        });

        Authenticate::redirectUsing(
            fn ($request) => $request->is('portal/*') ? route('portal.login') : route('login')
        );

        RedirectIfAuthenticated::redirectUsing(
            fn ($request) => $request->is('portal/*') ? route('portal.dashboard') : route('dashboard')
        );

        ResetPassword::createUrlUsing(function ($notifiable, string $token) {
            $routeName = $notifiable instanceof AkunPendaftar ? 'portal.password.reset' : 'password.reset';

            return url(route($routeName, [
                'token' => $token,
                'email' => $notifiable->getEmailForPasswordReset(),
            ], false));
        });

        Notification::extend('whatsapp', fn ($app) => new WhatsAppChannel);
    }
}
