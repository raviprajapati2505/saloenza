import React from 'react'
import { Link } from 'react-router-dom'
import { Award, Users } from 'lucide-react'
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

/**
 * Staff ranking / performance list.
 * items: [{ id, name, role, servicesCompleted, revenue, rating, attendance, photo }]
 */
export default function StaffPerformance({
  title = 'Staff performance',
  subtitle = 'Based on recent appointments',
  items = [],
  loading = false,
  error = '',
  emptyMessage = 'No staff activity yet.',
  viewAllTo = '/staff',
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
            View all
          </Link>
        ) : null}
      </div>

      {loading ? (
        <div className="space-y-3">
          {Array.from({ length: 4 }).map((_, i) => (
            <div key={i} className="flex animate-pulse gap-3">
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
        <div className="flex flex-1 flex-col items-center justify-center gap-2 py-10 text-center">
          <Users className="h-8 w-8 text-slate-300" />
          <p className="text-sm text-slate-500">{emptyMessage}</p>
        </div>
      ) : null}

      {!loading && !error && items.length > 0 ? (
        <ul className="space-y-2">
          {items.map((member, index) => (
            <li
              key={member.id}
              className="flex items-center gap-3 rounded-xl border border-slate-100 bg-slate-50/40 p-3 transition hover:bg-white hover:shadow-sm"
            >
              <span className="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-slate-200 text-[10px] font-bold text-slate-600">
                {index === 0 ? <Award className="h-3.5 w-3.5 text-brand-500" /> : index + 1}
              </span>
              {member.photo ? (
                <img src={member.photo} alt="" className="h-10 w-10 rounded-full object-cover" />
              ) : (
                <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-gradient-to-br from-brand-500 to-brand-400 text-xs font-bold text-white">
                  {getInitials(member.name)}
                </div>
              )}
              <div className="min-w-0 flex-1">
                <p className="truncate text-sm font-semibold text-slate-900">{member.name}</p>
                <p className="truncate text-xs text-slate-500">
                  {member.role || 'Staff'}
                  {member.servicesCompleted != null ? ` · ${member.servicesCompleted} services` : ''}
                </p>
              </div>
              <div className="shrink-0 text-right">
                <p className="text-sm font-bold text-slate-900">{formatMoney(member.revenue)}</p>
                {member.rating != null ? (
                  <p className="text-[11px] text-brand-600">★ {Number(member.rating).toFixed(1)}</p>
                ) : null}
              </div>
            </li>
          ))}
        </ul>
      ) : null}
    </div>
  )
}
