import React from 'react'
import { render, screen, waitFor } from '@testing-library/react'
import { describe, expect, it, vi, beforeEach } from 'vitest'
import { MemoryRouter } from 'react-router-dom'

const mockAuthStore = {
  token: 'mock-token',
  can: vi.fn(() => true),
  canPlatform: vi.fn(() => true),
  canAffiliate: vi.fn(() => true),
}

vi.mock('../../../../stores/auth', () => ({
  useAuthStore: () => mockAuthStore,
}))

const mockFetchOnboardingRecords = vi.fn()
const mockCompleteOwner = vi.fn()
const mockResetOwner = vi.fn()

vi.mock('../../../../services/adminOnboardingService.js', () => ({
  fetchOnboardingRecords: (...args) => mockFetchOnboardingRecords(...args),
  completeOwnerOnboarding: (...args) => mockCompleteOwner(...args),
  resetOwnerOnboarding: (...args) => mockResetOwner(...args),
}))

import AdminOnboardingHubPage from '../AdminOnboardingHubPage.jsx'

describe('AdminOnboardingHubPage', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    mockFetchOnboardingRecords.mockResolvedValue({
      onboardings: [
        {
          id: 1,
          business_name: 'Glow Studio',
          city: 'Mumbai',
          state: 'Maharashtra',
          onboarding_status: 'pending',
          branch_count: 1,
          created_at: '2026-07-01T00:00:00.000Z',
          owner: { id: 10, name: 'Ravi Owner', email: 'owner@glow.com' },
        },
      ],
      summary: { total: 1, completed: 0, pending: 1, no_owner: 0 },
    })
  })

  it('renders onboarding management hub and loads records', async () => {
    render(
      <MemoryRouter>
        <AdminOnboardingHubPage />
      </MemoryRouter>,
    )

    expect(screen.getByRole('heading', { name: 'Salon Onboarding' })).toBeInTheDocument()
    expect(await screen.findByText('Glow Studio')).toBeInTheDocument()
    expect(mockFetchOnboardingRecords).toHaveBeenCalled()
  })

  it('links to create new salon onboarding', async () => {
    render(
      <MemoryRouter>
        <AdminOnboardingHubPage />
      </MemoryRouter>,
    )

    await waitFor(() => {
      expect(screen.getByRole('link', { name: /onboard new salon/i })).toHaveAttribute('href', '/admin/onboarding/new')
    })
  })
})
