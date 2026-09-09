import React from 'react'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { renderWithProviders, findByClass, makeAuthMock } from '../../../test-utils/responsive.jsx'

const mockAuth = makeAuthMock()

vi.mock('../../../stores/auth', () => ({
  useAuthStore: () => mockAuth,
}))

vi.mock('../../../lib/apiHelpers', () => ({
  apiGet: vi.fn(async () => ({ data: {} })),
  fetchMasterList: vi.fn(async () => []),
  parseList: vi.fn(() => []),
}))

vi.mock('../../../services/branchService.js', () => ({
  fetchBranches: vi.fn(async () => ({ branches: [] })),
}))

vi.mock('../../../components/dashboard/RevenueChart.jsx', () => ({ default: () => <div>chart</div> }))
vi.mock('../../../components/dashboard/AppointmentTrendChart.jsx', () => ({ default: () => <div>chart</div> }))
vi.mock('../../../components/dashboard/BranchPerformance.jsx', () => ({ default: () => <div>chart</div> }))
vi.mock('../../../components/dashboard/AppointmentsList.jsx', () => ({ default: () => <div>list</div> }))
vi.mock('../../../components/dashboard/StaffPerformance.jsx', () => ({ default: () => <div>chart</div> }))
vi.mock('../../../components/dashboard/CustomerInsights.jsx', () => ({ default: () => <div>chart</div> }))
vi.mock('../../../components/dashboard/TopServices.jsx', () => ({ default: () => <div>chart</div> }))
vi.mock('../../../components/dashboard/QuickActions.jsx', () => ({ default: () => <div>actions</div> }))
vi.mock('../../../components/dashboard/OnboardingStatusChart.jsx', () => ({ default: () => <div>chart</div> }))
vi.mock('../../../components/dashboard/RecentActivity.jsx', () => ({ default: () => <div>activity</div> }))
vi.mock('../../../components/dashboard/LatestOrganizations.jsx', () => ({ default: () => <div>orgs</div> }))
vi.mock('../../../components/dashboard/SubscriptionOverview.jsx', () => ({ default: () => <div>subs</div> }))
vi.mock('../../../components/dashboard/NotificationPanel.jsx', () => ({ default: () => <div>notes</div> }))
vi.mock('../../../components/dashboard/StatsGrid.jsx', () => ({
  default: () => <div className="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4">stats</div>,
}))
vi.mock('../../../components/dashboard/HeroBanner.jsx', () => ({
  default: ({ title }) => <div>{title || 'banner'}</div>,
}))

vi.mock('../../../services/notificationService.js', () => ({
  fetchAdminPendingCounts: vi.fn(async () => ({})),
  fetchAdminNotifications: vi.fn(async () => ({ notifications: [], unread_count: 0 })),
  markAdminNotificationRead: vi.fn(),
  markAllAdminNotificationsRead: vi.fn(),
}))

vi.mock('../../../services/adminOnboardingService.js', () => ({
  fetchOnboardingRecords: vi.fn(async () => ({
    summary: { total: 0, completed: 0, pending: 0, no_owner: 0 },
    onboardings: [],
  })),
}))

import SalonOwnerDashboardView from '../SalonOwnerDashboardView.jsx'
import BranchManagerDashboardView from '../BranchManagerDashboardView.jsx'
import StaffDashboardView from '../StaffDashboardView.jsx'
import PlatformDashboardView from '../PlatformDashboardView.jsx'

describe('Dashboard screens responsive contracts', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    Object.assign(mockAuth, makeAuthMock())
  })

  it('owner dashboard uses stacked-to-xl multi-column layout', async () => {
    const { container } = renderWithProviders(<SalonOwnerDashboardView />)
    expect(findByClass(container, 'space-y-6')).toBeTruthy()
    expect(
      findByClass(container, 'xl:grid-cols-2')
      || findByClass(container, 'xl:grid-cols-5')
      || findByClass(container, 'lg:grid-cols-2'),
    ).toBeTruthy()
  })

  it('branch manager dashboard uses responsive grid contracts', () => {
    Object.assign(mockAuth, makeAuthMock({
      isBranchManager: true,
      isBranchScoped: true,
      user: { name: 'Manager', branch_name: 'Andheri', firstname: 'Manager' },
      role: { scope: 'branch', code: 'salon.branch_manager' },
    }))
    const { container } = renderWithProviders(<BranchManagerDashboardView />)
    expect(findByClass(container, 'space-y-6')).toBeTruthy()
    expect(
      findByClass(container, 'xl:grid-cols-3')
      || findByClass(container, 'xl:grid-cols-5')
      || findByClass(container, 'lg:grid-cols-2'),
    ).toBeTruthy()
  })

  it('staff dashboard uses stacked mobile layout with xl multi-column sections', () => {
    Object.assign(mockAuth, makeAuthMock({
      isStaffMember: true,
      isBranchScoped: true,
      isFranchiseOwner: false,
      user: { name: 'Stylist', firstname: 'Stylist' },
      role: { scope: 'branch', code: 'salon.staff', name: 'Staff' },
    }))
    const { container } = renderWithProviders(<StaffDashboardView />)
    expect(findByClass(container, 'space-y-6')).toBeTruthy()
    expect(
      findByClass(container, 'xl:grid-cols-2')
      || findByClass(container, 'lg:grid-cols-2')
      || findByClass(container, 'sm:p-6'),
    ).toBeTruthy()
  })

  it('platform dashboard uses responsive queue/overview grids', () => {
    Object.assign(mockAuth, makeAuthMock({
      workspace: 'platform',
      isSystemAdmin: true,
      grantsAllPermissions: true,
      role: { scope: 'platform' },
      tenant: null,
    }))
    const { container } = renderWithProviders(<PlatformDashboardView />)
    expect(findByClass(container, 'space-y-6')).toBeTruthy()
    expect(
      findByClass(container, 'xl:grid-cols-3')
      || findByClass(container, 'lg:grid-cols-2'),
    ).toBeTruthy()
  })
})
