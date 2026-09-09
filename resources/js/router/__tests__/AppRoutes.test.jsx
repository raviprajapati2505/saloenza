import React from 'react'
import { render, screen, waitFor } from '@testing-library/react'
import { describe, expect, it, vi, beforeEach } from 'vitest'
import { MemoryRouter, Routes, Route } from 'react-router-dom'
import { AppRoutes } from '../index.jsx'

const mockAuthStore = {
  initialized: true,
  isAuthenticated: true,
  isSystemAdmin: false,
  grantsAllPermissions: false,
  shouldOnboard: false,
  can: vi.fn(() => true),
  canAny: vi.fn(() => true),
  canPlatform: vi.fn(() => false),
  initialize: vi.fn(),
}

vi.mock('../../stores/auth', () => ({
  useAuthStore: () => mockAuthStore,
  AuthProvider: ({ children }) => children,
}))

vi.mock('../../views/DashboardView.jsx', () => ({ default: () => <div>Dashboard Page</div> }))
vi.mock('../../views/admin/onboarding/AdminOnboardingHubPage.jsx', () => ({
  default: () => <div>Admin Onboarding Hub</div>,
}))
vi.mock('../../views/admin/onboarding/AdminOnboardingPage.jsx', () => ({
  default: () => <div>Admin Onboarding Create</div>,
}))
vi.mock('../../views/admin/onboarding/AdminSalonEditPage.jsx', () => ({
  default: () => <div>Admin Salon Edit</div>,
}))
vi.mock('../../views/onboarding/OnboardingWizard.jsx', () => ({ default: () => <div>Tenant Onboarding</div> }))
vi.mock('../../views/onboarding/OnboardingComplete.jsx', () => ({ default: () => <div>Onboarding Complete</div> }))
vi.mock('../../views/auth/LoginView.jsx', () => ({ default: () => <div>Login Page</div> }))
vi.mock('../../views/auth/RegisterView.jsx', () => ({ default: () => <div>Register Page</div> }))
vi.mock('../../views/auth/ForgotPasswordView.jsx', () => ({ default: () => <div>Forgot Password</div> }))
vi.mock('../../views/auth/ResetPasswordView.jsx', () => ({ default: () => <div>Reset Password</div> }))
vi.mock('../../views/auth/TwoFactorView.jsx', () => ({ default: () => <div>Two Factor</div> }))
vi.mock('../../views/ComponentsTestView.jsx', () => ({ default: () => <div>UI Test</div> }))
vi.mock('../../views/StaffView.jsx', () => ({ default: () => <div>Staff Page</div> }))
vi.mock('../../views/InventoryView.jsx', () => ({ default: () => <div>Inventory Page</div> }))
vi.mock('../../views/CategoriesView.jsx', () => ({ default: () => <div>Categories Page</div> }))
vi.mock('../../views/CustomersView.jsx', () => ({ default: () => <div>Customers Page</div> }))
vi.mock('../../views/BillingView.jsx', () => ({ default: () => <div>Billing Page</div> }))
vi.mock('../../views/AnalyticsView.jsx', () => ({ default: () => <div>Analytics Page</div> }))
vi.mock('../../views/SettingsView.jsx', () => ({ default: () => <div>Settings Page</div> }))
vi.mock('../../views/CatalogView.jsx', () => ({ default: () => <div>Catalog Page</div> }))
vi.mock('../../views/settings/ProductsMasterView.jsx', () => ({ default: () => <div>Products Master</div> }))
vi.mock('../../views/settings/ServicesMasterView.jsx', () => ({ default: () => <div>Services Master</div> }))
vi.mock('../../views/settings/RolesView.jsx', () => ({ default: () => <div>Roles Page</div> }))
vi.mock('../../views/settings/AssignPermissionsView.jsx', () => ({ default: () => <div>Assign Permissions</div> }))
vi.mock('../../views/AppointmentsView.jsx', () => ({ default: () => <div>Appointments Page</div> }))

function renderAt(path) {
  return render(
    <MemoryRouter initialEntries={[path]}>
      <AppRoutes />
    </MemoryRouter>,
  )
}

describe('AppRoutes guards', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    mockAuthStore.initialized = true
    mockAuthStore.isAuthenticated = true
    mockAuthStore.isSystemAdmin = false
    mockAuthStore.shouldOnboard = false
  })

  it('redirects non-admin users away from admin onboarding', async () => {
    renderAt('/admin/onboarding')

    await waitFor(() => {
      expect(screen.getByText('403')).toBeInTheDocument()
    })
  })

  it('allows platform onboarding managers to access admin onboarding hub', async () => {
    mockAuthStore.canPlatform = vi.fn((code) => code === 'platform.salon_onboarding.view')
    renderAt('/admin/onboarding')

    expect(await screen.findByText('Admin Onboarding Hub')).toBeInTheDocument()
  })

  it('allows system admins to access admin onboarding create form', async () => {
    mockAuthStore.isSystemAdmin = true
    mockAuthStore.canPlatform = vi.fn(() => true)
    renderAt('/admin/onboarding/new')

    expect(await screen.findByText('Admin Onboarding Create')).toBeInTheDocument()
  })

  it('allows platform owners to access tenant workspace routes', async () => {
    mockAuthStore.isSystemAdmin = true
    mockAuthStore.grantsAllPermissions = true
    mockAuthStore.canPlatform = vi.fn(() => true)
    mockAuthStore.can = vi.fn(() => true)
    mockAuthStore.canAny = vi.fn(() => true)
    renderAt('/staff')

    expect(await screen.findByText('Staff Page')).toBeInTheDocument()
  })

  it('redirects unfinished onboarding users to tenant onboarding', async () => {
    mockAuthStore.shouldOnboard = true
    renderAt('/dashboard')

    expect(await screen.findByText('Tenant Onboarding')).toBeInTheDocument()
  })
})
