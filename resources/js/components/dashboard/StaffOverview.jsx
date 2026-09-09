import React from 'react'
import { Users } from 'lucide-react'
import { formatMoneyDefault } from '../../lib/tenantFormatting.js'

function getInitials(name) {
  if (!name) return '?'
  return name
    .split(' ')
    .map((n) => n[0])
    .join('')
    .toUpperCase()
    .slice(0, 2)
}

const STATUS_STYLES = {
  available: 'bg-brand-500',
  busy: 'bg-brand-700',
  'on-break': 'bg-brand-400',
  leave: 'bg-rose-500',
  offline: 'bg-slate-400',
}

/**
 * Staff working at the branch today.
 * items: [{ id, name, role, photo, status, appointments, revenue }]
 * status: available | busy | on-break | leave | offline
 */
export default function StaffOverview({
  title = 'Staff on duty',
  subtitle = 'Branch team status today',
  items = [],
  loading = false,
  error = '',
  emptyMessage = 'No staff assigned to this branch.',
  formatMoney = formatMoneyDefault,
}) {
  return (
    <div className="flex h-full flex-col rounded-[18px] border border-slate-200 bg-white p-5 shadow-[0_8px_30px_rgb(0,0,0,0.04)] sm:p-6">
      <div className="mb-5">
        <h3 className="text-base font-semibold text-slate-900">{title}</h3>
        {subtitle ? <p className="mt-0.5 text-sm text-slate-500">{subtitle}</p> : null}
      </div>

      {loading ? (
        <div className="space-y-3">
          {Array.from({ length: 4 }).map((_, i) => (
            <div key={i} className="flex animate-pulse gap-3 rounded-xl p-2">
              <div className="h-11 w-11 rounded-full bg-slate-100" />
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
        <div className="flex flex-1 flex-col items-center justify-center gap-2 py-10 text-center">
          <Users className="h-8 w-8 text-slate-300" />
          <p className="text-sm text-slate-500">{emptyMessage}</p>
        </div>
      ) : null}

      {!loading && !error && items.length > 0 ? (
        <ul className="space-y-2">
          {items.map((member) => {
            const status = String(member.status || 'offline').toLowerCase()
            const dot = STATUS_STYLES[status] || STATUS_STYLES.offline
            return (
              <li
                key={member.id}
                className="flex items-center gap-3 rounded-xl border border-slate-100 bg-slate-50/40 p-3 transition hover:bg-white hover:shadow-sm"
              >
                <div className="relative shrink-0">
                  {member.photo ? (
                    <img src={member.photo} alt="" className="h-11 w-11 rounded-full object-cover ring-2 ring-white" />
                  ) : (
                    <div className="flex h-11 w-11 items-center justify-center rounded-full bg-gradient-to-br from-brand-500 to-brand-700 text-xs font-bold text-white ring-2 ring-white">
                      {getInitials(member.name)}
                    </div>
                  )}
                  <span className={`absolute bottom-0 right-0 h-3 w-3 rounded-full border-2 border-white ${dot}`} />
                </div>
                <div className="min-w-0 flex-1">
                  <p className="truncate text-sm font-semibold text-slate-900">{member.name}</p>
                  <p className="truncate text-xs text-slate-500">
                    {member.role || 'Staff'} · {status.replace(/-/g, ' ')}
                  </p>
                </div>
                <div className="shrink-0 text-right">
                  <p className="text-sm font-bold text-slate-900">{formatMoney(member.revenue)}</p>
                  <p className="text-[11px] text-slate-400">{member.appointments ?? 0} appts</p>
                </div>
              </li>
            )
          })}
        </ul>
      ) : null}
    </div>
  )
}
