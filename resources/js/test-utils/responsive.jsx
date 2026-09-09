import React from 'react'
import { render } from '@testing-library/react'
import { MemoryRouter } from 'react-router-dom'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { vi } from 'vitest'

export function setViewport(width) {
  Object.defineProperty(window, 'innerWidth', {
    configurable: true,
    writable: true,
    value: width,
  })
  window.dispatchEvent(new Event('resize'))
}

export function createQueryClient() {
  return new QueryClient({
    defaultOptions: {
      queries: { retry: false },
      mutations: { retry: false },
    },
  })
}

export function renderWithProviders(ui, {
  route = '/',
  queryClient = createQueryClient(),
} = {}) {
  return render(
    <QueryClientProvider client={queryClient}>
      <MemoryRouter initialEntries={[route]}>
        {ui}
      </MemoryRouter>
    </QueryClientProvider>,
  )
}

export function hasClass(el, token) {
  if (!el) return false
  if (el.classList?.contains?.(token)) return true
  const cn = typeof el.className === 'string'
    ? el.className
    : (el.className?.baseVal ?? el.getAttribute?.('class') ?? '')
  return String(cn).split(/\s+/).includes(token)
}

export function findByClass(container, token) {
  return Array.from(container.querySelectorAll('*')).find((el) => hasClass(el, token))
}

export function findAllByClass(container, token) {
  return Array.from(container.querySelectorAll('*')).filter((el) => hasClass(el, token))
}

export function makeAuthMock(overrides = {}) {
  return {
    isAuthenticated: true,
    workspace: 'tenant',
    tenant: { name: 'Glow Demo', plan: { name: 'Pro' } },
    planName: 'Pro',
    isFree: false,
    isSystemAdmin: false,
    grantsAllPermissions: false,
    isBranchManager: false,
    isStaffMember: false,
    isBranchScoped: false,
    isFranchiseOwner: true,
    user: { id: 1, name: 'Owner User', firstname: 'Owner' },
    role: { scope: 'salon', code: 'salon.franchise_owner', name: 'Owner' },
    roleDisplay: { label: 'Owner' },
    subscriptionModules: [
      'appointments', 'customers', 'staff', 'catalog', 'roles', 'settings', 'billing', 'analytics',
    ],
    subscriptionLimits: { max_branches: 3, max_staff: 20, branches_used: 1, staff_used: 2 },
    permissions: [],
    can: vi.fn(() => true),
    canAny: vi.fn(() => true),
    canPlatform: vi.fn(() => true),
    canModule: vi.fn(() => true),
    authHeaders: vi.fn(() => ({ Authorization: 'Bearer test-token' })),
    login: vi.fn(),
    logout: vi.fn(),
    initialized: true,
    ...overrides,
  }
}
