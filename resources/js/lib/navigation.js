import {
  LayoutDashboard,
  CalendarDays,
  Users,
  PackageOpen,
  UsersRound,
  CreditCard,
  BarChart3,
  Settings,
  Tags,
  ShieldCheck,
  KeyRound,
  Layers,
  Building2,
  MapPinned,
  HandCoins,
  History,
  Link2,
  Wallet,
  Gift,
  Home,
  Calendar,
  MoreHorizontal,
  CalendarClock,
  Upload,
  ShoppingBag,
  ListOrdered,
  Sparkles,
  HeartHandshake,
  GitCompareArrows,
  Megaphone,
  Boxes,
  TicketPercent,
  ListPlus,
  ShieldAlert,
  Percent,
  Star,
  Clock,
} from 'lucide-react'
import { PLATFORM_PERMISSIONS } from './platformPermissions.js'
import { isPlatformPermissionCode } from './tenantPermissions.js'
import { authHasModule, moduleForPath } from './subscriptionModules.js'

/**
 * Navigation item permission can be:
 * - null: any authenticated user
 * - string: single permission code
 * - string[]: user needs any one of the codes
 *
 * tenantOnly: salon workspace only (hidden from platform/system admins)
 */
export const NAV_GROUPS = [
  {
    title: 'Overview',
    items: [
      { label: 'Dashboard', to: '/dashboard', icon: LayoutDashboard, permission: null },
      { label: 'Reports', to: '/reports', icon: BarChart3, permission: 'analytics.view', tenantOnly: true },
      { label: 'Insights', to: '/insights', icon: Sparkles, permission: 'analytics.insights.view', tenantOnly: true },
      { label: 'Benchmarks', to: '/benchmarks', icon: GitCompareArrows, permission: ['analytics.benchmark.view', 'analytics.view'], tenantOnly: true },
    ],
  },
  {
    title: 'Organization',
    items: [
      {
        label: 'Branch Management',
        scopedLabel: 'My Branch',
        to: '/branches',
        icon: MapPinned,
        permission: 'branches.view',
        tenantOnly: true,
      },
    ],
  },
  {
    title: 'People & Access',
    items: [
      { label: 'Staff', to: '/staff', icon: Users, permission: 'staff.view' },
      { label: 'Roles', to: '/roles', icon: ShieldCheck, permission: 'roles.view' },
      {
        label: 'Assign Permissions',
        to: '/assign-permissions',
        icon: KeyRound,
        permission: 'assign_permissions.view',
      },
      { label: 'Attendance', to: '/attendance', icon: Clock, permission: ['attendance.view', 'attendance.punch'], tenantOnly: true },
      { label: 'Payroll', to: '/payroll', icon: Wallet, permission: 'payroll.view', tenantOnly: true },
    ],
  },
  {
    title: 'Operations',
    items: [
      { label: 'Appointments', to: '/appointments', icon: CalendarDays, permission: 'appointments.view' },
      { label: 'Import appointments', to: '/appointment-imports', icon: Upload, appointmentImport: true },
      { label: 'Live queue', to: '/queue', icon: ListOrdered, permission: 'queue.view', tenantOnly: true },
      { label: 'Waitlist', to: '/waitlist', icon: ListPlus, permission: 'waitlist.view', tenantOnly: true },
      { label: 'Booking links', to: '/booking-links', icon: Link2, permission: 'booking_links.view', tenantOnly: true },
      { label: 'My earnings', to: '/staff/earnings', icon: Wallet, permission: 'staff.earnings.view', tenantOnly: true },
      { label: 'Commissions', to: '/commissions', icon: HandCoins, permission: 'commissions.view', tenantOnly: true },
      { label: 'POS', to: '/pos', icon: ShoppingBag, permission: 'appointments.view', tenantOnly: true },
      { label: 'Customers', to: '/customers', icon: UsersRound, permission: 'customers.view' },
      { label: 'Retention', to: '/retention', icon: HeartHandshake, permission: 'crm.retention.view', tenantOnly: true },
      { label: 'Packages', to: '/packages', icon: Boxes, permission: 'packages.view', tenantOnly: true },
      { label: 'Gift cards', to: '/gift-cards', icon: TicketPercent, permission: 'giftcards.view', tenantOnly: true },
      { label: 'Inventory', to: '/inventory', icon: PackageOpen, permission: 'inventory.view' },
      { label: 'Marketing', to: '/marketing', icon: Megaphone, permission: 'marketing.view', tenantOnly: true },
      { label: 'Reviews', to: '/reviews', icon: Star, permission: 'reviews.view', tenantOnly: true },
      { label: 'No-show policy', to: '/no-show-policy', icon: ShieldAlert, permission: 'payments.policy.manage', tenantOnly: true },
      { label: 'Pricing rules', to: '/pricing-rules', icon: Percent, permission: 'pricing_rules.view', tenantOnly: true },
    ],
  },
  {
    title: 'Master Catalog',
    items: [
      {
        label: 'Categories',
        to: '/categories',
        icon: Tags,
        permission: 'categories.view',
      },
      {
        label: 'Services & Products',
        to: '/catalog',
        icon: Layers,
        permission: ['services.view', 'products.view'],
      },
    ],
  },
  {
    title: 'Finance',
    items: [
      { label: 'Billing', to: '/billing', icon: CreditCard, permission: 'settings.view' },
      { label: 'Referral Program', to: '/referrals', icon: Gift, permission: 'referrals.view', tenantOnly: true },
    ],
  },
  {
    title: 'Administration',
    platformOnly: true,
    items: [
      {
        label: 'Salon Onboarding',
        to: '/admin/onboarding',
        icon: Building2,
        permission: PLATFORM_PERMISSIONS.ONBOARDING,
      },
      {
        label: 'Branches',
        to: '/admin/branches',
        icon: MapPinned,
        permission: PLATFORM_PERMISSIONS.BRANCHES,
      },
      {
        label: 'Affiliates',
        to: '/admin/affiliates',
        icon: Link2,
        permission: PLATFORM_PERMISSIONS.AFFILIATES,
        pendingCountKeys: ['affiliate_applications', 'affiliate_withdrawals'],
      },
      {
        label: 'Subscription Plans',
        to: '/admin/subscription-plans',
        icon: CreditCard,
        permission: PLATFORM_PERMISSIONS.SUBSCRIPTIONS,
      },
      {
        label: 'Subscription Renewals',
        to: '/admin/subscription-renewals',
        icon: CalendarClock,
        permission: PLATFORM_PERMISSIONS.SUBSCRIPTIONS,
      },
      {
        label: 'Upgrade Requests',
        to: '/admin/subscription-upgrades',
        icon: HandCoins,
        permission: PLATFORM_PERMISSIONS.UPGRADE_REQUESTS,
        pendingCountKeys: ['subscription_upgrades'],
      },
      {
        label: 'Subscription History',
        to: '/admin/subscription-history',
        icon: History,
        superAdminOnly: true,
      },
      {
        label: 'Reports',
        to: '/admin/reports',
        icon: BarChart3,
        permission: PLATFORM_PERMISSIONS.REPORTS,
      },
    ],
  },
]

export const AFFILIATE_NAV_GROUPS = [
  {
    title: 'Overview',
    items: [
      { label: 'Dashboard', to: '/affiliate/dashboard', icon: LayoutDashboard, permission: 'affiliate.dashboard.view' },
      { label: 'Referrals', to: '/affiliate/referrals', icon: Link2, permission: 'affiliate.referrals.view' },
      { label: 'Reports', to: '/affiliate/reports', icon: BarChart3, permission: 'affiliate.reports.view' },
    ],
  },
  {
    title: 'Earnings',
    items: [
      { label: 'Commissions', to: '/affiliate/commissions', icon: HandCoins, permission: 'affiliate.commissions.view' },
      { label: 'Withdrawals', to: '/affiliate/withdrawals', icon: Wallet, permission: 'affiliate.withdrawals.view' },
    ],
  },
]

export const BOTTOM_NAV_ITEMS = [
  { label: 'Home', to: '/dashboard', icon: Home, permission: null },
  { label: 'Schedule', to: '/appointments', icon: Calendar, permission: 'appointments.view' },
  { label: 'Clients', to: '/customers', icon: Users, permission: 'customers.view' },
  { label: 'Billing', to: '/billing', icon: CreditCard, permission: 'settings.view' },
  { label: 'More', to: '/settings', icon: MoreHorizontal, permission: 'settings.view' },
]

/** Minimal nav while free trial is expired (portal locked). */
export const LOCKED_TENANT_NAV_GROUP = {
  title: 'Upgrade',
  items: [
    {
      label: 'Billing & Plans',
      to: '/billing',
      icon: CreditCard,
      permission: 'settings.view',
      tenantOnly: true,
    },
  ],
}

/** Routes that are not listed in the sidebar but still permission-gated */
export const EXTRA_ROUTE_PERMISSIONS = {
  '/settings/products': 'products.view',
  '/settings/services': 'services.view',
  '/staff/earnings': 'staff.earnings.view',
  '/queue': 'queue.view',
  '/booking-links': 'booking_links.view',
  '/commissions': 'commissions.view',
  '/insights': 'analytics.insights.view',
  '/benchmarks': 'analytics.benchmark.view',
  '/retention': 'crm.retention.view',
  '/marketing': 'marketing.view',
  '/packages': 'packages.view',
  '/gift-cards': 'giftcards.view',
  '/payroll': 'payroll.view',
  '/reviews': 'reviews.view',
  '/attendance': 'attendance.view',
  '/waitlist': 'waitlist.view',
  '/no-show-policy': 'payments.policy.manage',
  '/pricing-rules': 'pricing_rules.view',
  '/admin/onboarding/new': PLATFORM_PERMISSIONS.ONBOARDING,
  '/admin/onboarding/:id/edit': PLATFORM_PERMISSIONS.ONBOARDING,
  '/admin/subscription-plans': PLATFORM_PERMISSIONS.SUBSCRIPTIONS,
  '/admin/subscription-renewals': PLATFORM_PERMISSIONS.SUBSCRIPTIONS,
  '/admin/subscription-upgrades': PLATFORM_PERMISSIONS.UPGRADE_REQUESTS,
  '/admin/subscription-history': null,
  '/admin/branches': PLATFORM_PERMISSIONS.BRANCHES,
  '/admin/affiliates': PLATFORM_PERMISSIONS.AFFILIATES,
  '/admin/reports': PLATFORM_PERMISSIONS.REPORTS,
}

export function normalizePermissionRequirement(permission) {
  if (!permission) return []
  return Array.isArray(permission) ? permission : [permission]
}

function isAffiliateWorkspaceUser(auth) {
  return auth?.workspace === 'affiliate' || auth?.role?.scope === 'affiliate'
}

function isPlatformWorkspaceUser(auth) {
  return Boolean(
    auth?.grantsAllPermissions
    || auth?.isSystemAdmin
    || auth?.role?.scope === 'platform',
  )
}

export function canAccessNavItem(auth, item) {
  if (item?.tenantOnly && isPlatformWorkspaceUser(auth)) {
    return false
  }

  if (item?.superAdminOnly) {
    return Boolean(auth?.grantsAllPermissions || auth?.isSystemAdmin)
  }

  if (item?.appointmentImport) {
    return Boolean(
      auth?.grantsAllPermissions
      || auth?.isSystemAdmin
      || auth?.isFranchiseOwner
      || auth?.role?.code === 'salon.franchise_owner',
    )
  }

  if (auth?.grantsAllPermissions) return true

  const isLocked = auth?.tenant?.subscription_access_mode === 'locked'
  const module = moduleForPath(item?.to)
  if (module && !(isLocked && item?.to === '/billing') && !authHasModule(auth, module)) return false

  const codes = normalizePermissionRequirement(item?.permission)
  if (codes.length === 0) return Boolean(auth?.isAuthenticated)

  return codes.some((code) => {
    if (isPlatformPermissionCode(code)) {
      return auth.canPlatform(code)
    }
    return auth.can(code)
  })
}

/** True when route belongs to salon tenant workspace (not platform admin). */
export function isTenantNavItem(item) {
  if (item?.platformOnly) return false
  const codes = normalizePermissionRequirement(item?.permission)
  if (codes.length === 0) return true
  return codes.every((code) => !isPlatformPermissionCode(code))
}

export function filterNavGroups(auth, groups = NAV_GROUPS) {
  return groups
    .filter((group) => {
      if (!group.platformOnly) return true
      return group.items.some((item) => canAccessNavItem(auth, item))
    })
    .map((group) => ({
      ...group,
      items: group.items.filter((item) => canAccessNavItem(auth, item)),
    }))
    .filter((group) => group.items.length > 0)
}

export function filterAffiliateNavGroups(auth, groups = AFFILIATE_NAV_GROUPS) {
  return groups
    .map((group) => ({
      ...group,
      items: group.items.filter((item) => canAccessNavItem(auth, item)),
    }))
    .filter((group) => group.items.length > 0)
}

export function filterTenantNavGroups(auth, groups = NAV_GROUPS) {
  if (auth?.tenant?.subscription_access_mode === 'locked') {
    const items = LOCKED_TENANT_NAV_GROUP.items.filter((item) => canAccessNavItem(auth, item))
    return items.length > 0 ? [{ ...LOCKED_TENANT_NAV_GROUP, items }] : []
  }

  return filterNavGroups(auth, groups).filter((group) => !group.platformOnly)
}

export function filterBottomNavItems(auth, items = BOTTOM_NAV_ITEMS) {
  if (auth?.tenant?.subscription_access_mode === 'locked') {
    const billing = BOTTOM_NAV_ITEMS.find((item) => item.to === '/billing')
    return billing && canAccessNavItem(auth, billing) ? [billing] : []
  }

  if (isAffiliateWorkspaceUser(auth)) {
    return getAccessibleNavItems(auth).slice(0, 5)
  }

  const visible = items.filter((item) => canAccessNavItem(auth, item))
  if (visible.length > 0) return visible

  const fallback = getAccessibleNavItems(auth)[0]
  return fallback ? [fallback] : []
}

export function getAccessibleNavItems(auth) {
  if (isAffiliateWorkspaceUser(auth)) {
    const status = auth?.affiliatePartner?.status
    if (status && status !== 'active') {
      return []
    }
  }

  if (auth?.tenant?.activation_pending) {
    return []
  }

  if (auth?.tenant?.subscription_access_mode === 'locked') {
    return LOCKED_TENANT_NAV_GROUP.items.filter((item) => canAccessNavItem(auth, item))
  }

  const groups = isAffiliateWorkspaceUser(auth) ? AFFILIATE_NAV_GROUPS : NAV_GROUPS
  return groups.flatMap((group) => group.items).filter((item) => canAccessNavItem(auth, item))
}

export function getDefaultAuthenticatedPath(auth) {
  if (auth?.shouldOnboard) return '/onboarding'
  if (isAffiliateWorkspaceUser(auth)) {
    const status = auth?.affiliatePartner?.status
    if (status && status !== 'active') {
      return '/affiliate/pending'
    }
    return '/affiliate/dashboard'
  }
  if (auth?.tenant?.activation_pending) {
    return '/activation-pending'
  }
  if (auth?.tenant?.subscription_access_mode === 'locked') {
    return '/subscription-expired'
  }
  if (
    auth?.workspace === 'platform'
    || auth?.grantsAllPermissions
    || auth?.isSystemAdmin
    || auth?.role?.scope === 'platform'
  ) {
    return '/dashboard'
  }

  return '/dashboard'
}

export function canAccessPath(auth, pathname) {
  if (!auth?.isAuthenticated) return false
  if (
    pathname === '/403'
    || pathname === '/dashboard'
    || pathname.startsWith('/onboarding')
    || pathname.startsWith('/affiliate/pending')
    || pathname.startsWith('/affiliate/apply')
    || pathname.startsWith('/activation-pending')
    || pathname.startsWith('/subscription-expired')
    || pathname.startsWith('/billing')
  ) return true

  if (auth?.tenant?.activation_pending && !pathname.startsWith('/activation-pending')) {
    return false
  }

  if (
    auth?.tenant?.subscription_access_mode === 'locked'
    && !auth?.tenant?.activation_pending
    && !pathname.startsWith('/subscription-expired')
    && !pathname.startsWith('/billing')
  ) {
    return false
  }
  const flatItems = [
    ...NAV_GROUPS.flatMap((group) => group.items),
    { to: '/settings', permission: 'settings.view' },
    { to: '/branches', permission: 'branches.view' },
  ]

  for (const item of flatItems) {
    if (pathname === item.to || pathname.startsWith(`${item.to}/`)) {
      return canAccessNavItem(auth, item)
    }
  }

  if (pathname.startsWith('/admin/onboarding')) {
    return canAccessNavItem(auth, {
      permission: PLATFORM_PERMISSIONS.ONBOARDING,
    })
  }

  if (pathname.startsWith('/admin/branches')) {
    return canAccessNavItem(auth, {
      permission: PLATFORM_PERMISSIONS.BRANCHES,
    })
  }

  if (pathname.startsWith('/admin/subscription-upgrades')) {
    return canAccessNavItem(auth, {
      permission: PLATFORM_PERMISSIONS.UPGRADE_REQUESTS,
    })
  }

  if (pathname.startsWith('/admin/subscription-history')) {
    return canAccessNavItem(auth, {
      superAdminOnly: true,
    })
  }

  if (pathname.startsWith('/admin/subscription-plans')) {
    return canAccessNavItem(auth, {
      permission: PLATFORM_PERMISSIONS.SUBSCRIPTIONS,
    })
  }

  if (pathname.startsWith('/admin/subscription-renewals')) {
    return canAccessNavItem(auth, {
      permission: PLATFORM_PERMISSIONS.SUBSCRIPTIONS,
    })
  }

  if (pathname.startsWith('/admin/affiliates')) {
    return canAccessNavItem(auth, {
      permission: PLATFORM_PERMISSIONS.AFFILIATES,
    })
  }

  if (pathname.startsWith('/admin/reports')) {
    return canAccessNavItem(auth, {
      permission: PLATFORM_PERMISSIONS.REPORTS,
    })
  }

  if (pathname.startsWith('/affiliate/dashboard')) {
    return canAccessNavItem(auth, {
      permission: 'affiliate.dashboard.view',
    })
  }

  if (pathname.startsWith('/affiliate/referrals')) {
    return canAccessNavItem(auth, {
      permission: 'affiliate.referrals.view',
    })
  }

  if (pathname.startsWith('/affiliate/commissions')) {
    return canAccessNavItem(auth, {
      permission: 'affiliate.commissions.view',
    })
  }

  if (pathname.startsWith('/affiliate/reports')) {
    return canAccessNavItem(auth, {
      permission: 'affiliate.reports.view',
    })
  }

  if (pathname.startsWith('/affiliate/withdrawals')) {
    return canAccessNavItem(auth, {
      permission: 'affiliate.withdrawals.view',
    })
  }

  if (pathname.startsWith('/settings/products') || pathname.startsWith('/settings/services')) {
    if (!isPlatformWorkspaceUser(auth)) {
      return false
    }
    return canAccessNavItem(auth, {
      permission: pathname.startsWith('/settings/products') ? 'products.view' : 'services.view',
    })
  }

  if (pathname === '/pos' || pathname.startsWith('/pos/')) {
    return canAccessNavItem(auth, {
      permission: 'appointments.view',
      tenantOnly: true,
    })
  }

  if (pathname === '/queue' || pathname.startsWith('/queue/')) {
    return canAccessNavItem(auth, {
      permission: 'queue.view',
      tenantOnly: true,
    })
  }

  if (pathname === '/staff/earnings' || pathname.startsWith('/staff/earnings/')) {
    return canAccessNavItem(auth, {
      permission: 'staff.earnings.view',
      tenantOnly: true,
    })
  }

  if (pathname === '/booking-links' || pathname.startsWith('/booking-links/')) {
    return canAccessNavItem(auth, {
      permission: 'booking_links.view',
      tenantOnly: true,
    })
  }

  if (pathname === '/ui-test') {
    return auth.isSystemAdmin
  }

  return false
}
