import { getNavModuleMap, getSubscriptionModuleCatalog } from './moduleCatalog.js'
import { formatPlanPrice as formatPlanPriceFromRegional } from './tenantFormatting.js'

/** @deprecated Use getNavModuleMap() — kept for tests and static imports. */
export const NAV_MODULE_MAP = getNavModuleMap()

/** @deprecated Use getSubscriptionModuleCatalog() — kept for tests and static imports. */
export const SUBSCRIPTION_MODULES = getSubscriptionModuleCatalog()

export { getNavModuleMap, getSubscriptionModuleCatalog }

export function moduleForPath(pathname) {
  if (!pathname) return null
  const entries = Object.entries(getNavModuleMap())
  for (const [prefix, module] of entries) {
    if (pathname === prefix || pathname.startsWith(`${prefix}/`)) {
      return module
    }
  }
  return null
}

export function authHasModule(auth, module) {
  if (!module) return true
  if (auth?.grantsAllPermissions || auth?.isSystemAdmin) return true
  const modules = auth?.subscriptionModules ?? auth?.tenant?.modules ?? []
  return modules.includes(module)
}

export function isSubscriptionReadOnly(auth) {
  const mode = auth?.tenant?.subscription_access_mode
  if (mode === 'read_only') return true
  if (mode === 'locked') return false
  // Fallback when API flag is present but access_mode was missing from a stale session.
  if (auth?.tenant?.subscription_expired) {
    const slug = String(auth?.tenant?.plan?.slug || '').toLowerCase()
    return slug !== 'free' && slug !== 'free-trial'
  }
  return false
}

export function isSubscriptionLocked(auth) {
  const mode = auth?.tenant?.subscription_access_mode
  if (mode === 'locked') return true
  if (mode === 'read_only') return false
  if (auth?.tenant?.subscription_expired) {
    const slug = String(auth?.tenant?.plan?.slug || '').toLowerCase()
    return slug === 'free' || slug === 'free-trial'
  }
  return false
}

/** True when tenant user may perform a create/update/delete action. */
export function canMutate(auth, permission) {
  if (!auth) return false
  if (auth.grantsAllPermissions || auth.isSystemAdmin) return true
  if (isSubscriptionLocked(auth)) {
    return false
  }
  if (isSubscriptionReadOnly(auth)) {
    if (!permission) return false
    if (isMutationPermission(permission)) return false
    return Boolean(auth.can?.(permission))
  }
  return Boolean(auth.can?.(permission))
}

/** Permissions blocked while subscription is in view-only (expired paid) mode. */
export function isMutationPermission(code) {
  if (!code || typeof code !== 'string') return false
  if (code.endsWith('.view')) return false
  return /\.(create|update|delete|manage)$/.test(code)
    || code === 'assign_permissions.update'
}

/** Raw permission lookup — ignores subscription read-only mutation blocks. */
export function hasPermissionCode(auth, code) {
  if (!code) return true
  if (auth?.grantsAllPermissions || auth?.isSystemAdmin) return true
  return (auth?.permissions ?? []).includes(code)
}

/**
 * Salon owners/managers may edit Business Profile + Salon Configuration even when
 * other mutations are blocked (e.g. expired paid plan read-only mode).
 */
export function canConfigureSalonSettings(auth) {
  if (!auth) return false
  if (auth.grantsAllPermissions || auth.isSystemAdmin) return false
  if (auth.role?.scope === 'platform') return false
  if (isSubscriptionLocked(auth)) return false
  if (!authHasModule(auth, 'settings')) return false

  const canViewSettings = hasPermissionCode(auth, 'settings.view')
    || auth.isFranchiseOwner
    || auth.isFranchiseManager

  if (!canViewSettings) return false

  if (auth.isFranchiseOwner || auth.isFranchiseManager) {
    return hasPermissionCode(auth, 'settings.update') || hasPermissionCode(auth, 'settings.view')
  }

  return hasPermissionCode(auth, 'settings.update') && !isSubscriptionReadOnly(auth)
}

/** Billing renewal stays available in read-only mode (matches API renewal exempt routes). */
export function canRenewSubscription(auth) {
  if (!auth) return false
  if (auth.grantsAllPermissions || auth.isSystemAdmin) return true
  return Boolean(auth.can?.('settings.view'))
}

/** True when the salon may request another billing period for its current paid plan. */
export function canRenewCurrentPlan(auth, context = {}) {
  if (!canRenewSubscription(auth)) return false

  const plan = context.plan ?? auth.tenant?.plan
  if (!plan) return false

  const slug = String(plan.slug || plan.name || '').toLowerCase()
  if (slug === 'free' || slug === 'free-trial') return false
  if (Number(plan.price || 0) <= 0 && plan.is_free !== false) return false

  const accessMode = context.accessMode ?? auth.tenant?.subscription_access_mode
  const lifecycle = context.lifecycle ?? auth.tenant?.subscription_summary?.lifecycle
  const status = context.status ?? auth.tenant?.subscription?.status

  if (accessMode === 'read_only') return true
  if (lifecycle === 'expired' || status === 'expired') return true

  return false
}

/** Subtitle hint for tenant pages when actions are hidden. */
export function subscriptionPageSubtitle(auth, activeSubtitle, readOnlySubtitle = 'View-only — renew your subscription to make changes.') {
  return isSubscriptionReadOnly(auth) ? readOnlySubtitle : activeSubtitle
}

export function formatLimit(value) {
  return value == null ? 'Unlimited' : String(value)
}

export function formatPlanPrice(plan, auth = null) {
  return formatPlanPriceFromRegional(plan, auth)
}

/** Common free-trial durations used across plan admin + onboarding. */
export const TRIAL_DURATION_OPTIONS = [
  { value: 0, label: 'No trial' },
  { value: 15, label: '15 days' },
  { value: 30, label: '1 month (30 days)' },
  { value: 60, label: '2 months (60 days)' },
]

export function formatTrialDays(days) {
  const value = Number(days || 0)
  if (value <= 0) return 'No trial'
  if (value === 15) return '15 days'
  if (value === 30) return '1 month'
  if (value === 60) return '2 months'
  return `${value} days`
}
