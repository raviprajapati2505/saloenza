import React from 'react'
import { describe, expect, it, vi, beforeEach } from 'vitest'
import { renderWithProviders, findByClass, makeAuthMock } from '../../../test-utils/responsive.jsx'

const mockAuth = makeAuthMock({
  workspace: 'guest',
  isAuthenticated: false,
  initialized: true,
  login: vi.fn(),
})

vi.mock('../../../stores/auth', () => ({
  useAuthStore: () => mockAuth,
}))

import LoginView from '../LoginView.jsx'
import RegisterView from '../RegisterView.jsx'
import ForgotPasswordView from '../ForgotPasswordView.jsx'

describe('Auth screens responsive contracts', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  it('Login: marketing pane is desktop-only; mobile brand is lg:hidden', () => {
    const { container } = renderWithProviders(<LoginView />, { route: '/login' })

    expect(findByClass(container, 'lg:grid-cols-2')).toBeTruthy()
    expect(findByClass(container, 'lg:hidden')).toBeTruthy()
    // Left marketing column uses hidden + lg:flex
    const marketing = Array.from(container.querySelectorAll('section,div')).find(
      (el) => el.className.includes('hidden') && el.className.includes('lg:flex'),
    )
    expect(marketing).toBeTruthy()
  })

  it('Register: uses mobile-first padded shell and sm:p-8 card', () => {
    const { container } = renderWithProviders(<RegisterView />, { route: '/register' })

    expect(findByClass(container, 'sm:px-6') || findByClass(container, 'px-4')).toBeTruthy()
    expect(findByClass(container, 'sm:p-8')).toBeTruthy()
    expect(findByClass(container, 'max-w-md')).toBeTruthy()
  })

  it('Forgot password: uses mobile-first padded shell and sm:p-8 card', () => {
    const { container } = renderWithProviders(<ForgotPasswordView />, { route: '/forgot-password' })

    expect(findByClass(container, 'sm:p-8')).toBeTruthy()
    expect(findByClass(container, 'max-w-md')).toBeTruthy()
  })
})
