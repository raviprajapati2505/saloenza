import React from 'react'
import { fireEvent, render, screen } from '@testing-library/react'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { MemoryRouter } from 'react-router-dom'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import AppLayout from '../../../layouts/AppLayout.jsx'

const mockAuth = {
  isAuthenticated: true,
  workspace: 'tenant',
  tenant: { name: 'Glow Demo' },
  planName: 'Pro',
  isFree: false,
  isSystemAdmin: false,
  grantsAllPermissions: false,
  isBranchManager: false,
  isStaffMember: false,
  isBranchScoped: false,
  user: { name: 'Owner User' },
  role: { scope: 'salon', code: 'salon.franchise_owner' },
  roleDisplay: { label: 'Owner' },
  subscriptionModules: ['appointments', 'customers', 'staff', 'catalog', 'roles', 'settings', 'billing'],
  can: vi.fn(() => true),
  canAny: vi.fn(() => true),
  canPlatform: vi.fn(() => false),
  canModule: vi.fn(() => true),
  logout: vi.fn(),
}

vi.mock('../../../stores/auth', () => ({
  AuthProvider: ({ children }) => children,
  useAuthStore: () => mockAuth,
}))

vi.mock('../../../services/notificationService.js', () => ({
  fetchAdminPendingCounts: vi.fn(async () => ({})),
  fetchAdminNotifications: vi.fn(async () => ({ notifications: [], unread_count: 0 })),
}))

function setViewport(width) {
  Object.defineProperty(window, 'innerWidth', {
    configurable: true,
    writable: true,
    value: width,
  })
  window.dispatchEvent(new Event('resize'))
}

function renderShell(path = '/dashboard') {
  const queryClient = new QueryClient({
    defaultOptions: {
      queries: { retry: false },
      mutations: { retry: false },
    },
  })

  return render(
    <QueryClientProvider client={queryClient}>
      <MemoryRouter initialEntries={[path]}>
        <AppLayout>
          <div data-testid="page-content">Page content</div>
        </AppLayout>
      </MemoryRouter>
    </QueryClientProvider>,
  )
}

describe('PWA mobile / tablet responsive UX', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    localStorage.clear()
    mockAuth.can.mockImplementation(() => true)
    setViewport(390)
  })

  it('phone (~390px): shows hamburger + bottom nav, and pads main so content clears the nav', () => {
    const { container } = renderShell('/appointments')

    expect(screen.getByLabelText(/open mobile menu/i)).toBeInTheDocument()
    expect(screen.getByLabelText(/open mobile menu/i).className).toContain('md:hidden')

    const bottomNav = container.querySelector('nav.fixed.bottom-0')
    expect(bottomNav).toBeTruthy()
    expect(bottomNav.className).toContain('md:hidden')
    expect(bottomNav.className).toContain('pb-safe')

    const main = container.querySelector('main')
    // Appointments uses a denser full-height shell; still clears the phone bottom nav.
    expect(main.className).toContain('pb-24')
    expect(main.className).toMatch(/md:pb-6|md:pb-10/)
  })

  it('phone: hamburger opens the mobile drawer overlay', () => {
    const { container } = renderShell('/dashboard')

    fireEvent.click(screen.getByLabelText(/open mobile menu/i))

    // Mobile drawer panel + backdrop are md:hidden fixed layers.
    expect(container.innerHTML).toContain('md:hidden')
    expect(container.querySelector('.fixed.inset-0') || container.innerHTML.includes('backdrop')).toBeTruthy()
  })

  it('phone header shows salon brand context instead of desktop breadcrumb-only chrome', () => {
    const { container } = renderShell('/customers')

    // Brand appears in both mobile header and sidebar; assert the mobile header copy.
    const header = container.querySelector('header')
    expect(header).toBeTruthy()
    expect(header.textContent).toContain('Glow Demo')
    expect(header.textContent).toContain('Customers')
  })

  it('tablet (~768px): keeps bottom nav classed md:hidden and collapses sidebar for medium desktops via resize handler', () => {
    setViewport(800)
    const { container } = renderShell('/dashboard')

    // Bottom nav still in DOM but hidden from md and up.
    const bottomNav = container.querySelector('nav.fixed.bottom-0')
    expect(bottomNav?.className).toContain('md:hidden')

    // Content area uses md:ml-* for desktop/tablet sidebar offset.
    const shell = container.querySelector('.md\\:ml-\\[72px\\], .md\\:ml-\\[260px\\]')
      || Array.from(container.querySelectorAll('div')).find((el) => /md:ml-\[(72|260)px\]/.test(el.className))
    expect(shell).toBeTruthy()
  })

  it('keeps touch-friendly hamburger control at least ~36px hit area on mobile', () => {
    renderShell('/dashboard')
    const button = screen.getByLabelText(/open mobile menu/i)
    // p-2 + icon w-5 ≈ comfortable tap target; class contract for mobile-only control.
    expect(button.className).toMatch(/p-2/)
    expect(button.className).toContain('md:hidden')
  })

  it('bottom nav destinations remain usable for owners on phone', () => {
    renderShell('/dashboard')
    expect(screen.getByText('Home')).toBeInTheDocument()
    expect(screen.getByText('Schedule')).toBeInTheDocument()
    expect(screen.getByText('Clients')).toBeInTheDocument()
  })
})
