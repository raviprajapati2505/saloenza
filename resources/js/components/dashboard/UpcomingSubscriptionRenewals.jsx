import React from 'react'
import { Link } from 'react-router-dom'
import { AlertTriangle, CalendarClock, ChevronRight, Mail, Phone } from 'lucide-react'
import BaseBadge from '../ui/BaseBadge.jsx'
import { formatDaysRemaining, formatRenewalDue, lifecycleLabel, lifecycleVariant } from '../../lib/subscriptionLifecycle.js'

export default function UpcomingSubscriptionRenewals({
  items = [],
  summary = {},
  loading = false,
  error = '',
  viewAllTo = '/admin/subscription-renewals',
}) {
  return (
    <div className="rounded-[18px] border border-slate-200 bg-white p-5 shadow-[0_8px_30px_rgb(0,0,0,0.04)] sm:p-6">
      <div className="mb-4 flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
        <div>
          <div className="flex items-center gap-2">
            <CalendarClock className="h-4 w-4 text-amber-600" />
            <h3 className="text-base font-semibold text-slate-900">Subscription renewals</h3>
          </div>
          <p className="mt-0.5 text-sm text-slate-500">
            Salons expiring soon or already past due — follow up before access is restricted.
          </p>
        </div>
        {viewAllTo ? (
          <Link to={viewAllTo} className="text-xs font-semibold text-brand-600 hover:text-brand-700">
            View all
          </Link>
        ) : null}
      </div>

      {!loading && !error ? (
        <div className="mb-4 grid grid-cols-2 gap-2 sm:grid-cols-4">
          {[
            { key: 'expiring_total', label: 'Expiring soon', tone: 'text-amber-700 bg-amber-50 border-amber-100' },
            { key: 'expired', label: 'Expired', tone: 'text-rose-700 bg-rose-50 border-rose-100' },
            { key: 'locked', label: 'Locked', tone: 'text-slate-700 bg-slate-50 border-slate-200' },
            { key: 'active', label: 'Active', tone: 'text-emerald-700 bg-emerald-50 border-emerald-100' },
          ].map((stat) => (
            <div key={stat.key} className={`rounded-xl border px-3 py-2 ${stat.tone}`}>
              <p className="text-[10px] font-bold uppercase tracking-wide opacity-80">{stat.label}</p>
              <p className="mt-1 text-xl font-black">{summary[stat.key] ?? 0}</p>
            </div>
          ))}
        </div>
      ) : null}

      {loading ? (
        <div className="space-y-3">
          {Array.from({ length: 4 }).map((_, i) => (
            <div key={i} className="animate-pulse rounded-xl border border-slate-100 p-3">
              <div className="h-3 w-1/3 rounded bg-slate-100" />
              <div className="mt-2 h-3 w-1/2 rounded bg-slate-50" />
            </div>
          ))}
        </div>
      ) : null}

      {!loading && error ? (
        <div className="rounded-xl border border-rose-100 bg-rose-50 px-4 py-6 text-center text-sm text-rose-700">
          {error}
        </div>
      ) : null}

      {!loading && !error && items.length === 0 ? (
        <div className="flex flex-col items-center justify-center gap-2 rounded-xl border border-dashed border-slate-200 bg-slate-50/60 px-4 py-10 text-center">
          <AlertTriangle className="h-8 w-8 text-slate-300" />
          <p className="text-sm text-slate-500">No salons need renewal attention in the next 30 days.</p>
        </div>
      ) : null}

      {!loading && !error && items.length > 0 ? (
        <div className="space-y-2">
          {items.map((row) => {
            const sub = row.subscription || {}
            const lifecycle = sub.lifecycle
            const editTo = row.saloon?.id ? `/admin/onboarding/${row.saloon.id}/edit` : null

            return (
              <div
                key={row.saloon?.id || row.owner?.email}
                className="flex flex-col gap-3 rounded-xl border border-slate-100 bg-slate-50/40 p-3 sm:flex-row sm:items-center sm:justify-between"
              >
                <div className="min-w-0 flex-1">
                  <div className="flex flex-wrap items-center gap-2">
                    <p className="font-semibold text-slate-900">{row.saloon?.name || 'Salon'}</p>
                    <BaseBadge variant={lifecycleVariant(lifecycle)} size="sm">
                      {sub.lifecycle_label || lifecycleLabel(lifecycle)}
                    </BaseBadge>
                    {sub.plan?.name ? (
                      <span className="text-xs font-medium text-slate-500">{sub.plan.name}</span>
                    ) : null}
                  </div>
                  <div className="mt-1 flex flex-wrap gap-x-4 gap-y-1 text-xs text-slate-500">
                    <span>{formatDaysRemaining(sub.days_remaining)}</span>
                    <span>Due {formatRenewalDue(sub.renewal_due_at)}</span>
                    {row.saloon?.city ? <span>{row.saloon.city}</span> : null}
                  </div>
                  {row.owner ? (
                    <div className="mt-2 flex flex-wrap items-center gap-3 text-xs text-slate-600">
                      <span className="font-medium">{row.owner.name}</span>
                      {row.owner.email ? (
                        <a href={`mailto:${row.owner.email}`} className="inline-flex items-center gap-1 hover:text-brand-600">
                          <Mail className="h-3 w-3" /> {row.owner.email}
                        </a>
                      ) : null}
                      {row.owner.phone ? (
                        <span className="inline-flex items-center gap-1">
                          <Phone className="h-3 w-3" /> {row.owner.phone}
                        </span>
                      ) : null}
                    </div>
                  ) : null}
                </div>
                {editTo ? (
                  <Link
                    to={editTo}
                    className="inline-flex shrink-0 items-center gap-1 rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-700 hover:border-brand-200 hover:text-brand-700"
                  >
                    Open salon
                    <ChevronRight className="h-3.5 w-3.5" />
                  </Link>
                ) : null}
              </div>
            )
          })}
        </div>
      ) : null}
    </div>
  )
}
