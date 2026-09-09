import React from 'react'
import { Link } from 'react-router-dom'
import { Building2, MapPinned, TrendingUp, Users } from 'lucide-react'
import { formatMoneyDefault } from '../../lib/tenantFormatting.js'

/**
 * Multi-branch performance comparison.
 * Hide when branches.length <= 1 (handled by parent) or pass hideWhenSingle.
 *
 * items: [{ id, name, revenue, appointments, staffCount, customerCount, isTop }]
 */
export default function BranchPerformance({
  title = 'Branch performance',
  subtitle = 'Compare activity across locations',
  items = [],
  loading = false,
  error = '',
  emptyMessage = 'No branch data available.',
  hideWhenSingle = true,
  viewAllTo = '/branches',
  formatMoney = formatMoneyDefault,
}) {
  if (!loading && hideWhenSingle && items.length <= 1) {
    return null
  }

  return (
    <div className="rounded-[18px] border border-slate-200 bg-white p-5 shadow-[0_8px_30px_rgb(0,0,0,0.04)] sm:p-6">
      <div className="mb-5 flex items-center justify-between gap-3">
        <div>
          <h3 className="text-base font-semibold text-slate-900">{title}</h3>
          {subtitle ? <p className="mt-0.5 text-sm text-slate-500">{subtitle}</p> : null}
        </div>
        {viewAllTo ? (
          <Link to={viewAllTo} className="text-xs font-semibold text-brand-600 hover:text-brand-700">
            Manage
          </Link>
        ) : null}
      </div>

      {loading ? (
        <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
          {Array.from({ length: 3 }).map((_, i) => (
            <div key={i} className="h-36 animate-pulse rounded-2xl bg-slate-50" />
          ))}
        </div>
      ) : null}

      {!loading && error ? (
        <div className="rounded-xl border border-rose-100 bg-rose-50 px-4 py-6 text-center text-sm text-rose-700">
          {error}
        </div>
      ) : null}

      {!loading && !error && items.length === 0 ? (
        <div className="flex flex-col items-center justify-center gap-2 rounded-xl border border-dashed border-slate-200 bg-slate-50/60 py-10 text-center">
          <MapPinned className="h-8 w-8 text-slate-300" />
          <p className="text-sm text-slate-500">{emptyMessage}</p>
        </div>
      ) : null}

      {!loading && !error && items.length > 0 ? (
        <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
          {items.map((branch) => (
            <div
              key={branch.id}
              className={`relative rounded-2xl border p-4 transition hover:-translate-y-0.5 hover:shadow-sm ${
                branch.isTop
                  ? 'border-brand-200 bg-gradient-to-br from-brand-50/80 to-white'
                  : 'border-slate-100 bg-slate-50/40'
              }`}
            >
              {branch.isTop ? (
                <span className="absolute right-3 top-3 inline-flex items-center gap-1 rounded-full bg-brand-600 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-white">
                  <TrendingUp className="h-3 w-3" /> Top
                </span>
              ) : null}
              <div className="flex items-center gap-3">
                <div className="flex h-10 w-10 items-center justify-center rounded-xl bg-white text-brand-600 shadow-sm">
                  <Building2 className="h-4 w-4" />
                </div>
                <div className="min-w-0">
                  <p className="truncate font-semibold text-slate-900">{branch.name}</p>
                  <p className="text-xs text-slate-500">{branch.appointments ?? 0} appointments</p>
                </div>
              </div>
              <div className="mt-4 grid grid-cols-2 gap-3">
                <div>
                  <p className="text-[10px] font-semibold uppercase tracking-wider text-slate-400">Revenue</p>
                  <p className="mt-0.5 text-sm font-bold text-slate-900">{formatMoney(branch.revenue)}</p>
                </div>
                <div>
                  <p className="text-[10px] font-semibold uppercase tracking-wider text-slate-400">Staff</p>
                  <p className="mt-0.5 flex items-center gap-1 text-sm font-bold text-slate-900">
                    <Users className="h-3.5 w-3.5 text-slate-400" />
                    {branch.staffCount ?? 0}
                  </p>
                </div>
              </div>
            </div>
          ))}
        </div>
      ) : null}
    </div>
  )
}
