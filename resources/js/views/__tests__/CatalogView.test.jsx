import React from 'react'
import { render, screen, waitFor } from '@testing-library/react'
import { describe, expect, it, vi, beforeEach } from 'vitest'
import { MemoryRouter } from 'react-router-dom'

const mockAuthStore = {
  isSystemAdmin: false,
  can: vi.fn((code) => [
    'services.view',
    'services.create',
    'services.update',
    'services.delete',
    'products.view',
    'products.create',
    'products.update',
    'products.delete',
  ].includes(code)),
  canPlatform: vi.fn(() => false),
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

import CatalogView from '../CatalogView.jsx'

describe('CatalogView (tenant mode)', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    mockAuthStore.isSystemAdmin = false

    mockApiGet.mockImplementation((path) => {
      if (path === '/v1/services') {
        return Promise.resolve({
          data: { data: { services: [{ id: 1, name: 'Haircut', default_price: 500, duration_minutes: 45, is_active: true }] } },
        })
      }
      if (path === '/v1/products') {
        return Promise.resolve({
          data: { data: { products: [{ id: 1, name: 'Shampoo', is_active: true }] } },
        })
      }
      if (path === '/v1/catalog') {
        return Promise.resolve({
          data: {
            data: {
              catalog: [{
                id: 99,
                service_id: 1,
                product_id: 1,
                price: 650,
                duration_minutes: 45,
                is_active: true,
                service: { id: 1, name: 'Haircut' },
                product: { id: 1, name: 'Shampoo' },
              }],
            },
          },
        })
      }
      return Promise.resolve({ data: { data: {} } })
    })
  })

  it('shows salon offerings tab and loads catalog items for tenants', async () => {
    render(
      <MemoryRouter>
        <CatalogView />
      </MemoryRouter>,
    )

    await waitFor(() => {
      expect(mockApiGet).toHaveBeenCalledWith('/v1/catalog', expect.objectContaining({ page: 1, per_page: 100 }))
    })

    expect(screen.getByText('Haircut')).toBeInTheDocument()
    expect(screen.getByText('Shampoo')).toBeInTheDocument()
    expect(screen.getByText('Salon Offerings')).toBeInTheDocument()
    expect(screen.getByText('Salon Services')).toBeInTheDocument()
    expect(screen.getByText('Salon Products')).toBeInTheDocument()
  })

  it('does not fetch tenant catalog for system admins', async () => {
    mockAuthStore.isSystemAdmin = true

    render(
      <MemoryRouter>
        <CatalogView />
      </MemoryRouter>,
    )

    await waitFor(() => {
      expect(mockApiGet).toHaveBeenCalledWith('/v1/services', expect.any(Object))
    })

    expect(mockApiGet).not.toHaveBeenCalledWith('/v1/catalog', expect.any(Object))
    expect(screen.queryByText('Salon Offerings')).not.toBeInTheDocument()
  })

  it('derives salon service/product lists from catalog for tenants', async () => {
    mockApiGet.mockImplementation((path) => {
      if (path === '/v1/services') {
        return Promise.resolve({
          data: {
            data: {
              services: [
                { id: 1, name: 'Haircut', default_price: 500, duration_minutes: 45, is_active: true },
                { id: 2, name: 'Platform Only Service', default_price: 999, duration_minutes: 30, is_active: true },
              ],
            },
          },
        })
      }
      if (path === '/v1/products') {
        return Promise.resolve({
          data: {
            data: {
              products: [
                { id: 1, name: 'Shampoo', is_active: true },
                { id: 2, name: 'Platform Only Product', is_active: true },
              ],
            },
          },
        })
      }
      if (path === '/v1/catalog') {
        return Promise.resolve({
          data: {
            data: {
              catalog: [{
                id: 99,
                service_id: 1,
                product_id: 1,
                price: 650,
                duration_minutes: 45,
                is_active: true,
                service: { id: 1, name: 'Haircut' },
                product: { id: 1, name: 'Shampoo' },
              }],
            },
          },
        })
      }
      if (path === '/v1/branches') {
        return Promise.resolve({ data: { data: { branches: [] } } })
      }
      return Promise.resolve({ data: { data: {} } })
    })

    const { userEvent } = await import('@testing-library/user-event')
    const user = userEvent.setup()

    render(
      <MemoryRouter>
        <CatalogView />
      </MemoryRouter>,
    )

    await waitFor(() => {
      expect(screen.getByText('Salon Offerings')).toBeInTheDocument()
    })

    await user.click(screen.getByText('Salon Services'))
    await waitFor(() => {
      expect(screen.getByText('Haircut')).toBeInTheDocument()
    })
    expect(screen.queryByText('Platform Only Service')).not.toBeInTheDocument()

    await user.click(screen.getByText('Salon Products'))
    await waitFor(() => {
      expect(screen.getByText('Shampoo')).toBeInTheDocument()
    })
    expect(screen.queryByText('Platform Only Product')).not.toBeInTheDocument()
  })
})
