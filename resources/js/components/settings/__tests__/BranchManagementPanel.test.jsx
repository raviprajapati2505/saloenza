import React from 'react'
import { render, screen, waitFor } from '@testing-library/react'
import { describe, expect, it, vi, beforeEach } from 'vitest'
import BranchManagementPanel from '../BranchManagementPanel.jsx'

const mockAuthStore = {
  user: { branch_id: 11 },
  isBranchScoped: false,
  subscriptionLimits: { max_branches: 3, branches_used: 2 },
  can: vi.fn(() => true),
  fetchMe: vi.fn(),
}

vi.mock('../../../stores/auth', () => ({
  AuthProvider: ({ children }) => children,
  useAuthStore: () => mockAuthStore,
}))

const mockFetchBranches = vi.fn()
const mockUpdateBranch = vi.fn()
const mockCreateBranch = vi.fn()

vi.mock('../../../services/branchService.js', () => ({
  fetchBranches: (...args) => mockFetchBranches(...args),
  updateBranch: (...args) => mockUpdateBranch(...args),
  createBranch: (...args) => mockCreateBranch(...args),
}))

describe('BranchManagementPanel', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    mockAuthStore.isBranchScoped = false
    mockAuthStore.user = { branch_id: 11 }
    mockAuthStore.can.mockImplementation(() => true)
    mockFetchBranches.mockResolvedValue({
      branches: [
        {
          id: 11,
          branch_name: 'Andheri West',
          business_address_1: 'Andheri Road',
          city: 'Mumbai',
          state: 'Maharashtra',
          area_pincode: '400053',
          country: 'India',
          is_active: true,
          users_count: 4,
          catalog_items_count: 6,
          manager: { name: 'Riya Shah', email: 'manager.andheri@glowdemo.com' },
        },
        {
          id: 12,
          branch_name: 'Bandra',
          business_address_1: 'Bandra Road',
          city: 'Mumbai',
          state: 'Maharashtra',
          area_pincode: '400050',
          country: 'India',
          is_active: true,
          users_count: 3,
          catalog_items_count: 5,
        },
      ],
      limits: { max_branches: 3, branches_used: 2 },
      canCreateBranch: true,
    })
  })

  it('shows salon-wide capacity and branch list for owners', async () => {
    render(<BranchManagementPanel />)

    await waitFor(() => {
      expect(screen.getByText('Your branches')).toBeInTheDocument()
    })

    expect(screen.getByText('Plan Capacity')).toBeInTheDocument()
    expect(screen.getByText('Current Usage')).toBeInTheDocument()
    expect(screen.getByText('Andheri West')).toBeInTheDocument()
    expect(screen.getByText('Bandra')).toBeInTheDocument()
    expect(screen.getByText('4 users assigned')).toBeInTheDocument()
    expect(screen.getByText(/Add Branch/i)).toBeInTheDocument()
  })

  it('shows only assigned branch details for branch managers', async () => {
    mockAuthStore.isBranchScoped = true
    mockAuthStore.can.mockImplementation((code) => code === 'branches.view' || code === 'branches.update')
    mockFetchBranches.mockResolvedValue({
      branches: [
        {
          id: 11,
          branch_name: 'Andheri West',
          business_address_1: 'Andheri Road',
          city: 'Mumbai',
          state: 'Maharashtra',
          area_pincode: '400053',
          country: 'India',
          is_active: true,
        },
      ],
      limits: null,
      canCreateBranch: false,
    })

    render(<BranchManagementPanel />)

    await waitFor(() => {
      expect(screen.getByText('Andheri West')).toBeInTheDocument()
    })

    expect(screen.getByText('My Branch')).toBeInTheDocument()
    expect(screen.queryByText('Plan Capacity')).not.toBeInTheDocument()
    expect(screen.queryByText('Current Usage')).not.toBeInTheDocument()
    expect(screen.queryByText('Your branches')).not.toBeInTheDocument()
    expect(screen.queryByText('Bandra')).not.toBeInTheDocument()
    expect(screen.queryByText(/users assigned/i)).not.toBeInTheDocument()
    expect(screen.queryByText(/Add Branch/i)).not.toBeInTheDocument()
    expect(screen.getByText(/Edit Branch Details/i)).toBeInTheDocument()
  })
})
