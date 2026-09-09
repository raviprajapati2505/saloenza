import React from 'react'
import { render, screen } from '@testing-library/react'
import { describe, expect, it, vi, beforeEach } from 'vitest'
import { MemoryRouter } from 'react-router-dom'
import AppSidebar from '../AppSidebar.jsx'

const mockAuthStore = {
  isAuthenticated: true,
  workspace: 'tenant',
  tenant: { name: 'Demo Salon' },
  planName: 'Free Trial',
  isFree: true,
  isSystemAdmin: false,
  grantsAllPermissions: false,
  isBranchManager: false,
  isStaffMember: false,
  isBranchScoped: false,
  role: { scope: 'salon', code: 'salon.franchise_owner' },
  subscriptionModules: ['appointments', 'customers', 'staff', 'catalog', 'roles', 'settings'],
  can: vi.fn(() => true),
  canAny: vi.fn(() => true),
  canPlatform: vi.fn(() => false),
}

vi.mock('../../../stores/auth', () => ({
  AuthProvider: ({ children }) => children,
  useAuthStore: () => mockAuthStore,
}))

function renderSidebar(path = '/dashboard') {
  return render(
    <MemoryRouter initialEntries={[path]}>
      <AppSidebar collapsed={false} onToggleSidebar={vi.fn()} />
    </MemoryRouter>,
  )
}

describe('AppSidebar', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    mockAuthStore.isSystemAdmin = false
    mockAuthStore.grantsAllPermissions = false
    mockAuthStore.isBranchManager = false
    mockAuthStore.isStaffMember = false
    mockAuthStore.isBranchScoped = false
    mockAuthStore.role = { scope: 'salon', code: 'salon.franchise_owner' }
    mockAuthStore.can.mockImplementation(() => true)
  })

  it('hides the requested navigation links from the sidebar', () => {
    renderSidebar()
    expect(screen.getByText('Dashboard')).toBeInTheDocument()
    expect(screen.queryByText('Analytics')).not.toBeInTheDocument()
    expect(screen.getByText('Customers')).toBeInTheDocument()
    expect(screen.getByText('Organization')).toBeInTheDocument()
    expect(screen.getByText('People & Access')).toBeInTheDocument()
    expect(screen.queryByText('System')).not.toBeInTheDocument()
    expect(screen.getByText('Staff')).toBeInTheDocument()
    expect(screen.queryByText('Inventory')).not.toBeInTheDocument()
    expect(screen.getByText('Categories')).toBeInTheDocument()
    expect(screen.getByText('Branch Management')).toBeInTheDocument()
    expect(screen.queryByText('Salon Onboarding')).not.toBeInTheDocument()
  })

  it('shows My Branch label for branch-scoped managers', () => {
    mockAuthStore.isBranchManager = true
    mockAuthStore.isBranchScoped = true
    mockAuthStore.role = { scope: 'branch', code: 'salon.branch_manager' }
    renderSidebar()

    expect(screen.getByText('My Branch')).toBeInTheDocument()
    expect(screen.queryByText('Branch Management')).not.toBeInTheDocument()
  })

  it('shows Salon Onboarding link for platform onboarding managers', () => {
    mockAuthStore.isSystemAdmin = true
    mockAuthStore.grantsAllPermissions = true
    mockAuthStore.canPlatform.mockImplementation((code) => code === 'platform.salon_onboarding.view')
    renderSidebar()

    const link = screen.getByRole('link', { name: /salon onboarding/i })
    expect(link).toBeInTheDocument()
    expect(link).toHaveAttribute('href', '/admin/onboarding')
    expect(screen.getByText('Administration')).toBeInTheDocument()
  })

  it('shows platform Branches and hides tenant Branch Management for system admin', () => {
    mockAuthStore.isSystemAdmin = true
    mockAuthStore.grantsAllPermissions = true
    mockAuthStore.canPlatform.mockImplementation(() => true)
    renderSidebar()

    expect(screen.queryByText('Branch Management')).not.toBeInTheDocument()
    const link = screen.getByRole('link', { name: /^branches$/i })
    expect(link).toBeInTheDocument()
    expect(link).toHaveAttribute('href', '/admin/branches')
  })

  it('hides permission-gated links when user lacks access', () => {
    mockAuthStore.can.mockImplementation((code) => code !== 'customers.view')
    mockAuthStore.canAny.mockImplementation((codes) => codes.every((code) => code !== 'customers.view'))
    renderSidebar()

    expect(screen.queryByText('Customers')).not.toBeInTheDocument()
    expect(screen.getByText('Dashboard')).toBeInTheDocument()
  })

  it('keeps dashboard visible for staff and still shows appointments when permitted', () => {
    mockAuthStore.can.mockImplementation((code) => code === 'appointments.view')
    mockAuthStore.canAny.mockImplementation((codes) => codes.some((code) => code === 'appointments.view'))
    renderSidebar()

    expect(screen.getByText('Dashboard')).toBeInTheDocument()
    expect(screen.getByText('Appointments')).toBeInTheDocument()
    expect(screen.queryByText('Staff')).not.toBeInTheDocument()
    expect(screen.queryByText('Billing')).not.toBeInTheDocument()
  })
})
