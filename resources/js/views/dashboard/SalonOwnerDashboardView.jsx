import React, { useCallback, useEffect, useMemo, useState } from 'react'
import { Link } from 'react-router-dom'
import {
  CalendarDays,
  CalendarPlus,
  Layers,
  MapPinned,
  Settings,
  ShieldCheck,
  Users,
  UserPlus,
  CircleDollarSign,
  CheckCircle2,
  Clock,
  Building2,
} from 'lucide-react'
import { format, subDays } from 'date-fns'
import BaseButton from '../../components/ui/BaseButton.jsx'
import HeroBanner from '../../components/dashboard/HeroBanner.jsx'
import StatsGrid from '../../components/dashboard/StatsGrid.jsx'
import RevenueChart from '../../components/dashboard/RevenueChart.jsx'
import AppointmentTrendChart from '../../components/dashboard/AppointmentTrendChart.jsx'
import BranchPerformance from '../../components/dashboard/BranchPerformance.jsx'
import AppointmentsList from '../../components/dashboard/AppointmentsList.jsx'
import StaffPerformance from '../../components/dashboard/StaffPerformance.jsx'
import CustomerInsights from '../../components/dashboard/CustomerInsights.jsx'
import TopServices from '../../components/dashboard/TopServices.jsx'
import QuickActions from '../../components/dashboard/QuickActions.jsx'
import { useAuthStore } from '../../stores/auth'
import { authHasModule } from '../../lib/subscriptionModules.js'
import { createTenantFormatter } from '../../lib/tenantFormatting.js'
import { apiGet, fetchMasterList, parseList } from '../../lib/apiHelpers'
import { fetchCustomers } from '../../services/customerService.js'
import { fetchBranches } from '../../services/branchService.js'
import { revenueAmount } from '../../lib/appointmentPayments.js'

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

export default function SalonOwnerDashboardView() {
  const auth = useAuthStore()
  const fmt = useMemo(() => createTenantFormatter(auth), [auth])
  const limits = auth.subscriptionLimits || auth.tenant?.limits || {}
  const salonName = auth.tenant?.name || 'Your salon'
  const planName = auth.planName || auth.tenant?.plan?.name || 'No plan'
  const greetingName = auth.user?.firstname || auth.user?.name || 'Owner'

  const canBranches = auth.can('branches.view') && authHasModule(auth, 'settings')
  const canStaff = auth.can('staff.view') && authHasModule(auth, 'staff')
  const canAppointments = auth.can('appointments.view') && authHasModule(auth, 'appointments')
  const canCustomers = auth.can('customers.view') && authHasModule(auth, 'customers')
  const canCatalog = (auth.can('services.view') || auth.can('products.view')) && authHasModule(auth, 'catalog')
  const canCreateAppt = auth.can('appointments.create') && authHasModule(auth, 'appointments')
  const canCreateStaff = auth.can('staff.create') && authHasModule(auth, 'staff')
  const canCreateCustomer = auth.can('customers.manage') && authHasModule(auth, 'customers')

  const [loading, setLoading] = useState(true)
  const [error, setError] = useState('')
  const [branches, setBranches] = useState([])
  const [staff, setStaff] = useState([])
  const [appointments, setAppointments] = useState([])
  const [customers, setCustomers] = useState([])

  const todayStr = format(new Date(), 'yyyy-MM-dd')
  const weekFrom = format(subDays(new Date(), 6), 'yyyy-MM-dd')

  const load = useCallback(async () => {
    setLoading(true)
    setError('')
    try {
      const tasks = []

      tasks.push(
        canBranches
          ? fetchBranches().then((r) => setBranches(r.branches || [])).catch(() => setBranches([]))
          : Promise.resolve(setBranches([])),
      )
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
        canCustomers
          ? fetchCustomers({ page: 1, per_page: 100 }).then(setCustomers)
              .catch(() => setCustomers([]))
          : Promise.resolve(setCustomers([])),
      )

      await Promise.all(tasks)
    } catch (err) {
      setError(err?.response?.data?.message || err?.message || 'Unable to load salon dashboard.')
    } finally {
      setLoading(false)
    }
  }, [canBranches, canStaff, canAppointments, canCustomers, weekFrom, todayStr])

  useEffect(() => {
    void load()
  }, [load])

  const todaysAppointments = useMemo(
    () => appointments.filter((a) => dateKey(a.starts_at) === todayStr),
    [appointments, todayStr],
  )

  const completedToday = useMemo(
    () => todaysAppointments.filter((a) => a.status === 'completed'),
    [todaysAppointments],
  )

  const todayRevenue = useMemo(
    () => todaysAppointments.reduce((sum, a) => sum + apptAmount(a), 0),
    [todaysAppointments],
  )

  const weekRevenue = useMemo(
    () => appointments.reduce((sum, a) => sum + apptAmount(a), 0),
    [appointments],
  )

  const completionRate = useMemo(() => {
    if (!todaysAppointments.length) return null
    return Math.round((completedToday.length / todaysAppointments.length) * 100)
  }, [todaysAppointments, completedToday])

  const upcomingCount = useMemo(
    () => todaysAppointments.filter((a) => ['scheduled', 'confirmed'].includes(a.status)).length,
    [todaysAppointments],
  )

  const heroSummary = useMemo(
    () => [
      { key: 'revenue', label: "Today's revenue", value: fmt.money(todayRevenue) },
      { key: 'appts', label: "Today's appointments", value: todaysAppointments.length },
      {
        key: 'completion',
        label: 'Completion rate',
        value: completionRate == null ? '—' : `${completionRate}%`,
      },
      {
        key: 'branches',
        label: 'Branches',
        value: `${limits.branches_used ?? branches.length} / ${limits.max_branches ?? '∞'}`,
      },
    ],
    [fmt, todayRevenue, todaysAppointments.length, completionRate, limits, branches.length],
  )

  const kpiItems = useMemo(
    () => [
      {
        key: 'today_rev',
        title: "Today's revenue",
        value: fmt.money(todayRevenue),
        icon: CircleDollarSign,
        color: 'teal',
      },
      {
        key: 'week_rev',
        title: '7-day revenue',
        value: fmt.money(weekRevenue),
        icon: CircleDollarSign,
        color: 'blue',
      },
      {
        key: 'today_appts',
        title: "Today's appointments",
        value: todaysAppointments.length,
        icon: CalendarDays,
        color: 'indigo',
      },
      {
        key: 'upcoming',
        title: 'Upcoming today',
        value: upcomingCount,
        icon: Clock,
        color: 'amber',
      },
      {
        key: 'completed',
        title: 'Completed today',
        value: completedToday.length,
        icon: CheckCircle2,
        color: 'teal',
      },
      {
        key: 'customers',
        title: 'Customers',
        value: canCustomers ? customers.length : '—',
        icon: Users,
        color: 'indigo',
      },
      {
        key: 'staff',
        title: 'Staff used',
        value: `${limits.staff_used ?? staff.length} / ${limits.max_staff ?? '∞'}`,
        icon: Users,
        color: 'rose',
      },
      {
        key: 'plan',
        title: 'Active plan',
        value: planName,
        icon: Building2,
        color: 'amber',
      },
    ],
    [
      fmt,
      todayRevenue,
      weekRevenue,
      todaysAppointments.length,
      upcomingCount,
      completedToday.length,
      customers.length,
      canCustomers,
      limits,
      staff.length,
      planName,
    ],
  )

  const revenueSeries = useMemo(() => {
    const days = Array.from({ length: 7 }, (_, i) => {
      const d = subDays(new Date(), 6 - i)
      const key = format(d, 'yyyy-MM-dd')
      const dayAppts = appointments.filter((a) => dateKey(a.starts_at) === key)
      return {
        label: format(d, 'dd MMM'),
        date: key,
        amount: dayAppts.reduce((sum, a) => sum + apptAmount(a), 0),
        value: dayAppts.length,
      }
    })
    return days
  }, [appointments])

  const appointmentTrend = useMemo(
    () => revenueSeries.map((d) => ({ label: d.label, value: d.value })),
    [revenueSeries],
  )

  const branchItems = useMemo(() => {
    if (!branches.length) return []
    const mapped = branches.map((b) => {
      const branchAppts = appointments.filter((a) => Number(a.branch_id) === Number(b.id))
      const staffCount = staff.filter((s) => Number(s.branch_id || s.branch?.id) === Number(b.id)).length
      return {
        id: b.id,
        name: b.branch_name || b.name || `Branch #${b.id}`,
        revenue: branchAppts.reduce((sum, a) => sum + apptAmount(a), 0),
        appointments: branchAppts.length,
        staffCount,
        isTop: false,
      }
    })
    const topRevenue = Math.max(0, ...mapped.map((b) => b.revenue))
    return mapped
      .map((b) => ({ ...b, isTop: topRevenue > 0 && b.revenue === topRevenue }))
      .sort((a, b) => b.revenue - a.revenue)
  }, [branches, appointments, staff])

  const appointmentListItems = useMemo(
    () =>
      [...todaysAppointments]
        .sort((a, b) => new Date(a.starts_at) - new Date(b.starts_at))
        .slice(0, 8)
        .map((a) => ({
          id: a.id,
          customerName: a.customer?.name || 'Walk-in',
          serviceName: serviceNames(a),
          staffName: staffNames(a),
          startsAt: a.starts_at,
          amount: apptAmount(a),
          status: a.status,
          href: '/appointments',
        })),
    [todaysAppointments],
  )

  const staffPerfItems = useMemo(() => {
    const byStaff = new Map()

    for (const appt of appointments) {
      const lines = appt.services?.length
        ? appt.services
        : [{ staff_id: appt.staff_id, staff: appt.staff, price: apptAmount(appt) }]

      for (const line of lines) {
        const id = line.staff_id || line.staff?.id || appt.staff_id
        if (!id) continue
        const name = line.staff?.name || appt.staff?.name || `Staff #${id}`
        const existing = byStaff.get(id) || { id, name, servicesCompleted: 0, revenue: 0 }
        existing.servicesCompleted += 1
        existing.revenue += Number(line.price ?? 0) || (lines.length === 1 ? apptAmount(appt) : 0)
        byStaff.set(id, existing)
      }
    }

    // Enrich with role from staff roster
    for (const member of staff) {
      const row = byStaff.get(member.id)
      if (row) {
        row.role = member.role?.name || member.role || 'Staff'
        row.photo = member.photo_url || member.photo
      } else {
        byStaff.set(member.id, {
          id: member.id,
          name: member.name,
          role: member.role?.name || 'Staff',
          photo: member.photo_url || member.photo,
          servicesCompleted: 0,
          revenue: 0,
        })
      }
    }

    return [...byStaff.values()]
      .sort((a, b) => b.revenue - a.revenue || b.servicesCompleted - a.servicesCompleted)
      .slice(0, 6)
  }, [appointments, staff])

  const customerInsightItems = useMemo(() => {
    if (!canCustomers) return []
    const returning = customers.filter((c) => (c.appointments_count ?? 0) >= 2).length
    const tagged = customers.filter((c) => (c.tags?.length ?? 0) > 0).length
    const withEmail = customers.filter((c) => c.email).length
    return [
      { key: 'total', label: 'Total customers', value: customers.length, iconKey: 'total', color: 'teal', linkTo: '/customers' },
      { key: 'returning', label: 'Returning', value: returning, iconKey: 'returning', color: 'emerald', hint: '2+ visits', linkTo: '/customers?segment=returning' },
      { key: 'tagged', label: 'Tagged', value: tagged, iconKey: 'loyal', color: 'indigo', hint: 'Has segment tag', linkTo: '/customers' },
      { key: 'email', label: 'With email', value: withEmail, iconKey: 'membership', color: 'amber', linkTo: '/customers' },
    ]
  }, [canCustomers, customers])

  const topServices = useMemo(() => {
    const map = new Map()
    for (const appt of appointments) {
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
  }, [appointments])

  const quickActions = useMemo(
    () =>
      [
        canCreateAppt && {
          key: 'new-appt',
          label: 'New appointment',
          icon: CalendarPlus,
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
        canCreateStaff && {
          key: 'add-staff',
          label: 'Add staff',
          icon: Users,
          to: '/staff',
          color: 'amber',
        },
        canCatalog && {
          key: 'catalog',
          label: 'Services',
          icon: Layers,
          to: '/catalog',
          color: 'emerald',
        },
        canBranches && {
          key: 'branches',
          label: 'Branches',
          icon: MapPinned,
          to: '/branches',
          color: 'rose',
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
        canAppointments && {
          key: 'calendar',
          label: 'Calendar',
          icon: CalendarDays,
          to: '/appointments',
          color: 'teal',
        },
      ].filter(Boolean),
    [canCreateAppt, canCreateCustomer, canCreateStaff, canCatalog, canBranches, canAppointments, auth],
  )

  const greetingHour = new Date().getHours()
  const timeGreeting =
    greetingHour < 12 ? 'Good morning' : greetingHour < 17 ? 'Good afternoon' : 'Good evening'

  return (
    <div className="space-y-6">
      <HeroBanner
        title={salonName}
        greeting={`${timeGreeting}, ${greetingName}`}
        subtitle={`Plan · ${planName}. Here's how your salon is performing today.`}
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

      {auth.tenant?.activation_pending ? (
        <div className="rounded-xl border border-brand-200 bg-brand-50 px-4 py-3 text-sm text-brand-800">
          Your salon activation is pending. Some features may be limited until approval.
        </div>
      ) : null}

      {error ? (
        <div className="rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-700">{error}</div>
      ) : null}

      <StatsGrid items={kpiItems} loading={loading} />

      {(canAppointments || loading) && (
        <div className="grid grid-cols-1 gap-6 xl:grid-cols-2">
          <RevenueChart data={revenueSeries} loading={loading} formatMoney={fmt.money} />
          <AppointmentTrendChart data={appointmentTrend} loading={loading} type="bar" />
        </div>
      )}

      <BranchPerformance items={branchItems} loading={loading} hideWhenSingle formatMoney={fmt.money} />

      <div className="grid grid-cols-1 gap-6 xl:grid-cols-5">
        <div className="xl:col-span-3">
          <AppointmentsList
            items={appointmentListItems}
            loading={loading}
            formatMoney={fmt.money}
            emptyMessage={
              canAppointments
                ? 'No appointments scheduled for today.'
                : 'Appointments module is not available on your plan.'
            }
            viewAllTo={canAppointments ? '/appointments' : undefined}
          />
        </div>
        <div className="xl:col-span-2">
          <QuickActions actions={quickActions} loading={loading} />
        </div>
      </div>

      <div className="grid grid-cols-1 gap-6 lg:grid-cols-2">
        <StaffPerformance
          items={canStaff || canAppointments ? staffPerfItems : []}
          loading={loading}
          formatMoney={fmt.money}
          emptyMessage={
            canStaff || canAppointments
              ? 'No staff activity in the last 7 days.'
              : 'Staff module is not available on your plan.'
          }
          viewAllTo={canStaff ? '/staff' : undefined}
        />
        <TopServices
          items={canAppointments ? topServices : []}
          loading={loading}
          formatMoney={fmt.money}
          emptyMessage={
            canAppointments
              ? 'No service bookings in the last 7 days.'
              : 'Appointments module is required for service rankings.'
          }
          viewAllTo={canCatalog ? '/catalog' : undefined}
        />
      </div>

      {(canCustomers || loading) && (
        <CustomerInsights items={customerInsightItems} loading={loading} />
      )}
    </div>
  )
}
