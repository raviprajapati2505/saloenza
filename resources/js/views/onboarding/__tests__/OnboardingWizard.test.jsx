import React from 'react'
import { render, screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { describe, expect, it, vi, beforeEach } from 'vitest'
import { MemoryRouter } from 'react-router-dom'

// ── Mocks ──────────────────────────────────────────────────────────────────

const mockNavigate = vi.fn()
vi.mock('react-router-dom', async () => {
  const actual = await vi.importActual('react-router-dom')
  return {
    ...actual,
    useNavigate: () => mockNavigate,
  }
})

const mockAuthStore = {
  initialized: true,
  isAuthenticated: true,
  shouldOnboard: true,
  user: { id: 1, name: 'Salon Owner', email: 'owner@salonos.test' },
  planName: 'Free Trial',
  markOnboardingCompleted: vi.fn(),
  can: vi.fn(() => true),
  initialize: vi.fn(),
}

vi.mock('../../../stores/auth', () => ({
  AuthProvider: ({ children }) => children,
  useAuthStore: () => mockAuthStore,
}))

// Mock WeeklyHoursInput (complex component not under test)
vi.mock('../../../components/inputs/WeeklyHoursInput.jsx', () => ({
  default: function MockWeeklyHoursInput() {
    return <div data-testid="weekly-hours-input">Weekly Hours Mock</div>
  },
}))

// Mock API client
vi.mock('../../../lib/api', () => ({
  api: {
    post: vi.fn(),
    get: vi.fn(),
  },
}))

import { api } from '../../../lib/api'
import OnboardingWizard from '../OnboardingWizard.jsx'

// ── Helpers ────────────────────────────────────────────────────────────────

function renderWizard() {
  return render(
    <MemoryRouter initialEntries={['/onboarding']}>
      <OnboardingWizard />
    </MemoryRouter>,
  )
}

// ── Tests ──────────────────────────────────────────────────────────────────

describe('OnboardingWizard', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    api.get.mockResolvedValue({
      data: {
        subscription_plans: [
          { id: 1, name: 'Free Trial', slug: 'free', price: 0 },
          { id: 2, name: 'Pro Plan', slug: 'pro', price: 999 },
        ],
      },
    })
  })

  // ── Step 1: Account Setup ──────────────────────────────────────────────

  it('renders Step 1 (Account Setup) initially', async () => {
    renderWizard()
    const accountSetupElements = screen.getAllByText('Account Setup')
    expect(accountSetupElements.length).toBeGreaterThanOrEqual(1)
    expect(await screen.findByText('Free Trial')).toBeInTheDocument()
    expect(screen.getByText('Salon Owner')).toBeInTheDocument()
    expect(screen.getByText('owner@salonos.test')).toBeInTheDocument()
  })

  it('shows the plan selection step', async () => {
    renderWizard()
    expect(await screen.findByText('Free Trial')).toBeInTheDocument()
  })

  it('renders step indicators with correct numbering', () => {
    renderWizard()
    expect(screen.getByText('1')).toBeInTheDocument()
    expect(screen.getByText('2')).toBeInTheDocument()
    expect(screen.getByText('3')).toBeInTheDocument()
  })

  // ── Navigation ────────────────────────────────────────────────────────

  it('Back button is disabled on Step 1', () => {
    renderWizard()
    const backButton = screen.getByRole('button', { name: /back/i })
    expect(backButton).toBeDisabled()
  })

  it('navigates to Step 2 when clicking Next on Step 1', async () => {
    const user = userEvent.setup()
    api.post.mockResolvedValue({ data: { success: true } })
    renderWizard()

    await user.click(screen.getByRole('button', { name: /next/i }))

    await waitFor(() => {
      // "Your Salon Branch" appears in sidebar + content heading
      const branchElements = screen.getAllByText('Your Salon Branch')
      expect(branchElements.length).toBeGreaterThanOrEqual(2)
    })
  })

  // ── Error handling ────────────────────────────────────────────────────

  it('shows error banner when step save fails', async () => {
    const user = userEvent.setup()
    api.post.mockRejectedValue({
      response: { data: { message: 'Server validation failed' } },
    })
    renderWizard()

    await user.click(screen.getByRole('button', { name: /next/i }))

    expect(await screen.findByText('Server validation failed')).toBeInTheDocument()
  })

  it('shows default error when no response message', async () => {
    const user = userEvent.setup()
    api.post.mockRejectedValue(new Error('Network Error'))
    renderWizard()

    await user.click(screen.getByRole('button', { name: /next/i }))

    expect(await screen.findByText(/unable to save this step/i)).toBeInTheDocument()
  })

  // ── UI elements ───────────────────────────────────────────────────────

  it('renders the brand logo', () => {
    renderWizard()
    expect(screen.getByRole('img', { name: /Glowsuite/i })).toBeInTheDocument()
  })

  it('shows the sidebar heading', () => {
    renderWizard()
    expect(screen.getByText('Set up your salon')).toBeInTheDocument()
  })
})
