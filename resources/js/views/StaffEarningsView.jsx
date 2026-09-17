import React, { useCallback, useEffect, useMemo, useState } from 'react'
import { format, startOfMonth, subMonths } from 'date-fns'
import {
  CalendarRange,
  Download,
  CircleDollarSign,
  RefreshCw,
  UserRound,
} from 'lucide-react'
import PageHeader from '../components/ui/PageHeader.jsx'
import BaseButton from '../components/ui/BaseButton.jsx'
import BaseInput from '../components/ui/BaseInput.jsx'
import BaseSelect from '../components/ui/BaseSelect.jsx'
import BaseBadge from '../components/ui/BaseBadge.jsx'
import StatsGrid from '../components/dashboard/StatsGrid.jsx'
import { useAuthStore } from '../stores/auth'
import { authHasModule, subscriptionPageSubtitle } from '../lib/subscriptionModules.js'
import { useTenantFormatter } from '../hooks/useTenantFormatter.js'
import { fetchStaffEarnings } from '../services/staffEarningsService.js'
import { fetchMasterList, parseList } from '../lib/apiHelpers'
import { customerPhoneDisplay } from '../lib/customerContact.js'
import { pushToast } from '../stores/toast.js'

const PERIOD_OPTIONS = [
  { value: 'today', label: 'Today' },
  { value: 'current_month', label: 'Current month' },
  { value: 'previous_month', label: 'Previous month' },
  { value: 'custom', label: 'Custom range' },
]

const STATUS_VARIANT = {
  completed: 'success',
  'in-progress': 'warning',
  scheduled: 'info',
  confirmed: 'info',
}

export default function StaffEarningsView() {
  const auth = useAuthStore()
  const fmt = useTenantFormatter()
  const canViewTeam = auth.can('staff.view') && authHasModule(auth, 'staff')
  const canSeeCustomers = auth.can('customers.view')

  const [period, setPeriod] = useState('today')
  const [from, setFrom] = useState(format(new Date(), 'yyyy-MM-dd'))
  const [to, setTo] = useState(format(new Date(), 'yyyy-MM-dd'))
  const [staffId, setStaffId] = useState(auth.user?.id ? String(auth.user.id) : '')
  const [staffOptions, setStaffOptions] = useState([])
  const [search, setSearch] = useState('')
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState('')
  const [report, setReport] = useState(null)

  useEffect(() => {
    if (!canViewTeam) return
    void fetchMasterList('/v1/staff', 'staff')
      .then((rows) => setStaffOptions(rows || []))
      .catch(() => setStaffOptions([]))
  }, [canViewTeam])

  const load = useCallback(async () => {
    setLoading(true)
    setError('')
    try {
      const params = {
        search: search.trim() || undefined,
        staff_id: staffId ? Number(staffId) : undefined,
      }

      if (period === 'custom') {
        params.from = from
        params.to = to
      } else {
        params.preset = period
      }

      const next = await fetchStaffEarnings(params)
      setReport(next)
    } catch (err) {
      setError(err?.response?.data?.message || 'Unable to load earnings report.')
      setReport(null)
    } finally {
      setLoading(false)
    }
  }, [period, from, to, staffId, search])

  useEffect(() => {
    void load()
  }, [load])

  useEffect(() => {
    if (period === 'today') {
      const today = format(new Date(), 'yyyy-MM-dd')
      setFrom(today)
      setTo(today)
    } else if (period === 'current_month') {
      setFrom(format(startOfMonth(new Date()), 'yyyy-MM-dd'))
      setTo(format(new Date(), 'yyyy-MM-dd'))
    } else if (period === 'previous_month') {
      const prev = subMonths(new Date(), 1)
      setFrom(format(startOfMonth(prev), 'yyyy-MM-dd'))
      setTo(format(new Date(prev.getFullYear(), prev.getMonth() + 1, 0), 'yyyy-MM-dd'))
    }
  }, [period])

  const summary = report?.summary ?? {}
  const lines = report?.lines ?? []

  const kpiItems = useMemo(
    () => [
      {
        key: 'completed',
        title: 'Appointments completed',
        value: summary.appointments_completed ?? 0,
        icon: CalendarRange,
        color: 'teal',
      },
      {
        key: 'services',
        title: 'Jobs performed',
        value: summary.services_performed ?? 0,
        icon: UserRound,
        color: 'indigo',
      },
      {
        key: 'hours',
        title: 'Hours worked',
        value: summary.hours_worked ?? 0,
        icon: CalendarRange,
        color: 'violet',
      },
      {
        key: 'commission',
        title: 'Commission earned',
        value: fmt.money(summary.commission_earned ?? 0),
        icon: CircleDollarSign,
        color: 'emerald',
      },
    ],
    [fmt, summary],
  )

  const exportCsv = () => {
    if (!lines.length) {
      pushToast('Nothing to export for this period.', 'info')
      return
    }

    const headers = [
      'Date',
      'Time',
      'Job',
      'Status',
      'Generated',
      'Collected share',
      'Commission',
      ...(canSeeCustomers ? ['Customer', 'Phone'] : []),
    ]

    const rows = lines.map((line) => [
      line.date || '',
      line.starts_at ? format(new Date(line.starts_at), 'p') : '',
      line.service_name || '',
      line.status || '',
      line.revenue ?? 0,
      line.collected ?? 0,
      line.commission ?? 0,
      ...(canSeeCustomers ? [line.customer?.name || '', customerPhoneDisplay(line.customer) || ''] : []),
    ])

    const csv = [headers, ...rows]
      .map((row) => row.map((cell) => `"${String(cell).replace(/"/g, '""')}"`).join(','))
      .join('\n')

    const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' })
    const url = URL.createObjectURL(blob)
    const anchor = document.createElement('a')
    anchor.href = url
    anchor.download = `staff-earnings-${report?.period?.from || 'report'}.csv`
    anchor.click()
    URL.revokeObjectURL(url)
  }

  return (
    <div className="space-y-6">
      <PageHeader
        title="Work & earnings"
        subtitle={subscriptionPageSubtitle(
          auth,
          report?.staff?.name
            ? `${report.staff.name} · daily worklog & ${report.staff.commission_rate ?? 0}% commission`
            : 'Track daily worklog, revenue, and commission.',
        )}
        actions={(
          <div className="flex flex-wrap gap-2">
            <BaseButton variant="secondary" size="sm" leftIcon={Download} onClick={exportCsv}>
              Export CSV
            </BaseButton>
            <BaseButton variant="secondary" size="sm" leftIcon={RefreshCw} onClick={() => void load()} loading={loading}>
              Refresh
            </BaseButton>
          </div>
        )}
      />

      <div className="grid gap-4 rounded-[18px] border border-slate-200 bg-white p-5 shadow-[0_8px_30px_rgb(0,0,0,0.04)] lg:grid-cols-4">
        <BaseSelect
          label="Period"
          value={period}
          onChange={(event) => setPeriod(event.target.value)}
          options={PERIOD_OPTIONS}
        />
        {canViewTeam ? (
          <BaseSelect
            label="Staff member"
            value={staffId}
            onChange={(event) => setStaffId(event.target.value)}
            options={[
              { value: String(auth.user?.id || ''), label: 'Me' },
              ...staffOptions
                .filter((member) => String(member.id) !== String(auth.user?.id))
                .map((member) => ({ value: String(member.id), label: member.name })),
            ]}
          />
        ) : null}
        {period === 'custom' ? (
          <>
            <BaseInput label="From" type="date" value={from} onChange={(event) => setFrom(event.target.value)} />
            <BaseInput label="To" type="date" value={to} onChange={(event) => setTo(event.target.value)} />
          </>
        ) : (
          <div className="lg:col-span-2 flex items-end">
            <p className="text-sm text-slate-500">
              Showing {report?.period?.from || '—'} to {report?.period?.to || '—'}
            </p>
          </div>
        )}
        <div className={period === 'custom' ? 'lg:col-span-4' : 'lg:col-span-2'}>
          <BaseInput
            label="Search"
            value={search}
            onChange={(event) => setSearch(event.target.value)}
            placeholder="Service, customer, status…"
          />
        </div>
      </div>

      {error ? (
        <div className="rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-700">{error}</div>
      ) : null}

      <StatsGrid items={kpiItems} loading={loading} />

      <div className="overflow-hidden rounded-[18px] border border-slate-200 bg-white shadow-[0_8px_30px_rgb(0,0,0,0.04)]">
        <div className="border-b border-slate-100 px-5 py-4">
          <h3 className="text-base font-semibold text-slate-900">Detailed report</h3>
          <p className="mt-0.5 text-sm text-slate-500">Daily hisab: jobs, hours, generated revenue, and commission</p>
        </div>

        {loading ? (
          <div className="space-y-3 p-5">
            {Array.from({ length: 6 }).map((_, index) => (
              <div key={index} className="h-14 animate-pulse rounded-xl bg-slate-50" />
            ))}
          </div>
        ) : null}

        {!loading && lines.length === 0 ? (
          <div className="px-5 py-12 text-center text-sm text-slate-500">No work or earnings found for this period.</div>
        ) : null}

        {!loading && lines.length > 0 ? (
          <div className="overflow-x-auto">
            <table className="min-w-full text-sm">
              <thead className="bg-slate-50 text-left text-xs uppercase tracking-wider text-slate-500">
                <tr>
                  <th className="px-5 py-3">Date & time</th>
                  <th className="px-5 py-3">Job</th>
                  {canSeeCustomers ? <th className="px-5 py-3">Customer</th> : null}
                  <th className="px-5 py-3">Status</th>
                  <th className="px-5 py-3 text-right">Generated</th>
                  <th className="px-5 py-3 text-right">Collected share</th>
                  <th className="px-5 py-3 text-right">Commission</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-slate-100">
                {lines.map((line, index) => (
                  <tr key={`${line.appointment_id}-${index}`} className="hover:bg-slate-50/80">
                    <td className="px-5 py-4">
                      <p className="font-medium text-slate-900">
                        {line.starts_at ? format(new Date(line.starts_at), 'd MMM yyyy') : '—'}
                      </p>
                      <p className="text-xs text-slate-500">
                        {line.starts_at ? format(new Date(line.starts_at), 'p') : '—'}
                      </p>
                    </td>
                    <td className="px-5 py-4 text-slate-700">{line.service_name}</td>
                    {canSeeCustomers ? (
                      <td className="px-5 py-4">
                        <p className="font-medium text-slate-900">{line.customer?.name || 'Walk-in'}</p>
                        {customerPhoneDisplay(line.customer) ? (
                          <p className="text-xs text-slate-500">{customerPhoneDisplay(line.customer)}</p>
                        ) : null}
                      </td>
                    ) : null}
                    <td className="px-5 py-4">
                      <BaseBadge variant={STATUS_VARIANT[line.status] || 'default'} size="sm">
                        {(line.status || 'unknown').replace(/-/g, ' ')}
                      </BaseBadge>
                    </td>
                    <td className="px-5 py-4 text-right font-medium text-slate-900">{fmt.money(line.revenue ?? 0)}</td>
                    <td className="px-5 py-4 text-right text-slate-700">{fmt.money(line.collected ?? 0)}</td>
                    <td className="px-5 py-4 text-right font-semibold text-brand-600">{fmt.money(line.commission ?? 0)}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        ) : null}
      </div>
    </div>
  )
}
