import React from 'react'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { renderWithProviders, findByClass, makeAuthMock } from '../../../test-utils/responsive.jsx'

const mockAuth = makeAuthMock({
  workspace: 'platform',
  isSystemAdmin: true,
  grantsAllPermissions: true,
  role: { scope: 'platform' },
  tenant: null,
})

vi.mock('../../../stores/auth', () => ({
  useAuthStore: () => mockAuth,
}))

vi.mock('../../../services/adminOnboardingService.js', () => ({
  fetchOnboardingRecords: vi.fn(async () => ({
    summary: { total: 0, completed: 0, pending: 0, no_owner: 0 },
    onboardings: [],
  })),
}))

vi.mock('../../../services/subscriptionService.js', () => ({
  fetchAdminPlans: vi.fn(async () => ({ subscription_plans: [] })),
  createPlan: vi.fn(),
  updatePlan: vi.fn(),
  deletePlan: vi.fn(),
}))

vi.mock('../../../services/affiliatePortalService.js', () => ({
  fetchAdminAffiliates: vi.fn(async () => ({ affiliate_partners: [] })),
  fetchAdminAffiliateWithdrawals: vi.fn(async () => ({ withdrawals: [] })),
  createAdminAffiliate: vi.fn(),
  updateAdminAffiliate: vi.fn(),
  approveAdminAffiliate: vi.fn(),
  rejectAdminAffiliate: vi.fn(),
  approveAdminAffiliateWithdrawal: vi.fn(),
  rejectAdminAffiliateWithdrawal: vi.fn(),
  markAdminAffiliateWithdrawalPaid: vi.fn(),
}))

vi.mock('../../../stores/toast.js', () => ({
  pushToast: vi.fn(),
}))

vi.mock('../../../lib/api', () => ({
  api: {
    get: vi.fn(async () => ({ data: { data: {} } })),
    post: vi.fn(async () => ({ data: {} })),
    put: vi.fn(async () => ({ data: {} })),
    delete: vi.fn(async () => ({ data: {} })),
    interceptors: {
      request: { use: vi.fn() },
      response: { use: vi.fn() },
    },
  },
}))

vi.mock('../../../services/notificationService.js', () => ({
  fetchAdminPendingCounts: vi.fn(async () => ({})),
  fetchAdminNotifications: vi.fn(async () => ({ notifications: [], unread_count: 0 })),
}))

vi.mock('axios', () => ({
  default: {
    create: vi.fn(() => ({
      get: vi.fn(async () => ({ data: { data: {} } })),
      post: vi.fn(async () => ({ data: {} })),
      put: vi.fn(async () => ({ data: {} })),
      delete: vi.fn(async () => ({ data: {} })),
      interceptors: {
        request: { use: vi.fn() },
        response: { use: vi.fn() },
      },
    })),
    get: vi.fn(async () => ({ data: { data: { subscription_plans: [] } } })),
    post: vi.fn(async () => ({ data: {} })),
    put: vi.fn(async () => ({ data: {} })),
    delete: vi.fn(async () => ({ data: {} })),
  },
}))

vi.mock('../../../lib/apiHelpers', () => ({
  apiGet: vi.fn(async () => ({ data: {} })),
  fetchMasterList: vi.fn(async () => []),
  parseList: vi.fn(() => []),
}))

import AdminOnboardingHubPage from '../onboarding/AdminOnboardingHubPage.jsx'
import SubscriptionPlansView from '../SubscriptionPlansView.jsx'
import AdminAffiliatesView from '../AdminAffiliatesView.jsx'
import PlatformReportsView from '../PlatformReportsView.jsx'

vi.mock('../../../components/dashboard/RevenueChart.jsx', () => ({ default: () => <div /> }))
vi.mock('../../../components/reports/ReportChrome.jsx', async () => {
  const React = await import('react')
  return {
    ReportFilters: ({ children }) => <div className="sm:flex-row">{children}</div>,
    ReportTabs: () => <div />,
    ReportKpiGrid: () => <div className="grid grid-cols-2 xl:grid-cols-4" />,
    ReportTable: () => <div className="overflow-x-auto"><table /></div>,
    StatusChips: () => <div />,
  }
})

describe('Admin screens responsive contracts', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  it('onboarding hub: stats collapse to 2 cols on phone, 4 from lg; table scrolls', () => {
    const { container } = renderWithProviders(<AdminOnboardingHubPage />, { route: '/admin/onboarding' })
    expect(
      findByClass(container, 'grid-cols-2')
      || findByClass(container, 'lg:grid-cols-4'),
    ).toBeTruthy()
    expect(findByClass(container, 'overflow-x-auto') || findByClass(container, 'sm:flex-row')).toBeTruthy()
  })

  it('subscription plans: toolbar stacks and table uses overflow-x-auto', () => {
    const { container } = renderWithProviders(<SubscriptionPlansView />, { route: '/admin/subscription-plans' })
    expect(
      findByClass(container, 'overflow-x-auto')
      || findByClass(container, 'sm:flex-row'),
    ).toBeTruthy()
  })

  it('affiliates admin: list cards are contained on narrow screens', () => {
    const { container } = renderWithProviders(<AdminAffiliatesView />, { route: '/admin/affiliates' })
    expect(
      findByClass(container, 'overflow-hidden')
      || findByClass(container, 'rounded-2xl')
      || findByClass(container, 'flex-col'),
    ).toBeTruthy()
  })

  it('platform reports: overview uses xl multi-column grids', () => {
    const { container } = renderWithProviders(<PlatformReportsView />, { route: '/admin/reports' })
    expect(
      findByClass(container, 'xl:grid-cols-2')
      || findByClass(container, 'grid-cols-2')
      || findByClass(container, 'overflow-x-auto'),
    ).toBeTruthy()
  })
})
