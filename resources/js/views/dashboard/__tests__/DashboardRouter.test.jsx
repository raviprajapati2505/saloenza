import React from 'react'
import { render, screen } from '@testing-library/react'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { MemoryRouter } from 'react-router-dom'

const mockAuth = {
  workspace: 'platform',
  isSystemAdmin: true,
  grantsAllPermissions: true,
  isAffiliatePartner: false,
  isBranchManager: false,
  isStaffMember: false,
  isBranchScoped: false,
  token: 'token',
  user: { name: 'Platform Owner' },
  role: { scope: 'platform', name: 'Super Admin' },
  roleDisplay: { label: 'Super Admin' },
  can: vi.fn(() => true),
  canPlatform: vi.fn(() => true),
  subscriptionModules: [],
  subscriptionLimits: null,
  tenant: null,
  planName: null,
}

vi.mock('../../../stores/auth', () => ({
  useAuthStore: () => mockAuth,
}))

vi.mock('../PlatformDashboardView.jsx', () => ({
  default: () => <div>Platform Dashboard</div>,
}))
vi.mock('../SalonOwnerDashboardView.jsx', () => ({
  default: () => <div>Salon Dashboard</div>,
}))
vi.mock('../BranchManagerDashboardView.jsx', () => ({
  default: () => <div>Branch Dashboard</div>,
}))
vi.mock('../StaffDashboardView.jsx', () => ({
  default: () => <div>Staff Dashboard</div>,
}))

import DashboardRouter from '../DashboardRouter.jsx'

describe('DashboardRouter', () => {
  beforeEach(() => {
    mockAuth.workspace = 'platform'
    mockAuth.isSystemAdmin = true
    mockAuth.grantsAllPermissions = true
    mockAuth.isAffiliatePartner = false
    mockAuth.isBranchManager = false
    mockAuth.isStaffMember = false
    mockAuth.isBranchScoped = false
    mockAuth.role = { scope: 'platform', name: 'Super Admin' }
    mockAuth.tenant = null
  })

  it('renders platform dashboard for platform owners', () => {
    render(
      <MemoryRouter>
        <DashboardRouter />
      </MemoryRouter>,
    )
    expect(screen.getByText('Platform Dashboard')).toBeInTheDocument()
  })

  it('renders salon owner dashboard for franchise owners', () => {
    mockAuth.workspace = 'tenant'
    mockAuth.isSystemAdmin = false
    mockAuth.grantsAllPermissions = false
    mockAuth.role = { scope: 'salon', name: 'Salon Franchise Owner', code: 'salon.franchise_owner' }
    mockAuth.tenant = { name: 'Glow Demo' }

    render(
      <MemoryRouter>
        <DashboardRouter />
      </MemoryRouter>,
    )
    expect(screen.getByText('Salon Dashboard')).toBeInTheDocument()
  })

  it('renders branch manager dashboard', () => {
    mockAuth.workspace = 'tenant'
    mockAuth.isSystemAdmin = false
    mockAuth.grantsAllPermissions = false
    mockAuth.isBranchManager = true
    mockAuth.isBranchScoped = true
    mockAuth.role = { scope: 'branch', name: 'Branch Manager', code: 'salon.branch_manager' }

    render(
      <MemoryRouter>
        <DashboardRouter />
      </MemoryRouter>,
    )
    expect(screen.getByText('Branch Dashboard')).toBeInTheDocument()
  })

  it('renders staff dashboard', () => {
    mockAuth.workspace = 'tenant'
    mockAuth.isSystemAdmin = false
    mockAuth.grantsAllPermissions = false
    mockAuth.isBranchManager = false
    mockAuth.isStaffMember = true
    mockAuth.isBranchScoped = true
    mockAuth.role = { scope: 'branch', name: 'Staff', code: 'salon.staff' }

    render(
      <MemoryRouter>
        <DashboardRouter />
      </MemoryRouter>,
    )
    expect(screen.getByText('Staff Dashboard')).toBeInTheDocument()
  })

  it('redirects affiliates to affiliate dashboard', () => {
    mockAuth.workspace = 'affiliate'
    mockAuth.isSystemAdmin = false
    mockAuth.grantsAllPermissions = false
    mockAuth.isAffiliatePartner = true
    mockAuth.role = { scope: 'affiliate', name: 'Affiliate Partner' }

    render(
      <MemoryRouter initialEntries={['/dashboard']}>
        <DashboardRouter />
      </MemoryRouter>,
    )

    expect(screen.queryByText('Platform Dashboard')).not.toBeInTheDocument()
    expect(screen.queryByText('Salon Dashboard')).not.toBeInTheDocument()
    expect(screen.queryByText('Staff Dashboard')).not.toBeInTheDocument()
  })
})
