import React from 'react'
import { Link } from 'react-router-dom'
import { Bell, CheckCheck } from 'lucide-react'

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

/**
 * Notification list panel.
 * items: [{ id, title, body, created_at, read_at, action_url }]
 */
export default function NotificationPanel({
  title = 'Notifications',
  items = [],
  loading = false,
  error = '',
  unreadCount = 0,
  emptyMessage = 'You are all caught up.',
  onMarkRead,
  onMarkAllRead,
  markingAll = false,
}) {
  return (
    <div className="flex h-full flex-col rounded-[18px] border border-slate-200 bg-white p-5 shadow-[0_8px_30px_rgb(0,0,0,0.04)] sm:p-6">
      <div className="mb-5 flex items-center justify-between gap-3">
        <div className="flex items-center gap-2">
          <h3 className="text-base font-semibold text-slate-900">{title}</h3>
          {unreadCount > 0 ? (
            <span className="inline-flex h-5 min-w-5 items-center justify-center rounded-full bg-brand-600 px-1.5 text-[10px] font-bold text-white">
              {unreadCount > 99 ? '99+' : unreadCount}
            </span>
          ) : null}
        </div>
        {onMarkAllRead && unreadCount > 0 ? (
          <button
            type="button"
            onClick={onMarkAllRead}
            disabled={markingAll}
            className="inline-flex items-center gap-1 text-xs font-semibold text-brand-600 hover:text-brand-700 disabled:opacity-50"
          >
            <CheckCheck className="h-3.5 w-3.5" />
            Mark all read
          </button>
        ) : null}
      </div>

      {loading ? (
        <div className="space-y-3">
          {Array.from({ length: 4 }).map((_, i) => (
            <div key={i} className="flex animate-pulse gap-3 rounded-xl p-2">
              <div className="h-9 w-9 rounded-xl bg-slate-100" />
              <div className="flex-1 space-y-2 py-1">
                <div className="h-3 w-3/4 rounded bg-slate-100" />
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
        <div className="flex flex-1 flex-col items-center justify-center gap-2 rounded-xl border border-dashed border-slate-200 bg-slate-50/60 px-4 py-10 text-center">
          <Bell className="h-8 w-8 text-slate-300" />
          <p className="text-sm text-slate-500">{emptyMessage}</p>
        </div>
      ) : null}

      {!loading && !error && items.length > 0 ? (
        <ul className="space-y-1">
          {items.map((item) => {
            const unread = !item.read_at
            const inner = (
              <div
                className={`flex gap-3 rounded-xl p-2.5 transition hover:bg-slate-50 ${
                  unread ? 'bg-brand-50/40' : ''
                }`}
              >
                <div
                  className={`flex h-9 w-9 shrink-0 items-center justify-center rounded-xl ${
                    unread ? 'bg-brand-100 text-brand-700' : 'bg-slate-100 text-slate-500'
                  }`}
                >
                  <Bell className="h-4 w-4" />
                </div>
                <div className="min-w-0 flex-1">
                  <div className="flex items-start justify-between gap-2">
                    <p className={`text-sm ${unread ? 'font-semibold text-slate-900' : 'font-medium text-slate-700'}`}>
                      {item.title || 'Notification'}
                    </p>
                    {unread ? (
                      <span className="mt-1.5 h-2 w-2 shrink-0 rounded-full bg-brand-500" aria-label="Unread" />
                    ) : null}
                  </div>
                  {item.body ? (
                    <p className="mt-0.5 line-clamp-2 text-xs text-slate-500">{item.body}</p>
                  ) : null}
                  <p className="mt-1 text-[11px] text-slate-400">{formatRelativeTime(item.created_at)}</p>
                </div>
              </div>
            )

            if (item.action_url) {
              return (
                <li key={item.id}>
                  <Link
                    to={item.action_url}
                    onClick={() => {
                      if (unread && onMarkRead) onMarkRead(item.id)
                    }}
                    className="block"
                  >
                    {inner}
                  </Link>
                </li>
              )
            }

            return (
              <li key={item.id}>
                <button
                  type="button"
                  className="w-full text-left"
                  onClick={() => {
                    if (unread && onMarkRead) onMarkRead(item.id)
                  }}
                >
                  {inner}
                </button>
              </li>
            )
          })}
        </ul>
      ) : null}
    </div>
  )
}
