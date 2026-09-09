import React, { useEffect } from 'react'
import { Navigate, Outlet, useLocation } from 'react-router-dom'
import { useAuthStore } from '../stores/auth'
import PageSpinner from '../components/ui/PageSpinner.jsx'
import {
  canAccessPath,
  getDefaultAuthenticatedPath,
  normalizePermissionRequirement,
} from '../lib/navigation.js'
import { isPlatformPermissionCode } from '../lib/tenantPermissions.js'

export function InitAuth() {
  const auth = useAuthStore()

  useEffect(() => {
    if (!auth.initialized) {
      void auth.initialize()
    }
  }, [auth.initialized, auth.initialize])

  return null
}

export function GuestOnly({ children }) {
  const auth = useAuthStore()

  if (!auth.initialized) return <PageSpinner fullScreen label="Loading your workspace..." />
  if (auth.isAuthenticated) {
    const destination = auth.shouldOnboard ? '/onboarding' : getDefaultAuthenticatedPath(auth)
    return <Navigate to={destination} replace />
  }

  return children
}

export function hasTenantContext(auth) {
  if (auth?.grantsAllPermissions) return true
  return Boolean(auth?.tenant?.id || auth?.user?.saloon_id)
}

function userHasPermission(auth, permission, anyPermission) {
  if (auth.grantsAllPermissions) return true

  if (permission) {
    const codes = normalizePermissionRequirement(permission)
    if (codes.some(isPlatformPermissionCode)) {
      return codes.some((code) => auth.canPlatform(code))
    }
    return auth.canAny(codes)
  }

  if (anyPermission?.length) {
    return auth.canAny(anyPermission)
  }

  return true
}

export function RequireAuth({ permission, anyPermission, redirectOnDenied = false }) {
  const auth = useAuthStore()
  const location = useLocation()

  if (!auth.initialized) return <PageSpinner fullScreen label="Loading your workspace..." />

  if (!auth.isAuthenticated) {
    const redirect = encodeURIComponent(`${location.pathname}${location.search}`)
    return <Navigate to={`/login?redirect=${redirect}`} replace />
  }

  if (auth.shouldOnboard && !location.pathname.startsWith('/onboarding') && !location.pathname.startsWith('/admin/onboarding') && auth.workspace !== 'affiliate') {
    return <Navigate to="/onboarding" replace />
  }

  if (
    auth.tenant?.activation_pending
    && !location.pathname.startsWith('/activation-pending')
    && !location.pathname.startsWith('/onboarding')
    && auth.workspace !== 'affiliate'
    && !auth.grantsAllPermissions
  ) {
    return <Navigate to="/activation-pending" replace />
  }

  if (
    auth.tenant?.subscription_access_mode === 'locked'
    && !auth.tenant?.activation_pending
    && !location.pathname.startsWith('/subscription-expired')
    && !location.pathname.startsWith('/billing')
    && !location.pathname.startsWith('/onboarding')
    && auth.workspace !== 'affiliate'
    && !auth.grantsAllPermissions
  ) {
    return <Navigate to="/subscription-expired" replace />
  }

  if (!userHasPermission(auth, permission, anyPermission)) {
    if (redirectOnDenied) {
      return <Navigate to={getDefaultAuthenticatedPath(auth)} replace />
    }
    return <Navigate to="/403" replace />
  }

  return <Outlet />
}

/** Ensures the user belongs to a salon tenant (platform admins are redirected). */
export function RequireTenant() {
  const auth = useAuthStore()
  const location = useLocation()

  if (!auth.initialized) return <PageSpinner fullScreen label="Loading your workspace..." />

  if (!auth.isAuthenticated) {
    const redirect = encodeURIComponent(`${location.pathname}${location.search}`)
    return <Navigate to={`/login?redirect=${redirect}`} replace />
  }

  if (auth.shouldOnboard && !location.pathname.startsWith('/onboarding')) {
    return <Navigate to="/onboarding" replace />
  }

  if (auth.grantsAllPermissions) {
    return <Outlet />
  }

  if (auth.tenant?.activation_pending && !location.pathname.startsWith('/activation-pending')) {
    return <Navigate to="/activation-pending" replace />
  }

  if (
    auth.tenant?.subscription_access_mode === 'locked'
    && !auth.tenant?.activation_pending
    && !location.pathname.startsWith('/subscription-expired')
    && !location.pathname.startsWith('/billing')
  ) {
    return <Navigate to="/subscription-expired" replace />
  }

  if (!hasTenantContext(auth)) {
    return <Navigate to="/403" replace />
  }

  return <Outlet />
}

/** Tenant workspace route guard — combines tenant context + tenant permission checks. */
export function RequireTenantPermission({ permission, anyPermission, redirectOnDenied = false }) {
  const auth = useAuthStore()

  if (!auth.initialized) return <PageSpinner fullScreen label="Loading your workspace..." />

  if (!userHasPermission(auth, permission, anyPermission)) {
    if (redirectOnDenied) {
      return <Navigate to={getDefaultAuthenticatedPath(auth)} replace />
    }
    return <Navigate to="/403" replace />
  }

  return <Outlet />
}

export function RequirePlatformPermission({ permission }) {
  const auth = useAuthStore()

  if (!auth.initialized) return <PageSpinner fullScreen label="Loading your workspace..." />

  if (!auth.canPlatform(permission)) {
    return <Navigate to="/403" replace />
  }

  return <Outlet />
}

export function RequireSystemAdmin() {
  const auth = useAuthStore()

  if (!auth.initialized) return <PageSpinner fullScreen label="Loading your workspace..." />

  if (!auth.grantsAllPermissions && !auth.isSystemAdmin) {
    return <Navigate to="/403" replace />
  }

  return <Outlet />
}

export function RequireAffiliatePermission({ permission }) {
  const auth = useAuthStore()
  const location = useLocation()

  if (!auth.initialized) return <PageSpinner fullScreen label="Loading your workspace..." />

  if (!auth.isAuthenticated) {
    return <Navigate to="/login" replace />
  }

  const isAffiliate = auth.workspace === 'affiliate'
    || auth.role?.scope === 'affiliate'
    || Boolean(auth.affiliatePartner)

  if (!isAffiliate && !auth.grantsAllPermissions) {
    return <Navigate to={getDefaultAuthenticatedPath(auth)} replace />
  }

  const status = auth.affiliatePartner?.status
  if (status && status !== 'active' && !location.pathname.startsWith('/affiliate/pending')) {
    return <Navigate to="/affiliate/pending" replace />
  }

  if (!auth.canAffiliate(permission)) {
    return <Navigate to="/403" replace />
  }

  return <Outlet />
}

export function RootRedirect() {
  const auth = useAuthStore()

  if (!auth.initialized) return <PageSpinner fullScreen label="Loading your workspace..." />

  if (!auth.isAuthenticated) {
    return <Navigate to="/login" replace />
  }

  return <Navigate to={getDefaultAuthenticatedPath(auth)} replace />
}

export function ProtectedPath({ path, element }) {
  const auth = useAuthStore()
  const location = useLocation()

  if (!auth.initialized) return <PageSpinner fullScreen label="Loading your workspace..." />

  if (!auth.isAuthenticated) {
    const redirect = encodeURIComponent(`${location.pathname}${location.search}`)
    return <Navigate to={`/login?redirect=${redirect}`} replace />
  }

  if (!canAccessPath(auth, path)) {
    return <Navigate to={getDefaultAuthenticatedPath(auth)} replace />
  }

  return element
}

export function ForbiddenView() {
  const auth = useAuthStore()

  return (
    <div className="p-8">
      <h1 className="mb-2 text-2xl font-semibold">403</h1>
      <p className="mb-4">You do not have permission to access this page.</p>
      {auth.isAuthenticated && (
        <a href={getDefaultAuthenticatedPath(auth)} className="text-sm font-medium text-brand-600 hover:text-brand-700">
          Go to your workspace
        </a>
      )}
    </div>
  )
}
