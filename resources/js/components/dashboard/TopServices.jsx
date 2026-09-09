import React from 'react'
import { Link } from 'react-router-dom'
import { Scissors, TrendingDown, TrendingUp } from 'lucide-react'
import { formatMoneyDefault } from '../../lib/tenantFormatting.js'

/**
 * Top selling / booked services ranking.
 * items: [{ id, name, revenue, bookings, growth }]
 */
export default function TopServices({
  title = 'Top services',
  subtitle = 'By bookings in the selected period',
  items = [],
  loading = false,
  error = '',
  emptyMessage = 'No service bookings yet.',
  viewAllTo = '/catalog',
  formatMoney = formatMoneyDefault,
}) {
  return (
    <div className="flex h-full flex-col rounded-[18px] border border-slate-200 bg-white p-5 shadow-[0_8px_30px_rgb(0,0,0,0.04)] sm:p-6">
      <div className="mb-5 flex items-center justify-between gap-3">
        <div>
          <h3 className="text-base font-semibold text-slate-900">{title}</h3>
          {subtitle ? <p className="mt-0.5 text-sm text-slate-500">{subtitle}</p> : null}
        </div>
        {viewAllTo ? (
          <Link to={viewAllTo} className="text-xs font-semibold text-brand-600 hover:text-brand-700">
            Catalog
          </Link>
        ) : null}
      </div>

      {loading ? (
        <div className="space-y-3">
          {Array.from({ length: 5 }).map((_, i) => (
            <div key={i} className="h-14 animate-pulse rounded-xl bg-slate-50" />
          ))}
        </div>
      ) : null}

      {!loading && error ? (
        <div className="rounded-xl border border-rose-100 bg-rose-50 px-4 py-6 text-center text-sm text-rose-700">
          {error}
        </div>
      ) : null}

      {!loading && !error && items.length === 0 ? (
        <div className="flex flex-1 flex-col items-center justify-center gap-2 py-10 text-center">
          <Scissors className="h-8 w-8 text-slate-300" />
          <p className="text-sm text-slate-500">{emptyMessage}</p>
        </div>
      ) : null}

      {!loading && !error && items.length > 0 ? (
        <ul className="space-y-2">
          {items.map((service, index) => {
            const growth = Number(service.growth)
            const hasGrowth = Number.isFinite(growth)
            const positive = growth >= 0
            return (
              <li
                key={service.id || service.name || index}
                className="flex items-center gap-3 rounded-xl border border-slate-100 bg-slate-50/40 p-3 transition hover:bg-white hover:shadow-sm"
              >
                <span className="flex h-8 w-8 shrink-0 items-center justify-center rounded-xl bg-brand-50 text-xs font-bold text-brand-700">
                  {index + 1}
                </span>
                <div className="min-w-0 flex-1">
                  <p className="truncate text-sm font-semibold text-slate-900">{service.name}</p>
                  <p className="text-xs text-slate-500">{service.bookings ?? 0} bookings</p>
                </div>
                <div className="shrink-0 text-right">
                  <p className="text-sm font-bold text-slate-900">{formatMoney(service.revenue)}</p>
                  {hasGrowth ? (
                    <p className={`mt-0.5 inline-flex items-center gap-0.5 text-[11px] font-semibold ${positive ? 'text-brand-600' : 'text-rose-500'}`}>
                      {positive ? <TrendingUp className="h-3 w-3" /> : <TrendingDown className="h-3 w-3" />}
                      {positive ? '+' : ''}{growth}%
                    </p>
                  ) : null}
                </div>
              </li>
            )
          })}
        </ul>
      ) : null}
    </div>
  )
}
