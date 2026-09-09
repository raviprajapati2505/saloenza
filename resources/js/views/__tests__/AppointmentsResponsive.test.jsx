import React from 'react'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { waitFor } from '@testing-library/react'
import { renderWithProviders, findByClass, makeAuthMock } from '../../test-utils/responsive.jsx'

const mockAuth = makeAuthMock()

vi.mock('../../stores/auth', () => ({
  useAuthStore: () => mockAuth,
}))

const appointmentState = {
  viewMode: 'calendar',
  selectedEvent: {
    id: 1,
    title: 'Haircut',
    start: new Date(),
    end: new Date(),
    extendedProps: {
      status: 'scheduled',
      customer: 'Aditi',
      customerPhone: '+919999999999',
      staffName: 'Stylist',
      branchName: 'Andheri',
      grandTotal: 500,
      service: 'Haircut',
    },
  },
  isBookingModalOpen: false,
  filters: {},
  setViewMode: vi.fn(),
  setSelectedEvent: vi.fn(),
  openBookingModal: vi.fn(),
  closeBookingModal: vi.fn(),
  closeDetail: vi.fn(),
  openEditModal: vi.fn(),
  openCreateModal: vi.fn(),
  openWalkInModal: vi.fn(),
}

vi.mock('../../stores/appointmentStore', () => ({
  useAppointmentStore: (selector) => (
    typeof selector === 'function' ? selector(appointmentState) : appointmentState
  ),
}))

vi.mock('../../lib/apiHelpers', () => ({
  apiGet: vi.fn(async () => ({
    data: {
      data: {
        appointments: [{
          id: 1,
          status: 'scheduled',
          type: 'appointment',
          starts_at: new Date().toISOString(),
          grand_total: 500,
          customer: { name: 'Aditi', phone: '+919999999999' },
          service: { name: 'Haircut' },
          staff: { name: 'Stylist' },
          branch: { name: 'Andheri' },
        }],
        branches: [],
        staff: [],
        services: [],
      },
    },
  })),
  apiPut: vi.fn(async () => ({ data: {} })),
  apiDelete: vi.fn(async () => ({ data: {} })),
  fetchMasterList: vi.fn(async () => ([{
    id: 1,
    status: 'scheduled',
    type: 'appointment',
    starts_at: new Date().toISOString(),
    grand_total: 500,
    customer: { name: 'Aditi', phone: '+919999999999' },
    service: { name: 'Haircut' },
    staff: { name: 'Stylist' },
    branch: { name: 'Andheri' },
  }])),
  parseList: vi.fn(() => []),
}))

vi.mock('../../services/branchService.js', () => ({
  fetchBranches: vi.fn(async () => ({ branches: [] })),
}))

vi.mock('@fullcalendar/react', () => ({
  default: () => <div data-testid="fullcalendar-stub">calendar</div>,
}))

vi.mock('@fullcalendar/daygrid', () => ({ default: {} }))
vi.mock('@fullcalendar/timegrid', () => ({ default: {} }))
vi.mock('@fullcalendar/interaction', () => ({ default: {} }))
vi.mock('@fullcalendar/list', () => ({ default: {} }))

vi.mock('../../hooks/useCalendar.jsx', () => ({
  useCalendar: () => ({
    calendarRef: { current: null },
    calendarProps: {},
    mountInitialDate: new Date(),
    events: [],
    currentDate: new Date(),
    currentView: 'timeGridDay',
    isLoading: false,
    isFetching: false,
    isError: false,
    error: null,
    goPrev: vi.fn(),
    goNext: vi.fn(),
    goToday: vi.fn(),
    changeView: vi.fn(),
  }),
}))

vi.mock('../../components/appointments/BookingModal.jsx', () => ({
  default: () => null,
}))

import AppointmentsView from '../AppointmentsView.jsx'
import AppointmentDetail from '../../components/appointments/AppointmentDetail.jsx'
import BaseModal from '../../components/ui/BaseModal.jsx'

describe('Appointments screens responsive contracts', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    appointmentState.viewMode = 'calendar'
    appointmentState.selectedEvent = {
      id: 1,
      title: 'Haircut',
      start: new Date(),
      end: new Date(),
      extendedProps: {
        status: 'scheduled',
        customer: 'Aditi',
        customerPhone: '+919999999999',
        staffName: 'Stylist',
        branchName: 'Andheri',
        grandTotal: 500,
        service: 'Haircut',
      },
    }
  })

  it('AppointmentsView: calendar uses a tall full-width panel', () => {
    const { container } = renderWithProviders(<AppointmentsView />, { route: '/appointments' })

    expect(
      findByClass(container, 'appointments-calendar')
      || findByClass(container, 'h-[calc(100dvh'),
    ).toBeTruthy()
  })

  it('AppointmentsView list mode wraps tables in overflow-x-auto for phone scroll', async () => {
    appointmentState.viewMode = 'list'
    const { container } = renderWithProviders(<AppointmentsView />, { route: '/appointments' })
    await waitFor(() => {
      expect(findByClass(container, 'overflow-x-auto')).toBeTruthy()
    })
  })

  it('AppointmentDetail: mobile bottom sheet + desktop overlay drawer both exist', () => {
    const { container } = renderWithProviders(<AppointmentDetail />)

    const mobileSheet = Array.from(container.querySelectorAll('div')).find(
      (el) => typeof el.className === 'string'
        && el.className.includes('xl:hidden')
        && el.className.includes('fixed')
        && el.className.includes('bottom-0'),
    )
    expect(mobileSheet).toBeTruthy()

    const desktopPanel = Array.from(container.querySelectorAll('div')).find(
      (el) => typeof el.className === 'string'
        && el.className.includes('hidden')
        && el.className.includes('xl:flex')
        && el.className.includes('fixed')
        && el.className.includes('right-0'),
    )
    expect(desktopPanel).toBeTruthy()
  })

  it('BaseModal: mobile bottom-sheet chrome (items-end, rounded-t-3xl) with desktop centered override', () => {
    renderWithProviders(
      <BaseModal open onClose={() => {}} title="Book" size="2xl" footer={<div>Footer</div>}>
        <div>Body</div>
      </BaseModal>,
    )

    // Headless UI Dialog portals to document.body
    const root = document.body
    expect(findByClass(root, 'items-end') || findByClass(root, 'sm:items-center')).toBeTruthy()
    expect(findByClass(root, 'rounded-t-3xl') || findByClass(root, 'sm:rounded-2xl')).toBeTruthy()
    expect(findByClass(root, 'sm:hidden') || findByClass(root, 'sm:items-center')).toBeTruthy()
  })
})
