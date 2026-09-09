import React from 'react'
import { Link } from 'react-router-dom'
import { UserPlus, Users, UserCheck, Cake, Crown, CreditCard } from 'lucide-react'
import { WIDGET_ACCENT } from './brandColors.js'

const ICON_MAP = {
  new: UserPlus,
  returning: UserCheck,
  total: Users,
  birthday: Cake,
  loyal: Crown,
  membership: CreditCard,
}

/**
 * Customer insight metric cards.
 * items: [{ key, label, value, hint, color, iconKey }]
 */
export default function CustomerInsights({
  title = 'Customer insights',
  items = [],
  loading = false,
  error = '',
  emptyMessage = 'No customer metrics yet.',
  viewAllTo = '/customers',
}) {
  return (
    <div className="rounded-[18px] border border-slate-200 bg-white p-5 shadow-[0_8px_30px_rgb(0,0,0,0.04)] sm:p-6">
      <div className="mb-5 flex items-center justify-between gap-3">
        <h3 className="text-base font-semibold text-slate-900">{title}</h3>
        {viewAllTo ? (
          <Link to={viewAllTo} className="text-xs font-semibold text-brand-600 hover:text-brand-700">
            View all
          </Link>
        ) : null}
      </div>

      {loading ? (
        <div className="grid grid-cols-2 gap-3 lg:grid-cols-3">
          {Array.from({ length: 4 }).map((_, i) => (
            <div key={i} className="h-24 animate-pulse rounded-2xl bg-slate-50" />
          ))}
        </div>
      ) : null}

      {!loading && error ? (
        <div className="rounded-xl border border-rose-100 bg-rose-50 px-4 py-6 text-center text-sm text-rose-700">
          {error}
        </div>
      ) : null}

      {!loading && !error && items.length === 0 ? (
        <div className="flex flex-col items-center justify-center gap-2 py-10 text-center">
          <Users className="h-8 w-8 text-slate-300" />
          <p className="text-sm text-slate-500">{emptyMessage}</p>
        </div>
      ) : null}

      {!loading && !error && items.length > 0 ? (
        <div className="grid grid-cols-2 gap-3 lg:grid-cols-3">
          {items.map((item) => {
            const Icon = item.icon || ICON_MAP[item.iconKey] || Users
            const colors = WIDGET_ACCENT[item.color] || WIDGET_ACCENT.teal
            const inner = (
              <>
                <div className={`inline-flex h-9 w-9 items-center justify-center rounded-xl border ${colors}`}>
                  <Icon className="h-4 w-4" />
                </div>
                <p className="mt-3 text-2xl font-bold tracking-tight text-slate-900">{item.value}</p>
                <p className="mt-0.5 text-xs font-semibold text-slate-700">{item.label}</p>
                {item.hint ? <p className="mt-0.5 text-[11px] text-slate-400">{item.hint}</p> : null}
              </>
            )
            return item.linkTo ? (
              <Link
                key={item.key || item.label}
                to={item.linkTo}
                className="block rounded-2xl border border-slate-100 bg-slate-50/50 p-4 transition hover:border-brand-200 hover:bg-white hover:shadow-sm"
              >
                {inner}
              </Link>
            ) : (
              <div
                key={item.key || item.label}
                className="rounded-2xl border border-slate-100 bg-slate-50/50 p-4 transition hover:border-brand-200 hover:bg-white hover:shadow-sm"
              >
                {inner}
              </div>
            )
          })}
        </div>
      ) : null}
    </div>
  )
}
