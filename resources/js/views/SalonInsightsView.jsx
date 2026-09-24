import React, { useCallback, useEffect, useState } from 'react'
import { RefreshCw } from 'lucide-react'
import PageHeader from '../components/ui/PageHeader.jsx'
import BaseButton from '../components/ui/BaseButton.jsx'
import BaseBadge from '../components/ui/BaseBadge.jsx'
import BaseSelect from '../components/ui/BaseSelect.jsx'
import { useAuthStore } from '../stores/auth'
import { subscriptionPageSubtitle } from '../lib/subscriptionModules.js'
import { useTenantFormatter } from '../hooks/useTenantFormatter.js'
import { fetchBranches } from '../services/branchService.js'
import { fetchSalonInsights } from '../services/insightsService.js'

const SEVERITY = {
  critical: 'danger',
  high: 'danger',
  medium: 'warning',
  low: 'info',
  info: 'info',
  warn: 'warning',
}

/** Human-readable metric values (avoids "[object Object]" for nested metrics). */
function formatMetricValue(key, value, fmt) {
  if (value == null) return '—'
  if (typeof value === 'boolean') return value ? 'Yes' : 'No'
  if (typeof value === 'number') {
    if (/amount|revenue|spend|loss|value|ticket|recovery|recoverable/i.test(key)) {
      return fmt.money(value)
    }
    if (/rate|pct|percent/i.test(key) && value <= 1) {
      return `${Math.round(value * 100)}%`
    }
    return String(value)
  }
  if (typeof value === 'string') return value

  if (Array.isArray(value)) {
    if (value.length === 0) return 'none'
    // string[] e.g. soft_days / peak_days
    if (value.every((item) => typeof item === 'string' || typeof item === 'number')) {
      return value.join(', ')
    }
    // object[] e.g. by_day — compact "Sun QAR 120 · Mon QAR 80"
    return value
      .map((item) => {
        if (item == null || typeof item !== 'object') return String(item)
        const label = item.day || item.name || item.label || item.category || item.dow
        const amount = item.revenue ?? item.amount ?? item.value ?? item.count
        if (label != null && typeof amount === 'number') {
          const isMoney = item.revenue != null || item.amount != null || item.value != null
          return `${label} ${isMoney ? fmt.money(amount) : amount}`
        }
        if (label != null) return String(label)
        return null
      })
      .filter(Boolean)
      .join(' · ')
  }

  if (typeof value === 'object') {
    // flat scalar map → "a: 1, b: 2"; otherwise omit raw dump
    const entries = Object.entries(value)
    if (entries.length > 0 && entries.every(([, v]) => v == null || ['string', 'number', 'boolean'].includes(typeof v))) {
      return entries.map(([k, v]) => `${k}: ${v}`).join(', ')
    }
    return '—'
  }

  return String(value)
}

export default function SalonInsightsView() {
  const auth = useAuthStore()
  const fmt = useTenantFormatter()
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState('')
  const [insights, setInsights] = useState(null)
  const [branchId, setBranchId] = useState('')
  const [branches, setBranches] = useState([])
  const [expanded, setExpanded] = useState(null)

  useEffect(() => {
    void fetchBranches()
      .then((payload) => setBranches(payload?.branches ?? []))
      .catch(() => setBranches([]))
  }, [])

  const load = useCallback(async () => {
    setLoading(true)
    setError('')
    try {
      const data = await fetchSalonInsights(branchId ? { branch_id: Number(branchId) } : {})
      setInsights(data)
    } catch (err) {
      setError(err?.response?.data?.message || 'Unable to load insights.')
      setInsights(null)
    } finally {
      setLoading(false)
    }
  }, [branchId])

  useEffect(() => {
    void load()
  }, [load])

  const cards = insights?.cards ?? []

  return (
    <div className="space-y-6">
      <PageHeader
        title="Salon insights"
        subtitle={subscriptionPageSubtitle(auth, 'Actionable analytics cards with drill-downs.')}
        actions={(
          <BaseButton variant="secondary" size="sm" leftIcon={RefreshCw} loading={loading} onClick={() => void load()}>
            Refresh
          </BaseButton>
        )}
      />

      <div className="max-w-xs">
        <BaseSelect
          label="Branch (optional)"
          value={branchId}
          onChange={(e) => setBranchId(e.target.value)}
          options={[
            { value: '', label: 'All branches' },
            ...branches.map((b) => ({ value: String(b.id), label: b.name || b.branch_name })),
          ]}
        />
      </div>

      {error ? <div className="rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-700">{error}</div> : null}

      {loading ? (
        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
          {Array.from({ length: 3 }).map((_, i) => <div key={i} className="h-36 animate-pulse rounded-[18px] bg-slate-100" />)}
        </div>
      ) : null}

      {!loading && cards.length === 0 ? (
        <div className="rounded-[18px] border border-dashed border-slate-200 bg-white px-6 py-12 text-center text-sm text-slate-500">
          No insight cards for this scope.
        </div>
      ) : null}

      {!loading && cards.length > 0 ? (
        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
          {cards.map((card, index) => {
            const key = card.type || index
            const open = expanded === key
            const drill = card.drilldown || card.rows || []
            return (
              <div key={key} className="rounded-[18px] border border-slate-200 bg-white p-5 shadow-[0_8px_30px_rgb(0,0,0,0.04)]">
                <div className="flex items-start justify-between gap-2">
                  <h3 className="text-base font-semibold text-slate-900">{card.title || card.type}</h3>
                  <BaseBadge size="sm" variant={SEVERITY[card.severity] || 'default'}>{card.severity || 'info'}</BaseBadge>
                </div>
                <p className="mt-2 text-sm text-slate-600">{card.summary}</p>
                {card.metrics ? (
                  <dl className="mt-3 space-y-1 text-xs text-slate-500">
                    {Object.entries(card.metrics).map(([k, v]) => {
                      // by_day is also in drill-down; keep a compact one-line summary only
                      const display = formatMetricValue(k, v, fmt)
                      if (display === '—' && (Array.isArray(v) || (v && typeof v === 'object'))) {
                        return null
                      }
                      return (
                        <div key={k} className="flex justify-between gap-2">
                          <dt className="shrink-0 capitalize">{k.replace(/_/g, ' ')}</dt>
                          <dd className="text-right font-medium text-slate-800 break-words">{display}</dd>
                        </div>
                      )
                    })}
                  </dl>
                ) : null}
                {card.recommended_action_code ? (
                  <p className="mt-3 text-xs font-medium text-brand-700">Action: {card.recommended_action_code}</p>
                ) : null}
                {Array.isArray(drill) && drill.length > 0 ? (
                  <div className="mt-4">
                    <BaseButton size="sm" variant="secondary" onClick={() => setExpanded(open ? null : key)}>
                      {open ? 'Hide details' : `Drill-down (${drill.length})`}
                    </BaseButton>
                    {open ? (
                      <div className="mt-3 max-h-56 overflow-auto rounded-xl border border-slate-100">
                        <table className="min-w-full text-xs">
                          <tbody className="divide-y divide-slate-50">
                            {drill.map((row, i) => {
                              const label = row.name
                                || row.label
                                || row.customer_name
                                || row.customer
                                || row.category
                                || row.day
                                || row.service
                                || `Row ${i + 1}`
                              const amount = row.revenue ?? row.value ?? row.amount ?? row.lifetime_spend
                                ?? row.current_revenue ?? row.lost_revenue
                              const count = row.count ?? row.no_show_count
                              const right = typeof amount === 'number'
                                ? fmt.money(amount)
                                : (count != null ? String(count) : '')
                              const sub = row.delta_pct != null
                                ? ` (${row.delta_pct > 0 ? '+' : ''}${row.delta_pct}%)`
                                : (typeof amount === 'number' && count != null ? ` · ${count} bookings` : '')
                              return (
                                <tr key={i}>
                                  <td className="px-3 py-2 text-slate-700">
                                    {label}{sub && amount == null ? sub : ''}
                                  </td>
                                  <td className="px-3 py-2 text-right font-medium text-slate-900">
                                    {right}{typeof amount === 'number' && count != null ? ` (${count})` : ''}
                                    {row.delta_pct != null ? ` ${row.delta_pct > 0 ? '+' : ''}${row.delta_pct}%` : ''}
                                  </td>
                                </tr>
                              )
                            })}
                          </tbody>
                        </table>
                      </div>
                    ) : null}
                  </div>
                ) : null}
              </div>
            )
          })}
        </div>
      ) : null}
    </div>
  )
}
