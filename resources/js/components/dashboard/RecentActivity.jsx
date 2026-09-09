import React from 'react'
import { Activity, Clock } from 'lucide-react'
import { Link } from 'react-router-dom'
import BaseBadge from '../ui/BaseBadge.jsx'

function formatRelativeTime(value) {
  if (!value) return ''
  const date = new Date(value)
  const diffMs = Date.now() - date.getTime()
  const minutes = Math.floor(diffMs / 60000)
  if (minutes < 1) return 'Just now'
  if (minutes < 60) return `${minutes}m ago`
  const hours = Math.floor(minutes / 60)
  if (hours < 24) return `${hours}h ago`
  const days = Math.floor(hours / 24)
  return `${days}d ago`
}

const STATUS_VARIANT = {
  success: 'success',
  completed: 'success',
  pending: 'warning',
  warning: 'warning',
  error: 'danger',
  rejected: 'danger',
  info: 'info',
  default: 'default',
}

/**
 * Timeline of recent platform activity.
 * Each item: { id, title, description, actor, timestamp, status, statusLabel, icon, href }
 */
export default function RecentActivity({
  title = 'Recent activity',
  items = [],
  loading = false,
  error = '',
  emptyMessage = 'No recent activity yet.',
  viewAllTo,
}) {
  return (
    <div className="flex h-full flex-col rounded-[18px] border border-slate-200 bg-white p-5 shadow-[0_8px_30px_rgb(0,0,0,0.04)] sm:p-6">
      <div className="mb-5 flex items-center justify-between gap-3">
        <h3 className="text-base font-semibold text-slate-900">{title}</h3>
        {viewAllTo ? (
          <Link to={viewAllTo} className="text-xs font-semibold text-brand-600 hover:text-brand-700">
            View all
          </Link>
        ) : null}
      </div>

      {loading ? (
        <div className="space-y-4">
          {Array.from({ length: 4 }).map((_, i) => (
            <div key={i} className="flex animate-pulse gap-3">
              <div className="h-10 w-10 shrink-0 rounded-xl bg-slate-100" />
              <div className="flex-1 space-y-2 py-1">
                <div className="h-3 w-2/3 rounded bg-slate-100" />
                <div className="h-3 w-full rounded bg-slate-50" />
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
        <div className="flex flex-1 flex-col items-center justify-center gap-2 rounded-xl border border-dashed border-slate-200 bg-slate-50/60 px-4 py-10 text-center">
          <Activity className="h-8 w-8 text-slate-300" />
          <p className="text-sm text-slate-500">{emptyMessage}</p>
        </div>
      ) : null}

      {!loading && !error && items.length > 0 ? (
        <ul className="relative space-y-0">
          <div className="absolute bottom-2 left-5 top-2 w-px bg-slate-100" aria-hidden />
          {items.map((item) => {
            const Icon = item.icon || Clock
            const badgeVariant = STATUS_VARIANT[item.status] || 'default'
            const content = (
              <div className="group relative flex gap-3 rounded-xl p-2 transition hover:bg-slate-50">
                <div className="relative z-10 flex h-10 w-10 shrink-0 items-center justify-center rounded-xl border border-slate-100 bg-white text-brand-600 shadow-sm">
                  <Icon className="h-4 w-4" />
                </div>
                <div className="min-w-0 flex-1 pt-0.5">
                  <div className="flex flex-wrap items-start justify-between gap-2">
                    <p className="text-sm font-semibold text-slate-900">{item.title}</p>
                    {item.statusLabel ? (
                      <BaseBadge variant={badgeVariant} size="sm">
                        {item.statusLabel}
                      </BaseBadge>
                    ) : null}
                  </div>
                  {item.description ? (
                    <p className="mt-0.5 line-clamp-2 text-xs text-slate-500">{item.description}</p>
                  ) : null}
                  <div className="mt-1.5 flex flex-wrap items-center gap-x-2 gap-y-0.5 text-[11px] text-slate-400">
                    {item.actor ? <span className="font-medium text-slate-500">{item.actor}</span> : null}
                    {item.actor && item.timestamp ? <span>·</span> : null}
                    {item.timestamp ? <span>{formatRelativeTime(item.timestamp)}</span> : null}
                  </div>
                </div>
              </div>
            )

            return (
              <li key={item.id}>
                {item.href ? (
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
  )
}
