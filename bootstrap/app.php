<?php

use App\Http\Middleware\EnsureAffiliatePermission;
use App\Http\Middleware\EnsurePlatformPermission;
use App\Http\Middleware\EnsureSubscriptionAccess;
use App\Http\Middleware\EnsureSubscriptionModule;
use App\Http\Middleware\EnsureSystemAdmin;
use App\Http\Middleware\EnsureTenantContext;
use App\Http\Middleware\EnsureTenantPermission;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'system.admin' => EnsureSystemAdmin::class,
            'affiliate.permission' => EnsureAffiliatePermission::class,
            'platform.permission' => EnsurePlatformPermission::class,
            'tenant.context' => EnsureTenantContext::class,
            'tenant.permission' => EnsureTenantPermission::class,
            'subscription.module' => EnsureSubscriptionModule::class,
            'subscription.access' => EnsureSubscriptionAccess::class,
        ]);
    })
    ->withSchedule(function (Schedule $schedule): void {
        $schedule->command('subscriptions:send-renewal-reminders')->dailyAt('09:00');
        $schedule->command('subscriptions:expire')->dailyAt('00:15');
        $schedule->command('appointments:send-reminders')->hourly();
        $schedule->command('payments:send-outstanding-reminders')->dailyAt('10:00');
        $schedule->command('customers:send-occasion-wishes')->dailyAt('08:00');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
