import React, { createContext } from 'react'
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

// ── Auth mock ──────────────────────────────────────────────────────────────
// LoginView.jsx imports from '../../stores/auth' (relative to its position).
// The test is at views/auth/__tests__/, LoginView is at views/auth/.
// When Vitest resolves the import inside LoginView, it resolves the path
// relative to LoginView.jsx. We must mock the SAME resolved module.
// From this test file (__tests__/), '../../stores/auth' becomes views/stores/auth
// which is WRONG. The actual module is at resources/js/stores/auth.
// From LoginView at views/auth/, '../../stores/auth' becomes resources/js/stores/auth — CORRECT.
// vi.mock resolves from the TEST file, so we need ../../..stores/auth.
// BUT Vitest is caching by resolved path, so let's verify:

const mockLogin = vi.fn()
const mockAuthState = {
  initialized: true,
  isAuthenticated: false,
  shouldOnboard: false,
  user: null,
  token: null,
  tenant: null,
  role: null,
  isSystemAdmin: false,
  permissions: [],
  loading: false,
  isFree: false,
  planName: null,
  authHeaders: () => ({}),
  clearAuthState: vi.fn(),
  markOnboardingCompleted: vi.fn(),
  login: mockLogin,
  logout: vi.fn(),
  fetchMe: vi.fn(),
  can: vi.fn(() => true),
  initialize: vi.fn(),
}

// The correct path from __tests__/LoginView.test.jsx → stores/auth.js
// __tests__ → auth → views → js → then stores/auth? No.
// File structure: resources/js/views/auth/__tests__/LoginView.test.jsx
// Target: resources/js/stores/auth.js
// Relative: ../../../stores/auth
vi.mock('../../../stores/auth.js', () => ({
  AuthProvider: ({ children }) => children,
  useAuthStore: () => mockAuthState,
}))

// ── Imports (after mocks) ──────────────────────────────────────────────────

import LoginView from '../LoginView.jsx'

// ── Helpers ────────────────────────────────────────────────────────────────

function renderLogin(route = '/login') {
  return render(
    <MemoryRouter initialEntries={[route]}>
      <LoginView />
    </MemoryRouter>,
  )
}

// ── Tests ──────────────────────────────────────────────────────────────────

describe('LoginView', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    mockAuthState.isAuthenticated = false
    mockAuthState.shouldOnboard = false
  })

  it('renders the login form', () => {
    renderLogin()
    expect(screen.getByText('Welcome back')).toBeInTheDocument()
    expect(screen.getByPlaceholderText(/you@example.com/i)).toBeInTheDocument()
    expect(screen.getByPlaceholderText(/enter your password/i)).toBeInTheDocument()
    expect(screen.getByRole('button', { name: /sign in/i })).toBeInTheDocument()
  })

  it('renders "Start free trial" link to register page', () => {
    renderLogin()
    const link = screen.getByRole('link', { name: /start free trial/i })
    expect(link).toHaveAttribute('href', '/register')
  })

  it('renders "Forgot password?" link', () => {
    renderLogin()
    const link = screen.getByRole('link', { name: /forgot password/i })
    expect(link).toHaveAttribute('href', '/forgot-password')
  })

  it('shows error for invalid email on submit', async () => {
    const user = userEvent.setup()
    renderLogin()

    await user.type(screen.getByPlaceholderText(/you@example.com/i), 'bad-input')
    await user.type(screen.getByPlaceholderText(/enter your password/i), 'password123')
    await user.click(screen.getByRole('button', { name: /sign in/i }))

    expect(await screen.findByText(/enter a valid email address or phone number/i)).toBeInTheDocument()
    expect(mockLogin).not.toHaveBeenCalled()
  })

  it('shows error for short password on submit', async () => {
    const user = userEvent.setup()
    renderLogin()

    await user.type(screen.getByPlaceholderText(/you@example.com/i), 'user@salon.com')
    await user.type(screen.getByPlaceholderText(/enter your password/i), 'short')
    await user.click(screen.getByRole('button', { name: /sign in/i }))

    expect(await screen.findByText(/password must be at least 8 characters/i)).toBeInTheDocument()
    expect(mockLogin).not.toHaveBeenCalled()
  })

  it('shows error for empty fields on submit', async () => {
    const user = userEvent.setup()
    renderLogin()

    await user.click(screen.getByRole('button', { name: /sign in/i }))

    expect(await screen.findByText(/enter a valid email address or phone number/i)).toBeInTheDocument()
    expect(await screen.findByText(/password must be at least 8 characters/i)).toBeInTheDocument()
    expect(mockLogin).not.toHaveBeenCalled()
  })

  it('validates email on blur', async () => {
    const user = userEvent.setup()
    renderLogin()

    const emailInput = screen.getByPlaceholderText(/you@example.com/i)
    await user.type(emailInput, 'not-valid')
    await user.tab()

    expect(await screen.findByText(/enter a valid email address or phone number/i)).toBeInTheDocument()
  })

  it('calls login and navigates to /dashboard on success', async () => {
    const user = userEvent.setup()
    mockLogin.mockResolvedValue({ data: { user: { id: 1 }, should_onboard: false } })
    renderLogin()

    await user.type(screen.getByPlaceholderText(/you@example.com/i), 'user@salon.com')
    await user.type(screen.getByPlaceholderText(/enter your password/i), 'password123')
    await user.click(screen.getByRole('button', { name: /sign in/i }))

    await waitFor(() => {
      expect(mockLogin).toHaveBeenCalledWith('user@salon.com', 'password123', 'web')
      expect(mockNavigate).toHaveBeenCalledWith('/dashboard')
    })
  })

  it('passes device_name "web-remember" when remember me is checked', async () => {
    const user = userEvent.setup()
    mockLogin.mockResolvedValue({ data: { user: { id: 1 } } })
    renderLogin()

    await user.type(screen.getByPlaceholderText(/you@example.com/i), 'user@salon.com')
    await user.type(screen.getByPlaceholderText(/enter your password/i), 'password123')
    await user.click(screen.getByRole('checkbox'))
    await user.click(screen.getByRole('button', { name: /sign in/i }))

    await waitFor(() => {
      expect(mockLogin).toHaveBeenCalledWith('user@salon.com', 'password123', 'web-remember')
    })
  })

  it('accepts phone number as valid login', async () => {
    const user = userEvent.setup()
    mockLogin.mockResolvedValue({ data: { user: { id: 1 } } })
    renderLogin()

    await user.type(screen.getByPlaceholderText(/you@example.com/i), '+91 9876543210')
    await user.type(screen.getByPlaceholderText(/enter your password/i), 'password123')
    await user.click(screen.getByRole('button', { name: /sign in/i }))

    await waitFor(() => {
      expect(mockLogin).toHaveBeenCalledWith('+91 9876543210', 'password123', 'web')
    })
  })

  it('redirects to /verify-2fa when 2FA is required', async () => {
    const user = userEvent.setup()
    mockLogin.mockResolvedValue({ data: { requires_2fa: true } })
    renderLogin()

    await user.type(screen.getByPlaceholderText(/you@example.com/i), 'user@salon.com')
    await user.type(screen.getByPlaceholderText(/enter your password/i), 'password123')
    await user.click(screen.getByRole('button', { name: /sign in/i }))

    await waitFor(() => {
      expect(mockNavigate).toHaveBeenCalledWith('/verify-2fa')
    })
  })

  it('redirects to /onboarding when should_onboard is true', async () => {
    const user = userEvent.setup()
    mockLogin.mockResolvedValue({ data: { user: { id: 1 }, should_onboard: true } })
    renderLogin()

    await user.type(screen.getByPlaceholderText(/you@example.com/i), 'user@salon.com')
    await user.type(screen.getByPlaceholderText(/enter your password/i), 'password123')
    await user.click(screen.getByRole('button', { name: /sign in/i }))

    await waitFor(() => {
      expect(mockNavigate).toHaveBeenCalledWith('/onboarding')
    })
  })

  it('respects ?redirect= query parameter after login', async () => {
    const user = userEvent.setup()
    mockAuthState.isSystemAdmin = true
    mockLogin.mockResolvedValue({ data: { user: { id: 1 }, is_system_admin: true, should_onboard: false } })
    renderLogin('/login?redirect=%2Fstaff')

    await user.type(screen.getByPlaceholderText(/you@example.com/i), 'user@salon.com')
    await user.type(screen.getByPlaceholderText(/enter your password/i), 'password123')
    await user.click(screen.getByRole('button', { name: /sign in/i }))

    await waitFor(() => {
      expect(mockNavigate).toHaveBeenCalledWith('/staff')
    })
  })

  it('shows server error message on auth failure', async () => {
    const user = userEvent.setup()
    mockLogin.mockRejectedValue({
      response: { data: { message: 'These credentials do not match our records.' } },
    })
    renderLogin()

    await user.type(screen.getByPlaceholderText(/you@example.com/i), 'user@salon.com')
    await user.type(screen.getByPlaceholderText(/enter your password/i), 'wrongpassword1')
    await user.click(screen.getByRole('button', { name: /sign in/i }))

    expect(await screen.findByText('These credentials do not match our records.')).toBeInTheDocument()
  })

  it('shows connection error when no response object (network failure)', async () => {
    const user = userEvent.setup()
    mockLogin.mockRejectedValue(new Error('Network Error'))
    renderLogin()

    await user.type(screen.getByPlaceholderText(/you@example.com/i), 'user@salon.com')
    await user.type(screen.getByPlaceholderText(/enter your password/i), 'wrongpassword1')
    await user.click(screen.getByRole('button', { name: /sign in/i }))

    expect(await screen.findByText(/unable to connect/i)).toBeInTheDocument()
  })

  it('shows loading spinner during submission', async () => {
    const user = userEvent.setup()
    mockLogin.mockReturnValue(new Promise(() => {}))
    renderLogin()

    await user.type(screen.getByPlaceholderText(/you@example.com/i), 'user@salon.com')
    await user.type(screen.getByPlaceholderText(/enter your password/i), 'password123')
    await user.click(screen.getByRole('button', { name: /sign in/i }))

    expect(await screen.findByText(/signing in/i)).toBeInTheDocument()
  })
})
