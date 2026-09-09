import { describe, expect, it } from 'vitest'
import {
  NAV_GROUPS,
  canAccessNavItem,
  filterBottomNavItems,
  filterTenantNavGroups,
  getAccessibleNavItems,
  getDefaultAuthenticatedPath,
} from '../navigation.js'

const ALL_MODULES = [
  'appointments',
  'customers',
  'staff',
  'billing',
  'analytics',
  'inventory',
  'catalog',
  'roles',
  'settings',
]

function makeAuth(permissions = [], overrides = {}) {
  const subscriptionModules = overrides.subscriptionModules ?? ALL_MODULES

  return {
    isAuthenticated: true,
    isSystemAdmin: false,
    shouldOnboard: false,
    permissions,
    subscriptionModules,
    can(code) {
      if (!code) return true
      if (this.isSystemAdmin) return true
      return this.permissions.includes(code)
    },
    canAny(codes) {
      if (!codes?.length) return true
      if (this.isSystemAdmin) return true
      return codes.some((code) => this.permissions.includes(code))
    },
    canPlatform(code) {
      return this.can(code)
    },
    canModule(module) {
      if (this.isSystemAdmin) return true
      return subscriptionModules.includes(module)
    },
    ...overrides,
    subscriptionModules,
  }
}

describe('navigation permissions', () => {
  it('groups branch under Organization and staff/roles under People & Access', () => {
    expect(NAV_GROUPS.map((group) => group.title)).toEqual([
      'Overview',
      'Organization',
      'People & Access',
      'Operations',
      'Master Catalog',
      'Finance',
      'Administration',
    ])

    const byTitle = Object.fromEntries(NAV_GROUPS.map((group) => [group.title, group.items.map((item) => item.label)]))
    expect(byTitle.Organization).toEqual(['Branch Management'])
    expect(byTitle['People & Access']).toEqual(['Staff', 'Roles', 'Assign Permissions'])
    expect(byTitle.Operations).toEqual([
      'Appointments',
      'Live queue',
      'Booking links',
      'My earnings',
      'POS',
      'Customers',
      'Inventory',
    ])
    expect(NAV_GROUPS.some((group) => group.title === 'System')).toBe(false)
  })

  it('keeps Organization and People & Access visible for tenant users with access', () => {
    const auth = makeAuth([
      'branches.view',
      'staff.view',
      'roles.view',
      'assign_permissions.view',
    ])
    const titles = filterTenantNavGroups(auth).map((group) => group.title)
    expect(titles).toContain('Organization')
    expect(titles).toContain('People & Access')
    expect(titles).not.toContain('System')
    expect(titles).not.toContain('Administration')
  })

  it('returns dashboard as default home for tenant users', () => {
    const auth = makeAuth(['appointments.view', 'customers.view', 'services.view'])
    expect(getDefaultAuthenticatedPath(auth)).toBe('/dashboard')
  })

  it('returns platform dashboard for system admins', () => {
    const auth = makeAuth([], {
      isSystemAdmin: true,
      grantsAllPermissions: true,
      workspace: 'platform',
      role: { scope: 'platform' },
    })
    expect(getDefaultAuthenticatedPath(auth)).toBe('/dashboard')
  })

  it('filters sidebar items for branch manager', () => {
    const auth = makeAuth([
      'appointments.view',
      'customers.view',
      'staff.view',
      'services.view',
      'inventory.view',
      'branches.view',
    ])

    const labels = getAccessibleNavItems(auth).map((item) => item.label)
    expect(labels).toContain('Dashboard')
    expect(labels).toContain('Appointments')
    expect(labels).toContain('Staff')
    expect(labels).toContain('Branch Management')
    expect(labels).not.toContain('Billing')
  })

  it('hides tenant branch management for platform admins', () => {
    const auth = makeAuth(['platform.branches.view', 'branches.view'], {
      isSystemAdmin: true,
      grantsAllPermissions: true,
      role: { scope: 'platform' },
    })
    const labels = getAccessibleNavItems(auth).map((item) => item.label)
    expect(labels).toContain('Branches')
    expect(labels).not.toContain('Branch Management')
  })

  it('shows branch management when branches.view is granted', () => {
    const auth = makeAuth(['branches.view', 'settings.view'])
    const labels = getAccessibleNavItems(auth).map((item) => item.label)
    expect(labels).toContain('Branch Management')
  })

  it('hides branch management without branches.view', () => {
    const auth = makeAuth(['settings.view', 'roles.view'])
    const labels = getAccessibleNavItems(auth).map((item) => item.label)
    expect(labels).not.toContain('Branch Management')
    expect(labels).toContain('Roles')
  })

  it('shows platform onboarding only with platform permission', () => {
    const auth = makeAuth(['platform.salon_onboarding.view'])
    const item = { permission: 'platform.salon_onboarding.view' }
    expect(canAccessNavItem(auth, item)).toBe(true)
  })

  it('filters bottom nav for staff', () => {
    const auth = makeAuth(['appointments.view', 'customers.view', 'services.view'])
    const items = filterBottomNavItems(auth)
    expect(items.map((item) => item.label)).toEqual(['Home', 'Schedule', 'Clients'])
  })

  it('shows reports when analytics module and permission exist', () => {
    const auth = makeAuth(['analytics.view', 'appointments.view'], {
      subscriptionModules: ['appointments', 'analytics'],
    })
    const labels = getAccessibleNavItems(auth).map((item) => item.label)
    expect(labels).toContain('Reports')
  })

  it('hides billing and reports when subscription modules are missing', () => {
    const auth = makeAuth(
      ['settings.view', 'settings.update', 'analytics.view', 'appointments.view'],
      { subscriptionModules: ['appointments', 'customers'] },
    )

    const labels = getAccessibleNavItems(auth).map((item) => item.label)
    expect(labels).toContain('Appointments')
    expect(labels).not.toContain('Billing')
    expect(labels).not.toContain('Reports')
  })

  it('shows billing when settings permission and billing/settings module exist', () => {
    const auth = makeAuth(['settings.view', 'settings.update'], {
      subscriptionModules: ['settings', 'billing'],
    })
    const labels = getAccessibleNavItems(auth).map((item) => item.label)
    expect(labels).toContain('Billing')
  })

  it('routes affiliates to affiliate dashboard by default', () => {
    const auth = makeAuth(['affiliate.dashboard.view'], {
      workspace: 'affiliate',
      isAffiliatePartner: true,
      role: { scope: 'affiliate' },
      subscriptionModules: [],
    })
    expect(getDefaultAuthenticatedPath(auth)).toBe('/affiliate/dashboard')
  })
})
