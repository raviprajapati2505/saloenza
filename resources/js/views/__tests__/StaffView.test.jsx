import React from 'react'
import { render, screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { describe, expect, it, vi, beforeEach } from 'vitest'
import { MemoryRouter } from 'react-router-dom'

const mockAuthStore = {
  can: vi.fn((code) => ['staff.create', 'staff.update', 'staff.view'].includes(code)),
  isSystemAdmin: false,
  isBranchScoped: false,
  subscriptionLimits: null,
}

vi.mock('../../stores/auth', () => ({
  AuthProvider: ({ children }) => children,
  useAuthStore: () => mockAuthStore,
}))

const mockApiGet = vi.fn()

vi.mock('../../lib/apiHelpers', () => ({
  apiGet: (...args) => mockApiGet(...args),
  apiPost: vi.fn(),
  apiPut: vi.fn(),
  apiDelete: vi.fn(),
  parseList: (response, key) => response?.data?.data?.[key] ?? [],
  parseItem: (response, key) => response?.data?.data?.[key] ?? null,
}))

vi.mock('../staff/StaffProfileView.jsx', () => ({
  default: () => <div>Staff Profile Mock</div>,
}))

import StaffView from '../StaffView.jsx'

describe('StaffView', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    localStorage.clear()
    mockApiGet.mockImplementation((path) => {
      if (path === '/v1/staff') {
        return Promise.resolve({
          data: {
            data: {
              staff: [
                {
                  id: 10,
                  name: 'Emma Watson',
                  firstname: 'Emma',
                  lastname: 'Watson',
                  email: 'emma@salon.test',
                  phone: '+91 9876500001',
                  is_active: true,
                  role_id: 2,
                  role: { id: 2, name: 'Stylist' },
                  created_at: '2026-01-15T00:00:00.000Z',
                },
                {
                  id: 11,
                  name: 'Riya Shah',
                  firstname: 'Riya',
                  lastname: 'Shah',
                  email: 'riya@salon.test',
                  phone: '+91 9876500002',
                  is_active: true,
                  role_id: 3,
                  role: { id: 3, name: 'Branch Manager' },
                  created_at: '2026-02-01T00:00:00.000Z',
                },
              ],
            },
          },
        })
      }

      if (path === '/v1/staff/assignable-roles') {
        return Promise.resolve({
          data: {
            data: {
              roles: [
                { id: 2, name: 'Stylist', is_active: true, scope: 'branch' },
                { id: 3, name: 'Branch Manager', is_active: true, scope: 'branch' },
              ],
            },
          },
        })
      }

      if (path === '/v1/branches' || path === '/v1/saloons') {
        return Promise.resolve({ data: { data: { branches: [], saloons: [] } } })
      }

      return Promise.resolve({ data: { data: {} } })
    })
  })

  it('loads staff roster from the API', async () => {
    render(
      <MemoryRouter>
        <StaffView />
      </MemoryRouter>,
    )

    await waitFor(() => {
      expect(mockApiGet).toHaveBeenCalledWith('/v1/staff', expect.objectContaining({ page: 1, per_page: 100 }))
      expect(mockApiGet).toHaveBeenCalledWith('/v1/staff/assignable-roles', {})
    })

    expect(await screen.findByText('Emma Watson')).toBeInTheDocument()
    expect(screen.getByText('Staff Management')).toBeInTheDocument()
  })

  it('groups staff by role and switches between cards and list layouts', async () => {
    const user = userEvent.setup()

    render(
      <MemoryRouter>
        <StaffView />
      </MemoryRouter>,
    )

    expect(await screen.findByText('Emma Watson')).toBeInTheDocument()
    expect(screen.getByRole('heading', { name: 'Branch Manager' })).toBeInTheDocument()
    expect(screen.getByRole('heading', { name: 'Stylist' })).toBeInTheDocument()

    await user.click(screen.getByRole('button', { name: /list/i }))

    const tables = await screen.findAllByRole('table')
    expect(tables.length).toBeGreaterThanOrEqual(2)
    expect(screen.getByText('Emma Watson')).toBeInTheDocument()
    expect(screen.getByText('Riya Shah')).toBeInTheDocument()
    expect(screen.getByRole('button', { name: /list/i })).toHaveAttribute('aria-pressed', 'true')

    await user.click(screen.getByRole('button', { name: /cards/i }))
    expect(screen.getByRole('button', { name: /cards/i })).toHaveAttribute('aria-pressed', 'true')
    expect(screen.queryByRole('table')).not.toBeInTheDocument()
  })
})
