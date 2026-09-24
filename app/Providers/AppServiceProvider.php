<?php

namespace App\Providers;

use App\Contracts\Notifications\EmailOtpSenderInterface;
use App\Contracts\Notifications\OtpNotificationServiceInterface;
use App\Contracts\Notifications\SmsOtpSenderInterface;
use App\Repositories\Contracts\SaloonRepositoryInterface;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Repositories\Eloquent\SaloonRepository;
use App\Repositories\Eloquent\UserRepository;
use App\Services\Notifications\AwsSesEmailOtpSender;
use App\Services\Notifications\AwsSnsSmsOtpSender;
use App\Services\Notifications\LogEmailOtpSender;
use App\Services\Notifications\LogSmsOtpSender;
use App\Services\Notifications\OtpNotificationService;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use App\Models\SalonServiceProduct;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Symfony\Component\Mailer\Bridge\Brevo\Transport\BrevoTransportFactory;
use Symfony\Component\Mailer\Transport\Dsn;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(UserRepositoryInterface::class, UserRepository::class);
        $this->app->bind(SaloonRepositoryInterface::class, SaloonRepository::class);
        $this->app->bind(EmailOtpSenderInterface::class, function () {
            return match (config('otp.channels.email.driver', 'log')) {
                'aws' => new AwsSesEmailOtpSender(config('otp.aws', [])),
                default => new LogEmailOtpSender,
            };
        });
        $this->app->bind(SmsOtpSenderInterface::class, function () {
            return match (config('otp.channels.sms.driver', 'log')) {
                'aws' => new AwsSnsSmsOtpSender([
                    ...config('otp.aws', []),
                    'channels' => config('otp.channels', []),
                ]),
                default => new LogSmsOtpSender,
            };
        });
        $this->app->bind(OtpNotificationServiceInterface::class, OtpNotificationService::class);

        $this->app->singleton(\App\Services\Payment\PaymentGatewayManager::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Route::model('catalog', SalonServiceProduct::class);

        Mail::extend('brevo', function () {
            return (new BrevoTransportFactory)->create(
                new Dsn(
                    'brevo+api',
                    'default',
                    config('services.brevo.key'),
                )
            );
        });

        RateLimiter::for('auth', function (Request $request): Limit {
            return Limit::perMinute(10)->by($request->ip());
        });

        RateLimiter::for('otp', function (Request $request): Limit {
            $login = (string) $request->input('login', '');

            return Limit::perMinute(5)->by($request->ip().'|'.$login);
        });
    }
}
