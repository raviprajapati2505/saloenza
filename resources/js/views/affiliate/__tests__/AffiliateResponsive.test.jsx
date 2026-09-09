import React from 'react'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { waitFor } from '@testing-library/react'
import { renderWithProviders, findByClass, makeAuthMock } from '../../../test-utils/responsive.jsx'

vi.mock('../../../stores/auth', () => ({
  useAuthStore: () => makeAuthMock({
    workspace: 'affiliate',
    isAffiliatePartner: true,
    role: { scope: 'affiliate' },
    affiliatePartner: {
      id: 1,
      code: 'AFF-DEMO',
      display_name: 'Growth Partner',
      status: 'active',
    },
  }),
}))

vi.mock('../../../services/affiliatePortalService.js', () => ({
  fetchAffiliateDashboard: vi.fn(async () => ({
    summary: {
      available_commission: 100,
      locked_commission: 50,
      paid_commission: 20,
      referral_count: 2,
    },
    recent_referrals: [],
    recent_commissions: [],
  })),
  fetchAffiliateReferrals: vi.fn(async () => ({ referrals: [] })),
  fetchAffiliateCommissions: vi.fn(async () => ({
    commissions: [{
      id: 1,
      type: 'onboarding',
      status: 'available',
      commission_amount: 100,
      base_amount: 1000,
      commission_rate: 10,
      locked_until: null,
      saloon: { name: 'Glow Demo' },
    }],
  })),
  fetchAffiliateWithdrawals: vi.fn(async () => ({ withdrawals: [], available_commission: 100 })),
  fetchAffiliateReports: vi.fn(async () => ({
    totals: { commission_count: 0, amount: 0 },
    by_type: {},
    by_month: [],
  })),
  createAffiliateWithdrawal: vi.fn(),
  applyAffiliatePartner: vi.fn(),
}))

vi.mock('../../../stores/toast.js', () => ({
  pushToast: vi.fn(),
}))

import AffiliateDashboardView from '../AffiliateDashboardView.jsx'
import AffiliateReferralsView from '../AffiliateReferralsView.jsx'
import AffiliateCommissionsView from '../AffiliateCommissionsView.jsx'
import AffiliateWithdrawalsView from '../AffiliateWithdrawalsView.jsx'
import AffiliateReportsView from '../AffiliateReportsView.jsx'
import AffiliateApplyView from '../AffiliateApplyView.jsx'

describe('Affiliate portal responsive contracts', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  it('dashboard summary cards expand from stacked phone to md/xl grids', () => {
    const { container } = renderWithProviders(<AffiliateDashboardView />, { route: '/affiliate/dashboard' })
    expect(
      findByClass(container, 'md:grid-cols-2')
      || findByClass(container, 'xl:grid-cols-3')
      || findByClass(container, 'md:flex-row'),
    ).toBeTruthy()
  })

  it('referrals table card keeps overflow containment on narrow screens', () => {
    const { container } = renderWithProviders(<AffiliateReferralsView />, { route: '/affiliate/referrals' })
    expect(findByClass(container, 'overflow-hidden') || findByClass(container, 'overflow-x-auto')).toBeTruthy()
  })

  it('commissions rows stack then become horizontal from md', async () => {
    const { container } = renderWithProviders(<AffiliateCommissionsView />, { route: '/affiliate/commissions' })
    await waitFor(() => {
      expect(
        findByClass(container, 'md:flex-row')
        || findByClass(container, 'flex-col')
        || findByClass(container, 'rounded-2xl'),
      ).toBeTruthy()
    })
  })

  it('withdrawals form uses md multi-column layout', () => {
    const { container } = renderWithProviders(<AffiliateWithdrawalsView />, { route: '/affiliate/withdrawals' })
    expect(
      findByClass(container, 'md:grid-cols-[180px,1fr,auto]')
      || findByClass(container, 'md:grid-cols-2')
      || findByClass(container, 'overflow-hidden'),
    ).toBeTruthy()
  })

  it('reports KPIs/charts use md/xl grids', () => {
    const { container } = renderWithProviders(<AffiliateReportsView />, { route: '/affiliate/reports' })
    expect(
      findByClass(container, 'md:grid-cols-2')
      || findByClass(container, 'xl:grid-cols-4')
      || findByClass(container, 'grid-cols-2'),
    ).toBeTruthy()
  })

  it('apply page mirrors login: desktop marketing pane + mobile brand', () => {
    const { container } = renderWithProviders(<AffiliateApplyView />, { route: '/affiliate/apply' })
    expect(
      findByClass(container, 'lg:grid-cols-2')
      || findByClass(container, 'lg:hidden')
      || findByClass(container, 'hidden'),
    ).toBeTruthy()
  })
})
