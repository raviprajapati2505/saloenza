import React from 'react'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { waitFor } from '@testing-library/react'
import { renderWithProviders, findByClass, makeAuthMock } from '../../test-utils/responsive.jsx'

const mockAuth = makeAuthMock()

vi.mock('../../stores/auth', () => ({
  useAuthStore: () => mockAuth,
}))

vi.mock('../../lib/apiHelpers', () => ({
  apiGet: vi.fn(async () => ({ data: { customers: [], staff: [], catalog: [] } })),
  fetchMasterList: vi.fn(async () => []),
  parseList: vi.fn(() => []),
}))

vi.mock('../../services/branchService.js', () => ({
  fetchBranches: vi.fn(async () => ({ branches: [] })),
}))

vi.mock('../../services/subscriptionService.js', () => ({
  fetchBillingConfig: vi.fn(async () => ({ driver: 'manual', allow_instant_upgrade: false })),
  fetchTenantSubscription: vi.fn(async () => ({
    plan: { name: 'Pro', slug: 'pro', modules: ['billing'] },
    subscription: { status: 'active' },
    limits: { max_branches: 3, max_staff: 20, branches_used: 1, staff_used: 2 },
    modules: ['billing', 'settings', 'appointments'],
  })),
  fetchPublicPlans: vi.fn(async () => ([
    { id: 1, name: 'Free', slug: 'free', price: 0, modules: [] },
    { id: 2, name: 'Pro', slug: 'pro', price: 499, modules: ['billing'] },
  ])),
  checkoutSubscription: vi.fn(),
  confirmSubscriptionCheckout: vi.fn(),
  upgradeSubscription: vi.fn(),
  trialDaysRemaining: () => null,
  usagePercent: () => 0,
  isUpgradePlan: () => true,
  processCheckoutResult: vi.fn(),
  subscriptionPeriodHeadline: vi.fn(() => ({ headline: 'Active', detail: null })),
}))

vi.mock('../../components/dashboard/RevenueChart.jsx', () => ({ default: () => <div /> }))
vi.mock('../../components/dashboard/AppointmentTrendChart.jsx', () => ({ default: () => <div /> }))
vi.mock('../../components/dashboard/TopServices.jsx', () => ({ default: () => <div /> }))
vi.mock('../../components/dashboard/StaffPerformance.jsx', () => ({ default: () => <div /> }))
vi.mock('../../components/settings/BranchManagementPanel.jsx', () => ({
  default: () => <div>branches</div>,
}))

import CustomersView from '../CustomersView.jsx'
import StaffView from '../StaffView.jsx'
import CatalogView from '../CatalogView.jsx'
import SettingsView from '../SettingsView.jsx'
import BillingView from '../BillingView.jsx'
import SalonReportsView from '../SalonReportsView.jsx'

describe('Operations / settings / billing / reports responsive contracts', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    Object.assign(mockAuth, makeAuthMock())
  })

  it('CustomersView: KPI and card grids collapse on phone', () => {
    const { container } = renderWithProviders(<CustomersView />, { route: '/customers' })
    expect(
      findByClass(container, 'grid-cols-2')
      || findByClass(container, 'sm:grid-cols-2')
      || findByClass(container, 'lg:grid-cols-4'),
    ).toBeTruthy()
    expect(
      findByClass(container, 'sm:flex-row')
      || findByClass(container, 'flex-col'),
    ).toBeTruthy()
  })

  it('StaffView: card grid and/or overflow-x table wrappers for narrow screens', () => {
    const { container } = renderWithProviders(<StaffView />, { route: '/staff' })
    expect(
      findByClass(container, 'grid-cols-2')
      || findByClass(container, 'sm:grid-cols-2')
      || findByClass(container, 'lg:grid-cols-3')
      || findByClass(container, 'lg:grid-cols-4')
      || findByClass(container, 'overflow-x-auto'),
    ).toBeTruthy()
  })

  it('CatalogView: tables scroll horizontally on phone', () => {
    const { container } = renderWithProviders(<CatalogView />, { route: '/catalog' })
    expect(
      findByClass(container, 'overflow-x-auto')
      || findByClass(container, 'sm:flex-row'),
    ).toBeTruthy()
  })

  it('SettingsView: aside/content split only from lg breakpoint', () => {
    const { container } = renderWithProviders(<SettingsView />, { route: '/settings' })
    expect(findByClass(container, 'lg:grid-cols-12')).toBeTruthy()
    expect(
      findByClass(container, 'lg:col-span-3')
      || findByClass(container, 'lg:col-span-9'),
    ).toBeTruthy()
  })

  it('BillingView: plan/usage grids stack on phone and expand on lg/xl', async () => {
    const { container } = renderWithProviders(<BillingView />, { route: '/billing' })
    await waitFor(() => {
      expect(
        findByClass(container, 'lg:grid-cols-2')
        || findByClass(container, 'md:grid-cols-2')
        || findByClass(container, 'xl:grid-cols-3')
        || findByClass(container, 'sm:flex-row'),
      ).toBeTruthy()
    })
  })

  it('SalonReportsView: charts/KPIs use responsive grids and table overflow', () => {
    const { container } = renderWithProviders(<SalonReportsView />, { route: '/reports' })
    expect(
      findByClass(container, 'xl:grid-cols-2')
      || findByClass(container, 'lg:grid-cols-2')
      || findByClass(container, 'grid-cols-2')
      || findByClass(container, 'overflow-x-auto'),
    ).toBeTruthy()
  })
})
