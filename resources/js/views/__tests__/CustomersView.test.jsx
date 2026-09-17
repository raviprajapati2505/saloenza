import React from 'react'
import { render, screen, waitFor, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { describe, expect, it, vi, beforeEach } from 'vitest'
import { MemoryRouter, Route, Routes } from 'react-router-dom'

const mockAuthStore = {
  can: vi.fn((code) => code === 'customers.view' || code === 'customers.manage'),
  isSystemAdmin: false,
  grantsAllPermissions: false,
  user: { saloon_id: 1 },
}

vi.mock('../../stores/auth', () => ({
  AuthProvider: ({ children }) => children,
  useAuthStore: () => mockAuthStore,
}))

vi.mock('../../hooks/useTenantFormatter.js', () => ({
  useTenantFormatter: () => ({
    money: (v) => `ر.ق${Number(v).toFixed(0)}`,
    symbol: () => 'ر.ق',
  }),
}))

const mockFetchCustomers = vi.fn()
const mockFetchCustomerTags = vi.fn()
const mockFetchCustomer = vi.fn()
const mockApiGet = vi.fn()

vi.mock('../../services/customerService.js', () => ({
  fetchCustomers: (...args) => mockFetchCustomers(...args),
  fetchCustomerTags: (...args) => mockFetchCustomerTags(...args),
  fetchCustomer: (...args) => mockFetchCustomer(...args),
  createCustomer: vi.fn(),
  updateCustomer: vi.fn(),
  deleteCustomer: vi.fn(),
  createCustomerTag: vi.fn(),
}))

vi.mock('../../lib/apiHelpers', () => ({
  apiGet: (...args) => mockApiGet(...args),
  parseList: (response, key) => response?.data?.data?.[key] ?? [],
  parseItem: (response, key) => response?.data?.data?.[key] ?? null,
}))

import CustomersView from '../CustomersView.jsx'

const sampleCustomer = {
  id: 1,
  name: 'Priya Sharma',
  email: 'priya@example.com',
  phone: '+91 9876543210',
  notes: null,
  is_active: true,
  saloon_ids: [1],
  saloons: [{ id: 1, name: 'Demo Salon' }],
  tags: [{ id: 10, name: 'VIP', color: 'amber' }],
  appointments_count: 2,
  created_at: '2026-05-01T00:00:00.000Z',
}

describe('CustomersView', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    mockAuthStore.can.mockImplementation((code) => code === 'customers.view' || code === 'customers.manage')
    mockAuthStore.isSystemAdmin = false
    mockAuthStore.grantsAllPermissions = false
    mockFetchCustomers.mockResolvedValue([sampleCustomer])
    mockFetchCustomerTags.mockResolvedValue([{ id: 10, name: 'VIP', color: 'amber' }])
    mockFetchCustomer.mockResolvedValue({
      ...sampleCustomer,
      visit_stats: { total_visits: 2, total_spent: 1500, last_visit_at: '2026-06-01T00:00:00.000Z' },
      recent_visits: [{
        id: 99,
        starts_at: '2026-06-01T10:00:00.000Z',
        status: 'completed',
        service_name: 'Hair Cut',
        grand_total: 750,
      }],
    })
    mockApiGet.mockResolvedValue({ data: { data: { saloons: [] } } })
  })

  function renderView(initialEntries = ['/customers']) {
    return render(
      <MemoryRouter initialEntries={initialEntries}>
        <Routes>
          <Route path="/customers" element={<CustomersView />} />
        </Routes>
      </MemoryRouter>,
    )
  }

  it('loads customers from the API on mount', async () => {
    renderView()

    await waitFor(() => {
      expect(mockFetchCustomers).toHaveBeenCalledWith(expect.objectContaining({ page: 1, per_page: 100 }))
    })

    expect(await screen.findByText('Priya Sharma')).toBeInTheDocument()
    expect(screen.getByText('Add Customer')).toBeInTheDocument()
    expect(within(screen.getByText('Priya Sharma').closest('div')).getByText('VIP')).toBeInTheDocument()
  })

  it('opens add customer form when Add Customer is clicked', async () => {
    const user = userEvent.setup()
    renderView()

    await screen.findByText('Priya Sharma')
    await user.click(screen.getByText('Add Customer'))

    expect(await screen.findByText('Create Customer')).toBeInTheDocument()
  })

  it('shows visit history in detail modal', async () => {
    const user = userEvent.setup()
    renderView()

    await screen.findByText('Priya Sharma')
    await user.click(screen.getByText('Priya Sharma'))

    await waitFor(() => {
      expect(mockFetchCustomer).toHaveBeenCalledWith(1)
    })

    expect(await screen.findByText('Visit History')).toBeInTheDocument()
    expect(screen.getByText('Hair Cut')).toBeInTheDocument()
  })

  it('opens create form from ?create=1 query param', async () => {
    renderView(['/customers?create=1'])

    expect(await screen.findByText('Create Customer')).toBeInTheDocument()
  })

  it('shows salon filter for system admins and loads all salons', async () => {
    mockAuthStore.isSystemAdmin = true
    mockAuthStore.can.mockImplementation(() => true)
    mockApiGet.mockResolvedValue({
      data: { data: { saloons: [{ id: 2, name: 'North Salon' }] } },
    })

    renderView()

    expect(await screen.findByText('Manage customers across all salons.')).toBeInTheDocument()
    expect(await screen.findByDisplayValue('All salons')).toBeInTheDocument()
    await waitFor(() => {
      expect(mockApiGet).toHaveBeenCalledWith('/v1/saloons', expect.objectContaining({ page: 1, per_page: 100 }))
    })
  })
})
