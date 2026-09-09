import { create } from 'zustand'

const emptyFilters = {
  saloon_id: '',
  branch_id: '',
  status: '',
  type: '',
  staff_id: '',
  customer_id: '',
  search: '',
  from: '',
  to: '',
}

export const useAppointmentStore = create((set) => ({
  selectedEvent: null,
  selectedSlot: null,
  selectedBranchId: '',
  isBookingModalOpen: false,
  editingAppointment: null,
  bookingType: 'appointment', // 'appointment' | 'walk_in'
  bookingSource: 'appointments', // 'appointments' | 'pos'
  viewMode: 'calendar',
  calendarView: 'timeGridDay',
  calendarDate: null,
  filters: { ...emptyFilters },

  setSelectedEvent: (event) => set({ selectedEvent: event }),
  setSelectedSlot: (slot) => set({ selectedSlot: slot }),
  setSelectedBranchId: (branchId) => set({ selectedBranchId: branchId }),
  setIsBookingModalOpen: (isOpen) => set({ isBookingModalOpen: isOpen }),
  setViewMode: (viewMode) => set({ viewMode }),
  setCalendarView: (calendarView) => set({ calendarView }),
  setCalendarDate: (calendarDate) => set({ calendarDate }),
  setFilters: (filters) => set((state) => ({ filters: { ...state.filters, ...filters } })),
  resetFilters: () => set({ filters: { ...emptyFilters } }),

  openBookingModalWithSlot: (slot, options = {}) => set({
    selectedSlot: slot,
    editingAppointment: null,
    bookingType: options.type || 'appointment',
    bookingSource: options.source || 'appointments',
    isBookingModalOpen: true,
  }),

  openCreateModal: (options = {}) => set({
    selectedSlot: null,
    editingAppointment: null,
    bookingType: options.type || 'appointment',
    bookingSource: options.source || 'appointments',
    isBookingModalOpen: true,
  }),

  openWalkInModal: () => set({
    selectedSlot: null,
    editingAppointment: null,
    bookingType: 'walk_in',
    bookingSource: 'pos',
    isBookingModalOpen: true,
  }),

  openEditModal: (appointment) => set({
    editingAppointment: appointment,
    selectedSlot: null,
    bookingType: appointment?.type || 'appointment',
    bookingSource: appointment?.type === 'walk_in' ? 'pos' : 'appointments',
    isBookingModalOpen: true,
  }),

  closeBookingModal: () => set({
    isBookingModalOpen: false,
    editingAppointment: null,
    selectedSlot: null,
  }),

  clearSelection: () => set({
    selectedEvent: null,
    selectedSlot: null,
  }),
}))
