import React, { useEffect } from 'react'
import { render, waitFor } from '@testing-library/react'
import { describe, expect, it, vi, beforeEach } from 'vitest'

vi.mock('../../lib/api', () => ({
  api: {
    get: vi.fn(),
    post: vi.fn(),
  },
  setAuthToken: vi.fn(),
}))

import { api } from '../../lib/api'
import { AuthProvider, useAuthStore } from '../../stores/auth.js'

function Probe() {
  const auth = useAuthStore()

  useEffect(() => {
    if (!auth.initialized) {
      void auth.initialize()
    }
  }, [auth.initialized, auth.initialize])

  return <div data-testid="should-onboard">{String(auth.shouldOnboard)}</div>
}

describe('AuthProvider fetchMe', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    localStorage.clear()
  })

  it('restores should_onboard from GET /v1/me during initialize', async () => {
    localStorage.setItem('salonos_token', 'saved-token')
    api.get.mockResolvedValue({
      data: {
        data: {
          user: { id: 1, name: 'Owner', email: 'owner@test.com' },
          tenant: { id: 1, name: 'salon', plan: { name: 'Free', slug: 'free' } },
          is_system_admin: false,
          should_onboard: true,
          role: null,
          permissions: ['customers.view'],
        },
      },
    })

    const { getByTestId } = render(
      <AuthProvider>
        <Probe />
      </AuthProvider>,
    )

    await waitFor(() => {
      expect(getByTestId('should-onboard').textContent).toBe('true')
    })

    expect(api.get).toHaveBeenCalledWith('/v1/me')
  })
})
