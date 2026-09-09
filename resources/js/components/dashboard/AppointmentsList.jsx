import React from 'react'
import { Link } from 'react-router-dom'
import { CalendarDays, ChevronRight } from 'lucide-react'
import BaseBadge from '../ui/BaseBadge.jsx'
import { formatMoneyDefault } from '../../lib/tenantFormatting.js'

const STATUS_VARIANT = {
  scheduled: 'info',
  confirmed: 'success',
  'checked-in': 'info',
  'in-progress': 'info',
  completed: 'success',
  cancelled: 'danger',
  'no-show': 'danger',
}

function getInitials(name) {
  if (!name) return '?'
  return name
    .split(' ')
    .map((n) => n[0])
    .join('')
    .toUpperCase()
    .slice(0, 2)
}

function formatTime(iso) {
  if (!iso) return '—'
  return new Date(iso).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })
}

/**
 * Today's (or any) appointment list — fully prop-driven.
 * items: [{ id, customerName, customerAvatar, serviceName, staffName, startsAt, amount, status, href }]
 */
export default function AppointmentsList({
  title = "Today's appointments",
  items = [],
  loading = false,
  error = '',
  emptyMessage = 'No appointments scheduled.',
  viewAllTo = '/appointments',
  formatMoney = formatMoneyDefault,
  /** Optional: (item) => [{ key, label, onClick, disabled? }] */
  getQuickActions,
}) {
  return (
    <div className="flex h-full flex-col rounded-[18px] border border-slate-200 bg-white shadow-[0_8px_30px_rgb(0,0,0,0.04)]">
      <div className="flex items-center justify-between border-b border-slate-100 px-5 py-4 sm:px-6">
        <h3 className="text-base font-semibold text-slate-900">{title}</h3>
        {viewAllTo ? (
          <Link to={viewAllTo} className="text-xs font-semibold text-brand-600 hover:text-brand-700">
            View all
          </Link>
        ) : null}
      </div>

      <div className="flex-1 p-3 sm:p-4">
        {loading ? (
          <div className="space-y-3">
            {Array.from({ length: 5 }).map((_, i) => (
              <div key={i} className="flex animate-pulse gap-3 rounded-xl p-2">
                <div className="h-10 w-10 rounded-full bg-slate-100" />
                <div className="flex-1 space-y-2 py-1">
                  <div className="h-3 w-1/3 rounded bg-slate-100" />
                  <div className="h-3 w-1/2 rounded bg-slate-50" />
                </div>
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
          <div className="flex flex-col items-center justify-center gap-2 py-12 text-center">
            <CalendarDays className="h-8 w-8 text-slate-300" />
            <p className="text-sm text-slate-500">{emptyMessage}</p>
          </div>
        ) : null}

        {!loading && !error && items.length > 0 ? (
          <ul className="space-y-1">
            {items.map((item) => {
              const status = String(item.status || 'scheduled')
              const actions = typeof getQuickActions === 'function' ? (getQuickActions(item) || []) : []
              const content = (
                <div className="group flex flex-col gap-2 rounded-xl p-2.5 transition hover:bg-slate-50">
                  <div className="flex items-center gap-3">
                    {item.customerAvatar ? (
                      <img
                        src={item.customerAvatar}
                        alt=""
                        className="h-10 w-10 shrink-0 rounded-full object-cover ring-2 ring-white"
                      />
                    ) : (
                      <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-gradient-to-br from-brand-500 to-brand-700 text-xs font-bold text-white shadow-sm">
                        {getInitials(item.customerName)}
                      </div>
                    )}
                    <div className="min-w-0 flex-1">
                      <div className="flex flex-wrap items-center gap-2">
                        <p className="truncate text-sm font-semibold text-slate-900">
                          {item.customerName || 'Walk-in'}
                        </p>
                        <BaseBadge variant={STATUS_VARIANT[status] || 'default'} size="sm">
                          {status.replace(/-/g, ' ')}
                        </BaseBadge>
                      </div>
                      <p className="mt-0.5 truncate text-xs text-slate-500">
                        {item.serviceName || 'Service'}
                        {item.staffName ? ` · ${item.staffName}` : ''}
                      </p>
                    </div>
                    <div className="shrink-0 text-right">
                      <p className="text-xs font-semibold text-slate-700">{formatTime(item.startsAt)}</p>
                      <p className="mt-0.5 text-xs text-slate-500">{item.amount == null || item.amount === '' ? '—' : formatMoney(item.amount)}</p>
                    </div>
                    {!actions.length && item.href ? (
                      <ChevronRight className="h-4 w-4 shrink-0 text-slate-300 opacity-0 transition group-hover:opacity-100" />
                    ) : null}
                  </div>
                  {actions.length > 0 ? (
                    <div className="flex flex-wrap gap-1.5 pl-13 sm:pl-[3.25rem]">
                      {actions.map((action) => (
                        <button
                          key={action.key || action.label}
                          type="button"
                          disabled={action.disabled || action.loading}
                          onClick={(e) => {
                            e.preventDefault()
                            e.stopPropagation()
                            action.onClick?.(item)
                          }}
                          className="rounded-lg border border-slate-200 bg-white px-2.5 py-1 text-[11px] font-semibold text-slate-600 transition hover:border-brand-300 hover:bg-brand-50 hover:text-brand-700 disabled:cursor-not-allowed disabled:opacity-50"
                        >
                          {action.label}
                        </button>
                      ))}
                    </div>
                  ) : null}
                </div>
              )

              return (
                <li key={item.id}>
                  {item.href && !actions.length ? (
                    <Link to={item.href} className="block">
                      {content}
                    </Link>
                  ) : (
                    content
                  )}
                </li>
              )
            })}
          </ul>
        ) : null}
      </div>
    </div>
  )
}
