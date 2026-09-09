import React from 'react'
import { render, screen } from '@testing-library/react'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { MemoryRouter } from 'react-router-dom'
import AppBottomNav from '../AppBottomNav.jsx'

const mockAuth = {
  isAuthenticated: true,
  workspace: 'tenant',
  isSystemAdmin: false,
  grantsAllPermissions: false,
  isBranchManager: false,
  isStaffMember: false,
  isBranchScoped: false,
  role: { scope: 'salon', code: 'salon.franchise_owner' },
  subscriptionModules: ['appointments', 'customers', 'staff', 'catalog', 'roles', 'settings', 'analytics', 'billing'],
  can: vi.fn(() => true),
  canAny: vi.fn(() => true),
  canPlatform: vi.fn(() => false),
  canModule: vi.fn(() => true),
}

vi.mock('../../../stores/auth', () => ({
  useAuthStore: () => mockAuth,
}))

describe('AppBottomNav (mobile shell)', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    mockAuth.role = { scope: 'salon', code: 'salon.franchise_owner' }
    mockAuth.subscriptionModules = ['appointments', 'customers', 'staff', 'catalog', 'roles', 'settings', 'analytics', 'billing']
    mockAuth.can.mockImplementation(() => true)
    mockAuth.canAny.mockImplementation(() => true)
  })

  it('uses fixed bottom bar with md:hidden so it only appears on phone/tablet shells', () => {
    const { container } = render(
      <MemoryRouter initialEntries={['/appointments']}>
        <AppBottomNav />
      </MemoryRouter>,
    )

    const nav = container.querySelector('nav')
    expect(nav).toBeTruthy()
    expect(nav.className).toContain('md:hidden')
    expect(nav.className).toContain('fixed')
    expect(nav.className).toContain('bottom-0')
    expect(nav.className).toContain('pb-safe')
  })

  it('shows mobile bottom destinations with Home/Schedule labels', () => {
    render(
      <MemoryRouter initialEntries={['/dashboard']}>
        <AppBottomNav />
      </MemoryRouter>,
    )

    expect(screen.getByText('Home')).toBeInTheDocument()
    expect(screen.getByText('Schedule')).toBeInTheDocument()
    expect(screen.getByText('Clients')).toBeInTheDocument()
  })

  it('falls back to Home when permission-gated bottom items are inaccessible', () => {
    mockAuth.can.mockImplementation(() => false)
    mockAuth.canAny.mockImplementation(() => false)

    const { container } = render(
      <MemoryRouter initialEntries={['/dashboard']}>
        <AppBottomNav />
      </MemoryRouter>,
    )

    // Home has permission: null, so it remains available as fallback/shell item.
    expect(container.querySelector('nav')).toBeTruthy()
    expect(screen.getByText('Home')).toBeInTheDocument()
  })
})
