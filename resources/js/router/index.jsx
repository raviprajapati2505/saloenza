import React from 'react'
import { Navigate, Route, Routes } from 'react-router-dom'
import SalonReportsView from '../views/SalonReportsView.jsx'
import PlatformReportsView from '../views/admin/PlatformReportsView.jsx'
import AppointmentsView from '../views/AppointmentsView.jsx'
import PosView from '../views/PosView.jsx'
import LiveQueueView from '../views/LiveQueueView.jsx'
import StaffEarningsView from '../views/StaffEarningsView.jsx'
import BookingLinksView from '../views/BookingLinksView.jsx'
import PublicBookingView from '../views/public/PublicBookingView.jsx'
import BillingView from '../views/BillingView.jsx'
import CustomersView from '../views/CustomersView.jsx'
import DashboardView from '../views/DashboardView.jsx'
import InventoryView from '../views/InventoryView.jsx'
import SettingsView from '../views/SettingsView.jsx'
import BranchesView from '../views/BranchesView.jsx'
import RolesView from '../views/settings/RolesView.jsx'
import AssignPermissionsView from '../views/settings/AssignPermissionsView.jsx'
import ProductsMasterView from '../views/settings/ProductsMasterView.jsx'
import ServicesMasterView from '../views/settings/ServicesMasterView.jsx'
import CatalogView from '../views/CatalogView.jsx'
import StaffView from '../views/StaffView.jsx'
import CategoriesView from '../views/CategoriesView.jsx'
import ForgotPasswordView from '../views/auth/ForgotPasswordView.jsx'
import LoginView from '../views/auth/LoginView.jsx'
import RegisterView from '../views/auth/RegisterView.jsx'
import ResetPasswordView from '../views/auth/ResetPasswordView.jsx'
import TwoFactorView from '../views/auth/TwoFactorView.jsx'
import ComponentsTestView from '../views/ComponentsTestView.jsx'
import OnboardingComplete from '../views/onboarding/OnboardingComplete.jsx'
import OnboardingWizard from '../views/onboarding/OnboardingWizard.jsx'
import AdminOnboardingHubPage from '../views/admin/onboarding/AdminOnboardingHubPage.jsx'
import AdminOnboardingPage from '../views/admin/onboarding/AdminOnboardingPage.jsx'
import AdminSalonEditPage from '../views/admin/onboarding/AdminSalonEditPage.jsx'
import SubscriptionPlansView from '../views/admin/SubscriptionPlansView.jsx'
import AdminSubscriptionUpgradesView from '../views/admin/AdminSubscriptionUpgradesView.jsx'
import AdminSubscriptionHistoryView from '../views/admin/AdminSubscriptionHistoryView.jsx'
import AdminSubscriptionRenewalsView from '../views/admin/AdminSubscriptionRenewalsView.jsx'
import AdminBranchesView from '../views/admin/AdminBranchesView.jsx'
import AdminAffiliatesView from '../views/admin/AdminAffiliatesView.jsx'
import AffiliateApplyView from '../views/affiliate/AffiliateApplyView.jsx'
import AffiliatePendingView from '../views/affiliate/AffiliatePendingView.jsx'
import ActivationPendingView from '../views/auth/ActivationPendingView.jsx'
import SubscriptionExpiredView from '../views/auth/SubscriptionExpiredView.jsx'
import AffiliateDashboardView from '../views/affiliate/AffiliateDashboardView.jsx'
import AffiliateReferralsView from '../views/affiliate/AffiliateReferralsView.jsx'
import AffiliateCommissionsView from '../views/affiliate/AffiliateCommissionsView.jsx'
import AffiliateWithdrawalsView from '../views/affiliate/AffiliateWithdrawalsView.jsx'
import AffiliateReportsView from '../views/affiliate/AffiliateReportsView.jsx'
import SalonReferralProgramView from '../views/referrals/SalonReferralProgramView.jsx'
import { PLATFORM_PERMISSIONS } from '../lib/platformPermissions.js'
import { TENANT_PERMISSIONS } from '../lib/tenantPermissions.js'
import {
  ForbiddenView,
  GuestOnly,
  InitAuth,
  ProtectedPath,
  RequireAuth,
  RequireAffiliatePermission,
  RequirePlatformPermission,
  RequireSystemAdmin,
  RequireTenant,
  RequireTenantPermission,
  RootRedirect,
} from './guards.jsx'

const authLayoutPaths = new Set([
  '/login',
  '/register',
  '/affiliate/apply',
  '/forgot-password',
  '/reset-password',
  '/verify-2fa',
])

export function isAuthLayoutPath(pathname) {
  if (pathname.startsWith('/book/')) {
    return true
  }

  if (pathname === '/onboarding' || pathname.startsWith('/onboarding/')) {
    return true
  }

  return authLayoutPaths.has(pathname)
}

export function AppRoutes() {
  return (
    <>
      <InitAuth />
      <Routes>
        <Route path="/login" element={<GuestOnly><LoginView /></GuestOnly>} />
        <Route path="/register" element={<GuestOnly><RegisterView /></GuestOnly>} />
        <Route path="/affiliate/apply" element={<GuestOnly><AffiliateApplyView /></GuestOnly>} />
        <Route path="/forgot-password" element={<GuestOnly><ForgotPasswordView /></GuestOnly>} />
        <Route path="/reset-password" element={<GuestOnly><ResetPasswordView /></GuestOnly>} />
        <Route path="/verify-2fa" element={<GuestOnly><TwoFactorView /></GuestOnly>} />

        <Route path="/book/:token" element={<PublicBookingView />} />

          <Route element={<RequireAuth />}>
          <Route path="/onboarding" element={<OnboardingWizard />} />
          <Route path="/onboarding/complete" element={<OnboardingComplete />} />
          <Route path="/activation-pending" element={<ActivationPendingView />} />
          <Route path="/subscription-expired" element={<SubscriptionExpiredView />} />
          <Route path="/affiliate/pending" element={<AffiliatePendingView />} />

          <Route path="/dashboard" element={<DashboardView />} />

          <Route element={<RequirePlatformPermission permission={PLATFORM_PERMISSIONS.SUBSCRIPTIONS} />}>
            <Route path="/admin/subscription-plans" element={<SubscriptionPlansView />} />
            <Route path="/admin/subscription-renewals" element={<AdminSubscriptionRenewalsView />} />
          </Route>
          <Route element={<RequirePlatformPermission permission={PLATFORM_PERMISSIONS.UPGRADE_REQUESTS} />}>
            <Route path="/admin/subscription-upgrades" element={<AdminSubscriptionUpgradesView />} />
          </Route>
          <Route element={<RequireSystemAdmin />}>
            <Route path="/admin/subscription-history" element={<AdminSubscriptionHistoryView />} />
          </Route>

          <Route element={<RequirePlatformPermission permission={PLATFORM_PERMISSIONS.BRANCHES} />}>
            <Route path="/admin/branches" element={<AdminBranchesView />} />
          </Route>
          <Route element={<RequirePlatformPermission permission={PLATFORM_PERMISSIONS.AFFILIATES} />}>
            <Route path="/admin/affiliates" element={<AdminAffiliatesView />} />
          </Route>

          <Route element={<RequirePlatformPermission permission={PLATFORM_PERMISSIONS.REPORTS} />}>
            <Route path="/admin/reports" element={<PlatformReportsView />} />
          </Route>

          <Route element={<RequirePlatformPermission permission={PLATFORM_PERMISSIONS.ONBOARDING} />}>
            <Route path="/admin/onboarding" element={<AdminOnboardingHubPage />} />
          </Route>
          <Route element={<RequirePlatformPermission permission={PLATFORM_PERMISSIONS.ONBOARDING_CREATE} />}>
            <Route path="/admin/onboarding/new" element={<AdminOnboardingPage />} />
          </Route>
          <Route element={<RequirePlatformPermission permission={PLATFORM_PERMISSIONS.ONBOARDING_UPDATE} />}>
            <Route path="/admin/onboarding/:id/edit" element={<AdminSalonEditPage />} />
          </Route>

          <Route element={<RequireAffiliatePermission permission="affiliate.dashboard.view" />}>
            <Route path="/affiliate/dashboard" element={<AffiliateDashboardView />} />
          </Route>
          <Route element={<RequireAffiliatePermission permission="affiliate.referrals.view" />}>
            <Route path="/affiliate/referrals" element={<AffiliateReferralsView />} />
          </Route>
          <Route element={<RequireAffiliatePermission permission="affiliate.commissions.view" />}>
            <Route path="/affiliate/commissions" element={<AffiliateCommissionsView />} />
          </Route>
          <Route element={<RequireAffiliatePermission permission="affiliate.reports.view" />}>
            <Route path="/affiliate/reports" element={<AffiliateReportsView />} />
          </Route>
          <Route element={<RequireAffiliatePermission permission="affiliate.withdrawals.view" />}>
            <Route path="/affiliate/withdrawals" element={<AffiliateWithdrawalsView />} />
          </Route>

          <Route element={<RequireTenant />}>
            <Route element={<RequireTenantPermission permission={TENANT_PERMISSIONS.STAFF_VIEW} />}>
              <Route path="/staff" element={<StaffView />} />
            </Route>

            <Route element={<RequireTenantPermission permission={TENANT_PERMISSIONS.INVENTORY_VIEW} />}>
              <Route path="/inventory" element={<InventoryView />} />
            </Route>

            <Route element={<RequireTenantPermission permission={TENANT_PERMISSIONS.CATEGORIES_VIEW} />}>
              <Route path="/categories" element={<CategoriesView />} />
            </Route>
            <Route element={<RequireTenantPermission anyPermission={[TENANT_PERMISSIONS.SERVICES_VIEW, TENANT_PERMISSIONS.PRODUCTS_VIEW]} />}>
              <Route path="/catalog" element={<CatalogView />} />
            </Route>
            <Route element={<RequireTenantPermission permission={TENANT_PERMISSIONS.PRODUCTS_VIEW} />}>
              <Route path="/settings/products" element={<ProductsMasterView />} />
            </Route>
            <Route element={<RequireTenantPermission permission={TENANT_PERMISSIONS.SERVICES_VIEW} />}>
              <Route path="/settings/services" element={<ServicesMasterView />} />
            </Route>

            <Route element={<RequireTenantPermission permission={TENANT_PERMISSIONS.CUSTOMERS_VIEW} />}>
              <Route path="/customers" element={<CustomersView />} />
            </Route>

            <Route element={<RequireTenantPermission permission={TENANT_PERMISSIONS.SETTINGS_VIEW} />}>
              <Route path="/billing" element={<BillingView />} />
            </Route>

            <Route element={<RequireTenantPermission permission={TENANT_PERMISSIONS.REFERRALS_VIEW} />}>
              <Route path="/referrals" element={<SalonReferralProgramView />} />
            </Route>

            <Route element={<RequireTenantPermission permission={TENANT_PERMISSIONS.ANALYTICS_VIEW} />}>
              <Route path="/reports" element={<SalonReportsView />} />
              <Route path="/analytics" element={<Navigate to="/reports" replace />} />
            </Route>

            <Route element={<RequireTenantPermission permission={TENANT_PERMISSIONS.SETTINGS_VIEW} />}>
              <Route path="/settings" element={<SettingsView />} />
            </Route>

            <Route element={<RequireTenantPermission permission={TENANT_PERMISSIONS.BRANCHES_VIEW} />}>
              <Route path="/branches" element={<BranchesView />} />
            </Route>

            <Route element={<RequireTenantPermission permission={TENANT_PERMISSIONS.ROLES_VIEW} />}>
              <Route path="/roles" element={<RolesView />} />
            </Route>

            <Route element={<RequireTenantPermission permission={TENANT_PERMISSIONS.ASSIGN_PERMISSIONS_VIEW} />}>
              <Route path="/assign-permissions" element={<AssignPermissionsView />} />
            </Route>

            <Route element={<RequireTenantPermission permission={TENANT_PERMISSIONS.APPOINTMENTS_VIEW} />}>
              <Route path="/appointments" element={<AppointmentsView />} />
              <Route path="/pos" element={<PosView />} />
            </Route>

            <Route
              path="/queue"
              element={(
                <ProtectedPath
                  path="/queue"
                  element={<LiveQueueView />}
                />
              )}
            />

            <Route element={<RequireTenantPermission permission={TENANT_PERMISSIONS.BOOKING_LINKS_VIEW} />}>
              <Route path="/booking-links" element={<BookingLinksView />} />
            </Route>

            <Route element={<RequireTenantPermission permission={TENANT_PERMISSIONS.STAFF_EARNINGS_VIEW} />}>
              <Route path="/staff/earnings" element={<StaffEarningsView />} />
            </Route>
          </Route>

          <Route
            path="/ui-test"
            element={(
              <ProtectedPath
                path="/ui-test"
                element={<ComponentsTestView />}
              />
            )}
          />
        </Route>

        <Route path="/403" element={<ForbiddenView />} />
        <Route path="/" element={<RootRedirect />} />
        <Route path="*" element={<RootRedirect />} />
      </Routes>
    </>
  )
}
