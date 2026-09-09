import { useRef, useMemo, useEffect, useState, useCallback } from 'react'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import dayGridPlugin from '@fullcalendar/daygrid'
import timeGridPlugin from '@fullcalendar/timegrid'
import interactionPlugin from '@fullcalendar/interaction'
import { format, subDays } from 'date-fns'
import { useAppointmentStore } from '../stores/appointmentStore'
import { apiPut, appointmentToCalendarEvent, fetchMasterList, isValidCalendarDate } from '../lib/apiHelpers'
import { shiftAppointmentServices } from '../lib/appointmentStatus.js'

function inclusiveRangeEnd(exclusiveEnd) {
  return format(subDays(exclusiveEnd, 1), 'yyyy-MM-dd')
}

function parseCalendarDate(value) {
  if (!value) return undefined
  const date = new Date(value)
  return Number.isNaN(date.getTime()) ? undefined : date
}

function isMonthView(viewName) {
  return viewName === 'dayGridMonth'
}

export function useCalendar({ canCreate = true, canUpdate = true } = {}) {
  const queryClient = useQueryClient()
  const calendarRef = useRef(null)
  const handlersRef = useRef({})

  const storedCalendarView = useAppointmentStore(state => state.calendarView)
  const storedCalendarDate = useAppointmentStore(state => state.calendarDate)
  const setCalendarView = useAppointmentStore(state => state.setCalendarView)
  const setCalendarDate = useAppointmentStore(state => state.setCalendarDate)

  const [currentDate, setCurrentDate] = useState(
    () => parseCalendarDate(storedCalendarDate) ?? new Date(),
  )
  const [mountInitialDate, setMountInitialDate] = useState(
    () => parseCalendarDate(storedCalendarDate) ?? new Date(),
  )
  const [currentView, setCurrentView] = useState(storedCalendarView || 'timeGridDay')
  const [dateRange, setDateRange] = useState({ from: null, to: null })

  const selectedEvent = useAppointmentStore(state => state.selectedEvent)
  const setSelectedEvent = useAppointmentStore(state => state.setSelectedEvent)
  const openBookingModalWithSlot = useAppointmentStore(state => state.openBookingModalWithSlot)
  const filters = useAppointmentStore(state => state.filters)
  const viewMode = useAppointmentStore(state => state.viewMode)

  const eventFilters = {
    ...(filters.saloon_id ? { saloon_id: Number(filters.saloon_id) } : {}),
    ...(filters.branch_id ? { branch_id: Number(filters.branch_id) } : {}),
    ...(filters.status ? { status: filters.status } : {}),
    ...(filters.type ? { type: filters.type } : {}),
    ...(filters.staff_id ? { staff_id: Number(filters.staff_id) } : {}),
    ...(filters.customer_id ? { customer_id: Number(filters.customer_id) } : {}),
    ...(filters.search ? { search: filters.search } : {}),
  }

  const from = dateRange.from
  const to = dateRange.to

  const { data: events = [], isLoading: isLoadingEvents, isFetching, isError, error } = useQuery({
    queryKey: ['calendar-events', from, to, eventFilters],
    queryFn: async () => {
      const appointments = await fetchMasterList('/v1/appointments', 'appointments', {
        from,
        to,
        ...eventFilters,
      })
      return appointments
        .map(appointmentToCalendarEvent)
        .filter(Boolean)
    },
    enabled: Boolean(from && to && viewMode === 'calendar'),
    placeholderData: (previous) => previous,
  })

  const safeEvents = useMemo(
    () => events.filter(
      (event) => isValidCalendarDate(event.start) && isValidCalendarDate(event.end),
    ),
    [events],
  )

  const eventsKey = ['calendar-events', from, to, eventFilters]

  const updateAppointmentMutation = useMutation({
    mutationFn: async (payload) => {
      const cached = queryClient.getQueryData(eventsKey) || []
      const existing = cached.find(e => e.id === String(payload.id))
      const raw = existing?.extendedProps?.raw || {}
      const services = shiftAppointmentServices(raw, payload.start)

      await apiPut(`/v1/appointments/${payload.id}`, {
        branch_id: raw.branch_id ?? null,
        customer_id: raw.customer_id ?? null,
        type: raw.type || 'appointment',
        starts_at: payload.start,
        status: raw.status || 'confirmed',
        discount: raw.discount ?? 0,
        notes: raw.notes ?? null,
        services,
      })
      return payload
    },
    onMutate: async (payload) => {
      if (!isValidCalendarDate(payload.start) || !isValidCalendarDate(payload.end)) {
        return {}
      }

      await queryClient.cancelQueries({ queryKey: ['calendar-events'] })
      const previousEvents = queryClient.getQueryData(eventsKey)
      queryClient.setQueryData(eventsKey, (old) => {
        if (!old) return []
        return old.map(evt => evt.id === String(payload.id) ? {
          ...evt,
          start: payload.start,
          end: payload.end,
        } : evt)
      })
      return { previousEvents }
    },
    onError: (_err, _payload, context) => {
      queryClient.setQueryData(eventsKey, context?.previousEvents)
    },
    onSettled: () => {
      queryClient.invalidateQueries({ queryKey: ['calendar-events'] })
    },
  })

  handlersRef.current = {
    setSelectedEvent,
    openBookingModalWithSlot,
    updateAppointment: (payload) => updateAppointmentMutation.mutate(payload),
    selectedEventId: selectedEvent?.id,
  }

  const getApi = useCallback(() => calendarRef.current?.getApi?.() ?? null, [])

  const goPrev = useCallback(() => {
    getApi()?.prev()
  }, [getApi])

  const goNext = useCallback(() => {
    getApi()?.next()
  }, [getApi])

  const goToday = useCallback(() => {
    getApi()?.today()
  }, [getApi])

  // Swap React view state instead of FullCalendar changeView() — avoids ScrollGrid crashes.
  const changeView = useCallback((viewName) => {
    if (!viewName || viewName === currentView) return
    setMountInitialDate(currentDate)
    setCurrentView(viewName)
    setCalendarView(viewName)
  }, [currentDate, currentView, setCalendarView])

  useEffect(() => {
    if (typeof window !== 'undefined' && window.Echo) {
      try {
        const channel = window.Echo.channel('tenant.mock')
        channel.listen('AppointmentUpdated', () => {
          queryClient.invalidateQueries({ queryKey: ['calendar-events'] })
        })
        return () => channel.stopListening('AppointmentUpdated')
      } catch (err) {
        console.warn('Echo listener setup failed:', err)
      }
    }
  }, [queryClient])

  useEffect(() => {
    const api = getApi()
    if (!api) return undefined

    const refreshSize = () => {
      try {
        api.updateSize()
      } catch {
        // FullCalendar may not be ready during view swaps.
      }
    }

    const frame = window.requestAnimationFrame(refreshSize)
    window.addEventListener('resize', refreshSize)

    return () => {
      window.cancelAnimationFrame(frame)
      window.removeEventListener('resize', refreshSize)
    }
  }, [currentView, getApi, viewMode])

  const sharedHandlers = useMemo(() => ({
    datesSet: (info) => {
      const viewDate = info.view.currentStart
      setCurrentDate(viewDate)
      setCalendarDate(viewDate.toISOString())
      setDateRange({
        from: format(info.start, 'yyyy-MM-dd'),
        to: inclusiveRangeEnd(info.end),
      })
    },

    eventClick: (info) => handlersRef.current.setSelectedEvent(info.event),

    select: (info) => {
      if (!canCreate) return
      handlersRef.current.openBookingModalWithSlot({
        start: info.startStr,
        end: info.endStr,
      }, { type: 'appointment', source: 'appointments' })
    },

    eventDrop: (info) => {
      if (!canUpdate) {
        info.revert()
        return
      }
      handlersRef.current.updateAppointment({
        id: info.event.id,
        start: info.event.startStr,
        end: info.event.endStr,
      })
    },

    eventResize: (info) => {
      if (!canUpdate || info.view.type === 'dayGridMonth') {
        info.revert()
        return
      }
      handlersRef.current.updateAppointment({
        id: info.event.id,
        start: info.event.startStr,
        end: info.event.endStr,
      })
    },

    eventContent: (arg) => {
      const { customer, service, status, staffName, serviceCount } = arg.event.extendedProps
      const timeText = arg.timeText
      const isSelected = handlersRef.current.selectedEventId === arg.event.id
      const extraServices = Math.max((Number(serviceCount) || 1) - 1, 0)
      const isMonth = arg.view.type === 'dayGridMonth'

      const statusColors = {
        scheduled: 'bg-sky-500',
        confirmed: 'bg-brand-500',
        'in-progress': 'bg-blue-500',
        completed: 'bg-slate-500',
        cancelled: 'bg-rose-500',
        'no-show': 'bg-rose-500',
      }

      const cardMap = {
        scheduled: 'bg-sky-50 border-sky-300 text-sky-950',
        confirmed: 'bg-brand-50 border-brand-300 text-slate-950',
        'in-progress': 'bg-blue-50 border-blue-300 text-blue-950',
        completed: 'bg-slate-100 border-slate-300 text-slate-900',
        cancelled: 'bg-rose-50 border-rose-300 text-rose-950',
        'no-show': 'bg-rose-50 border-rose-300 text-rose-950',
      }

      const cardClass = cardMap[status] || cardMap.confirmed
      const selectedClass = isSelected ? 'ring-2 ring-brand-500 shadow-md' : 'shadow-sm'

      if (isMonth) {
        return (
          <div className={`flex items-center gap-1.5 min-w-0 px-1.5 py-1 rounded-md border ${cardClass} ${selectedClass}`}>
            <div className={`h-2 w-2 rounded-full shrink-0 ${statusColors[status] || 'bg-slate-400'}`} />
            {timeText ? (
              <span className="text-[10px] font-bold text-slate-600 shrink-0">{timeText}</span>
            ) : null}
            <span className="text-[11px] font-bold truncate">{customer || service}</span>
          </div>
        )
      }

      const start = arg.event.start
      const end = arg.event.end
      const durationMs = end && start ? (end.getTime() - start.getTime()) : 0
      const durationMin = durationMs / (1000 * 60)
      const isShort = durationMin <= 45

      if (isShort) {
        return (
          <div className={`group h-full w-full rounded-lg border px-2.5 py-1.5 flex items-center justify-between gap-2 overflow-hidden transition-shadow ${cardClass} ${selectedClass} hover:shadow-md`}>
            <div className="flex items-center gap-1.5 min-w-0">
              <div className={`h-2 w-2 rounded-full shrink-0 ${statusColors[status] || 'bg-slate-400'}`} />
              <span className="font-bold text-[12px] truncate">{customer}</span>
              <span className="text-[11px] text-slate-600 truncate hidden sm:inline">· {service}</span>
              {extraServices > 0 ? (
                <span className="shrink-0 rounded-full bg-white/80 border border-slate-200 px-1.5 text-[10px] font-bold text-slate-700">+{extraServices}</span>
              ) : null}
            </div>
            <span className="text-[10px] font-semibold text-slate-600 shrink-0">{timeText}</span>
          </div>
        )
      }

      return (
        <div className={`group h-full w-full rounded-lg border p-2.5 flex flex-col relative overflow-hidden transition-shadow ${cardClass} ${selectedClass} hover:shadow-md`}>
          <div className="flex items-start justify-between gap-2 z-10">
            <div className="flex items-center gap-2 min-w-0">
              <div className="h-7 w-7 rounded-full bg-white border border-slate-200 shadow-sm flex items-center justify-center text-[11px] font-bold text-slate-700 shrink-0">
                {(customer || 'C').charAt(0)}
              </div>
              <div className="min-w-0">
                <div className="font-bold text-[13px] leading-tight truncate">{customer}</div>
                <div className="text-[11px] text-slate-600 truncate mt-0.5" title={service}>
                  {service}{staffName ? ` · ${staffName}` : ''}
                </div>
                {extraServices > 0 ? (
                  <div className="text-[10px] font-bold text-brand-700 mt-0.5">+{extraServices} more</div>
                ) : null}
              </div>
            </div>
            <div className={`h-2.5 w-2.5 rounded-full shrink-0 mt-0.5 ${statusColors[status] || 'bg-slate-400'}`} />
          </div>
          <div className="mt-auto pt-1.5 flex items-center justify-between text-[11px] font-semibold text-slate-600 z-10">
            <span>{timeText}</span>
          </div>
        </div>
      )
    },
  }), [canCreate, canUpdate, setCalendarDate])

  const calendarProps = useMemo(() => {
    const base = {
      headerToolbar: false,
      firstDay: 1,
      handleWindowResize: true,
      windowResizeDelay: 150,
      editable: canUpdate,
      eventStartEditable: canUpdate,
      eventDurationEditable: canUpdate,
      selectable: canCreate,
      selectMirror: canCreate,
      ...sharedHandlers,
    }

    if (isMonthView(currentView)) {
      return {
        ...base,
        plugins: [dayGridPlugin, interactionPlugin],
        initialView: 'dayGridMonth',
        height: 'auto',
        // Do not set dayMinWidth — it requires premium @fullcalendar/scrollgrid
        // and throws "No ScrollGrid implementation" on the free build.
        views: {
          dayGridMonth: {
            dayMaxEvents: 4,
            moreLinkClick: 'popover',
            fixedWeekCount: false,
          },
        },
      }
    }

    return {
      ...base,
      plugins: [timeGridPlugin, interactionPlugin],
      initialView: currentView,
      height: '100%',
      allDaySlot: false,
      slotMinTime: '00:00:00',
      slotMaxTime: '24:00:00',
      slotDuration: '00:30:00',
      slotLabelInterval: '01:00:00',
      scrollTime: '08:00:00',
      expandRows: false,
      stickyHeaderDates: true,
      nowIndicator: true,
      slotLabelFormat: {
        hour: 'numeric',
        minute: '2-digit',
        omitZeroMinute: true,
        meridiem: 'short',
      },
      views: {
        timeGridDay: {
          dayHeaderFormat: { weekday: 'long', month: 'long', day: 'numeric' },
        },
        timeGridWeek: {
          dayHeaderFormat: { weekday: 'short', month: 'short', day: 'numeric' },
        },
      },
    }
  }, [canCreate, canUpdate, currentView, sharedHandlers])

  return {
    calendarRef,
    calendarProps,
    mountInitialDate,
    events: safeEvents,
    currentDate,
    currentView,
    isLoading: isLoadingEvents && safeEvents.length === 0 && !from,
    isFetching,
    isError,
    error,
    goPrev,
    goNext,
    goToday,
    changeView,
  }
}

export { inclusiveRangeEnd }
