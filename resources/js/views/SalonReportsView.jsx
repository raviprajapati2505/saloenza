import React, { useCallback, useEffect, useMemo, useRef, useState } from 'react'
import { Link } from 'react-router-dom'
import { format, subDays } from 'date-fns'
import PageHeader from '../components/ui/PageHeader.jsx'
import BaseBadge from '../components/ui/BaseBadge.jsx'
import RevenueChart from '../components/dashboard/RevenueChart.jsx'
import AppointmentTrendChart from '../components/dashboard/AppointmentTrendChart.jsx'
import TopServices from '../components/dashboard/TopServices.jsx'
import StaffPerformance from '../components/dashboard/StaffPerformance.jsx'
import {
  ReportFilters,
  ReportTabs,
  ReportKpiGrid,
  ReportTable,
  StatusChips,
} from '../components/reports/ReportChrome.jsx'
import ProfitLossPanel from '../components/reports/ProfitLossPanel.jsx'
import OutstandingPaymentsPanel from '../components/reports/OutstandingPaymentsPanel.jsx'
import BusinessClosePanel from '../components/reports/BusinessClosePanel.jsx'
import { useAuthStore } from '../stores/auth'
import { authHasModule, subscriptionPageSubtitle } from '../lib/subscriptionModules.js'
import { apiGet, fetchMasterList, parseList } from '../lib/apiHelpers'
import { fetchCustomers } from '../services/customerService.js'
import { fetchAnalyticsSummary, fetchOutstandingPaymentsReport, fetchBusinessCloseReport } from '../services/analyticsService.js'
import { customerPhoneDisplay, customerEmailDisplay } from '../lib/customerContact.js'
import { remindAppointmentPayment } from '../services/appointmentService.js'
import { fetchBranches } from '../services/branchService.js'
import {
  createExpense,
  deleteExpense,
  fetchExpenseCategories,
  fetchExpenses,
  fetchProfitLossReport,
} from '../services/expenseService.js'
import { useTenantFormatter } from '../hooks/useTenantFormatter.js'
import { revenueAmount, paymentMethodLabel, PAYMENT_STATUS_LABELS } from '../lib/appointmentPayments.js'

function apptAmount(appt) {
  return revenueAmount(appt)
}

function bookedAmount(appt) {
  if (!appt || ['cancelled', 'no-show'].includes(appt.status)) return 0
  return Number(appt.grand_total ?? appt.price ?? 0) || 0
}

function dateKey(iso) {
  if (!iso) return null
  return format(new Date(iso), 'yyyy-MM-dd')
}

function serviceNames(appt) {
  if (appt.services?.length) {
    return appt.services.map((s) => s.service?.name).filter(Boolean).join(', ') || 'Service'
  }
  return appt.service?.name || 'Service'
}

function staffName(appt) {
  if (appt.services?.length) {
    const names = [...new Set(appt.services.map((s) => s.staff?.name).filter(Boolean))]
    if (names.length) return names.join(', ')
  }
  return appt.staff?.name || '—'
}

const STATUS_VARIANT = {
  scheduled: 'info',
  confirmed: 'success',
  'in-progress': 'warning',
  completed: 'success',
  cancelled: 'danger',
  'no-show': 'danger',
}

/**
 * Salon owner reports — real appointment/customer/staff/branch data only.
 * Gated by analytics.view + analytics subscription module.
 */
export default function SalonReportsView() {
  const auth = useAuthStore()
  const fmt = useTenantFormatter()
  const canAppointments = auth.can('appointments.view') && authHasModule(auth, 'appointments')
  const canStaff = auth.can('staff.view') && authHasModule(auth, 'staff')
  const canCustomers = auth.can('customers.view') && authHasModule(auth, 'customers')
  const canBranches = auth.can('branches.view') && authHasModule(auth, 'settings')
  const canProfit = auth.can('analytics.view') && authHasModule(auth, 'analytics')
  const canOutstanding = canAppointments && canProfit
  const canExpenses = auth.can('expenses.view') && authHasModule(auth, 'analytics')
  const canManageExpenses = canExpenses && auth.can('expenses.manage')
  const canRemindPayments = auth.can('appointments.update') && authHasModule(auth, 'appointments')

  const todayStr = format(new Date(), 'yyyy-MM-dd')
  const defaultFrom = format(subDays(new Date(), 29), 'yyyy-MM-dd')

  const [from, setFrom] = useState(defaultFrom)
  const [to, setTo] = useState(todayStr)
  const [branchFilter, setBranchFilter] = useState('')
  const loadSeqRef = useRef(0)
  const [statusFilter, setStatusFilter] = useState('all')
  const [search, setSearch] = useState('')
  const [tab, setTab] = useState('overview')

  const [loading, setLoading] = useState(true)
  const [error, setError] = useState('')
  const [appointments, setAppointments] = useState([])
  const [staff, setStaff] = useState([])
  const [customers, setCustomers] = useState([])
  const [branches, setBranches] = useState([])
  const [summary, setSummary] = useState(null)
  const [profit, setProfit] = useState(null)
  const [expenses, setExpenses] = useState([])
  const [expenseCategories, setExpenseCategories] = useState([])
  const [savingExpense, setSavingExpense] = useState(false)
  const [expenseError, setExpenseError] = useState('')
  const [outstanding, setOutstanding] = useState(null)
  const [businessClose, setBusinessClose] = useState(null)
  const [remindingId, setRemindingId] = useState(null)

  const tabs = useMemo(() => {
    const list = [{ key: 'overview', label: 'Overview' }]
    if (canAppointments) {
      list.push({ key: 'appointments', label: 'Appointments' })
      list.push({ key: 'services', label: 'Services' })
      list.push({ key: 'payments', label: 'Payments' })
      if (canOutstanding) list.push({ key: 'outstanding', label: 'Outstanding' })
    }
    if (canProfit) {
      list.push({ key: 'day-close', label: 'Day close' })
      list.push({ key: 'profit', label: 'Profit & loss' })
    }
    if (canStaff) list.push({ key: 'staff', label: 'Staff' })
    if (canCustomers) list.push({ key: 'customers', label: 'Customers' })
    if (canBranches) list.push({ key: 'branches', label: 'Branches' })
    return list
  }, [canAppointments, canOutstanding, canProfit, canStaff, canCustomers, canBranches])

  const buildFilterParams = useCallback(() => {
    const params = { from, to }
    if (branchFilter) params.branch_id = Number(branchFilter)
    return params
  }, [from, to, branchFilter])

  const load = useCallback(async () => {
    const requestId = loadSeqRef.current + 1
    loadSeqRef.current = requestId
    setLoading(true)
    setError('')
    setSummary(null)
    try {
      const tasks = []

      if (canAppointments) {
        const params = buildFilterParams()
        tasks.push(
          fetchMasterList('/v1/appointments', 'appointments', params)
            .then((rows) => {
              if (loadSeqRef.current === requestId) setAppointments(rows)
            })
            .catch((err) => {
              if (loadSeqRef.current === requestId) {
                setAppointments([])
                setError(err?.response?.data?.message || err?.message || 'Unable to load appointments.')
              }
            }),
              fetchAnalyticsSummary(params)
            .then((nextSummary) => {
              if (loadSeqRef.current === requestId) setSummary(nextSummary)
            })
            .catch((err) => {
              if (loadSeqRef.current === requestId) {
                setSummary(null)
                setError((prev) => prev || err?.response?.data?.message || err?.message || 'Unable to load analytics summary.')
              }
            }),
        )
        if (canOutstanding) {
          tasks.push(
            fetchOutstandingPaymentsReport(params)
              .then((next) => {
                if (loadSeqRef.current === requestId) setOutstanding(next)
              })
              .catch(() => {
                if (loadSeqRef.current === requestId) setOutstanding(null)
              }),
          )
        }
      } else {
        setAppointments([])
        setSummary(null)
        setOutstanding(null)
      }

      if (canStaff) {
        tasks.push(
          apiGet('/v1/staff', { page: 1, per_page: 100 })
            .then((r) => setStaff(parseList(r, 'staff')))
            .catch(() => setStaff([])),
        )
      }

      if (canCustomers) {
        tasks.push(
          fetchCustomers({ page: 1, per_page: 100 })
            .then(setCustomers)
            .catch(() => setCustomers([])),
        )
      }

      if (canBranches) {
        tasks.push(
          fetchBranches()
            .then((r) => setBranches(r.branches || []))
            .catch(() => setBranches([])),
        )
      }

      if (canProfit) {
        tasks.push(
          fetchProfitLossReport(buildFilterParams())
            .then((next) => {
              if (loadSeqRef.current === requestId) setProfit(next)
            })
            .catch(() => {
              if (loadSeqRef.current === requestId) setProfit(null)
            }),
          fetchBusinessCloseReport(buildFilterParams())
            .then((next) => {
              if (loadSeqRef.current === requestId) setBusinessClose(next)
            })
            .catch(() => {
              if (loadSeqRef.current === requestId) setBusinessClose(null)
            }),
        )
      }

      if (canExpenses) {
        tasks.push(
          fetchExpenses(buildFilterParams())
            .then((rows) => {
              if (loadSeqRef.current === requestId) setExpenses(rows)
            })
            .catch(() => {
              if (loadSeqRef.current === requestId) setExpenses([])
            }),
          fetchExpenseCategories()
            .then(setExpenseCategories)
            .catch(() => setExpenseCategories([])),
        )
      }

      await Promise.all(tasks)
    } catch (err) {
      setError(err?.response?.data?.message || err?.message || 'Unable to load reports.')
    } finally {
      setLoading(false)
    }
  }, [canAppointments, canOutstanding, canStaff, canCustomers, canBranches, canProfit, canExpenses, buildFilterParams])

  useEffect(() => {
    void load()
  }, [load])

  useEffect(() => {
    if (!tabs.find((t) => t.key === tab)) setTab(tabs[0]?.key || 'overview')
  }, [tabs, tab])

  const applyFilters = () => {
    void load()
  }

  const handleCreateExpense = async (payload) => {
    setSavingExpense(true)
    setExpenseError('')
    try {
      await createExpense(payload)
      await load()
      return true
    } catch (err) {
      setExpenseError(err?.response?.data?.message || err?.message || 'Unable to save this expense.')
      return false
    } finally {
      setSavingExpense(false)
    }
  }

  const handleDeleteExpense = async (expense) => {
    if (!window.confirm(`Delete "${expense.title}"?`)) return
    setExpenseError('')
    try {
      await deleteExpense(expense.id)
      await load()
    } catch (err) {
      setExpenseError(err?.response?.data?.message || err?.message || 'Unable to delete this expense.')
    }
  }

  const handleRemindOutstanding = async (bill) => {
    if (!bill?.appointment_id) return
    setRemindingId(bill.appointment_id)
    try {
      await remindAppointmentPayment(bill.appointment_id)
      await load()
    } catch (err) {
      setError(err?.response?.data?.message || err?.message || 'Unable to send a payment reminder.')
    } finally {
      setRemindingId(null)
    }
  }

  const scopedAppointments = useMemo(() => {
    let rows = [...appointments]
    if (statusFilter !== 'all') {
      rows = rows.filter((a) => a.status === statusFilter)
    }
    if (search.trim() && tab === 'appointments') {
      const q = search.trim().toLowerCase()
      rows = rows.filter((a) =>
        String(a.customer?.name || '').toLowerCase().includes(q)
        || String(serviceNames(a)).toLowerCase().includes(q)
        || String(staffName(a)).toLowerCase().includes(q),
      )
    }
    return rows
  }, [appointments, statusFilter, search, tab])

  const clientCollected = useMemo(
    () => appointments.reduce((sum, a) => sum + apptAmount(a), 0),
    [appointments],
  )

  const summaryMatchesFilters = summary?.range?.from === from && summary?.range?.to === to
  const activeSummary = summaryMatchesFilters ? summary : null

  const totalRevenue = activeSummary?.collected_revenue ?? clientCollected
  const outstandingRevenue = activeSummary?.outstanding_revenue ?? 0
  const totalEarning = Number(totalRevenue || 0) + Number(outstandingRevenue || 0)

  const completedCount = useMemo(
    () => appointments.filter((a) => a.status === 'completed').length,
    [appointments],
  )

  const cancelledCount = useMemo(
    () => appointments.filter((a) => a.status === 'cancelled' || a.status === 'no-show').length,
    [appointments],
  )

  const kpis = useMemo(
    () => [
      { key: 'earning', label: 'Total earning', value: fmt.money(totalEarning), hint: 'Collected + outstanding' },
      { key: 'revenue', label: 'Collected', value: fmt.money(totalRevenue), hint: 'Payments received' },
      { key: 'outstanding', label: 'Outstanding', value: fmt.money(outstandingRevenue), hint: 'Balance due' },
      { key: 'appts', label: 'Appointments', value: activeSummary?.appointments_count ?? appointments.length },
      { key: 'completed', label: 'Completed', value: activeSummary?.completed_count ?? completedCount },
      { key: 'cancelled', label: 'Cancelled / no-show', value: activeSummary?.cancelled_count ?? cancelledCount },
      { key: 'customers', label: 'Customers', value: canCustomers ? customers.length : '—' },
      {
        key: 'avg',
        label: 'Avg collected',
        value: (activeSummary?.appointments_count ?? appointments.length)
          ? fmt.money(totalRevenue / (activeSummary?.appointments_count ?? appointments.length))
          : fmt.money(0),
      },
    ],
    [
      totalEarning,
      totalRevenue,
      outstandingRevenue,
      activeSummary,
      appointments.length,
      completedCount,
      cancelledCount,
      customers.length,
      canCustomers,
      fmt,
    ],
  )

  const revenueSeries = useMemo(() => {
    if (activeSummary?.daily_collected?.length) {
      return activeSummary.daily_collected.map((d) => ({
        label: d.label,
        amount: d.amount,
        value: d.appointments,
      }))
    }

    const start = new Date(from)
    const end = new Date(to)
    const days = []
    const cursor = new Date(start)
    let guard = 0
    while (cursor <= end && guard < 62) {
      const key = format(cursor, 'yyyy-MM-dd')
      const dayRows = appointments.filter((a) => dateKey(a.starts_at) === key)
      days.push({
        label: format(cursor, 'dd MMM'),
        amount: dayRows.reduce((sum, a) => sum + apptAmount(a), 0),
        value: dayRows.length,
      })
      cursor.setDate(cursor.getDate() + 1)
      guard += 1
    }
    return days
  }, [appointments, from, to, activeSummary])

  const appointmentTrend = useMemo(
    () => revenueSeries.map((d) => ({ label: d.label, value: d.value })),
    [revenueSeries],
  )

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
    return [...map.values()].sort((a, b) => b.revenue - a.revenue || b.bookings - a.bookings)
  }, [appointments])

  const staffPerf = useMemo(() => {
    const byStaff = new Map()
    for (const appt of appointments) {
      // Match Day close / Staff earnings: only count work already done or in progress.
      if (!['completed', 'in-progress'].includes(appt.status)) continue

      const serviceLines = appt.services?.length
        ? appt.services
        : (appt.staff_id || appt.staff?.id
          ? [{ staff_id: appt.staff_id, staff: appt.staff, price: Number(appt.services_total ?? appt.price ?? 0) || 0 }]
          : [])
      const productLines = (appt.products || []).map((line) => ({
        staff_id: line.staff_id,
        staff: line.staff,
        price: Number(line.line_total ?? 0) || 0,
      }))

      for (const line of [...serviceLines, ...productLines]) {
        const id = line.staff_id || line.staff?.id || appt.staff_id
        if (!id) continue
        const existing = byStaff.get(id) || {
          id,
          name: line.staff?.name || appt.staff?.name || `Staff #${id}`,
          servicesCompleted: 0,
          revenue: 0,
        }
        existing.name = line.staff?.name || appt.staff?.name || existing.name
        existing.servicesCompleted += 1
        existing.revenue += Number(line.price ?? 0) || 0
        byStaff.set(id, existing)
      }
    }
    for (const member of staff) {
      const row = byStaff.get(member.id)
      if (row) {
        row.role = member.role?.name || 'Staff'
        row.photo = member.photo_url || member.photo
      }
    }
    return [...byStaff.values()].sort((a, b) => b.revenue - a.revenue)
  }, [appointments, staff])

  const branchRows = useMemo(() => {
    return branches.map((b) => {
      const branchAppts = appointments.filter((a) => Number(a.branch_id) === Number(b.id))
      return {
        id: b.id,
        name: b.branch_name || `Branch #${b.id}`,
        city: b.city || '—',
        appointments: branchAppts.length,
        revenue: branchAppts.reduce((sum, a) => sum + apptAmount(a), 0),
        is_active: b.is_active,
      }
    }).sort((a, b) => b.revenue - a.revenue)
  }, [branches, appointments])

  const filteredCustomers = useMemo(() => {
    let rows = [...(customers || [])]
    if (search.trim() && tab === 'customers') {
      const q = search.trim().toLowerCase()
      rows = rows.filter((c) =>
        String(c.name || '').toLowerCase().includes(q)
        || String(c.email || '').toLowerCase().includes(q)
        || String(c.phone || '').toLowerCase().includes(q),
      )
    }
    return rows
  }, [customers, search, tab])

  return (
    <div className="space-y-6">
      <PageHeader
        title="Reports"
        subtitle={subscriptionPageSubtitle(auth, 'Revenue, appointments, staff worklog, day close, and customer performance for your salon.')}
      />

      <ReportFilters
        from={from}
        to={to}
        onFromChange={setFrom}
        onToChange={setTo}
        onApply={applyFilters}
        onRefresh={() => void load()}
        loading={loading}
        applyLabel="Apply range"
      >
        {canBranches && branches.length > 0 ? (
          <div>
            <label className="mb-1 block text-xs font-medium text-slate-600">Branch</label>
            <select
              value={branchFilter}
              onChange={(e) => setBranchFilter(e.target.value)}
              className="rounded-xl border border-slate-200 px-3 py-2 text-sm"
              aria-label="Filter by branch"
            >
              <option value="">All branches</option>
              {branches.map((b) => (
                <option key={b.id} value={b.id}>{b.branch_name}</option>
              ))}
            </select>
          </div>
        ) : null}
      </ReportFilters>

      {error ? (
        <div className="rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-700">{error}</div>
      ) : null}

      <ReportTabs tabs={tabs} active={tab} onChange={setTab} />

      {tab === 'overview' ? (
        <>
          <ReportKpiGrid items={kpis} loading={loading} />
          {canAppointments ? (
            <div className="grid grid-cols-1 gap-6 xl:grid-cols-2">
              <RevenueChart
                title="Revenue trend"
                subtitle={`${from} → ${to}`}
                data={revenueSeries}
                loading={loading}
                formatMoney={fmt.money}
              />
              <AppointmentTrendChart
                title="Appointment volume"
                subtitle="Daily bookings in range"
                data={appointmentTrend}
                loading={loading}
                type="bar"
              />
            </div>
          ) : (
            <div className="rounded-xl border border-dashed border-slate-200 bg-slate-50 px-4 py-10 text-center text-sm text-slate-500">
              Enable the appointments module to see revenue and booking charts.
            </div>
          )}
          <div className="grid grid-cols-1 gap-6 lg:grid-cols-2">
            <TopServices items={topServices.slice(0, 8)} loading={loading} formatMoney={fmt.money} />
            <StaffPerformance items={staffPerf.slice(0, 8)} loading={loading} formatMoney={fmt.money} />
          </div>
        </>
      ) : null}

      {tab === 'appointments' ? (
        <>
          <div className="space-y-3">
            <StatusChips
              value={statusFilter}
              onChange={setStatusFilter}
              options={[
                { value: 'all', label: 'All' },
                { value: 'scheduled', label: 'Scheduled' },
                { value: 'confirmed', label: 'Confirmed' },
                { value: 'in-progress', label: 'In progress' },
                { value: 'completed', label: 'Completed' },
                { value: 'cancelled', label: 'Cancelled' },
                { value: 'no-show', label: 'No show' },
              ]}
            />
            <input
              type="search"
              value={search}
              onChange={(e) => setSearch(e.target.value)}
              placeholder="Search customer, service, staff…"
              className="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm sm:max-w-md"
            />
          </div>
          <ReportTable
            loading={loading}
            rows={[...scopedAppointments].sort((a, b) => new Date(b.starts_at) - new Date(a.starts_at))}
            emptyMessage="No appointments in this range."
            columns={[
              {
                key: 'when',
                label: 'When',
                render: (r) => (
                  <div>
                    <p className="font-medium text-slate-900">
                      {r.starts_at
                        ? new Date(r.starts_at).toLocaleDateString('en-IN', { day: 'numeric', month: 'short' })
                        : '—'}
                    </p>
                    <p className="text-xs text-slate-400">
                      {r.starts_at
                        ? new Date(r.starts_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })
                        : ''}
                    </p>
                  </div>
                ),
              },
              { key: 'customer', label: 'Customer', render: (r) => r.customer?.name || 'Walk-in' },
              { key: 'service', label: 'Service', render: (r) => serviceNames(r) },
              { key: 'staff', label: 'Staff', render: (r) => staffName(r) },
              {
                key: 'branch',
                label: 'Branch',
                render: (r) => r.branch?.branch_name || r.branch?.name || '—',
              },
              {
                key: 'status',
                label: 'Status',
                render: (r) => (
                  <BaseBadge size="sm" variant={STATUS_VARIANT[r.status] || 'default'}>
                    {String(r.status || '—').replace(/-/g, ' ')}
                  </BaseBadge>
                ),
              },
              {
                key: 'payment',
                label: 'Payment',
                render: (r) => (
                  <BaseBadge size="sm" variant={r.payment_status === 'paid' ? 'success' : r.payment_status === 'partial' ? 'info' : 'warning'}>
                    {PAYMENT_STATUS_LABELS[r.payment_status] || r.payment_status || 'Unpaid'}
                  </BaseBadge>
                ),
              },
              { key: 'amount', label: 'Collected', render: (r) => fmt.money(apptAmount(r)) },
              { key: 'booked', label: 'Booked', render: (r) => fmt.money(bookedAmount(r)) },
            ]}
          />
        </>
      ) : null}

      {tab === 'day-close' ? (
        <BusinessClosePanel fmt={fmt} loading={loading} report={businessClose} />
      ) : null}

      {tab === 'profit' ? (
        <ProfitLossPanel
          fmt={fmt}
          loading={loading}
          report={profit}
          expenses={expenses}
          categories={expenseCategories}
          branches={branches}
          canManageExpenses={canManageExpenses}
          canChooseBranch={canBranches && !auth.isBranchScoped}
          savingExpense={savingExpense}
          expenseError={expenseError}
          onCreateExpense={handleCreateExpense}
          onDeleteExpense={handleDeleteExpense}
          defaultDate={todayStr}
        />
      ) : null}

      {tab === 'services' ? (
        <ReportTable
          loading={loading}
          rows={topServices}
          emptyMessage="No service bookings in this range."
          columns={[
            { key: 'name', label: 'Service', render: (r) => r.name },
            { key: 'bookings', label: 'Bookings', render: (r) => r.bookings },
            { key: 'revenue', label: 'Revenue', render: (r) => fmt.money(r.revenue) },
            {
              key: 'avg',
              label: 'Avg value',
              render: (r) => fmt.money(r.bookings ? r.revenue / r.bookings : 0),
            },
          ]}
        />
      ) : null}

      {tab === 'payments' ? (
        <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
          <ReportTable
            loading={loading}
            rows={activeSummary?.payment_methods ?? []}
            emptyMessage="No payments recorded in this range."
            columns={[
              { key: 'method', label: 'Method', render: (r) => paymentMethodLabel(r.method) },
              { key: 'amount', label: 'Collected', render: (r) => fmt.money(r.amount) },
            ]}
          />
          <ReportTable
            loading={loading}
            rows={Object.entries(activeSummary?.payment_status_counts ?? {}).map(([status, count]) => ({ status, count }))}
            emptyMessage="No payment status data."
            columns={[
              { key: 'status', label: 'Status', render: (r) => PAYMENT_STATUS_LABELS[r.status] || r.status },
              { key: 'count', label: 'Appointments', render: (r) => r.count },
            ]}
          />
        </div>
      ) : null}

      {tab === 'outstanding' ? (
        <OutstandingPaymentsPanel
          fmt={fmt}
          loading={loading}
          report={outstanding}
          canRemind={canRemindPayments}
          remindingId={remindingId}
          onRemind={handleRemindOutstanding}
        />
      ) : null}

      {tab === 'staff' ? (
        <ReportTable
          loading={loading}
          rows={staffPerf}
          emptyMessage="No staff activity in this range."
          columns={[
            { key: 'name', label: 'Staff', render: (r) => r.name },
            { key: 'role', label: 'Role', render: (r) => r.role || '—' },
            { key: 'services', label: 'Jobs', render: (r) => r.servicesCompleted ?? 0 },
            { key: 'revenue', label: 'Generated', render: (r) => fmt.money(r.revenue) },
          ]}
        />
      ) : null}

      {tab === 'customers' ? (
        <>
          <input
            type="search"
            value={search}
            onChange={(e) => setSearch(e.target.value)}
            placeholder="Search customers…"
            className="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm sm:max-w-md"
          />
          <ReportTable
            loading={loading}
            rows={filteredCustomers}
            emptyMessage="No customers found."
            columns={[
              {
                key: 'name',
                label: 'Name',
                render: (r) => (
                  <Link to={`/customers?id=${r.id}`} className="font-semibold text-brand-600 hover:text-brand-700">
                    {r.name || '—'}
                  </Link>
                ),
              },
              { key: 'phone', label: 'Phone', render: (r) => customerPhoneDisplay(r) || '—' },
              { key: 'email', label: 'Email', render: (r) => customerEmailDisplay(r) || '—' },
              {
                key: 'tags',
                label: 'Tags',
                render: (r) => (r.tags?.length
                  ? r.tags.map((t) => t.name).join(', ')
                  : '—'),
              },
              {
                key: 'active',
                label: 'Status',
                render: (r) => (
                  <BaseBadge size="sm" variant={r.is_active ? 'success' : 'default'}>
                    {r.is_active ? 'Active' : 'Inactive'}
                  </BaseBadge>
                ),
              },
            ]}
          />
        </>
      ) : null}

      {tab === 'branches' ? (
        <ReportTable
          loading={loading}
          rows={branchRows}
          emptyMessage="No branch data for this range."
          columns={[
            { key: 'name', label: 'Branch', render: (r) => r.name },
            { key: 'city', label: 'City', render: (r) => r.city },
            { key: 'appts', label: 'Appointments', render: (r) => r.appointments },
            { key: 'revenue', label: 'Revenue', render: (r) => fmt.money(r.revenue) },
            {
              key: 'active',
              label: 'Status',
              render: (r) => (
                <BaseBadge size="sm" variant={r.is_active ? 'success' : 'default'}>
                  {r.is_active ? 'Active' : 'Inactive'}
                </BaseBadge>
              ),
            },
          ]}
        />
      ) : null}
    </div>
  )
}
