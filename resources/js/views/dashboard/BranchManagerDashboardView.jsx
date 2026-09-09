import React, { useCallback, useEffect, useMemo, useState } from 'react'
import { Link } from 'react-router-dom'
import {
  CalendarDays,
  CalendarPlus,
  CheckCircle2,
  Clock,
  Footprints,
  IndianRupee,
  Layers,
  ListOrdered,
  MapPinned,
  Settings,
  ShieldCheck,
  UserPlus,
  Users,
  XCircle,
} from 'lucide-react'
import { format, subDays } from 'date-fns'
import BaseButton from '../../components/ui/BaseButton.jsx'
import HeroBanner from '../../components/dashboard/HeroBanner.jsx'
import StatsGrid from '../../components/dashboard/StatsGrid.jsx'
import RevenueChart from '../../components/dashboard/RevenueChart.jsx'
import AppointmentTrendChart from '../../components/dashboard/AppointmentTrendChart.jsx'
import AppointmentStatusChart from '../../components/dashboard/AppointmentStatusChart.jsx'
import AppointmentsList from '../../components/dashboard/AppointmentsList.jsx'
import StaffOverview from '../../components/dashboard/StaffOverview.jsx'
import StaffPerformance from '../../components/dashboard/StaffPerformance.jsx'
import CustomerQueue from '../../components/dashboard/CustomerQueue.jsx'
import TopServices from '../../components/dashboard/TopServices.jsx'
import PendingTasks from '../../components/dashboard/PendingTasks.jsx'
import QuickActions from '../../components/dashboard/QuickActions.jsx'
import { useAuthStore } from '../../stores/auth'
import { authHasModule, subscriptionPageSubtitle } from '../../lib/subscriptionModules.js'
import { queueBucketForAppointment } from '../../lib/appointmentStatus.js'
import {
  customerQueueItemsFromSnapshot,
  liveQueueDashboardSubtitle,
} from '../../lib/liveQueueDashboard.js'
import { createTenantFormatter } from '../../lib/tenantFormatting.js'
import { apiGet, apiPut, fetchMasterList, parseList } from '../../lib/apiHelpers'
import { fetchBranches } from '../../services/branchService.js'
import { fetchLiveQueue } from '../../services/queueService.js'
import { revenueAmount } from '../../lib/appointmentPayments.js'
import { pushToast } from '../../stores/toast.js'

function apptAmount(appt) {
  return revenueAmount(appt)
}

function serviceNames(appt) {
  if (appt.services?.length) {
    return appt.services.map((s) => s.service?.name).filter(Boolean).join(', ') || 'Service'
  }
  return appt.service?.name || 'Service'
}

function staffNames(appt) {
  if (appt.services?.length) {
    const names = [...new Set(appt.services.map((s) => s.staff?.name).filter(Boolean))]
    if (names.length) return names.join(', ')
  }
  return appt.staff?.name || null
}

function dateKey(iso) {
  if (!iso) return null
  return format(new Date(iso), 'yyyy-MM-dd')
}

function buildStatusUpdatePayload(appt, status) {
  const raw = appt._raw || appt
  const services = (raw.services?.length
    ? raw.services
    : [{
        service_id: raw.service_id,
        product_id: raw.product_id,
        staff_id: raw.staff_id,
        price: raw.price,
        duration_minutes: raw.duration_minutes,
      }]
  ).map((line) => ({
    service_id: line.service_id,
    product_id: line.product_id ?? null,
    staff_id: line.staff_id ?? null,
    price: line.price ?? null,
    duration_minutes: line.duration_minutes ?? null,
  }))

  return {
    branch_id: raw.branch_id ?? null,
    customer_id: raw.customer_id ?? null,
    type: raw.type || 'appointment',
    starts_at: raw.starts_at,
    status,
    discount: raw.discount ?? 0,
    notes: raw.notes ?? null,
    services,
  }
}

export default function BranchManagerDashboardView() {
  const auth = useAuthStore()
  const fmt = useMemo(() => createTenantFormatter(auth), [auth])
  const greetingName = auth.user?.firstname || auth.user?.name || 'Manager'
  const salonName = auth.tenant?.name || 'Salon'
  const branchId = auth.user?.branch_id

  const canStaff = auth.can('staff.view') && authHasModule(auth, 'staff')
  const canAppointments = auth.can('appointments.view') && authHasModule(auth, 'appointments')
  const canUpdateAppt = auth.can('appointments.update') && authHasModule(auth, 'appointments')
  const canCreateAppt = auth.can('appointments.create') && authHasModule(auth, 'appointments')
  const canCreateCustomer = auth.can('customers.manage') && authHasModule(auth, 'customers')
  const canCatalog = (auth.can('services.view') || auth.can('products.view')) && authHasModule(auth, 'catalog')
  const canBranches = auth.can('branches.view') && authHasModule(auth, 'settings')
  const canQueue = auth.can('queue.view') && authHasModule(auth, 'queue')

  const [loading, setLoading] = useState(true)
  const [error, setError] = useState('')
  const [branchName, setBranchName] = useState(
    auth.user?.branch_name || auth.tenant?.branch_name || 'Your branch',
  )
  const [staff, setStaff] = useState([])
  const [appointments, setAppointments] = useState([])
  const [queueSnapshot, setQueueSnapshot] = useState(null)
  const [queueError, setQueueError] = useState('')
  const [updatingId, setUpdatingId] = useState(null)

  const todayStr = format(new Date(), 'yyyy-MM-dd')
  const weekFrom = format(subDays(new Date(), 6), 'yyyy-MM-dd')

  const load = useCallback(async () => {
    setLoading(true)
    setError('')
    try {
      const tasks = []

      if (canBranches) {
        tasks.push(
          fetchBranches()
            .then((r) => {
              const rows = r.branches || []
              const mine = branchId
                ? rows.find((b) => Number(b.id) === Number(branchId))
                : rows[0]
              if (mine?.branch_name) setBranchName(mine.branch_name)
            })
            .catch(() => {}),
        )
      }

      tasks.push(
        canStaff
          ? apiGet('/v1/staff', { page: 1, per_page: 100 })
              .then((r) => setStaff(parseList(r, 'staff')))
              .catch(() => setStaff([]))
          : Promise.resolve(setStaff([])),
      )

      tasks.push(
        canAppointments
          ? fetchMasterList('/v1/appointments', 'appointments', { from: weekFrom, to: todayStr })
              .then((rows) => setAppointments(rows))
              .catch(() => setAppointments([]))
          : Promise.resolve(setAppointments([])),
      )

      tasks.push(
        canQueue
          ? fetchLiveQueue()
              .then((data) => {
                setQueueSnapshot(data)
                setQueueError('')
              })
              .catch((err) => {
                setQueueSnapshot(null)
                setQueueError(err?.response?.data?.message || 'Unable to load live queue.')
              })
          : Promise.resolve().then(() => {
              setQueueSnapshot(null)
              setQueueError('')
            }),
      )

      await Promise.all(tasks)
    } catch (err) {
      setError(err?.response?.data?.message || err?.message || 'Unable to load branch dashboard.')
    } finally {
      setLoading(false)
    }
  }, [canBranches, canStaff, canAppointments, canQueue, branchId, weekFrom, todayStr])

  useEffect(() => {
    void load()
  }, [load])

  // Prefer branch name from appointment payload if still generic
  useEffect(() => {
    if (branchName && branchName !== 'Your branch') return
    const named = appointments.find((a) => a.branch?.branch_name || a.branch?.name)
    if (named?.branch?.branch_name || named?.branch?.name) {
      setBranchName(named.branch.branch_name || named.branch.name)
    }
  }, [appointments, branchName])

  const todaysAppointments = useMemo(
    () => appointments.filter((a) => dateKey(a.starts_at) === todayStr),
    [appointments, todayStr],
  )

  const todayRevenue = useMemo(
    () => todaysAppointments.reduce((sum, a) => sum + apptAmount(a), 0),
    [todaysAppointments],
  )

  const weekRevenue = useMemo(
    () => appointments.reduce((sum, a) => sum + apptAmount(a), 0),
    [appointments],
  )

  const completedToday = useMemo(
    () => todaysAppointments.filter((a) => a.status === 'completed'),
    [todaysAppointments],
  )

  const cancelledToday = useMemo(
    () => todaysAppointments.filter((a) => a.status === 'cancelled' || a.status === 'no-show'),
    [todaysAppointments],
  )

  const pendingCheckIns = useMemo(
    () => todaysAppointments.filter((a) => queueBucketForAppointment(a) === 'waiting').length,
    [todaysAppointments],
  )

  const upcomingToday = useMemo(
    () => todaysAppointments.filter((a) => queueBucketForAppointment(a) === 'upcoming').length,
    [todaysAppointments],
  )

  const walkInsToday = useMemo(
    () => todaysAppointments.filter((a) => a.type === 'walk_in').length,
    [todaysAppointments],
  )

  const inProgressCount = useMemo(
    () => todaysAppointments.filter((a) => a.status === 'in-progress').length,
    [todaysAppointments],
  )

  const activeStaff = useMemo(
    () => staff.filter((s) => s.is_active !== false),
    [staff],
  )

  const heroSummary = useMemo(
    () => [
      { key: 'week_rev', label: 'Weekly revenue', value: fmt.money(weekRevenue) },
      { key: 'week_appts', label: 'Weekly appointments', value: appointments.length },
      { key: 'today_appts', label: "Today's appointments", value: todaysAppointments.length },
      { key: 'today_rev', label: "Today's revenue", value: fmt.money(todayRevenue) },
    ],
    [fmt, weekRevenue, appointments.length, todaysAppointments.length, todayRevenue],
  )

  const kpiItems = useMemo(
    () => [
      {
        key: 'today_appts',
        title: "Today's appointments",
        value: todaysAppointments.length,
        icon: CalendarDays,
        color: 'amber',
      },
      {
        key: 'today_rev',
        title: "Today's revenue",
        value: fmt.money(todayRevenue),
        icon: IndianRupee,
        color: 'teal',
      },
      {
        key: 'week_rev',
        title: 'Weekly revenue',
        value: fmt.money(weekRevenue),
        icon: IndianRupee,
        color: 'blue',
      },
      {
        key: 'week_appts',
        title: 'Weekly appointments',
        value: appointments.length,
        icon: CalendarDays,
        color: 'indigo',
      },
      {
        key: 'staff_present',
        title: 'Staff present',
        value: activeStaff.length,
      icon: Users,
        color: 'teal',
      },
      {
        key: 'pending',
        title: 'Pending check-ins',
        value: pendingCheckIns,
        icon: Clock,
        color: 'amber',
      },
      {
        key: 'walkins',
        title: 'Walk-ins today',
        value: walkInsToday,
        icon: Footprints,
        color: 'rose',
      },
      {
        key: 'completed',
        title: 'Completed today',
        value: completedToday.length,
        icon: CheckCircle2,
        color: 'teal',
      },
      {
        key: 'cancelled',
        title: 'Cancelled / no-show',
        value: cancelledToday.length,
        icon: XCircle,
        color: 'rose',
      },
      {
        key: 'in_progress',
        title: 'In progress',
        value: inProgressCount,
        icon: Clock,
        color: 'indigo',
      },
    ],
    [
      fmt,
      todaysAppointments.length,
      todayRevenue,
      weekRevenue,
      appointments.length,
      activeStaff.length,
      pendingCheckIns,
      walkInsToday,
      completedToday.length,
      cancelledToday.length,
      inProgressCount,
    ],
  )

  const revenueSeries = useMemo(() => {
    return Array.from({ length: 7 }, (_, i) => {
      const d = subDays(new Date(), 6 - i)
      const key = format(d, 'yyyy-MM-dd')
      const dayAppts = appointments.filter((a) => dateKey(a.starts_at) === key)
      return {
        label: format(d, 'dd MMM'),
        amount: dayAppts.reduce((sum, a) => sum + apptAmount(a), 0),
        value: dayAppts.length,
      }
    })
  }, [appointments])

  const appointmentTrend = useMemo(
    () => revenueSeries.map((d) => ({ label: d.label, value: d.value })),
    [revenueSeries],
  )

  const statusSummary = useMemo(() => {
    const counts = {}
    for (const a of todaysAppointments) {
      const key = a.status || 'scheduled'
      counts[key] = (counts[key] || 0) + 1
    }
    return counts
  }, [todaysAppointments])

  const appointmentListItems = useMemo(
    () =>
      [...todaysAppointments]
        .sort((a, b) => new Date(a.starts_at) - new Date(b.starts_at))
        .slice(0, 10)
        .map((a) => ({
          id: a.id,
          customerName: a.customer?.name || 'Walk-in',
          serviceName: serviceNames(a),
          staffName: staffNames(a),
          startsAt: a.starts_at,
          amount: apptAmount(a),
          status: a.status,
          href: '/appointments',
          _raw: a,
        })),
    [todaysAppointments],
  )

  const handleStatusChange = useCallback(
    async (item, status) => {
      if (!canUpdateAppt || !item?._raw) return
      setUpdatingId(item.id)
      try {
        await apiPut(`/v1/appointments/${item.id}`, buildStatusUpdatePayload(item._raw, status))
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
    [canUpdateAppt],
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
          label: busy ? 'Updating…' : 'Start',
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
      if (['scheduled', 'confirmed', 'in-progress'].includes(status)) {
        actions.push({
          key: 'cancel',
          label: 'Cancel',
          disabled: busy,
          onClick: () => void handleStatusChange(item, 'cancelled'),
        })
      }
      return actions
    },
    [canUpdateAppt, updatingId, handleStatusChange],
  )

  const staffOverviewItems = useMemo(() => {
    const byStaff = new Map()

    for (const appt of todaysAppointments) {
      const lines = appt.services?.length
        ? appt.services
        : [{ staff_id: appt.staff_id, staff: appt.staff, price: apptAmount(appt) }]

      for (const line of lines) {
        const id = line.staff_id || line.staff?.id || appt.staff_id
        if (!id) continue
        const existing = byStaff.get(id) || {
          name: line.staff?.name || appt.staff?.name || `Staff #${id}`,
          appointments: 0,
          revenue: 0,
          busy: false,
        }
        existing.name = line.staff?.name || appt.staff?.name || existing.name
        existing.appointments += 1
        existing.revenue += Number(line.price ?? 0) || (lines.length === 1 ? apptAmount(appt) : 0)
        if (appt.status === 'in-progress') existing.busy = true
        byStaff.set(id, existing)
      }
    }

    const roster = canStaff ? staff : []
    if (roster.length) {
      return roster.map((member) => {
        const stats = byStaff.get(member.id) || { appointments: 0, revenue: 0, busy: false }
        let status = 'offline'
        if (member.is_active === false) status = 'leave'
        else if (stats.busy) status = 'busy'
        else if (member.is_active) status = 'available'
        return {
          id: member.id,
          name: member.name,
          role: member.role?.name || 'Staff',
          photo: member.photo_url || member.photo,
          status,
          appointments: stats.appointments,
          revenue: stats.revenue,
        }
      }).sort((a, b) => b.appointments - a.appointments || b.revenue - a.revenue)
    }

    return [...byStaff.entries()].map(([id, stats]) => ({
      id,
      name: stats.name || `Staff #${id}`,
      role: 'Staff',
      status: stats.busy ? 'busy' : 'available',
      appointments: stats.appointments,
      revenue: stats.revenue,
    }))
  }, [todaysAppointments, staff, canStaff])

  const staffPerfItems = useMemo(() => {
    return staffOverviewItems
      .filter((s) => s.appointments > 0 || s.revenue > 0)
      .slice(0, 6)
      .map((s) => ({
        id: s.id,
        name: s.name,
        role: s.role,
        photo: s.photo,
        servicesCompleted: s.appointments,
        revenue: s.revenue,
      }))
  }, [staffOverviewItems])

  const staffOverviewWithNames = useMemo(() => {
    if (!staff.length) return staffOverviewItems
    return staffOverviewItems.map((row) => {
      const member = staff.find((s) => Number(s.id) === Number(row.id))
      if (!member) return row
      return {
        ...row,
        name: member.name || row.name,
        role: member.role?.name || row.role,
        photo: member.photo_url || member.photo || row.photo,
      }
    })
  }, [staffOverviewItems, staff])

  const fallbackQueueItems = useMemo(
    () => [
      {
        key: 'waiting',
        label: 'Waiting now',
        value: pendingCheckIns,
        iconKey: 'waiting',
        hint: 'Due within 15 min',
      },
      {
        key: 'upcoming',
        label: 'Upcoming today',
        value: upcomingToday,
        iconKey: 'avg_wait',
        hint: 'Later today',
      },
      {
        key: 'in_progress',
        label: 'In service',
        value: inProgressCount,
        iconKey: 'checked_in',
        hint: 'Currently being served',
      },
      {
        key: 'walk_in',
        label: 'Walk-ins',
        value: walkInsToday,
        iconKey: 'walk_in',
        hint: 'Today',
      },
      {
        key: 'completed',
        label: 'Completed',
        value: completedToday.length,
        iconKey: 'avg_wait',
        hint: 'Finished today',
      },
    ],
    [pendingCheckIns, upcomingToday, inProgressCount, walkInsToday, completedToday.length],
  )

  const queueItems = useMemo(() => {
    if (canQueue && queueSnapshot?.summary) {
      return customerQueueItemsFromSnapshot(queueSnapshot.summary, {
        completedToday: canAppointments ? completedToday.length : null,
      })
    }

    return canAppointments ? fallbackQueueItems : []
  }, [canQueue, queueSnapshot, canAppointments, completedToday.length, fallbackQueueItems])

  const queueSubtitle = useMemo(() => {
    if (canQueue && queueSnapshot) {
      return liveQueueDashboardSubtitle(queueSnapshot)
    }

    return canAppointments ? 'Floor status from today’s appointments' : 'Floor status right now'
  }, [canQueue, queueSnapshot, canAppointments])

  const topServices = useMemo(() => {
    const map = new Map()
    for (const appt of todaysAppointments) {
      const lines = appt.services?.length
        ? appt.services
        : [{ service_id: appt.service_id, service: appt.service, price: apptAmount(appt) }]
      for (const line of lines) {
        const id = line.service_id || line.service?.id || line.service?.name
        if (!id && !line.service?.name) continue
        const name = line.service?.name || `Service #${id}`
        const key = id || name
        const existing = map.get(key) || { id: key, name, bookings: 0, revenue: 0 }
        existing.bookings += 1
        existing.revenue += Number(line.price ?? 0) || 0
        map.set(key, existing)
      }
    }
    return [...map.values()].sort((a, b) => b.bookings - a.bookings || b.revenue - a.revenue).slice(0, 6)
  }, [todaysAppointments])

  const pendingTasks = useMemo(() => {
    const tasks = []
        if (pendingCheckIns > 0) {
      tasks.push({
        id: 'checkins',
        title: 'Customers waiting now',
        description: 'Appointments due within 15 minutes',
        priority: pendingCheckIns > 5 ? 'high' : 'medium',
        count: pendingCheckIns,
        href: canQueue ? '/queue' : (canAppointments ? '/appointments' : undefined),
      })
    }
    if (cancelledToday.length > 0) {
      tasks.push({
        id: 'cancelled',
        title: 'Cancellations / no-shows',
        description: 'Review disrupted slots today',
        priority: 'low',
        count: cancelledToday.length,
        href: canAppointments ? '/appointments' : undefined,
      })
    }
    if (inProgressCount > 0) {
      tasks.push({
        id: 'inprogress',
        title: 'Services in progress',
        description: 'Monitor active appointments',
        priority: 'medium',
        count: inProgressCount,
        href: canQueue ? '/queue' : (canAppointments ? '/appointments' : undefined),
      })
    }
    return tasks
  }, [pendingCheckIns, cancelledToday.length, inProgressCount, canAppointments, canQueue])

  const quickActions = useMemo(
    () =>
      [
        canCreateAppt && {
          key: 'new-appt',
          label: 'New appointment',
          icon: CalendarPlus,
          to: '/appointments',
          color: 'amber',
        },
        canCreateAppt && {
          key: 'walk-in',
          label: 'Walk-in',
          icon: Footprints,
          to: '/appointments',
          color: 'teal',
        },
        canCreateCustomer && {
          key: 'add-customer',
          label: 'Add customer',
          icon: UserPlus,
          to: '/customers?create=1',
          color: 'indigo',
        },
        canAppointments && {
          key: 'calendar',
          label: 'Calendar',
          icon: CalendarDays,
          to: '/appointments',
          color: 'teal',
        },
        canQueue && {
          key: 'queue',
          label: 'Live queue',
          icon: ListOrdered,
          to: '/queue',
          color: 'violet',
        },
        canStaff && {
          key: 'staff',
          label: 'Manage staff',
          icon: Users,
          to: '/staff',
          color: 'rose',
        },
        canCatalog && {
          key: 'catalog',
          label: 'Services',
          icon: Layers,
          to: '/catalog',
          color: 'emerald',
        },
        canBranches && {
          key: 'branch',
          label: 'My branch',
          icon: MapPinned,
          to: '/branches',
          color: 'slate',
        },
        auth.can('roles.view') &&
          authHasModule(auth, 'roles') && {
            key: 'roles',
            label: 'Roles',
            icon: ShieldCheck,
      to: '/roles',
            color: 'slate',
          },
        auth.can('settings.view') && {
          key: 'settings',
          label: 'Settings',
          icon: Settings,
          to: '/settings',
          color: 'slate',
        },
      ].filter(Boolean),
    [
      canCreateAppt,
      canCreateCustomer,
      canAppointments,
      canQueue,
      canStaff,
      canCatalog,
      canBranches,
      auth,
    ],
  )

  const hour = new Date().getHours()
  const timeGreeting =
    hour < 12 ? 'Good morning' : hour < 17 ? 'Good afternoon' : 'Good evening'

  return (
    <div className="space-y-6">
      <HeroBanner
        tone="brand"
        title={branchName}
        greeting={`${timeGreeting}, ${greetingName}`}
        subtitle={subscriptionPageSubtitle(auth, `Branch ops for ${salonName}. Track today’s floor, staff, and revenue.`)}
        summaryCards={heroSummary}
        loading={loading}
        onRefresh={() => void load()}
        refreshing={loading}
        actions={
          canCreateAppt ? (
            <Link to="/appointments">
              <BaseButton size="sm" leftIcon={CalendarPlus}>
                New appointment
              </BaseButton>
            </Link>
          ) : null
        }
      />

      {error ? (
        <div className="rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-700">{error}</div>
      ) : null}

      <StatsGrid items={kpiItems} loading={loading} columns="grid-cols-1 sm:grid-cols-2 xl:grid-cols-5" />

      {(canAppointments || loading) && (
        <div className="grid grid-cols-1 gap-6 xl:grid-cols-3">
          <div className="xl:col-span-1">
            <RevenueChart
              title="Daily revenue"
              subtitle="Last 7 days"
              data={revenueSeries}
              loading={loading}
              formatMoney={fmt.money}
            />
          </div>
          <div className="xl:col-span-1">
            <AppointmentTrendChart
              title="Daily appointments"
              subtitle="Last 7 days"
              data={appointmentTrend}
              loading={loading}
              type="bar"
            />
          </div>
          <div className="xl:col-span-1">
            <AppointmentStatusChart summary={statusSummary} loading={loading} />
          </div>
        </div>
      )}

      <div className="grid grid-cols-1 gap-6 xl:grid-cols-5">
        <div className="xl:col-span-3">
          <AppointmentsList
            items={appointmentListItems}
            loading={loading}
            formatMoney={fmt.money}
            getQuickActions={getQuickActions}
            emptyMessage={
              canAppointments
                ? 'No appointments at this branch today.'
                : 'Appointments module is not available on your plan.'
            }
            viewAllTo={canAppointments ? '/appointments' : undefined}
          />
        </div>
        <div className="xl:col-span-2">
          <CustomerQueue
            items={canQueue || canAppointments ? queueItems : []}
            subtitle={queueSubtitle}
            loading={loading}
            error={canQueue ? queueError : ''}
            viewAllTo={canQueue ? '/queue' : undefined}
            emptyMessage={
              canQueue || canAppointments
                ? 'Queue is clear for now.'
                : 'Live queue is not available on your plan.'
            }
          />
        </div>
      </div>

      <div className="grid grid-cols-1 gap-6 lg:grid-cols-2 xl:grid-cols-3">
        <StaffOverview
          items={staffOverviewWithNames}
          loading={loading}
          formatMoney={fmt.money}
          emptyMessage={
            canStaff || canAppointments
              ? 'No staff activity for this branch yet.'
              : 'Staff module is not available on your plan.'
          }
        />
        <StaffPerformance
          title="Top performers today"
          subtitle="By appointments & revenue"
          items={staffPerfItems}
          loading={loading}
          formatMoney={fmt.money}
          viewAllTo={canStaff ? '/staff' : undefined}
        />
        <div className="space-y-6">
          <PendingTasks items={pendingTasks} loading={loading} />
          <QuickActions actions={quickActions} loading={loading} />
        </div>
          </div>

      {(canAppointments || loading) && (
        <TopServices
          title="Service performance"
          subtitle="Most booked at this branch today"
          items={topServices}
          loading={loading}
          formatMoney={fmt.money}
          viewAllTo={canCatalog ? '/catalog' : undefined}
        />
      )}
    </div>
  )
}
