import React, { useEffect, useMemo, useState } from 'react'
import { format, startOfMonth, endOfMonth, subMonths } from 'date-fns'
import { Calendar, CircleDollarSign, Scissors, TrendingUp } from 'lucide-react'
import PageHeader from '../../components/ui/PageHeader.jsx'
import BaseSelect from '../../components/ui/BaseSelect.jsx'
import StatsGrid from '../../components/dashboard/StatsGrid.jsx'
import { fetchStaffEarnings } from '../../services/staffEarningsService.js'
import { useTenantFormatter } from '../../hooks/useTenantFormatter.js'

function monthOptions(count = 6) {
  const options = []
  const now = new Date()

  for (let i = 0; i < count; i += 1) {
    const date = subMonths(now, i)
    options.push({
      value: format(date, 'yyyy-MM'),
      label: format(date, 'MMMM yyyy'),
    })
  }

  return options
}

export default function PerformanceView({ staffId, staffName }) {
  const fmt = useTenantFormatter()
  const [selectedMonth, setSelectedMonth] = useState(format(new Date(), 'yyyy-MM'))
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState('')
  const [report, setReport] = useState(null)

  const monthChoices = useMemo(() => monthOptions(), [])

  useEffect(() => {
    if (!staffId) {
      setReport(null)
      setLoading(false)
      return
    }

    const [year, month] = selectedMonth.split('-').map(Number)
    const from = format(startOfMonth(new Date(year, month - 1, 1)), 'yyyy-MM-dd')
    const to = format(endOfMonth(new Date(year, month - 1, 1)), 'yyyy-MM-dd')

    setLoading(true)
    setError('')

    void fetchStaffEarnings({ staff_id: staffId, from, to })
      .then(setReport)
      .catch((err) => {
        setReport(null)
        setError(err?.response?.data?.message || 'Unable to load performance data.')
      })
      .finally(() => setLoading(false))
  }, [staffId, selectedMonth])

  const serviceBreakdown = useMemo(() => {
    const lines = report?.lines ?? []
    const totals = new Map()

    for (const line of lines) {
      const name = line.service_name || 'Service'
      const current = totals.get(name) || { name, count: 0, revenue: 0 }
      current.count += 1
      current.revenue += Number(line.revenue) || 0
      totals.set(name, current)
    }

    const rows = Array.from(totals.values()).sort((a, b) => b.revenue - a.revenue)
    const totalRevenue = rows.reduce((sum, row) => sum + row.revenue, 0)

    return rows.map((row) => ({
      ...row,
      share: totalRevenue > 0 ? Math.round((row.revenue / totalRevenue) * 1000) / 10 : 0,
    }))
  }, [report])

  const summary = report?.summary ?? {}

  const kpiItems = [
    {
      key: 'revenue',
      title: 'Revenue generated',
      value: fmt.money(summary.revenue_generated ?? 0),
      icon: CircleDollarSign,
      color: 'brand',
    },
    {
      key: 'services',
      title: 'Jobs performed',
      value: summary.services_performed ?? 0,
      icon: Scissors,
      color: 'indigo',
    },
    {
      key: 'hours',
      title: 'Hours worked',
      value: summary.hours_worked ?? 0,
      icon: Calendar,
      color: 'violet',
    },
    {
      key: 'commission',
      title: 'Commission earned',
      value: fmt.money(summary.commission_earned ?? 0),
      icon: TrendingUp,
      color: 'amber',
    },
  ]

  return (
    <div className="space-y-6">
      <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <PageHeader
          title={staffName ? `${staffName}'s performance` : 'Staff performance'}
          subtitle="Daily worklog and earnings from completed and in-progress appointments."
        />
        <div className="w-full sm:w-48">
          <BaseSelect
            label="Period"
            value={selectedMonth}
            onChange={(event) => setSelectedMonth(event.target.value)}
            options={monthChoices}
          />
        </div>
      </div>

      {error ? (
        <div className="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">{error}</div>
      ) : null}

      <StatsGrid items={kpiItems} loading={loading} />

      {!loading && report?.days?.length ? (
        <div className="rounded-[18px] border border-slate-200 bg-white p-5 shadow-sm">
          <h4 className="text-sm font-bold uppercase tracking-wider text-slate-900">Daily worklog</h4>
          <p className="mt-1 text-xs text-slate-500">Jobs, hours, generated revenue, collected share, and commission by day.</p>
          <div className="mt-4 overflow-x-auto">
            <table className="w-full min-w-[520px] text-left text-sm">
              <thead>
                <tr className="border-b border-slate-100 text-xs font-bold uppercase tracking-wider text-slate-400">
                  <th className="pb-3">Date</th>
                  <th className="pb-3 text-center">Jobs</th>
                  <th className="pb-3 text-center">Hours</th>
                  <th className="pb-3 text-right">Generated</th>
                  <th className="pb-3 text-right">Collected</th>
                  <th className="pb-3 text-right">Commission</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-slate-100">
                {report.days.map((day) => (
                  <tr key={day.date}>
                    <td className="py-3 font-semibold text-slate-900">{day.date}</td>
                    <td className="py-3 text-center text-slate-600">{day.services}</td>
                    <td className="py-3 text-center text-slate-600">{day.hours_worked}</td>
                    <td className="py-3 text-right font-semibold text-slate-800">{fmt.money(day.revenue)}</td>
                    <td className="py-3 text-right text-slate-700">{fmt.money(day.collected)}</td>
                    <td className="py-3 text-right text-brand-600">{fmt.money(day.commission)}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </div>
      ) : null}

      {!loading && report ? (
        <div className="rounded-[18px] border border-slate-200 bg-white p-5 shadow-sm">
          <h4 className="text-sm font-bold uppercase tracking-wider text-slate-900">Job breakdown</h4>
          <p className="mt-1 text-xs text-slate-500">Grouped from service and product lines in this period.</p>

          {serviceBreakdown.length === 0 ? (
            <p className="mt-6 text-sm text-slate-500">No completed jobs in this period.</p>
          ) : (
            <div className="mt-4 overflow-x-auto">
              <table className="w-full min-w-[520px] text-left text-sm">
                <thead>
                  <tr className="border-b border-slate-100 text-xs font-bold uppercase tracking-wider text-slate-400">
                    <th className="pb-3">Job</th>
                    <th className="pb-3 text-center">Count</th>
                    <th className="pb-3 text-right">Generated</th>
                    <th className="pb-3 text-right">Share</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-slate-100">
                  {serviceBreakdown.map((row) => (
                    <tr key={row.name}>
                      <td className="py-3 font-semibold text-slate-900">{row.name}</td>
                      <td className="py-3 text-center text-slate-600">{row.count}</td>
                      <td className="py-3 text-right font-semibold text-slate-800">{fmt.money(row.revenue)}</td>
                      <td className="py-3 text-right text-slate-500">{row.share}%</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}
        </div>
      ) : null}
    </div>
  )
}
