import React from 'react'
import { Link } from 'react-router-dom'
import { CalendarClock, MapPin } from 'lucide-react'
import BaseBadge from '../ui/BaseBadge.jsx'

const STATUS_VARIANT = {
  scheduled: 'info',
  confirmed: 'success',
  upcoming: 'info',
  'checked-in': 'info',
  'in-progress': 'warning',
  completed: 'success',
  cancelled: 'danger',
  'no-show': 'danger',
}

function formatTime(iso) {
  if (!iso) return '—'
  return new Date(iso).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })
}

function formatDuration(minutes) {
  const n = Number(minutes)
  if (!Number.isFinite(n) || n <= 0) return null
  if (n < 60) return `${n} min`
  const h = Math.floor(n / 60)
  const m = n % 60
  return m ? `${h}h ${m}m` : `${h}h`
}

/**
 * Personal schedule list for a staff member.
 * items: [{ id, startsAt, durationMinutes, customerName, serviceName, branchName, status, notes, href }]
 */
export default function StaffSchedule({
  title = "Today's schedule",
  items = [],
  loading = false,
  error = '',
  emptyMessage = 'No appointments assigned to you today.',
  viewAllTo = '/appointments',
  getQuickActions,
}) {
  return (
    <div className="flex h-full flex-col rounded-[18px] border border-slate-200 bg-white shadow-[0_8px_30px_rgb(0,0,0,0.04)]">
      <div className="flex items-center justify-between border-b border-slate-100 px-5 py-4 sm:px-6">
        <h3 className="text-base font-semibold text-slate-900">{title}</h3>
        {viewAllTo ? (
          <Link to={viewAllTo} className="text-xs font-semibold text-brand-600 hover:text-brand-700">
            Full calendar
          </Link>
        ) : null}
      </div>

      <div className="flex-1 p-3 sm:p-4">
        {loading ? (
          <div className="space-y-3">
            {Array.from({ length: 4 }).map((_, i) => (
              <div key={i} className="h-24 animate-pulse rounded-xl bg-slate-50" />
            ))}
          </div>
        ) : null}

        {!loading && error ? (
          <div className="rounded-xl border border-rose-100 bg-rose-50 px-4 py-6 text-center text-sm text-rose-700">
            {error}
          </div>
        ) : null}

        {!loading && !error && items.length === 0 ? (
          <div className="flex flex-col items-center justify-center gap-2 py-12 text-center">
            <CalendarClock className="h-8 w-8 text-slate-300" />
            <p className="text-sm text-slate-500">{emptyMessage}</p>
          </div>
        ) : null}

        {!loading && !error && items.length > 0 ? (
          <ul className="space-y-2">
            {items.map((item) => {
              const status = String(item.status || 'scheduled')
              const actions = typeof getQuickActions === 'function' ? (getQuickActions(item) || []) : []
              const duration = formatDuration(item.durationMinutes)

              return (
                <li
                  key={item.id}
                  className="rounded-xl border border-slate-100 bg-slate-50/40 p-3.5 transition hover:border-brand-200 hover:bg-white hover:shadow-sm"
                >
                  <div className="flex items-start gap-3">
                    <div className="flex h-12 w-14 shrink-0 flex-col items-center justify-center rounded-xl bg-brand-50 text-brand-700">
                      <span className="text-xs font-bold leading-none">{formatTime(item.startsAt)}</span>
                      {duration ? <span className="mt-1 text-[10px] text-brand-400">{duration}</span> : null}
                    </div>
                    <div className="min-w-0 flex-1">
                      <div className="flex flex-wrap items-center gap-2">
                        <p className="text-sm font-semibold text-slate-900">{item.customerName || 'Walk-in'}</p>
                        <BaseBadge variant={STATUS_VARIANT[status] || 'default'} size="sm">
                          {status.replace(/-/g, ' ')}
                        </BaseBadge>
                      </div>
                      <p className="mt-0.5 text-xs text-slate-500">{item.serviceName || 'Service'}</p>
                      {item.branchName ? (
                        <p className="mt-1 flex items-center gap-1 text-[11px] text-slate-400">
                          <MapPin className="h-3 w-3" /> {item.branchName}
                        </p>
                      ) : null}
                      {item.notes ? (
                        <p className="mt-1 line-clamp-2 text-[11px] italic text-slate-400">{item.notes}</p>
                      ) : null}
                    </div>
                  </div>
                  {actions.length > 0 ? (
                    <div className="mt-3 flex flex-wrap gap-1.5">
                      {actions.map((action) => (
                        <button
                          key={action.key || action.label}
                          type="button"
                          disabled={action.disabled}
                          onClick={() => action.onClick?.(item)}
                          className="rounded-lg border border-slate-200 bg-white px-2.5 py-1 text-[11px] font-semibold text-slate-600 transition hover:border-brand-300 hover:bg-brand-50 hover:text-brand-700 disabled:opacity-50"
                        >
                          {action.label}
                        </button>
                      ))}
                    </div>
                  ) : null}
                </li>
              )
            })}
          </ul>
        ) : null}
      </div>
    </div>
  )
}
