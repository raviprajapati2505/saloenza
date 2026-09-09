import React, { useCallback, useEffect, useMemo, useState } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import {
  CalendarDays,
  CheckCircle2,
  Clock,
  IndianRupee,
  Layers,
  Settings,
  Users,
  UserRound,
} from 'lucide-react'
import { format, subDays, startOfMonth } from 'date-fns'
import BaseButton from '../../components/ui/BaseButton.jsx'
import HeroBanner from '../../components/dashboard/HeroBanner.jsx'
import StatsGrid from '../../components/dashboard/StatsGrid.jsx'
import RevenueChart from '../../components/dashboard/RevenueChart.jsx'
import AppointmentTrendChart from '../../components/dashboard/AppointmentTrendChart.jsx'
import StaffSchedule from '../../components/dashboard/StaffSchedule.jsx'
import RecentServices from '../../components/dashboard/RecentServices.jsx'
import PerformanceOverview from '../../components/dashboard/PerformanceOverview.jsx'
import AppointmentsList from '../../components/dashboard/AppointmentsList.jsx'
import CustomerQueue from '../../components/dashboard/CustomerQueue.jsx'
import QuickActions from '../../components/dashboard/QuickActions.jsx'
import { useAuthStore } from '../../stores/auth'
import { authHasModule, subscriptionPageSubtitle } from '../../lib/subscriptionModules.js'
import {
  customerQueueItemsFromSnapshot,
  liveQueueDashboardSubtitle,
} from '../../lib/liveQueueDashboard.js'
import { createTenantFormatter } from '../../lib/tenantFormatting.js'
import { apiPut, fetchMasterList } from '../../lib/apiHelpers'
import { fetchLiveQueue } from '../../services/queueService.js'
import { pushToast } from '../../stores/toast.js'
import { CHART_BRAND } from '../../components/dashboard/brandColors.js'

function dateKey(iso) {
  if (!iso) return null
  return format(new Date(iso), 'yyyy-MM-dd')
}

/** Lines on an appointment that belong to this staff member. */
function myLines(appt, staffId) {
  const id = Number(staffId)
  if (appt.services?.length) {
    const lines = appt.services.filter((s) => Number(s.staff_id || s.staff?.id) === id)
    if (lines.length) return lines
  }
  if (Number(appt.staff_id) === id || Number(appt.staff?.id) === id) {
    return [{
      price: appt.grand_total ?? appt.price ?? 0,
      duration_minutes: null,
      service: appt.service,
      service_id: appt.service_id,
    }]
  }
  return []
}

function isMine(appt, staffId) {
  return myLines(appt, staffId).length > 0
}

function myEarnings(appt, staffId) {
  return myLines(appt, staffId).reduce((sum, line) => sum + (Number(line.price) || 0), 0)
}

function myDuration(appt, staffId) {
  const lines = myLines(appt, staffId)
  const fromLines = lines.reduce((sum, line) => sum + (Number(line.duration_minutes) || 0), 0)
  if (fromLines > 0) return fromLines
  if (appt.starts_at && appt.ends_at) {
    return Math.max(0, Math.round((new Date(appt.ends_at) - new Date(appt.starts_at)) / 60000))
  }
  return 0
}

function myServiceNames(appt, staffId) {
  const lines = myLines(appt, staffId)
  const names = lines.map((l) => l.service?.name).filter(Boolean)
  if (names.length) return names.join(', ')
  return appt.service?.name || 'Service'
}

function buildStatusUpdatePayload(appt, status, staffId) {
  const services = (appt.services?.length
    ? appt.services
    : [{
        service_id: appt.service_id,
        product_id: appt.product_id,
        staff_id: staffId,
        price: appt.price,
        duration_minutes: null,
      }]
  ).map((line) => ({
    service_id: line.service_id,
    product_id: line.product_id ?? null,
    staff_id: line.staff_id ?? staffId,
    price: line.price ?? null,
    duration_minutes: line.duration_minutes ?? null,
  }))

  return {
    branch_id: appt.branch_id ?? null,
    customer_id: appt.customer_id ?? null,
    type: appt.type || 'appointment',
    starts_at: appt.starts_at,
    status,
    discount: appt.discount ?? 0,
    notes: appt.notes ?? null,
    services,
  }
}

export default function StaffDashboardView() {
  const auth = useAuthStore()
  const fmt = useMemo(() => createTenantFormatter(auth), [auth])
  const navigate = useNavigate()
  const staffId = auth.user?.id
  const greetingName = auth.user?.firstname || auth.user?.name || 'Team member'
  const salonName = auth.tenant?.name || 'your salon'
  const roleLabel = auth.roleDisplay?.label || auth.role?.name || 'Staff'

  const canAppointments = auth.can('appointments.view') && authHasModule(auth, 'appointments')
  const canUpdateAppt = auth.can('appointments.update') && authHasModule(auth, 'appointments')
  const canCatalog = (auth.can('services.view') || auth.can('products.view')) && authHasModule(auth, 'catalog')
  const canStaffDir = auth.can('staff.view') && authHasModule(auth, 'staff')
  const canSettings = auth.can('settings.view')
  const canEarnings = auth.can('staff.earnings.view') && authHasModule(auth, 'staff')
  const canQueue = auth.can('queue.view') && authHasModule(auth, 'queue')

  const [loading, setLoading] = useState(true)
  const [error, setError] = useState('')
  const [appointments, setAppointments] = useState([])
  const [queueSnapshot, setQueueSnapshot] = useState(null)
  const [queueError, setQueueError] = useState('')
  const [updatingId, setUpdatingId] = useState(null)
  const [nowLabel, setNowLabel] = useState(() =>
    new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }),
  )

  const todayStr = format(new Date(), 'yyyy-MM-dd')
  const weekFrom = format(subDays(new Date(), 6), 'yyyy-MM-dd')
  const monthFrom = format(startOfMonth(new Date()), 'yyyy-MM-dd')
  // Fetch from earlier of month start vs 7 days so both week + month metrics work
  const fetchFrom = monthFrom < weekFrom ? monthFrom : weekFrom

  useEffect(() => {
    const id = window.setInterval(() => {
      setNowLabel(new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }))
    }, 30000)
    return () => window.clearInterval(id)
  }, [])

  const load = useCallback(async () => {
    if (!staffId) {
      setLoading(false)
      return
    }
    setLoading(true)
    setError('')
    try {
      const tasks = []

      if (canAppointments) {
        tasks.push(
          fetchMasterList('/v1/appointments', 'appointments', {
            from: fetchFrom,
            to: todayStr,
            staff_id: staffId,
          })
            .then((rows) => setAppointments((rows || []).filter((a) => isMine(a, staffId))))
            .catch((err) => {
              setError(err?.response?.data?.message || err?.message || 'Unable to load your dashboard.')
              setAppointments([])
            }),
        )
      } else {
        setAppointments([])
      }

      if (canQueue) {
        tasks.push(
          fetchLiveQueue()
            .then((data) => {
              setQueueSnapshot(data)
              setQueueError('')
            })
            .catch((err) => {
              setQueueSnapshot(null)
              setQueueError(err?.response?.data?.message || 'Unable to load your queue.')
            }),
        )
      } else {
        setQueueSnapshot(null)
        setQueueError('')
      }

      if (tasks.length > 0) {
        await Promise.all(tasks)
      }
    } catch (err) {
      setError(err?.response?.data?.message || err?.message || 'Unable to load your dashboard.')
    } finally {
      setLoading(false)
    }
  }, [staffId, canAppointments, canQueue, fetchFrom, todayStr])

  useEffect(() => {
    void load()
  }, [load])

  const myAppts = appointments

  const todays = useMemo(
    () => myAppts.filter((a) => dateKey(a.starts_at) === todayStr),
    [myAppts, todayStr],
  )

  const weekAppts = useMemo(
    () => myAppts.filter((a) => dateKey(a.starts_at) >= weekFrom),
    [myAppts, weekFrom],
  )

  const monthAppts = useMemo(
    () => myAppts.filter((a) => dateKey(a.starts_at) >= monthFrom),
    [myAppts, monthFrom],
  )

  const completedToday = useMemo(
    () => todays.filter((a) => a.status === 'completed'),
    [todays],
  )

  const upcomingToday = useMemo(
    () => todays.filter((a) => ['scheduled', 'confirmed'].includes(a.status)),
    [todays],
  )

  const inProgress = useMemo(
    () => todays.filter((a) => a.status === 'in-progress'),
    [todays],
  )

  const todayEarnings = useMemo(
    () => todays.reduce((sum, a) => sum + myEarnings(a, staffId), 0),
    [todays, staffId],
  )

  const weekEarnings = useMemo(
    () => weekAppts.reduce((sum, a) => sum + myEarnings(a, staffId), 0),
    [weekAppts, staffId],
  )

  const monthEarnings = useMemo(
    () => monthAppts.reduce((sum, a) => sum + myEarnings(a, staffId), 0),
    [monthAppts, staffId],
  )

  const hoursWorkedToday = useMemo(() => {
    const mins = completedToday.reduce((sum, a) => sum + myDuration(a, staffId), 0)
      + inProgress.reduce((sum, a) => sum + myDuration(a, staffId), 0)
    return Math.round((mins / 60) * 10) / 10
  }, [completedToday, inProgress, staffId])

  const uniqueCustomers = useMemo(() => {
    const ids = new Set(
      monthAppts.map((a) => a.customer_id || a.customer?.id).filter(Boolean),
    )
    return ids.size
  }, [monthAppts])

  const repeatCustomers = useMemo(() => {
    const counts = new Map()
    for (const a of monthAppts) {
      const id = a.customer_id || a.customer?.id
      if (!id) continue
      counts.set(id, (counts.get(id) || 0) + 1)
    }
    return [...counts.values()].filter((n) => n > 1).length
  }, [monthAppts])

  const avgDuration = useMemo(() => {
    const completed = monthAppts.filter((a) => a.status === 'completed')
    if (!completed.length) return null
    const total = completed.reduce((sum, a) => sum + myDuration(a, staffId), 0)
    return Math.round(total / completed.length)
  }, [monthAppts, staffId])

  const heroSummary = useMemo(
    () => [
      { key: 'today', label: "Today's earnings", value: fmt.money(todayEarnings) },
      { key: 'week', label: 'Weekly earnings', value: fmt.money(weekEarnings) },
      { key: 'month', label: 'Month to date', value: fmt.money(monthEarnings) },
      { key: 'services', label: 'Completed today', value: completedToday.length },
    ],
    [fmt, todayEarnings, weekEarnings, monthEarnings, completedToday.length],
  )

  const kpiItems = useMemo(
    () => [
      {
        key: 'customers',
        title: "Today's customers",
        value: new Set(todays.map((a) => a.customer_id || a.customer?.id).filter(Boolean)).size,
        icon: Users,
        color: 'indigo',
      },
      {
        key: 'completed',
        title: 'Services completed',
        value: completedToday.length,
        icon: CheckCircle2,
        color: 'teal',
      },
      {
        key: 'upcoming',
        title: 'Upcoming today',
        value: upcomingToday.length,
        icon: Clock,
        color: 'violet',
      },
      {
        key: 'hours',
        title: 'Hours worked today',
        value: `${hoursWorkedToday}h`,
        icon: Clock,
        color: 'amber',
      },
      {
        key: 'today_earn',
        title: "Today's earnings",
        value: fmt.money(todayEarnings),
        icon: IndianRupee,
        color: 'teal',
      },
      {
        key: 'week_earn',
        title: 'Weekly earnings',
        value: fmt.money(weekEarnings),
        icon: IndianRupee,
        color: 'indigo',
      },
      {
        key: 'month_earn',
        title: 'Monthly earnings',
        value: fmt.money(monthEarnings),
        icon: IndianRupee,
        color: 'violet',
      },
      {
        key: 'month_services',
        title: 'Monthly services',
        value: monthAppts.filter((a) => a.status === 'completed').length,
        icon: CheckCircle2,
        color: 'blue',
      },
    ],
    [
      fmt,
      todays,
      completedToday.length,
      upcomingToday.length,
      hoursWorkedToday,
      todayEarnings,
      weekEarnings,
      monthEarnings,
      monthAppts,
    ],
  )

  const earningsSeries = useMemo(() => {
    return Array.from({ length: 7 }, (_, i) => {
      const d = subDays(new Date(), 6 - i)
      const key = format(d, 'yyyy-MM-dd')
      const dayRows = weekAppts.filter((a) => dateKey(a.starts_at) === key)
      return {
        label: format(d, 'dd MMM'),
        amount: dayRows.reduce((sum, a) => sum + myEarnings(a, staffId), 0),
        value: dayRows.length,
      }
    })
  }, [weekAppts, staffId])

  const serviceTrend = useMemo(
    () => earningsSeries.map((d) => ({ label: d.label, value: d.value })),
    [earningsSeries],
  )

  const scheduleItems = useMemo(
    () =>
      [...todays]
        .sort((a, b) => new Date(a.starts_at) - new Date(b.starts_at))
        .map((a) => ({
          id: a.id,
          startsAt: a.starts_at,
          durationMinutes: myDuration(a, staffId) || null,
          customerName: a.customer?.name || 'Walk-in',
          serviceName: myServiceNames(a, staffId),
          branchName: a.branch?.branch_name || a.branch?.name || null,
          status: a.status,
          notes: a.notes || null,
          href: '/appointments',
          _raw: a,
        })),
    [todays, staffId],
  )

  const upcomingItems = useMemo(
    () =>
      upcomingToday
        .sort((a, b) => new Date(a.starts_at) - new Date(b.starts_at))
        .slice(0, 6)
        .map((a) => ({
          id: a.id,
          customerName: a.customer?.name || 'Walk-in',
          serviceName: myServiceNames(a, staffId),
          startsAt: a.starts_at,
          amount: myEarnings(a, staffId),
          status: a.status,
          href: '/appointments',
        })),
    [upcomingToday, staffId],
  )

  const recentServices = useMemo(() => {
    return [...monthAppts]
      .filter((a) => a.status === 'completed')
      .sort((a, b) => new Date(b.starts_at) - new Date(a.starts_at))
      .slice(0, 8)
      .map((a) => ({
        id: a.id,
        date: a.starts_at,
        customerName: a.customer?.name || 'Customer',
        serviceName: myServiceNames(a, staffId),
        amount: myEarnings(a, staffId),
      }))
  }, [monthAppts, staffId])

  const servicesSummary = useMemo(() => {
    const total = recentServices.reduce((sum, r) => sum + (Number(r.amount) || 0), 0)
    return { total, paid: total, pending: 0 }
  }, [recentServices])

  const performanceItems = useMemo(
    () => [
      {
        key: 'customers',
        label: 'Customers served',
        value: uniqueCustomers,
        iconKey: 'customers',
        color: 'indigo',
        hint: 'This month',
      },
      {
        key: 'repeat',
        label: 'Repeat customers',
        value: repeatCustomers,
        iconKey: 'repeat',
        color: 'violet',
        hint: 'Seen more than once',
      },
      {
        key: 'services',
        label: 'Services completed',
        value: monthAppts.filter((a) => a.status === 'completed').length,
        iconKey: 'services',
        color: 'teal',
        hint: 'This month',
      },
      {
        key: 'duration',
        label: 'Avg service time',
        value: avgDuration == null ? '—' : `${avgDuration} min`,
        iconKey: 'duration',
        color: 'amber',
        hint: 'Completed only',
      },
      {
        key: 'earnings',
        label: 'Month earnings',
        value: fmt.money(monthEarnings),
        iconKey: 'score',
        color: 'emerald',
        hint: 'Assigned service value',
      },
      {
        key: 'today_count',
        label: "Today's bookings",
        value: todays.length,
        iconKey: 'services',
        color: 'indigo',
      },
    ],
    [
      fmt,
      uniqueCustomers,
      repeatCustomers,
      monthAppts,
      avgDuration,
      monthEarnings,
      todays.length,
    ],
  )

  const handleStatusChange = useCallback(
    async (item, status) => {
      if (!canUpdateAppt || !item?._raw || !staffId) return
      setUpdatingId(item.id)
      try {
        await apiPut(
          `/v1/appointments/${item.id}`,
          buildStatusUpdatePayload(item._raw, status, staffId),
        )
        setAppointments((prev) =>
          prev.map((a) => (a.id === item.id ? { ...a, status } : a)),
        )
        pushToast(`Marked as ${status.replace(/-/g, ' ')}.`, 'success')
      } catch (err) {
        pushToast(err?.response?.data?.message || 'Unable to update appointment.', 'error')
      } finally {
        setUpdatingId(null)
      }
    },
    [canUpdateAppt, staffId],
  )

  const getQuickActions = useCallback(
    (item) => {
      if (!canUpdateAppt) return []
      const status = item.status
      const busy = updatingId === item.id
      const actions = []
      if (['scheduled', 'confirmed'].includes(status)) {
        actions.push({
          key: 'start',
          label: busy ? 'Updating…' : 'Start service',
          disabled: busy,
          onClick: () => void handleStatusChange(item, 'in-progress'),
        })
      }
      if (status === 'in-progress') {
        actions.push({
          key: 'complete',
          label: busy ? 'Updating…' : 'Complete',
          disabled: busy,
          onClick: () => void handleStatusChange(item, 'completed'),
        })
      }
      actions.push({
        key: 'view',
        label: 'View',
        onClick: () => navigate('/appointments'),
      })
      return actions
    },
    [canUpdateAppt, updatingId, handleStatusChange, navigate],
  )

  const myQueueItems = useMemo(() => {
    if (!canQueue || !queueSnapshot?.summary) return []
    return customerQueueItemsFromSnapshot(queueSnapshot.summary)
  }, [canQueue, queueSnapshot])

  const myQueueSubtitle = useMemo(() => {
    if (!canQueue || !queueSnapshot) return 'Your assigned floor status'
    return liveQueueDashboardSubtitle(queueSnapshot, 'Your assigned floor status')
  }, [canQueue, queueSnapshot])

  const quickActions = useMemo(
    () =>
      [
        canEarnings && {
          key: 'earnings',
          label: 'My earnings',
          icon: IndianRupee,
          to: '/staff/earnings',
          color: 'emerald',
        },
        canQueue && {
          key: 'queue',
          label: 'Live queue',
          icon: Clock,
          to: '/queue',
          color: 'amber',
        },
        canAppointments && {
          key: 'schedule',
          label: 'My schedule',
          icon: CalendarDays,
          to: '/appointments',
          color: 'indigo',
        },
        canCatalog && {
          key: 'services',
          label: 'Services menu',
          icon: Layers,
          to: '/catalog',
          color: 'teal',
        },
        canStaffDir && {
          key: 'team',
          label: 'Team directory',
          icon: Users,
          to: '/staff',
          color: 'violet',
        },
        canSettings && {
          key: 'settings',
          label: 'My profile',
          icon: Settings,
          to: '/settings',
          color: 'slate',
        },
      ].filter(Boolean),
    [canAppointments, canCatalog, canStaffDir, canSettings, canEarnings, canQueue],
  )

  const hour = new Date().getHours()
  const timeGreeting =
    hour < 12 ? 'Good morning' : hour < 17 ? 'Good afternoon' : 'Good evening'

  return (
    <div className="space-y-6">
      <HeroBanner
        tone="brand"
        title={`Hi, ${greetingName}`}
        greeting={`${timeGreeting} · ${nowLabel}`}
        subtitle={subscriptionPageSubtitle(auth, `${roleLabel} at ${salonName}. Your personal schedule, earnings, and performance.`)}
        summaryCards={heroSummary}
        loading={loading}
        onRefresh={() => void load()}
        refreshing={loading}
        actions={
          canAppointments ? (
            <Link to="/appointments">
              <BaseButton size="sm" leftIcon={CalendarDays}>
                Open schedule
              </BaseButton>
            </Link>
          ) : null
        }
      />

      {error ? (
        <div className="rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-700">{error}</div>
      ) : null}

      <StatsGrid items={kpiItems} loading={loading} />

      {(canAppointments || loading) && (
        <div className="grid grid-cols-1 gap-6 xl:grid-cols-2">
          <RevenueChart
            title="My daily earnings"
            subtitle="Assigned service value · last 7 days"
            data={earningsSeries}
            loading={loading}
            formatMoney={fmt.money}
          />
          <AppointmentTrendChart
            title="My appointments"
            subtitle="Services assigned to you · last 7 days"
            data={serviceTrend}
            loading={loading}
            type="area"
            valueLabel="Appointments"
            color={CHART_BRAND.primary}
          />
        </div>
      )}

      <div className="grid grid-cols-1 gap-6 xl:grid-cols-5">
        <div className="xl:col-span-3">
          <StaffSchedule
            items={scheduleItems}
            loading={loading}
            getQuickActions={getQuickActions}
            emptyMessage={
              canAppointments
                ? 'No appointments assigned to you today.'
                : 'Appointments module is not available on your plan.'
            }
            viewAllTo={canAppointments ? '/appointments' : undefined}
          />
        </div>
        <div className="xl:col-span-2 space-y-6">
          {canQueue ? (
            <CustomerQueue
              title="My queue"
              subtitle={myQueueSubtitle}
              items={myQueueItems}
              loading={loading}
              error={queueError}
              viewAllTo="/queue"
              emptyMessage="No customers in your queue right now."
            />
          ) : null}
          <AppointmentsList
            title="Upcoming"
            items={upcomingItems}
            loading={loading}
            formatMoney={fmt.money}
            emptyMessage="Nothing upcoming for today."
            viewAllTo={canAppointments ? '/appointments' : undefined}
          />
          <QuickActions title="Quick actions" actions={quickActions} loading={loading} />
        </div>
      </div>

      <div className="grid grid-cols-1 gap-6 lg:grid-cols-2">
        <RecentServices
          items={recentServices}
          summary={servicesSummary}
          loading={loading}
          formatMoney={fmt.money}
          emptyMessage="No completed services this month yet."
        />
        <PerformanceOverview items={performanceItems} loading={loading} />
      </div>

      <div className="rounded-[18px] border border-slate-200 bg-white p-5 shadow-[0_8px_30px_rgb(0,0,0,0.04)] sm:p-6">
        <div className="flex items-start gap-3">
          <div className="flex h-11 w-11 items-center justify-center rounded-xl bg-brand-50 text-brand-600">
            <UserRound className="h-5 w-5" />
          </div>
          <div>
            <p className="text-xs font-semibold uppercase tracking-wider text-slate-400">Personal workspace</p>
            <p className="mt-1 text-sm text-slate-600">
              Only your assigned appointments and service earnings are shown here — not salon or branch-wide analytics.
            </p>
          </div>
        </div>
      </div>
    </div>
  )
}
