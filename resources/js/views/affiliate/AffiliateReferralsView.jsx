import React, { useCallback, useEffect, useMemo, useState } from 'react'
import { Link2, Mail, Phone, Search } from 'lucide-react'
import PageHeader from '../../components/ui/PageHeader.jsx'
import BaseBadge from '../../components/ui/BaseBadge.jsx'
import { fetchAffiliateReferrals } from '../../services/affiliatePortalService.js'
import { formatMoneyDefault, formatPlanPrice } from '../../lib/tenantFormatting.js'
import {
  formatDaysRemaining,
  formatRenewalDue,
  lifecycleLabel,
  lifecycleVariant,
} from '../../lib/subscriptionLifecycle.js'

const FILTER_OPTIONS = [
  { value: '', label: 'All salons' },
  { value: 'active', label: 'Active' },
  { value: 'expiring', label: 'Expiring soon' },
  { value: 'expired', label: 'Expired (view-only)' },
  { value: 'locked', label: 'Locked' },
  { value: 'activation_pending', label: 'Activation pending' },
]

function SummaryCards({ summary, loading }) {
  const cards = [
    { key: 'total', label: 'Total referred', value: summary.total ?? 0, tone: 'border-slate-200 bg-white' },
    { key: 'active', label: 'Active', value: summary.active ?? 0, tone: 'border-emerald-100 bg-emerald-50/60' },
    { key: 'expiring_total', label: 'Expiring soon', value: summary.expiring_total ?? 0, tone: 'border-amber-100 bg-amber-50/70' },
    { key: 'expired', label: 'Expired', value: summary.expired ?? 0, tone: 'border-rose-100 bg-rose-50/70' },
    { key: 'locked', label: 'Locked', value: summary.locked ?? 0, tone: 'border-slate-200 bg-slate-50' },
  ]

  if (loading) {
    return (
      <div className="grid grid-cols-2 gap-3 lg:grid-cols-5">
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
    <div className="grid grid-cols-2 gap-3 lg:grid-cols-5">
      {cards.map((card) => (
        <div key={card.key} className={`rounded-2xl border p-4 shadow-sm ${card.tone}`}>
          <p className="text-[10px] font-bold uppercase tracking-wide text-slate-500">{card.label}</p>
          <p className="mt-2 text-2xl font-black text-slate-900">{card.value}</p>
        </div>
      ))}
    </div>
  )
}

export default function AffiliateReferralsView() {
  const [rows, setRows] = useState([])
  const [summary, setSummary] = useState({})
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState('')
  const [search, setSearch] = useState('')
  const [lifecycleFilter, setLifecycleFilter] = useState('')
  const [debouncedSearch, setDebouncedSearch] = useState('')

  useEffect(() => {
    const timer = window.setTimeout(() => setDebouncedSearch(search.trim()), 300)
    return () => window.clearTimeout(timer)
  }, [search])

  const load = useCallback(async () => {
    setLoading(true)
    setError('')
    try {
      const payload = await fetchAffiliateReferrals({
        page: 1,
        per_page: 100,
        search: debouncedSearch || undefined,
        subscription_lifecycle: lifecycleFilter || undefined,
      })
      setRows(payload.referrals?.data || payload.referrals || [])
      setSummary(payload.subscription_summary || {})
    } catch (err) {
      setError(err?.response?.data?.message || 'Unable to load referrals.')
      setRows([])
      setSummary({})
    } finally {
      setLoading(false)
    }
  }, [debouncedSearch, lifecycleFilter])

  useEffect(() => {
    void load()
  }, [load])

  const emptyMessage = useMemo(() => {
    if (lifecycleFilter) return 'No salons match this subscription filter.'
    if (debouncedSearch) return 'No referrals match your search.'
    return 'No referrals found yet. Share your affiliate link to onboard salons.'
  }, [debouncedSearch, lifecycleFilter])

  return (
    <div className="space-y-6">
      <PageHeader
        title="Referred salons"
        subtitle="Track every salon you onboarded — subscription health, owner contacts, and renewal follow-ups."
      />

      <SummaryCards summary={summary} loading={loading} />

      <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div className="relative flex-1 max-w-md">
          <Search className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
          <input
            type="search"
            value={search}
            onChange={(e) => setSearch(e.target.value)}
            placeholder="Search salon, owner, email, phone..."
            className="w-full rounded-xl border border-slate-200 bg-white py-2.5 pl-10 pr-3 text-sm shadow-sm focus:border-brand-400 focus:outline-none focus:ring-2 focus:ring-brand-500/20"
          />
        </div>
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

      {error ? (
        <div className="rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-700">{error}</div>
      ) : null}

      <div className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div className="overflow-x-auto">
          <table className="min-w-[980px] w-full divide-y divide-slate-200 text-sm">
            <thead className="bg-slate-50">
              <tr>
                <th className="px-4 py-3 text-left font-semibold text-slate-600">Salon</th>
                <th className="px-4 py-3 text-left font-semibold text-slate-600">Owner</th>
                <th className="px-4 py-3 text-left font-semibold text-slate-600">Plan</th>
                <th className="px-4 py-3 text-left font-semibold text-slate-600">Subscription</th>
                <th className="px-4 py-3 text-left font-semibold text-slate-600">Renewal</th>
                <th className="px-4 py-3 text-left font-semibold text-slate-600">Referred</th>
                <th className="px-4 py-3 text-left font-semibold text-slate-600">Source</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-slate-100">
              {rows.map((row) => {
                const sub = row.subscription || {}
                const plan = sub.plan || {}

                return (
                  <tr key={row.id} className="hover:bg-slate-50/70">
                    <td className="px-4 py-3 align-top">
                      <p className="font-semibold text-slate-900">{row.saloon?.name || '—'}</p>
                      <p className="mt-0.5 text-xs text-slate-500">
                        {[row.saloon?.city, row.saloon?.state].filter(Boolean).join(', ') || '—'}
                      </p>
                      {row.saloon?.phone ? (
                        <p className="mt-1 inline-flex items-center gap-1 text-xs text-slate-500">
                          <Phone className="h-3 w-3" /> {row.saloon.phone}
                        </p>
                      ) : null}
                    </td>
                    <td className="px-4 py-3 align-top">
                      <p className="font-medium text-slate-800">{row.owner?.name || '—'}</p>
                      {row.owner?.email ? (
                        <a
                          href={`mailto:${row.owner.email}`}
                          className="mt-1 inline-flex items-center gap-1 text-xs text-brand-600 hover:text-brand-700"
                        >
                          <Mail className="h-3 w-3" /> {row.owner.email}
                        </a>
                      ) : null}
                      {row.owner?.phone ? (
                        <p className="mt-1 text-xs text-slate-500">{row.owner.phone}</p>
                      ) : null}
                    </td>
                    <td className="px-4 py-3 align-top">
                      <p className="font-medium text-slate-800">{plan.name || '—'}</p>
                      <p className="text-xs text-slate-500">
                        {plan.is_free ? 'Free' : plan.price != null ? formatPlanPrice(plan, null) : '—'}
                      </p>
                    </td>
                    <td className="px-4 py-3 align-top">
                      <BaseBadge variant={lifecycleVariant(sub.lifecycle)} size="sm">
                        {sub.lifecycle_label || lifecycleLabel(sub.lifecycle)}
                      </BaseBadge>
                      {sub.subscription?.status ? (
                        <p className="mt-1 text-xs capitalize text-slate-500">{sub.subscription.status}</p>
                      ) : null}
                    </td>
                    <td className="px-4 py-3 align-top">
                      <p className="font-medium text-slate-800">{formatDaysRemaining(sub.days_remaining)}</p>
                      <p className="text-xs text-slate-500">{formatRenewalDue(sub.renewal_due_at)}</p>
                    </td>
                    <td className="px-4 py-3 align-top text-slate-600">
                      {row.referred_at
                        ? new Date(row.referred_at).toLocaleDateString('en-IN', { day: 'numeric', month: 'short', year: 'numeric' })
                        : '—'}
                      {row.converted_at ? (
                        <p className="mt-1 text-[11px] text-emerald-600">Converted</p>
                      ) : null}
                    </td>
                    <td className="px-4 py-3 align-top">
                      <p className="font-medium text-slate-700">{row.source || '—'}</p>
                      <p className="mt-1 inline-flex items-center gap-1 text-xs text-slate-500">
                        <Link2 className="h-3 w-3" /> {row.referral_code || '—'}
                      </p>
                    </td>
                  </tr>
                )
              })}
            </tbody>
          </table>
        </div>

        {loading ? <div className="p-4 text-sm text-slate-500">Loading referred salons...</div> : null}
        {!loading && rows.length === 0 ? (
          <div className="p-8 text-center text-sm text-slate-500">{emptyMessage}</div>
        ) : null}
      </div>
    </div>
  )
}
