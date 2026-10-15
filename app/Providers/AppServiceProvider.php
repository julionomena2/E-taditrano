<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('registration', function (Request $request) {
            $email = $request->input('email');
            $email = is_string($email) ? Str::lower(trim($email)) : '';
            $emailKey = hash('sha256', $email);
            $ipKey = (string) $request->ip();

            return [
                Limit::perMinute(5)->by('registration-email-ip:'.hash('sha256', $email.'|'.$ipKey)),
                Limit::perHour(8)->by('registration-email:'.$emailKey),
                Limit::perMinute(30)->by('registration-ip:'.$ipKey),
            ];
        });

        RateLimiter::for('login', function (Request $request) {
            $email = $request->input('email');
            $email = is_string($email) ? Str::lower(trim($email)) : '';
            $ipKey = hash('sha256', (string) $request->ip());

            return [
                Limit::perMinute(5)->by('login-email-ip:'.hash('sha256', $email.'|'.$ipKey)),
                Limit::perMinute(30)->by('login-ip:'.$ipKey),
            ];
        });
    }
}
