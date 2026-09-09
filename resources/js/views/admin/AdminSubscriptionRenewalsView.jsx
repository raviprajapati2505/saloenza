import React, { useCallback, useEffect, useMemo, useState } from 'react'
import { Link } from 'react-router-dom'
import { CalendarClock, ChevronRight, Mail, Phone, RefreshCw, Search } from 'lucide-react'
import PageHeader from '../../components/ui/PageHeader.jsx'
import BaseButton from '../../components/ui/BaseButton.jsx'
import BaseBadge from '../../components/ui/BaseBadge.jsx'
import { useAuthStore } from '../../stores/auth'
import { fetchUpcomingSubscriptionRenewals } from '../../services/adminSubscriptionService.js'
import {
  formatDaysRemaining,
  formatRenewalDue,
  lifecycleLabel,
  lifecycleVariant,
} from '../../lib/subscriptionLifecycle.js'

const FILTER_OPTIONS = [
  { value: '', label: 'All attention needed' },
  { value: 'expiring', label: 'Expiring soon' },
  { value: 'expired', label: 'Expired (read-only)' },
  { value: 'locked', label: 'Locked' },
  { value: 'activation_pending', label: 'Activation pending' },
  { value: 'active', label: 'Active (in window)' },
]

const WINDOW_OPTIONS = [
  { value: 7, label: '7 days' },
  { value: 30, label: '30 days' },
  { value: 60, label: '60 days' },
  { value: 90, label: '90 days' },
]

function matchesLifecycleFilter(subscription, filter) {
  if (!filter) return true
  const lifecycle = subscription?.lifecycle
  if (filter === 'active') return lifecycle === 'active'
  if (filter === 'expiring') {
    return ['expiring_critical', 'expiring_soon', 'expiring_month'].includes(lifecycle)
  }
  if (filter === 'expired') return lifecycle === 'expired'
  if (filter === 'locked') return lifecycle === 'locked'
  if (filter === 'activation_pending') return lifecycle === 'activation_pending'
  return true
}

function matchesSearch(row, query) {
  if (!query) return true
  const haystack = [
    row.saloon?.name,
    row.saloon?.city,
    row.owner?.name,
    row.owner?.email,
    row.owner?.phone,
    row.subscription?.plan?.name,
  ]
    .filter(Boolean)
    .join(' ')
    .toLowerCase()
  return haystack.includes(query.toLowerCase())
}

function SummaryCards({ summary, loading }) {
  const cards = [
    { key: 'expiring_total', label: 'Expiring soon', tone: 'border-amber-100 bg-amber-50/70' },
    { key: 'expired', label: 'Expired', tone: 'border-rose-100 bg-rose-50/70' },
    { key: 'locked', label: 'Locked', tone: 'border-slate-200 bg-slate-50' },
    { key: 'active', label: 'Active', tone: 'border-emerald-100 bg-emerald-50/60' },
  ]

  if (loading) {
    return (
      <div className="grid grid-cols-2 gap-3 lg:grid-cols-4">
        {cards.map((card) => (
          <div key={card.key} className="animate-pulse rounded-2xl border border-slate-100 p-4">
            <div className="h-3 w-16 rounded bg-slate-100" />
            <div className="mt-3 h-7 w-10 rounded bg-slate-100" />
          </div>
        ))}
      </div>
    )
  }

  return (
    <div className="grid grid-cols-2 gap-3 lg:grid-cols-4">
      {cards.map((card) => (
        <div key={card.key} className={`rounded-2xl border p-4 shadow-sm ${card.tone}`}>
          <p className="text-[10px] font-bold uppercase tracking-wide text-slate-500">{card.label}</p>
          <p className="mt-2 text-2xl font-black text-slate-900">{summary[card.key] ?? 0}</p>
        </div>
      ))}
    </div>
  )
}

export default function AdminSubscriptionRenewalsView() {
  const auth = useAuthStore()
  const [rows, setRows] = useState([])
  const [summary, setSummary] = useState({})
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState('')
  const [search, setSearch] = useState('')
  const [debouncedSearch, setDebouncedSearch] = useState('')
  const [lifecycleFilter, setLifecycleFilter] = useState('')
  const [windowDays, setWindowDays] = useState(30)

  useEffect(() => {
    const timer = window.setTimeout(() => setDebouncedSearch(search.trim()), 300)
    return () => window.clearTimeout(timer)
  }, [search])

  const load = useCallback(async () => {
    setLoading(true)
    setError('')
    try {
      const payload = await fetchUpcomingSubscriptionRenewals({
        days: windowDays,
        limit: 50,
        include_expired: true,
      }, auth.token)
      setRows(payload.renewals || [])
      setSummary(payload.summary || {})
    } catch (err) {
      setError(err?.response?.data?.message || 'Unable to load subscription renewals.')
      setRows([])
      setSummary({})
    } finally {
      setLoading(false)
    }
  }, [auth.token, windowDays])

  useEffect(() => {
    void load()
  }, [load])

  const filteredRows = useMemo(
    () => rows.filter((row) => matchesLifecycleFilter(row.subscription, lifecycleFilter)
      && matchesSearch(row, debouncedSearch)),
    [rows, lifecycleFilter, debouncedSearch],
  )

  const emptyMessage = useMemo(() => {
    if (lifecycleFilter || debouncedSearch) return 'No salons match your filters.'
    return 'No salons need renewal attention in this window.'
  }, [debouncedSearch, lifecycleFilter])

  return (
    <div className="space-y-6">
      <div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <PageHeader
          title="Subscription renewals"
          subtitle="Salons expiring soon or already past due — follow up before access is restricted."
        />
        <div className="flex flex-wrap gap-2">
          <BaseButton variant="secondary" size="sm" leftIcon={RefreshCw} loading={loading} onClick={() => void load()}>
            Refresh
          </BaseButton>
          <Link to="/admin/subscription-plans">
            <BaseButton variant="secondary" size="sm">Manage plans</BaseButton>
          </Link>
        </div>
      </div>

      <SummaryCards summary={summary} loading={loading} />

      <div className="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
        <div className="relative flex-1 max-w-md">
          <Search className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
          <input
            type="search"
            value={search}
            onChange={(e) => setSearch(e.target.value)}
            placeholder="Search salon, owner, email, city..."
            className="w-full rounded-xl border border-slate-200 bg-white py-2.5 pl-10 pr-3 text-sm shadow-sm focus:border-brand-400 focus:outline-none focus:ring-2 focus:ring-brand-500/20"
          />
        </div>
        <div className="flex flex-wrap gap-2">
          <select
            value={String(windowDays)}
            onChange={(e) => setWindowDays(Number(e.target.value))}
            className="rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm font-medium text-slate-700 shadow-sm"
          >
            {WINDOW_OPTIONS.map((option) => (
              <option key={option.value} value={option.value}>Window: {option.label}</option>
            ))}
          </select>
          <select
            value={lifecycleFilter}
            onChange={(e) => setLifecycleFilter(e.target.value)}
            className="rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm font-medium text-slate-700 shadow-sm"
          >
            {FILTER_OPTIONS.map((option) => (
              <option key={option.value || 'all'} value={option.value}>{option.label}</option>
            ))}
          </select>
        </div>
      </div>

      {error ? (
        <div className="rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-700">{error}</div>
      ) : null}

      <div className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div className="flex items-center gap-2 border-b border-slate-100 bg-slate-50/80 px-4 py-3">
          <CalendarClock className="h-4 w-4 text-amber-600" />
          <p className="text-sm font-semibold text-slate-700">
            {loading ? 'Loading salons…' : `${filteredRows.length} salon${filteredRows.length === 1 ? '' : 's'} shown`}
          </p>
        </div>
        <div className="overflow-x-auto">
          <table className="min-w-[960px] w-full divide-y divide-slate-200 text-sm">
            <thead className="bg-slate-50">
              <tr>
                <th className="px-4 py-3 text-left font-semibold text-slate-600">Salon</th>
                <th className="px-4 py-3 text-left font-semibold text-slate-600">Owner</th>
                <th className="px-4 py-3 text-left font-semibold text-slate-600">Plan</th>
                <th className="px-4 py-3 text-left font-semibold text-slate-600">Status</th>
                <th className="px-4 py-3 text-left font-semibold text-slate-600">Renewal</th>
                <th className="px-4 py-3 text-right font-semibold text-slate-600">Action</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-slate-100">
              {!loading && filteredRows.length === 0 ? (
                <tr>
                  <td colSpan={6} className="px-4 py-12 text-center text-slate-500">{emptyMessage}</td>
                </tr>
              ) : null}
              {loading ? (
                Array.from({ length: 6 }).map((_, i) => (
                  <tr key={i}>
                    <td colSpan={6} className="px-4 py-4">
                      <div className="h-4 animate-pulse rounded bg-slate-100" />
                    </td>
                  </tr>
                ))
              ) : filteredRows.map((row) => {
                const sub = row.subscription || {}
                const editTo = row.saloon?.id ? `/admin/onboarding/${row.saloon.id}/edit` : null

                return (
                  <tr key={row.saloon?.id || row.owner?.email} className="hover:bg-slate-50/60">
                    <td className="px-4 py-3 align-top">
                      <p className="font-semibold text-slate-900">{row.saloon?.name || 'Salon'}</p>
                      {row.saloon?.city ? (
                        <p className="mt-0.5 text-xs text-slate-500">{row.saloon.city}</p>
                      ) : null}
                    </td>
                    <td className="px-4 py-3 align-top">
                      {row.owner ? (
                        <div className="space-y-1 text-xs text-slate-600">
                          <p className="font-medium text-slate-800">{row.owner.name}</p>
                          {row.owner.email ? (
                            <a href={`mailto:${row.owner.email}`} className="inline-flex items-center gap-1 hover:text-brand-600">
                              <Mail className="h-3 w-3" /> {row.owner.email}
                            </a>
                          ) : null}
                          {row.owner.phone ? (
                            <p className="inline-flex items-center gap-1">
                              <Phone className="h-3 w-3" /> {row.owner.phone}
                            </p>
                          ) : null}
                        </div>
                      ) : (
                        <span className="text-slate-400">—</span>
                      )}
                    </td>
                    <td className="px-4 py-3 align-top text-slate-700">
                      {sub.plan?.name || '—'}
                    </td>
                    <td className="px-4 py-3 align-top">
                      <BaseBadge variant={lifecycleVariant(sub.lifecycle)} size="sm">
                        {sub.lifecycle_label || lifecycleLabel(sub.lifecycle)}
                      </BaseBadge>
                      <p className="mt-1 text-xs text-slate-500">{formatDaysRemaining(sub.days_remaining)}</p>
                    </td>
                    <td className="px-4 py-3 align-top text-slate-600">
                      {formatRenewalDue(sub.renewal_due_at)}
                    </td>
                    <td className="px-4 py-3 align-top text-right">
                      {editTo ? (
                        <Link
                          to={editTo}
                          className="inline-flex items-center gap-1 rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 hover:border-brand-200 hover:text-brand-700"
                        >
                          Open salon
                          <ChevronRight className="h-3.5 w-3.5" />
                        </Link>
                      ) : (
                        <span className="text-slate-400">—</span>
                      )}
                    </td>
                  </tr>
                )
              })}
            </tbody>
          </table>
        </div>
      </div>
    </div>
  )
}
