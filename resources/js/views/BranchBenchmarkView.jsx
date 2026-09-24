import React, { useCallback, useEffect, useState } from 'react'
import { format, subDays } from 'date-fns'
import { RefreshCw } from 'lucide-react'
import PageHeader from '../components/ui/PageHeader.jsx'
import BaseButton from '../components/ui/BaseButton.jsx'
import BaseInput from '../components/ui/BaseInput.jsx'
import { useAuthStore } from '../stores/auth'
import { subscriptionPageSubtitle } from '../lib/subscriptionModules.js'
import { useTenantFormatter } from '../hooks/useTenantFormatter.js'
import { fetchBranchBenchmarks } from '../services/benchmarkService.js'

export default function BranchBenchmarkView() {
  const auth = useAuthStore()
  const fmt = useTenantFormatter()
  const [from, setFrom] = useState(format(subDays(new Date(), 29), 'yyyy-MM-dd'))
  const [to, setTo] = useState(format(new Date(), 'yyyy-MM-dd'))
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState('')
  const [comparison, setComparison] = useState(null)

  const load = useCallback(async () => {
    setLoading(true)
    setError('')
    try {
      const data = await fetchBranchBenchmarks({ from, to })
      setComparison(data)
    } catch (err) {
      setError(err?.response?.data?.message || 'Unable to load benchmarks.')
      setComparison(null)
    } finally {
      setLoading(false)
    }
  }, [from, to])

  useEffect(() => {
    void load()
  }, [load])

  const rows = comparison?.branches || comparison?.rows || (Array.isArray(comparison) ? comparison : [])
  const columns = comparison?.metrics || ['revenue', 'appointments', 'avg_ticket', 'no_show_rate', 'utilization']

  return (
    <div className="space-y-6">
      <PageHeader
        title="Branch benchmarks"
        subtitle={subscriptionPageSubtitle(auth, 'Compare branch performance over a date range.')}
        actions={(
          <BaseButton variant="secondary" size="sm" leftIcon={RefreshCw} loading={loading} onClick={() => void load()}>
            Refresh
          </BaseButton>
        )}
      />

      <div className="grid gap-4 rounded-[18px] border border-slate-200 bg-white p-5 shadow-[0_8px_30px_rgb(0,0,0,0.04)] sm:grid-cols-3">
        <BaseInput label="From" type="date" value={from} onChange={(e) => setFrom(e.target.value)} />
        <BaseInput label="To" type="date" value={to} onChange={(e) => setTo(e.target.value)} />
        <div className="flex items-end">
          <BaseButton onClick={() => void load()} loading={loading}>Apply range</BaseButton>
        </div>
      </div>

      {error ? <div className="rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-700">{error}</div> : null}

      <div className="overflow-hidden rounded-[18px] border border-slate-200 bg-white shadow-[0_8px_30px_rgb(0,0,0,0.04)]">
        {loading ? <div className="space-y-3 p-5">{Array.from({ length: 5 }).map((_, i) => <div key={i} className="h-12 animate-pulse rounded-xl bg-slate-50" />)}</div> : null}
        {!loading && rows.length === 0 ? <p className="px-5 py-12 text-center text-sm text-slate-500">No branch comparison data for this range.</p> : null}
        {!loading && rows.length > 0 ? (
          <div className="overflow-x-auto">
            <table className="min-w-full text-sm">
              <thead className="bg-slate-50 text-left text-xs uppercase tracking-wider text-slate-500">
                <tr>
                  <th className="px-5 py-3">Branch</th>
                  {columns.map((col) => (
                    <th key={col} className="px-5 py-3 text-right capitalize">{String(col).replace(/_/g, ' ')}</th>
                  ))}
                </tr>
              </thead>
              <tbody className="divide-y divide-slate-100">
                {rows.map((row) => (
                  <tr key={row.branch_id || row.id || row.name}>
                    <td className="px-5 py-3 font-medium text-slate-900">{row.branch_name || row.name || row.branch?.name || '—'}</td>
                    {columns.map((col) => {
                      const value = row[col] ?? row.metrics?.[col]
                      const isMoney = /revenue|ticket|amount|spend/i.test(col)
                      const isRate = /rate|pct|percent|utilization/i.test(col)
                      return (
                        <td key={col} className="px-5 py-3 text-right tabular-nums text-slate-700">
                          {value == null
                            ? '—'
                            : isMoney
                              ? fmt.money(value)
                              : isRate
                                ? `${Number(value).toFixed(1)}%`
                                : value}
                        </td>
                      )
                    })}
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
