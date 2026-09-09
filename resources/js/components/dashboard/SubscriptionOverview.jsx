import React from 'react'
import { Link } from 'react-router-dom'
import { Inbox } from 'lucide-react'
import { WIDGET_ACCENT } from './brandColors.js'

/**
 * Queue / subscription overview cards (active upgrades, trials, etc.).
 * Fully prop-driven — pass only metrics your APIs provide.
 *
 * items: [{ key, label, value, hint, icon, color, href }]
 */
export default function SubscriptionOverview({
  title = 'Work queues',
  subtitle,
  items = [],
  loading = false,
  emptyMessage = 'No queue metrics available.',
}) {
  return (
    <div className="rounded-[18px] border border-slate-200 bg-white p-5 shadow-[0_8px_30px_rgb(0,0,0,0.04)] sm:p-6">
      <div className="mb-5">
        <h3 className="text-base font-semibold text-slate-900">{title}</h3>
        {subtitle ? <p className="mt-0.5 text-sm text-slate-500">{subtitle}</p> : null}
      </div>

      {loading ? (
        <div className="grid grid-cols-2 gap-3 lg:grid-cols-4">
          {Array.from({ length: 4 }).map((_, i) => (
            <div key={i} className="animate-pulse rounded-2xl border border-slate-100 p-4">
              <div className="h-9 w-9 rounded-xl bg-slate-100" />
              <div className="mt-4 h-6 w-12 rounded bg-slate-100" />
              <div className="mt-2 h-3 w-20 rounded bg-slate-50" />
            </div>
          ))}
        </div>
      ) : null}

      {!loading && items.length === 0 ? (
        <div className="flex flex-col items-center justify-center gap-2 rounded-xl border border-dashed border-slate-200 bg-slate-50/60 px-4 py-10 text-center">
          <Inbox className="h-8 w-8 text-slate-300" />
          <p className="text-sm text-slate-500">{emptyMessage}</p>
        </div>
      ) : null}

      {!loading && items.length > 0 ? (
        <div className="grid grid-cols-2 gap-3 lg:grid-cols-4">
          {items.map((item) => {
            const Icon = item.icon
            const colors = WIDGET_ACCENT[item.color] || WIDGET_ACCENT.teal
            const body = (
              <div className="group h-full rounded-2xl border border-slate-100 bg-slate-50/40 p-4 transition hover:-translate-y-0.5 hover:border-brand-200 hover:bg-white hover:shadow-sm">
                {Icon ? (
                  <div className={`inline-flex h-9 w-9 items-center justify-center rounded-xl border ${colors}`}>
                    <Icon className="h-4 w-4" />
                  </div>
                ) : null}
                <p className="mt-3 text-2xl font-bold tracking-tight text-slate-900">{item.value}</p>
                <p className="mt-1 text-xs font-semibold text-slate-700">{item.label}</p>
                {item.hint ? <p className="mt-0.5 text-[11px] text-slate-400">{item.hint}</p> : null}
              </div>
            )

            return item.href ? (
              <Link key={item.key || item.label} to={item.href} className="block">
                {body}
              </Link>
            ) : (
              <div key={item.key || item.label}>{body}</div>
            )
          })}
        </div>
      ) : null}
    </div>
  )
}
