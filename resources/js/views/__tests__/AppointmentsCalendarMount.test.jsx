import React from 'react'
import { describe, expect, it, vi, beforeEach } from 'vitest'
import { render, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { MemoryRouter } from 'react-router-dom'
import AppointmentsView from '../AppointmentsView.jsx'
import { makeAuthMock } from '../../test-utils/responsive.jsx'

const mockAuth = makeAuthMock()

vi.mock('../../stores/auth', () => ({
  useAuthStore: () => mockAuth,
}))

vi.mock('../../lib/apiHelpers', () => ({
  apiGet: vi.fn(async () => ({
    data: {
      data: {
        branches: [{ id: 1, name: 'Main' }],
        staff: [{ id: 1, name: 'Stylist' }],
        services: [],
      },
    },
  })),
  apiPut: vi.fn(async () => ({ data: {} })),
  apiDelete: vi.fn(async () => ({ data: {} })),
  fetchMasterList: vi.fn(async () => []),
  parseList: vi.fn(() => []),
}))

vi.mock('../../components/appointments/BookingModal.jsx', () => ({
  default: () => null,
}))

function renderAppointments() {
  const queryClient = new QueryClient({
    defaultOptions: { queries: { retry: false } },
  })
  return render(
    <QueryClientProvider client={queryClient}>
      <MemoryRouter initialEntries={['/appointments']}>
        <AppointmentsView />
      </MemoryRouter>
    </QueryClientProvider>,
  )
}

describe('AppointmentsView calendar mount', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  it('renders without crashing when FullCalendar is real', async () => {
    const errorSpy = vi.spyOn(console, 'error').mockImplementation(() => {})
    const { getByText } = renderAppointments()

    await waitFor(() => {
      expect(getByText('Appointments')).toBeTruthy()
    }, { timeout: 5000 })

    errorSpy.mockRestore()
  })
})
