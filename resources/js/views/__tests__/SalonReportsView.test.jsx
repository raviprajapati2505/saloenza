import React from 'react'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { MemoryRouter } from 'react-router-dom'

const mockAuth = {
  workspace: 'tenant',
  isSystemAdmin: false,
  grantsAllPermissions: false,
  can: vi.fn(() => true),
  subscriptionModules: ['appointments', 'customers', 'staff', 'settings', 'analytics'],
}

vi.mock('../../stores/auth', () => ({
  useAuthStore: () => mockAuth,
}))

vi.mock('../../lib/subscriptionModules.js', async () => {
  const actual = await vi.importActual('../../lib/subscriptionModules.js')
  return {
    ...actual,
    authHasModule: (auth, module) => (auth.subscriptionModules || []).includes(module),
  }
})

const fetchMasterList = vi.fn(async () => [])
const fetchAnalyticsSummary = vi.fn(async () => ({
  collected_revenue: 0,
  outstanding_revenue: 0,
  appointments_count: 0,
  completed_count: 0,
  cancelled_count: 0,
  range: { from: '2026-01-01', to: '2026-01-31' },
  daily_collected: [],
  payment_methods: [],
  payment_status_counts: {},
  top_services: [],
}))

vi.mock('../../lib/apiHelpers', () => ({
  apiGet: vi.fn(async () => ({ data: { appointments: [] } })),
  fetchMasterList: (...args) => fetchMasterList(...args),
  parseList: vi.fn((payload, key) => payload?.data?.[key] || []),
}))

vi.mock('../../services/branchService.js', () => ({
  fetchBranches: vi.fn(async () => ({
    branches: [{ id: 1, branch_name: 'Andheri' }, { id: 2, branch_name: 'Bandra' }],
  })),
}))

vi.mock('../../services/customerService.js', () => ({
  fetchCustomers: vi.fn(async () => []),
}))

vi.mock('../../services/analyticsService.js', () => ({
  fetchAnalyticsSummary: (...args) => fetchAnalyticsSummary(...args),
  fetchOutstandingPaymentsReport: vi.fn(async () => ({
    paid_bills: 0,
    pending_bills: 0,
    collected: 0,
    outstanding: 0,
    overdue_days: 3,
    customers: [],
    bills: [],
  })),
  fetchBusinessCloseReport: vi.fn(async () => ({
    summary: {
      collected: 0,
      outstanding: 0,
      expenses: 0,
      staff_commission: 0,
      net_collected: 0,
      completed: 0,
    },
    payment_methods: [],
    expenses: { total: 0, by_category: [] },
    staff: [],
    daily: [],
  })),
}))

vi.mock('../../components/dashboard/RevenueChart.jsx', () => ({
  default: () => <div>RevenueChart</div>,
}))
vi.mock('../../components/dashboard/AppointmentTrendChart.jsx', () => ({
  default: () => <div>AppointmentTrendChart</div>,
}))
vi.mock('../../components/dashboard/TopServices.jsx', () => ({
  default: () => <div>TopServices</div>,
}))
vi.mock('../../components/dashboard/StaffPerformance.jsx', () => ({
  default: () => <div>StaffPerformance</div>,
}))

import SalonReportsView from '../SalonReportsView.jsx'

describe('SalonReportsView', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    mockAuth.can.mockImplementation(() => true)
    mockAuth.subscriptionModules = ['appointments', 'customers', 'staff', 'settings', 'analytics']
  })

  it('renders reports header for owners with analytics-capable modules', async () => {
    render(
      <MemoryRouter>
        <SalonReportsView />
      </MemoryRouter>,
    )

    expect(screen.getByText('Reports')).toBeInTheDocument()
    expect(
      screen.getByText(/Revenue, appointments, staff worklog, day close, and customer performance/i),
    ).toBeInTheDocument()

    await waitFor(() => {
      expect(screen.getByText('RevenueChart')).toBeInTheDocument()
    })
  })

  it('still mounts when appointment module is missing (empty charts / limited tabs)', async () => {
    mockAuth.subscriptionModules = ['customers', 'analytics']
    mockAuth.can.mockImplementation((code) => code !== 'appointments.view')

    render(
      <MemoryRouter>
        <SalonReportsView />
      </MemoryRouter>,
    )

    expect(screen.getByText('Reports')).toBeInTheDocument()
  })

  it('reloads analytics and appointments when the date range changes', async () => {
    render(
      <MemoryRouter>
        <SalonReportsView />
      </MemoryRouter>,
    )

    await waitFor(() => {
      expect(fetchMasterList).toHaveBeenCalled()
      expect(fetchAnalyticsSummary).toHaveBeenCalled()
    })

    const initialCalls = fetchAnalyticsSummary.mock.calls.length
    const fromInput = screen.getByLabelText('From')

    fireEvent.change(fromInput, { target: { value: '2026-01-01' } })

    await waitFor(() => {
      expect(fetchAnalyticsSummary.mock.calls.length).toBeGreaterThan(initialCalls)
      expect(fetchAnalyticsSummary).toHaveBeenLastCalledWith(
        expect.objectContaining({ from: '2026-01-01' }),
      )
    })
  })

  it('reloads data when branch filter changes', async () => {
    render(
      <MemoryRouter>
        <SalonReportsView />
      </MemoryRouter>,
    )

    await waitFor(() => {
      expect(screen.getByLabelText('Filter by branch')).toBeInTheDocument()
    })

    const initialCalls = fetchMasterList.mock.calls.length
    fireEvent.change(screen.getByLabelText('Filter by branch'), { target: { value: '1' } })

    await waitFor(() => {
      expect(fetchMasterList.mock.calls.length).toBeGreaterThan(initialCalls)
      expect(fetchMasterList).toHaveBeenLastCalledWith(
        '/v1/appointments',
        'appointments',
        expect.objectContaining({ branch_id: 1 }),
      )
    })
  })
})
