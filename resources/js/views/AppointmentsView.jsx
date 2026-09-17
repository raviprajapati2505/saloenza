import React, { useEffect, useState } from 'react'
import FullCalendar from '@fullcalendar/react'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { motion, AnimatePresence } from 'framer-motion'
import {
  Search, Plus, ChevronLeft, ChevronRight, Inbox, CalendarDays, List as ListIcon,
  Edit2, Trash2, Package, Filter, X, ShoppingBag, FileDown, Loader2,
} from 'lucide-react'
import { format, startOfWeek, endOfWeek } from 'date-fns'
import { useCalendar } from '../hooks/useCalendar.jsx'
import { useAppointmentStore } from '../stores/appointmentStore'
import { useAuthStore } from '../stores/auth'
import { apiGet, apiDelete, fetchMasterList, parseList } from '../lib/apiHelpers'
import { customerPhoneDisplay } from '../lib/customerContact.js'
import PageHeader from '../components/ui/PageHeader.jsx'
import BaseButton from '../components/ui/BaseButton.jsx'
import BaseSelect from '../components/ui/BaseSelect.jsx'
import BaseBadge from '../components/ui/BaseBadge.jsx'
import AppointmentDetail from '../components/appointments/AppointmentDetail.jsx'
import BookingModal from '../components/appointments/BookingModal.jsx'
import { subscriptionPageSubtitle } from '../lib/subscriptionModules.js'
import { formatMoney } from '../lib/tenantFormatting.js'
import SubscriptionAccessFallback from '../components/subscription/SubscriptionAccessFallback.jsx'
import { isForbiddenError } from '../lib/apiErrors.js'
import { downloadAppointmentReceipt } from '../services/appointmentService.js'
import { pushToast } from '../stores/toast.js'

const STATUS_OPTIONS = [
  { value: '', label: 'All statuses' },
  { value: 'scheduled', label: 'Scheduled' },
  { value: 'confirmed', label: 'Confirmed' },
  { value: 'in-progress', label: 'In Progress' },
  { value: 'completed', label: 'Completed' },
  { value: 'cancelled', label: 'Cancelled' },
  { value: 'no-show', label: 'No Show' },
]

const TYPE_OPTIONS = [
  { value: '', label: 'All types' },
  { value: 'appointment', label: 'Appointments' },
  { value: 'walk_in', label: 'Walk-ins' },
  { value: 'product_sale', label: 'Product sales' },
]

const STATUS_BADGE = {
  scheduled: 'info',
  confirmed: 'success',
  'in-progress': 'info',
  completed: 'default',
  cancelled: 'danger',
  'no-show': 'danger',
}

function FiltersBar({ branches, staff, saloons, isPlatformAdmin, lockBranch, showDateRange = true, compact = false }) {
  const filters = useAppointmentStore(state => state.filters)
  const setFilters = useAppointmentStore(state => state.setFilters)
  const resetFilters = useAppointmentStore(state => state.resetFilters)
  const [expanded, setExpanded] = React.useState(false)

  const activeFilterCount = React.useMemo(() => {
    let count = 0
    if (filters.search) count++
    if (filters.saloon_id) count++
    if (filters.branch_id) count++
    if (filters.status) count++
    if (filters.type) count++
    if (filters.staff_id) count++
    if (showDateRange && filters.from) count++
    if (showDateRange && filters.to) count++
    return count
  }, [filters, showDateRange])

  const hasActiveFilters = activeFilterCount > 0

  const salonOptions = [
    { value: '', label: 'All salons' },
    ...saloons.map(s => ({
      value: String(s.id),
      label: s.business_name || s.name || `Salon #${s.id}`,
    })),
  ]

  return (
    <div className={`bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden transition-all ${compact ? '' : 'mb-3 sm:mb-4'}`}>
      {/* Top Toggle Bar */}
      <div className="flex items-center justify-between p-2.5 sm:px-4 sm:py-3">
        <button
          type="button"
          onClick={() => setExpanded(v => !v)}
          className="flex items-center gap-2.5 text-sm font-semibold text-slate-700 hover:text-brand-600 transition-colors"
        >
          <div className={`flex h-8 w-8 items-center justify-center rounded-xl transition-colors ${expanded || hasActiveFilters ? 'bg-brand-500 text-white' : 'bg-slate-100 text-slate-500'}`}>
            <Filter className="w-4 h-4" />
          </div>
          <span>Search & Filters</span>
          {hasActiveFilters && (
            <span className="inline-flex items-center justify-center h-5 px-2 rounded-full bg-brand-100 text-brand-700 text-xs font-bold">
              {activeFilterCount} active
            </span>
          )}
          <ChevronRight className={`w-4 h-4 text-slate-400 transition-transform duration-200 ${expanded ? 'rotate-90' : ''}`} />
        </button>

        <div className="flex items-center gap-2">
          {hasActiveFilters && (
            <button
              type="button"
              onClick={resetFilters}
              className="inline-flex items-center gap-1 px-3 py-1.5 rounded-xl border border-rose-200 bg-rose-50 text-xs font-bold text-rose-600 hover:bg-rose-100 transition-colors"
            >
              <X className="w-3.5 h-3.5" /> Clear filters
            </button>
          )}
        </div>
      </div>

      {/* Collapsible Panel */}
      <AnimatePresence>
        {expanded && (
          <motion.div
            initial={{ height: 0, opacity: 0 }}
            animate={{ height: 'auto', opacity: 1 }}
            exit={{ height: 0, opacity: 0 }}
            transition={{ duration: 0.2 }}
            className="border-t border-slate-100 p-3 sm:p-4 bg-slate-50/50 overflow-hidden"
          >
            <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-3 items-end">
              <div className="relative flex-1 min-w-0 sm:col-span-2 lg:col-span-1">
                <label className="block text-xs font-semibold text-slate-700 mb-1">Search</label>
                <div className="relative">
                  <Search className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400 pointer-events-none" />
                  <input
                    type="text"
                    value={filters.search}
                    onChange={e => setFilters({ search: e.target.value })}
                    placeholder="Customer name or phone..."
                    className="w-full h-[42px] pl-9 pr-3 text-sm border border-slate-200 rounded-xl bg-white shadow-sm focus:outline-none focus:ring-2 focus:ring-brand-500/20 focus:border-brand-400"
                  />
                </div>
              </div>

              {isPlatformAdmin && (
                <BaseSelect
                  label="Salon"
                  className="w-full"
                  value={filters.saloon_id}
                  onChange={e => setFilters({ saloon_id: e.target.value, branch_id: '' })}
                  options={salonOptions}
                />
              )}

              {showDateRange && (
                <>
                  <div className="w-full">
                    <label className="block text-xs font-semibold text-slate-700 mb-1">From</label>
                    <input
                      type="date"
                      value={filters.from}
                      onChange={e => setFilters({ from: e.target.value })}
                      className="w-full h-[42px] px-3 text-sm border border-slate-200 rounded-xl bg-white shadow-sm focus:outline-none focus:ring-2 focus:ring-brand-500/20 focus:border-brand-400"
                    />
                  </div>

                  <div className="w-full">
                    <label className="block text-xs font-semibold text-slate-700 mb-1">To</label>
                    <input
                      type="date"
                      value={filters.to}
                      onChange={e => setFilters({ to: e.target.value })}
                      className="w-full h-[42px] px-3 text-sm border border-slate-200 rounded-xl bg-white shadow-sm focus:outline-none focus:ring-2 focus:ring-brand-500/20 focus:border-brand-400"
                    />
                  </div>
                </>
              )}

              {!lockBranch && (
                <BaseSelect
                  label="Branch"
                  className="w-full"
                  value={filters.branch_id}
                  onChange={e => setFilters({ branch_id: e.target.value })}
                  options={[{ value: '', label: 'All branches' }, ...branches.map(b => ({ value: String(b.id), label: b.name }))]}
                />
              )}

              <BaseSelect
                label="Staff"
                className="w-full"
                value={filters.staff_id}
                onChange={e => setFilters({ staff_id: e.target.value })}
                options={[{ value: '', label: 'All staff' }, ...staff.map(s => ({ value: String(s.id), label: s.name }))]}
              />

              <BaseSelect
                label="Type"
                className="w-full"
                value={filters.type}
                onChange={e => setFilters({ type: e.target.value })}
                options={TYPE_OPTIONS}
              />

              <BaseSelect
                label="Status"
                className="w-full"
                value={filters.status}
                onChange={e => setFilters({ status: e.target.value })}
                options={STATUS_OPTIONS}
              />
            </div>
          </motion.div>
        )}
      </AnimatePresence>
    </div>
  )
}

function CalendarPanel({ canCreate, canUpdate }) {
  const auth = useAuthStore()
  const {
    calendarRef,
    calendarProps,
    mountInitialDate,
    events,
    currentDate,
    currentView,
    isLoading,
    isFetching,
    isError,
    error,
    goPrev,
    goNext,
    goToday,
    changeView,
  } = useCalendar({ canCreate, canUpdate })
  const selectedEvent = useAppointmentStore(state => state.selectedEvent)

  const getFormattedDateString = () => {
    if (!currentDate) return ''
    if (currentView === 'timeGridDay') return format(currentDate, 'EEEE, d MMMM yyyy')
    if (currentView === 'timeGridWeek') {
      const weekStart = startOfWeek(currentDate, { weekStartsOn: 1 })
      const weekEnd = endOfWeek(currentDate, { weekStartsOn: 1 })
      return `${format(weekStart, 'd MMM')} – ${format(weekEnd, 'd MMM yyyy')}`
    }
    return format(currentDate, 'MMMM yyyy')
  }

  const isMonthView = currentView === 'dayGridMonth'
  // Explicit viewport height — flex-only chains were collapsing FullCalendar to a single line.
  const calendarShellClass = isMonthView
    ? 'min-h-[40rem] h-auto'
    : 'h-[calc(100dvh-12.5rem)] min-h-[36rem] max-md:h-[calc(100dvh-15rem)]'

  if (isError && isForbiddenError(error)) {
    const mode = error?.response?.data?.subscription_access_mode
    return (
      <SubscriptionAccessFallback
        mode={mode === 'locked' ? 'locked' : mode === 'read_only' ? 'read_only' : 'error'}
        message={error?.response?.data?.message}
        showRenew={auth.can('settings.view')}
      />
    )
  }

  return (
    <div className="flex w-full flex-col gap-2">
      <div className="flex shrink-0 flex-col gap-2 bg-white p-2 sm:flex-row sm:items-center sm:justify-between sm:p-2.5 rounded-2xl border border-slate-200 shadow-sm">
        <div className="flex items-center bg-slate-50 border border-slate-200 rounded-xl p-1 w-fit">
          <button type="button" onClick={goPrev} className="p-2 rounded-lg hover:bg-white hover:shadow-sm text-slate-600 transition-all touch-manipulation" aria-label="Previous">
            <ChevronLeft className="w-4 h-4" />
          </button>
          <button type="button" onClick={goToday} className="px-3 py-1.5 text-sm font-semibold hover:bg-white hover:shadow-sm text-slate-700 rounded-lg transition-all touch-manipulation">
            Today
          </button>
          <button type="button" onClick={goNext} className="p-2 rounded-lg hover:bg-white hover:shadow-sm text-slate-600 transition-all touch-manipulation" aria-label="Next">
            <ChevronRight className="w-4 h-4" />
          </button>
        </div>
        <motion.h2 key={currentDate?.toString()} initial={{ opacity: 0, y: -5 }} animate={{ opacity: 1, y: 0 }} className="text-sm sm:text-base font-bold text-slate-900 tracking-tight text-center">
          {getFormattedDateString()}
        </motion.h2>
        <BaseSelect
          className="w-full sm:w-36"
          value={currentView}
          onChange={e => changeView(e.target.value)}
          options={[
            { value: 'timeGridDay', label: 'Day View' },
            { value: 'timeGridWeek', label: 'Week View' },
            { value: 'dayGridMonth', label: 'Month View' },
          ]}
        />
      </div>

      <div className={`relative w-full ${calendarShellClass}`}>
        <div className="bg-white rounded-2xl border border-slate-200 shadow-sm flex flex-col relative w-full h-full overflow-hidden">
          {isLoading && (
            <div className="absolute inset-0 z-10 flex items-center justify-center bg-white/70 backdrop-blur-[1px]">
              <div className="h-8 w-8 animate-spin rounded-full border-2 border-brand-600 border-t-transparent" />
            </div>
          )}
          {isFetching && !isLoading && (
            <div className="absolute right-3 top-3 z-10 rounded-full bg-brand-50 px-2.5 py-1 text-[10px] font-bold uppercase tracking-wider text-brand-700">
              Updating…
            </div>
          )}
          <div className={`h-full w-full p-1.5 sm:p-2 ${isMonthView ? 'overflow-y-auto' : 'overflow-hidden'}`}>
            <div
              className={`appointments-calendar ${isMonthView ? 'appointments-calendar--month' : 'h-full w-full'}`}
              data-view={currentView}
            >
              <FullCalendar
                key={currentView}
                ref={calendarRef}
                initialDate={mountInitialDate}
                {...calendarProps}
                events={events}
              />
            </div>
          </div>
        </div>

        <AnimatePresence>
          {selectedEvent && <AppointmentDetail />}
        </AnimatePresence>
      </div>
    </div>
  )
}

function ListPanel({ canUpdate, canDelete }) {
  const queryClient = useQueryClient()
  const auth = useAuthStore()
  const filters = useAppointmentStore(state => state.filters)
  const openEditModal = useAppointmentStore(state => state.openEditModal)

  const [deleteId, setDeleteId] = React.useState(null)
  const [downloadingReceiptId, setDownloadingReceiptId] = React.useState(null)

  const listFilters = {
    ...(filters.saloon_id ? { saloon_id: Number(filters.saloon_id) } : {}),
    ...(filters.branch_id ? { branch_id: Number(filters.branch_id) } : {}),
    ...(filters.status ? { status: filters.status } : {}),
    ...(filters.type ? { type: filters.type } : {}),
    ...(filters.staff_id ? { staff_id: Number(filters.staff_id) } : {}),
    ...(filters.search ? { search: filters.search } : {}),
    ...(filters.from ? { from: filters.from } : {}),
    ...(filters.to ? { to: filters.to } : {}),
  }

  const { data: appointments = [], isLoading, isError, error } = useQuery({
    queryKey: ['appointments-list', listFilters],
    queryFn: async () => fetchMasterList('/v1/appointments', 'appointments', listFilters),
  })

  const deleteMutation = useMutation({
    mutationFn: async (id) => apiDelete(`/v1/appointments/${id}`),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['appointments-list'] })
      queryClient.invalidateQueries({ queryKey: ['calendar-events'] })
      setDeleteId(null)
    },
  })

  const handleDownloadReceipt = async (appointmentId) => {
    if (!appointmentId || downloadingReceiptId) return
    setDownloadingReceiptId(appointmentId)
    try {
      await downloadAppointmentReceipt(appointmentId)
      pushToast('Receipt downloaded.', 'success')
    } catch (err) {
      pushToast(err?.response?.data?.message || 'Unable to download receipt.', 'error')
    } finally {
      setDownloadingReceiptId(null)
    }
  }

  if (isError && isForbiddenError(error)) {
    const mode = error?.response?.data?.subscription_access_mode
    return (
      <SubscriptionAccessFallback
        mode={mode === 'locked' ? 'locked' : mode === 'read_only' ? 'read_only' : 'error'}
        message={error?.response?.data?.message}
        showRenew={auth.can('settings.view')}
      />
    )
  }

  const money = (v) => (v != null ? formatMoney(v, auth) : '—')
  const dateTime = (iso) => {
    if (!iso) return '—'
    const d = new Date(iso)
    return `${d.toLocaleDateString('en-IN', { day: 'numeric', month: 'short' })}, ${d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}`
  }

  const serviceLabel = (appt) => {
    const names = appt.services?.length
      ? appt.services.map(s => s.service?.name).filter(Boolean)
      : (appt.service?.name ? [appt.service.name] : [])
    const products = (appt.products ?? []).map(p => p.product?.name).filter(Boolean)
    return names.length ? names : products
  }

  const staffLabel = (appt) => {
    if (appt.services?.length) {
      const names = [...new Set(appt.services.map(s => s.staff?.name).filter(Boolean))]
      return names.join(', ') || appt.staff?.name || '—'
    }
    return appt.staff?.name || '—'
  }

  if (isLoading) return <div className="flex justify-center py-20 text-slate-400">Loading appointments...</div>

  if (appointments.length === 0) {
    return (
      <div className="flex flex-col items-center justify-center py-16 text-slate-400 border border-dashed border-slate-200 rounded-2xl bg-slate-50/50">
        <Inbox className="w-10 h-10 mb-3 text-slate-300" />
        <p className="font-bold text-slate-500">No appointments found</p>
        <p className="text-sm">Try adjusting your filters{canUpdate ? ', or create a new appointment.' : '.'}</p>
      </div>
    )
  }

  return (
    <div className="bg-white border border-slate-200 rounded-2xl shadow-sm overflow-hidden">
      <div className="overflow-x-auto">
        <table className="w-full text-sm">
          <thead>
            <tr className="bg-slate-50 border-b border-slate-200 text-left text-[11px] font-bold uppercase tracking-wider text-slate-500">
              <th className="px-4 py-3">Customer</th>
              <th className="px-4 py-3">Type</th>
              <th className="px-4 py-3">Service</th>
              <th className="px-4 py-3">Staff</th>
              <th className="px-4 py-3">Branch</th>
              <th className="px-4 py-3">When</th>
              <th className="px-4 py-3">Status</th>
              <th className="px-4 py-3 text-right">Total</th>
              <th className="px-4 py-3 text-right">Actions</th>
            </tr>
          </thead>
          <tbody className="divide-y divide-slate-100">
            {appointments.map(appt => (
              <tr key={appt.id} className="hover:bg-slate-50/60 transition-colors">
                <td className="px-4 py-3">
                  <div className="font-semibold text-slate-800">{appt.customer?.name || 'Walk-in'}</div>
                  {(() => {
                    const phone = customerPhoneDisplay(appt.customer)
                    return phone ? <div className="text-xs text-slate-400">{phone}</div> : null
                  })()}
                  {appt.saloon?.name && <div className="text-xs text-slate-400 mt-0.5">{appt.saloon.name}</div>}
                </td>
                <td className="px-4 py-3">
                  <BaseBadge variant={appt.type === 'walk_in' ? 'info' : appt.type === 'product_sale' ? 'success' : 'default'}>
                    {appt.type === 'walk_in' ? 'Walk-in' : appt.type === 'product_sale' ? 'Product' : 'Appt'}
                  </BaseBadge>
                </td>
                <td className="px-4 py-3">
                  <div className="flex flex-wrap gap-1 max-w-[220px]">
                    {(serviceLabel(appt).length ? serviceLabel(appt) : ['—']).map((name, idx) => (
                      <span
                        key={`${appt.id}-${name}-${idx}`}
                        className="inline-flex max-w-full items-center rounded-full bg-slate-100 px-2 py-0.5 text-[11px] font-semibold text-slate-700"
                        title={name}
                      >
                        <span className="truncate">{name}</span>
                      </span>
                    ))}
                  </div>
                  {appt.product?.name && !appt.services?.length && (
                    <div className="text-xs text-slate-400 flex items-center gap-1 mt-1">
                      <Package className="w-3 h-3" /> {appt.product.name}
                    </div>
                  )}
                </td>
                <td className="px-4 py-3 text-slate-600 max-w-[140px] truncate" title={staffLabel(appt)}>{staffLabel(appt)}</td>
                <td className="px-4 py-3 text-slate-600">{appt.branch?.name || appt.branch?.branch_name || '—'}</td>
                <td className="px-4 py-3 text-slate-600 whitespace-nowrap">{dateTime(appt.starts_at)}</td>
                <td className="px-4 py-3">
                  <BaseBadge variant={STATUS_BADGE[appt.status] || 'default'}>{appt.status?.replace('-', ' ')}</BaseBadge>
                  {(appt.payment_status === 'unpaid' || appt.payment_status === 'partial') && (
                    <div className="mt-1">
                      <BaseBadge variant="warning">{appt.payment_status === 'partial' ? 'Partial' : 'Pending payment'}</BaseBadge>
                    </div>
                  )}
                </td>
                <td className="px-4 py-3 text-right font-semibold text-slate-800 whitespace-nowrap">{money(appt.grand_total ?? appt.price)}</td>
                <td className="px-4 py-3">
                  <div className="flex items-center justify-end gap-2">
                    <button
                      type="button"
                      onClick={() => handleDownloadReceipt(appt.id)}
                      className="p-1.5 rounded-lg text-slate-400 hover:text-brand-600 hover:bg-brand-50 transition-colors disabled:opacity-50"
                      title="Download Receipt"
                      disabled={downloadingReceiptId === appt.id}
                    >
                      {downloadingReceiptId === appt.id
                        ? <Loader2 className="w-4 h-4 animate-spin" />
                        : <FileDown className="w-4 h-4" />}
                    </button>
                    {canUpdate && (
                      <button onClick={() => openEditModal(appt)} className="p-1.5 rounded-lg text-slate-400 hover:text-brand-600 hover:bg-brand-50 transition-colors" title="Edit">
                        <Edit2 className="w-4 h-4" />
                      </button>
                    )}
                    {canDelete && (
                      deleteId === appt.id ? (
                        <button onClick={() => deleteMutation.mutate(appt.id)} className="px-2 py-1 rounded-lg text-xs font-bold text-white bg-rose-600 hover:bg-rose-700" disabled={deleteMutation.isPending}>Confirm</button>
                      ) : (
                        <button onClick={() => setDeleteId(appt.id)} className="p-1.5 rounded-lg text-slate-400 hover:text-rose-600 hover:bg-rose-50 transition-colors" title="Delete">
                          <Trash2 className="w-4 h-4" />
                        </button>
                      )
                    )}
                  </div>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    </div>
  )
}

export default function AppointmentsView() {
  const viewMode = useAppointmentStore(state => state.viewMode)
  const setViewMode = useAppointmentStore(state => state.setViewMode)
  const openCreateModal = useAppointmentStore(state => state.openCreateModal)
  const openWalkInModal = useAppointmentStore(state => state.openWalkInModal)
  const filters = useAppointmentStore(state => state.filters)
  const auth = useAuthStore()

  const isPlatformAdmin = auth.grantsAllPermissions
  const canCreate = auth.can('appointments.create') && !isPlatformAdmin
  const canUpdate = auth.can('appointments.update') && !isPlatformAdmin
  const canDelete = auth.can('appointments.delete') && !isPlatformAdmin
  const lockBranch = auth.role?.scope === 'branch' && Boolean(auth.user?.branch_id)

  // Super admin: list-only view
  React.useEffect(() => {
    if (isPlatformAdmin && viewMode !== 'list') {
      setViewMode('list')
    }
  }, [isPlatformAdmin, viewMode, setViewMode])

  const bookingOptionsParams = {
    ...(filters.saloon_id ? { saloon_id: Number(filters.saloon_id) } : {}),
  }

  const { data: options } = useQuery({
    queryKey: ['appointment-booking-options', bookingOptionsParams],
    queryFn: async () => {
      const response = await apiGet('/v1/appointments/booking-options', bookingOptionsParams)
      return response?.data?.data ?? { branches: [], staff: [], services: [] }
    },
  })

  const { data: saloons = [] } = useQuery({
    queryKey: ['saloons-for-appointment-filter'],
    queryFn: async () => fetchMasterList('/v1/saloons', 'saloons'),
    enabled: isPlatformAdmin,
  })

  const branches = options?.branches ?? []
  const staff = options?.staff ?? []

  return (
    <div className="flex w-full flex-col relative">
      <div className={`flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 shrink-0 ${viewMode === 'calendar' ? 'mb-1.5' : 'mb-2 sm:mb-3'}`}>
        <PageHeader
          compact={viewMode === 'calendar' && !isPlatformAdmin}
          title="Appointments"
          subtitle={isPlatformAdmin
            ? 'Read-only overview across salons. Filter by salon, branch, staff and dates.'
            : subscriptionPageSubtitle(auth, 'Schedule, track and manage your salon appointments.')}
        />
        <div className="flex items-center gap-2 sm:gap-3 flex-wrap">
          {!isPlatformAdmin && (
            <div className="flex items-center bg-slate-100 rounded-xl p-1">
              <button
                type="button"
                onClick={() => setViewMode('calendar')}
                className={`flex items-center gap-1.5 px-3 py-2 rounded-lg text-sm font-semibold transition-all touch-manipulation ${viewMode === 'calendar' ? 'bg-white text-brand-600 shadow-sm' : 'text-slate-500 hover:text-slate-700'}`}
              >
                <CalendarDays className="w-4 h-4" /> Calendar
              </button>
              <button
                type="button"
                onClick={() => setViewMode('list')}
                className={`flex items-center gap-1.5 px-3 py-2 rounded-lg text-sm font-semibold transition-all touch-manipulation ${viewMode === 'list' ? 'bg-white text-brand-600 shadow-sm' : 'text-slate-500 hover:text-slate-700'}`}
              >
                <ListIcon className="w-4 h-4" /> List
              </button>
            </div>
          )}
          {canCreate && (
            <>
              <BaseButton
                variant="secondary"
                leftIcon={ShoppingBag}
                onClick={openWalkInModal}
              >
                POS
              </BaseButton>
              <BaseButton leftIcon={Plus} onClick={() => openCreateModal({ type: 'appointment', source: 'appointments' })}>
                New
              </BaseButton>
            </>
          )}
        </div>
      </div>

      <div className={`shrink-0 ${viewMode === 'calendar' ? 'mb-1.5' : 'mb-2 sm:mb-3'}`}>
        <FiltersBar
          branches={branches}
          staff={staff}
          saloons={saloons}
          isPlatformAdmin={isPlatformAdmin}
          lockBranch={lockBranch}
          showDateRange={viewMode === 'list' || isPlatformAdmin}
          compact={viewMode === 'calendar' && !isPlatformAdmin}
        />
      </div>

      {!isPlatformAdmin && (
        <div className={viewMode === 'calendar' ? 'w-full' : 'hidden'} aria-hidden={viewMode !== 'calendar'}>
          <CalendarPanel canCreate={canCreate} canUpdate={canUpdate} />
        </div>
      )}

      <div className={viewMode === 'list' || isPlatformAdmin ? 'pb-4' : 'hidden'} aria-hidden={viewMode !== 'list' && !isPlatformAdmin}>
        <ListPanel canUpdate={canUpdate} canDelete={canDelete} />
      </div>

      {!isPlatformAdmin && <BookingModal />}
    </div>
  )
}
