<?php

use App\Http\Controllers\Api\V1\Admin\AdminAffiliatePartnerController;
use App\Http\Controllers\Api\V1\Admin\AdminBranchController;
use App\Http\Controllers\Api\V1\Admin\AdminNotificationController;
use App\Http\Controllers\Api\V1\Admin\AdminOnboardingController;
use App\Http\Controllers\Api\V1\Admin\AdminPlatformSettingsController;
use App\Http\Controllers\Api\V1\Admin\AdminSubscriptionHistoryController;
use App\Http\Controllers\Api\V1\Admin\AdminSubscriptionRenewalController;
use App\Http\Controllers\Api\V1\Admin\AdminSubscriptionUpgradeController;
use App\Http\Controllers\Api\V1\Affiliate\AffiliatePortalController;
use App\Http\Controllers\Api\V1\Analytics\BusinessLedgerController;
use App\Http\Controllers\Api\V1\Analytics\OutstandingPaymentReportController;
use App\Http\Controllers\Api\V1\Analytics\ProductSalesReportController;
use App\Http\Controllers\Api\V1\Analytics\ProfitReportController;
use App\Http\Controllers\Api\V1\Analytics\SalonAnalyticsController;
use App\Http\Controllers\Api\V1\Appointment\AppointmentController;
use App\Http\Controllers\Api\V1\Appointment\AppointmentPaymentController;
use App\Http\Controllers\Api\V1\Appointment\AppointmentReceiptController;
use App\Http\Controllers\Api\V1\Auth\LoginController;
use App\Http\Controllers\Api\V1\Auth\LogoutController;
use App\Http\Controllers\Api\V1\Auth\MeController;
use App\Http\Controllers\Api\V1\Auth\RegisterController;
use App\Http\Controllers\Api\V1\Auth\RequestPasswordResetOtpController;
use App\Http\Controllers\Api\V1\Auth\ResetPasswordWithOtpController;
use App\Http\Controllers\Api\V1\Auth\VerifyPasswordResetOtpController;
use App\Http\Controllers\Api\V1\Billing\BillingConfigController;
use App\Http\Controllers\Api\V1\Branch\BranchController;
use App\Http\Controllers\Api\V1\Catalog\SalonCatalogController;
use App\Http\Controllers\Api\V1\Category\CategoryController;
use App\Http\Controllers\Api\V1\Customer\CustomerController;
use App\Http\Controllers\Api\V1\Customer\CustomerTagController;
use App\Http\Controllers\Api\V1\Expense\ExpenseController;
use App\Http\Controllers\Api\V1\Inventory\InventoryController;
use App\Http\Controllers\Api\V1\Inventory\PurchaseOrderController;
use App\Http\Controllers\Api\V1\Inventory\SupplierController;
use App\Http\Controllers\Api\V1\Onboarding\OnboardingController;
use App\Http\Controllers\Api\V1\Payment\PaymentWebhookController;
use App\Http\Controllers\Api\V1\Permission\PermissionController;
use App\Http\Controllers\Api\V1\Pos\PosController;
use App\Http\Controllers\Api\V1\Product\ProductController;
use App\Http\Controllers\Api\V1\Profile\ChangePasswordController;
use App\Http\Controllers\Api\V1\Profile\UpdateProfileController;
use App\Http\Controllers\Api\V1\Public\ApplyAffiliatePartnerController;
use App\Http\Controllers\Api\V1\Booking\BookingLinkController;
use App\Http\Controllers\Api\V1\Public\PublicBootstrapController;
use App\Http\Controllers\Api\V1\Public\PublicBookingController;
use App\Http\Controllers\Api\V1\Queue\LiveQueueController;
use App\Http\Controllers\Api\V1\Referral\SalonReferralController;
use App\Http\Controllers\Api\V1\Role\RoleController;
use App\Http\Controllers\Api\V1\Saloon\SaloonController;
use App\Http\Controllers\Api\V1\Saloon\SaloonServiceProductController;
use App\Http\Controllers\Api\V1\Service\ServiceController;
use App\Http\Controllers\Api\V1\Staff\StaffController;
use App\Http\Controllers\Api\V1\Staff\StaffEarningsController;
use App\Http\Controllers\Api\V1\Subscription\SaloonSubscriptionController;
use App\Http\Controllers\Api\V1\Subscription\SubscriptionPlanController;
use App\Http\Controllers\Api\V1\Tenant\SalonBusinessProfileController;
use App\Http\Controllers\Api\V1\Tenant\SalonSettingsController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::post('/webhooks/payment/razorpay', [PaymentWebhookController::class, 'razorpay']);
    Route::post('/webhooks/payment/stripe', [PaymentWebhookController::class, 'stripe']);

    Route::get('/public/platform-branding', [PublicBootstrapController::class, 'platformBranding']);
    Route::get('/public/subscription-modules', [PublicBootstrapController::class, 'subscriptionModules']);

    Route::middleware('throttle:60,1')->group(function (): void {
        Route::get('/public/book/{token}', [PublicBookingController::class, 'show']);
        Route::get('/public/book/{token}/slots', [PublicBookingController::class, 'slots']);
        Route::post('/public/book/{token}', [PublicBookingController::class, 'store']);
    });

    Route::middleware('throttle:auth')->group(function (): void {
        Route::post('/public/register', RegisterController::class);
        Route::post('/public/login', LoginController::class);
        Route::post('/public/affiliate/apply', ApplyAffiliatePartnerController::class);
    });

    Route::middleware('throttle:otp')->group(function (): void {
        Route::post('/public/forgot-password/request-otp', RequestPasswordResetOtpController::class);
        Route::post('/public/forgot-password/verify-otp', VerifyPasswordResetOtpController::class);
        Route::post('/public/forgot-password/reset-password', ResetPasswordWithOtpController::class);
    });

    Route::middleware(['auth:sanctum', 'platform.permission:platform.salon_onboarding.view'])->group(function (): void {
        Route::get('/admin/onboarding', [AdminOnboardingController::class, 'index']);
        Route::get('/admin/onboarding/{saloon}', [AdminOnboardingController::class, 'show']);
    });
    Route::middleware(['auth:sanctum', 'platform.permission:platform.salon_onboarding.create'])->group(function (): void {
        Route::post('/admin/onboarding', [AdminOnboardingController::class, 'store']);
    });
    Route::middleware(['auth:sanctum', 'platform.permission:platform.salon_onboarding.update'])->group(function (): void {
        Route::put('/admin/onboarding/{saloon}', [AdminOnboardingController::class, 'update']);
        Route::post('/admin/onboarding/owners/{owner}/complete', [AdminOnboardingController::class, 'completeOwner']);
        Route::post('/admin/onboarding/owners/{owner}/reset', [AdminOnboardingController::class, 'resetOwner']);
    });

    Route::middleware(['auth:sanctum', 'platform.permission:platform.salon_onboarding.view'])->group(function (): void {
        Route::get('saloons', [SaloonController::class, 'index']);
        Route::get('saloons/{saloon}', [SaloonController::class, 'show']);
    });
    Route::middleware(['auth:sanctum', 'platform.permission:platform.salon_onboarding.update'])->group(function (): void {
        Route::put('saloons/{saloon}', [SaloonController::class, 'update']);
        Route::patch('saloons/{saloon}', [SaloonController::class, 'update']);
    });
    Route::middleware(['auth:sanctum', 'platform.permission:platform.salon_onboarding.delete'])
        ->delete('saloons/{saloon}', [SaloonController::class, 'destroy']);

    Route::middleware(['auth:sanctum', 'platform.permission:platform.subscription_plans.create'])
        ->post('subscription-plans', [SubscriptionPlanController::class, 'store']);
    Route::middleware(['auth:sanctum', 'platform.permission:platform.subscription_plans.update'])->group(function (): void {
        Route::put('subscription-plans/{subscription_plan}', [SubscriptionPlanController::class, 'update']);
        Route::patch('subscription-plans/{subscription_plan}', [SubscriptionPlanController::class, 'update']);
    });
    Route::middleware(['auth:sanctum', 'platform.permission:platform.subscription_plans.delete'])
        ->delete('subscription-plans/{subscription_plan}', [SubscriptionPlanController::class, 'destroy']);

    Route::middleware(['auth:sanctum', 'platform.permission:platform.upgrade_requests.view'])->group(function (): void {
        Route::get('/admin/subscription-upgrades', [AdminSubscriptionUpgradeController::class, 'index']);
    });
    Route::middleware(['auth:sanctum', 'platform.permission:platform.upgrade_requests.update'])->group(function (): void {
        Route::post('/admin/subscription-upgrades/{subscriptionUpgradeOrder}/approve', [AdminSubscriptionUpgradeController::class, 'approve']);
        Route::post('/admin/subscription-upgrades/{subscriptionUpgradeOrder}/reject', [AdminSubscriptionUpgradeController::class, 'reject']);
        Route::post('/admin/saloons/{saloon}/subscription/assign', [AdminSubscriptionUpgradeController::class, 'assign']);
    });

    Route::middleware(['auth:sanctum', 'platform.permission:platform.subscription_plans.view'])->group(function (): void {
        Route::get('/admin/subscription-renewals', [AdminSubscriptionRenewalController::class, 'index']);
    });

    Route::middleware(['auth:sanctum', 'system.admin'])->group(function (): void {
        Route::get('/admin/subscription-history', [AdminSubscriptionHistoryController::class, 'index']);
        Route::get('/admin/platform-settings', [AdminPlatformSettingsController::class, 'index']);
        Route::get('/admin/platform-settings/{group}', [AdminPlatformSettingsController::class, 'show']);
        Route::put('/admin/platform-settings/{group}', [AdminPlatformSettingsController::class, 'update']);
        Route::post('/admin/platform-settings/branding/logo', [AdminPlatformSettingsController::class, 'uploadLogo']);
        Route::delete('/admin/platform-settings/branding/logo', [AdminPlatformSettingsController::class, 'deleteLogo']);
    });

    Route::middleware(['auth:sanctum', 'platform.permission:platform.branches.view'])->group(function (): void {
        Route::get('/admin/branches', [AdminBranchController::class, 'index']);
        Route::get('/admin/saloons/{saloon}/branch-capacity', [AdminBranchController::class, 'salonCapacity']);
    });
    Route::middleware(['auth:sanctum', 'platform.permission:platform.branches.create'])->group(function (): void {
        Route::post('/admin/branches', [AdminBranchController::class, 'store']);
    });
    Route::middleware(['auth:sanctum', 'platform.permission:platform.branches.update'])->group(function (): void {
        Route::put('/admin/branches/{branch}', [AdminBranchController::class, 'update']);
        Route::patch('/admin/branches/{branch}', [AdminBranchController::class, 'update']);
    });

    Route::middleware(['auth:sanctum', 'platform.permission:platform.affiliates.view|platform.upgrade_requests.view|platform.salon_onboarding.view'])->group(function (): void {
        Route::get('/admin/notifications', [AdminNotificationController::class, 'index']);
        Route::get('/admin/notifications/unread-count', [AdminNotificationController::class, 'unreadCount']);
        Route::get('/admin/notifications/pending-counts', [AdminNotificationController::class, 'pendingCounts']);
        Route::post('/admin/notifications/read-all', [AdminNotificationController::class, 'markAllRead']);
        Route::post('/admin/notifications/{id}/read', [AdminNotificationController::class, 'markRead']);
    });

    Route::middleware(['auth:sanctum', 'platform.permission:platform.affiliates.view'])->group(function (): void {
        Route::get('/admin/affiliates', [AdminAffiliatePartnerController::class, 'index']);
        Route::get('/admin/affiliate-withdrawals', [AdminAffiliatePartnerController::class, 'withdrawals']);
    });
    Route::middleware(['auth:sanctum', 'platform.permission:platform.affiliates.create'])->group(function (): void {
        Route::post('/admin/affiliates', [AdminAffiliatePartnerController::class, 'store']);
    });
    Route::middleware(['auth:sanctum', 'platform.permission:platform.affiliates.update'])->group(function (): void {
        Route::put('/admin/affiliates/{affiliatePartner}', [AdminAffiliatePartnerController::class, 'update']);
        Route::patch('/admin/affiliates/{affiliatePartner}', [AdminAffiliatePartnerController::class, 'update']);
        Route::post('/admin/affiliates/{affiliatePartner}/approve', [AdminAffiliatePartnerController::class, 'approve']);
        Route::post('/admin/affiliates/{affiliatePartner}/reject', [AdminAffiliatePartnerController::class, 'reject']);
        Route::post('/admin/affiliate-withdrawals/{withdrawal}/approve', [AdminAffiliatePartnerController::class, 'approveWithdrawal']);
        Route::post('/admin/affiliate-withdrawals/{withdrawal}/reject', [AdminAffiliatePartnerController::class, 'rejectWithdrawal']);
        Route::post('/admin/affiliate-withdrawals/{withdrawal}/mark-paid', [AdminAffiliatePartnerController::class, 'markWithdrawalPaid']);
    });

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::get('saloons/{saloon}/service-products', [SaloonServiceProductController::class, 'index']);
        Route::post('/logout', LogoutController::class);
        Route::get('/me', MeController::class);

        Route::post('/onboarding/account', [OnboardingController::class, 'account']);
        Route::post('/onboarding/branch', [OnboardingController::class, 'branch']);
        Route::post('/onboarding/services', [OnboardingController::class, 'services']);
        Route::post('/onboarding/complete', [OnboardingController::class, 'complete']);

        Route::get('subscription-plans', [SubscriptionPlanController::class, 'index']);
        Route::get('subscription-plans/{subscription_plan}', [SubscriptionPlanController::class, 'show']);

        Route::middleware(['tenant.permission:categories.view', 'subscription.access'])->group(function (): void {
            Route::get('categories', [CategoryController::class, 'index']);
            Route::get('categories/{category}', [CategoryController::class, 'show']);
        });
        Route::middleware(['tenant.permission:products.view', 'subscription.access'])->group(function (): void {
            Route::get('products', [ProductController::class, 'index']);
            Route::get('products/{product}', [ProductController::class, 'show']);
        });
        Route::middleware(['tenant.permission:services.view', 'subscription.access'])->group(function (): void {
            Route::get('services', [ServiceController::class, 'index']);
            Route::get('services/{service}', [ServiceController::class, 'show']);
        });

        Route::middleware(['tenant.permission:categories.create', 'subscription.access'])->post('categories', [CategoryController::class, 'store']);
        Route::middleware(['tenant.permission:categories.update', 'subscription.access'])->match(['put', 'patch'], 'categories/{category}', [CategoryController::class, 'update']);
        Route::middleware(['tenant.permission:categories.delete', 'subscription.access'])->delete('categories/{category}', [CategoryController::class, 'destroy']);
        Route::middleware(['tenant.permission:products.create', 'subscription.access'])->post('products', [ProductController::class, 'store']);
        Route::middleware(['tenant.permission:products.update', 'subscription.access'])->match(['put', 'patch'], 'products/{product}', [ProductController::class, 'update']);
        Route::middleware(['tenant.permission:products.delete', 'subscription.access'])->delete('products/{product}', [ProductController::class, 'destroy']);
        Route::middleware(['tenant.permission:services.create', 'subscription.access'])->post('services', [ServiceController::class, 'store']);
        Route::middleware(['tenant.permission:services.update', 'subscription.access'])->match(['put', 'patch'], 'services/{service}', [ServiceController::class, 'update']);
        Route::middleware(['tenant.permission:services.delete', 'subscription.access'])->delete('services/{service}', [ServiceController::class, 'destroy']);
    });

    Route::middleware(['auth:sanctum', 'tenant.context', 'subscription.access'])->group(function (): void {
        Route::middleware('tenant.permission:settings.update')->group(function (): void {
            Route::put('/profile', UpdateProfileController::class);
            Route::put('/password', ChangePasswordController::class);
        });

        Route::put('saloons/{saloon}/service-products', [SaloonServiceProductController::class, 'sync']);

        Route::middleware('tenant.permission:settings.view')->group(function (): void {
            Route::get('billing/config', [BillingConfigController::class, 'show']);
            Route::get('subscription', [SaloonSubscriptionController::class, 'show']);
            Route::get('salon/business-profile', [SalonBusinessProfileController::class, 'show']);
            Route::get('salon/settings', [SalonSettingsController::class, 'index']);
            Route::get('salon/settings/{group}', [SalonSettingsController::class, 'show']);
        });

        Route::middleware('tenant.permission:settings.update')->group(function (): void {
            Route::post('subscription/upgrade', [SaloonSubscriptionController::class, 'upgrade']);
            Route::post('subscription/checkout', [SaloonSubscriptionController::class, 'checkout']);
            Route::post('subscription/checkout/confirm', [SaloonSubscriptionController::class, 'confirmCheckout']);
            Route::put('salon/business-profile', [SalonBusinessProfileController::class, 'update']);
            Route::put('salon/settings/{group}', [SalonSettingsController::class, 'update']);
            Route::post('salon/settings/branding/logo', [SalonSettingsController::class, 'uploadLogo']);
            Route::delete('salon/settings/branding/logo', [SalonSettingsController::class, 'deleteLogo']);
        });

        Route::middleware(['tenant.permission:branches.view', 'subscription.module:settings'])->group(function (): void {
            Route::get('branches', [BranchController::class, 'index']);
        });

        Route::middleware(['tenant.permission:branches.create', 'subscription.module:settings'])->group(function (): void {
            Route::post('branches', [BranchController::class, 'store']);
        });
        Route::middleware(['tenant.permission:branches.update', 'subscription.module:settings'])->group(function (): void {
            Route::put('branches/{branch}', [BranchController::class, 'update']);
            Route::patch('branches/{branch}', [BranchController::class, 'update']);
        });

        Route::middleware(['tenant.permission:appointments.view', 'subscription.module:appointments'])->group(function (): void {
            Route::get('pos/catalog', [PosController::class, 'catalog']);
            Route::get('appointments/booking-options', [AppointmentController::class, 'bookingOptions']);
            Route::get('appointments/customers', [AppointmentController::class, 'customerSearch']);
            Route::get('appointments', [AppointmentController::class, 'index']);
            Route::get('appointments/{appointment}', [AppointmentController::class, 'show']);
            Route::get('appointments/{appointment}/receipt', AppointmentReceiptController::class);
            Route::get('booking-links', [BookingLinkController::class, 'index']);
        });

        Route::middleware(['tenant.permission:queue.view', 'subscription.module:queue'])->group(function (): void {
            Route::get('queue/live', [LiveQueueController::class, 'index']);
        });

        Route::middleware(['tenant.permission:appointments.create', 'subscription.module:appointments'])->group(function (): void {
            Route::post('pos/checkout', [PosController::class, 'checkout']);
            Route::post('appointments', [AppointmentController::class, 'store']);
        });

        Route::middleware(['tenant.permission:booking_links.manage', 'subscription.module:appointments'])->group(function (): void {
            Route::post('booking-links', [BookingLinkController::class, 'store']);
            Route::patch('booking-links/{bookingLink}', [BookingLinkController::class, 'update']);
        });

        Route::middleware(['tenant.permission:appointments.update', 'subscription.module:appointments'])->group(function (): void {
            Route::put('appointments/{appointment}', [AppointmentController::class, 'update']);
            Route::patch('appointments/{appointment}', [AppointmentController::class, 'update']);
            Route::post('appointments/{appointment}/capture-payment', [AppointmentPaymentController::class, 'capture']);
            Route::post('appointments/{appointment}/refund-payment', [AppointmentPaymentController::class, 'refund']);
            Route::post('appointments/{appointment}/remind-payment', [AppointmentPaymentController::class, 'remind']);
        });

        Route::middleware(['tenant.permission:appointments.delete', 'subscription.module:appointments'])->group(function (): void {
            Route::delete('appointments/{appointment}', [AppointmentController::class, 'destroy']);
        });

        Route::middleware(['tenant.permission:customers.view', 'subscription.module:customers'])->group(function (): void {
            Route::get('customers', [CustomerController::class, 'index']);
            Route::get('customers/{customer}', [CustomerController::class, 'show']);
            Route::get('customer-tags', [CustomerTagController::class, 'index']);
        });

        Route::middleware(['tenant.permission:customers.manage', 'subscription.module:customers'])->group(function (): void {
            Route::post('customers', [CustomerController::class, 'store']);
            Route::put('customers/{customer}', [CustomerController::class, 'update']);
            Route::patch('customers/{customer}', [CustomerController::class, 'update']);
            Route::delete('customers/{customer}', [CustomerController::class, 'destroy']);
            Route::post('customer-tags', [CustomerTagController::class, 'store']);
            Route::put('customer-tags/{tag}', [CustomerTagController::class, 'update']);
            Route::patch('customer-tags/{tag}', [CustomerTagController::class, 'update']);
            Route::delete('customer-tags/{tag}', [CustomerTagController::class, 'destroy']);
        });

        Route::middleware(['tenant.permission:analytics.view', 'subscription.module:analytics'])->group(function (): void {
            Route::get('analytics/summary', [SalonAnalyticsController::class, 'summary']);
            Route::get('reports/profit-loss', [ProfitReportController::class, 'summary']);
            Route::get('reports/outstanding-payments', [OutstandingPaymentReportController::class, 'summary']);
            Route::get('reports/business-close', [BusinessLedgerController::class, 'summary']);
        });

        Route::middleware(['tenant.permission:expenses.view', 'subscription.module:analytics'])->group(function (): void {
            Route::get('expenses', [ExpenseController::class, 'index']);
            Route::get('expenses/categories', [ExpenseController::class, 'categories']);
        });

        Route::middleware(['tenant.permission:expenses.manage', 'subscription.module:analytics'])->group(function (): void {
            Route::post('expenses', [ExpenseController::class, 'store']);
            Route::match(['put', 'patch'], 'expenses/{expense}', [ExpenseController::class, 'update']);
            Route::delete('expenses/{expense}', [ExpenseController::class, 'destroy']);
        });

        Route::middleware(['tenant.permission:inventory.view', 'subscription.module:inventory'])->group(function (): void {
            Route::get('inventory/product-sales', [ProductSalesReportController::class, 'summary']);
            Route::get('inventory/summary', [InventoryController::class, 'summary']);
            Route::get('inventory/stock', [InventoryController::class, 'stock']);
            Route::get('inventory/movements', [InventoryController::class, 'movements']);
            Route::get('suppliers', [SupplierController::class, 'index']);
            Route::get('purchase-orders', [PurchaseOrderController::class, 'index']);
            Route::get('purchase-orders/{purchaseOrder}', [PurchaseOrderController::class, 'show']);
        });

        Route::middleware(['tenant.permission:inventory.manage', 'subscription.module:inventory'])->group(function (): void {
            Route::post('inventory/stock', [InventoryController::class, 'storeStock']);
            Route::post('inventory/stock/{stock}/adjust', [InventoryController::class, 'adjust']);
            Route::post('suppliers', [SupplierController::class, 'store']);
            Route::put('suppliers/{supplier}', [SupplierController::class, 'update']);
            Route::patch('suppliers/{supplier}', [SupplierController::class, 'update']);
            Route::delete('suppliers/{supplier}', [SupplierController::class, 'destroy']);
            Route::post('purchase-orders', [PurchaseOrderController::class, 'store']);
            Route::post('purchase-orders/{purchaseOrder}/place', [PurchaseOrderController::class, 'place']);
            Route::post('purchase-orders/{purchaseOrder}/receive', [PurchaseOrderController::class, 'receive']);
            Route::post('purchase-orders/{purchaseOrder}/cancel', [PurchaseOrderController::class, 'cancel']);
        });

        Route::middleware(['tenant.permission:services.view|products.view', 'subscription.module:catalog|settings'])->group(function (): void {
            Route::get('catalog', [SalonCatalogController::class, 'index']);
            Route::get('catalog/{catalog}', [SalonCatalogController::class, 'show']);
        });

        Route::middleware(['tenant.permission:services.create|products.create', 'subscription.module:catalog'])->group(function (): void {
            Route::post('catalog', [SalonCatalogController::class, 'store']);
        });
        Route::middleware(['tenant.permission:services.update|products.update', 'subscription.module:catalog'])->group(function (): void {
            Route::put('catalog/{catalog}', [SalonCatalogController::class, 'update']);
            Route::patch('catalog/{catalog}', [SalonCatalogController::class, 'update']);
        });
        Route::middleware(['tenant.permission:services.delete|products.delete', 'subscription.module:catalog'])->group(function (): void {
            Route::delete('catalog/{catalog}', [SalonCatalogController::class, 'destroy']);
        });

        Route::middleware(['tenant.permission:staff.earnings.view', 'subscription.module:staff'])->group(function (): void {
            Route::get('staff/earnings', [StaffEarningsController::class, 'index']);
            Route::get('staff/earnings/presets', [StaffEarningsController::class, 'presets']);
        });

        Route::middleware(['tenant.permission:staff.view', 'subscription.module:staff'])->group(function (): void {
            Route::get('staff/assignable-roles', [StaffController::class, 'assignableRoles']);
            Route::get('staff', [StaffController::class, 'index']);
            Route::get('staff/{staff}', [StaffController::class, 'show']);
        });

        Route::middleware(['tenant.permission:staff.create', 'subscription.module:staff'])->group(function (): void {
            Route::post('staff', [StaffController::class, 'store']);
        });

        Route::middleware(['tenant.permission:staff.update', 'subscription.module:staff'])->group(function (): void {
            Route::put('staff/{staff}', [StaffController::class, 'update']);
            Route::patch('staff/{staff}', [StaffController::class, 'update']);
        });

        Route::middleware(['tenant.permission:staff.delete', 'subscription.module:staff'])->group(function (): void {
            Route::delete('staff/{staff}', [StaffController::class, 'destroy']);
        });

        Route::middleware(['tenant.permission:assign_permissions.view|assign_permissions.update', 'subscription.module:roles'])->group(function (): void {
            Route::get('permissions', [PermissionController::class, 'index']);
        });

        Route::middleware(['tenant.permission:roles.view', 'subscription.module:roles'])->group(function (): void {
            Route::get('roles', [RoleController::class, 'index']);
            Route::get('roles/{role}', [RoleController::class, 'show']);
        });

        Route::middleware(['tenant.permission:roles.create', 'subscription.module:roles'])->group(function (): void {
            Route::post('roles', [RoleController::class, 'store']);
            Route::post('roles/{role}/clone', [RoleController::class, 'clone']);
        });

        Route::middleware(['tenant.permission:roles.update', 'subscription.module:roles'])->group(function (): void {
            Route::put('roles/{role}', [RoleController::class, 'update']);
            Route::patch('roles/{role}', [RoleController::class, 'update']);
        });

        Route::middleware(['tenant.permission:roles.delete', 'subscription.module:roles'])->group(function (): void {
            Route::delete('roles/{role}', [RoleController::class, 'destroy']);
        });

        Route::middleware(['tenant.permission:assign_permissions.update', 'subscription.module:roles'])->group(function (): void {
            Route::put('roles/{role}/permissions', [RoleController::class, 'assignPermissions']);
        });

        Route::middleware('tenant.permission:referrals.view')->group(function (): void {
            Route::get('referrals/dashboard', [SalonReferralController::class, 'dashboard']);
        });
    });

    Route::middleware(['auth:sanctum', 'affiliate.permission:affiliate.dashboard.view'])->group(function (): void {
        Route::get('/affiliate/dashboard', [AffiliatePortalController::class, 'dashboard']);
    });
    Route::middleware(['auth:sanctum', 'affiliate.permission:affiliate.referrals.view'])->group(function (): void {
        Route::get('/affiliate/referrals', [AffiliatePortalController::class, 'referrals']);
    });
    Route::middleware(['auth:sanctum', 'affiliate.permission:affiliate.commissions.view'])->group(function (): void {
        Route::get('/affiliate/commissions', [AffiliatePortalController::class, 'commissions']);
    });
    Route::middleware(['auth:sanctum', 'affiliate.permission:affiliate.reports.view'])->group(function (): void {
        Route::get('/affiliate/reports', [AffiliatePortalController::class, 'reports']);
    });
    Route::middleware(['auth:sanctum', 'affiliate.permission:affiliate.withdrawals.view'])->group(function (): void {
        Route::get('/affiliate/withdrawals', [AffiliatePortalController::class, 'withdrawals']);
    });
    Route::middleware(['auth:sanctum', 'affiliate.permission:affiliate.withdrawals.create'])->group(function (): void {
        Route::post('/affiliate/withdrawals', [AffiliatePortalController::class, 'requestWithdrawal']);
    });
});
