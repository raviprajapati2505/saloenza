import React from 'react'
import { render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { describe, expect, it, vi, beforeEach } from 'vitest'
import { MemoryRouter } from 'react-router-dom'

const mockAuthStore = {
  initialized: true,
  isAuthenticated: true,
  shouldOnboard: false,
  isSystemAdmin: true,
  token: 'mock-test-token',
  user: { id: 1, name: 'System Admin', email: 'admin@salonos.com' },
  can: vi.fn(() => true),
  canPlatform: vi.fn(() => true),
  canAffiliate: vi.fn(() => true),
  initialize: vi.fn(),
}

vi.mock('../../../../stores/auth', () => ({
  AuthProvider: ({ children }) => children,
  useAuthStore: () => mockAuthStore,
}))

vi.mock('../../../../services/adminOnboardingService.js', () => ({
  submitOnboarding: vi.fn(),
}))

vi.mock('canvas-confetti', () => ({
  default: vi.fn(),
}))

import AdminOnboardingPage from '../AdminOnboardingPage.jsx'

function renderAdmin() {
  return render(
    <MemoryRouter initialEntries={['/admin/onboarding/new']}>
      <AdminOnboardingPage />
    </MemoryRouter>,
  )
}

describe('AdminOnboardingPage', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    mockAuthStore.token = 'mock-test-token'
  })

  it('renders the page heading', () => {
    renderAdmin()
    expect(screen.getByText('New Salon Onboarding')).toBeInTheDocument()
  })

  it('shows step 1 (Salon Information) initially with wizard controls', () => {
    renderAdmin()
    expect(screen.getByRole('heading', { name: 'Salon Information' })).toBeInTheDocument()
    expect(screen.queryByRole('heading', { name: 'Branch Information' })).not.toBeInTheDocument()
    expect(screen.getByText('Next')).toBeInTheDocument()
    expect(screen.getByText('Back')).toBeInTheDocument()
    expect(screen.getAllByText(/step 1 of 4/i).length).toBeGreaterThan(0)
  })

  it('shows page description text', () => {
    renderAdmin()
    expect(screen.getByText(/complete each step to register a new salon/i)).toBeInTheDocument()
  })

  it('allows typing in salon business name field', async () => {
    const user = userEvent.setup()
    renderAdmin()

    const businessNameInput = screen.getByLabelText(/business name/i)
    await user.type(businessNameInput, 'Glow Studio')

    expect(businessNameInput).toHaveValue('Glow Studio')
  })

  it('lists all four steps in the progress indicator', () => {
    renderAdmin()
    expect(screen.getAllByText('Salon Information').length).toBeGreaterThanOrEqual(1)
    expect(screen.getByText('Branch Information')).toBeInTheDocument()
    expect(screen.getByText('Owner User')).toBeInTheDocument()
    expect(screen.getByText('Service Products')).toBeInTheDocument()
  })
})
