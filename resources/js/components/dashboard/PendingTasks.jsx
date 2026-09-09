import React from 'react'
import { ListTodo } from 'lucide-react'
import { Link } from 'react-router-dom'
import BaseBadge from '../ui/BaseBadge.jsx'

const PRIORITY_VARIANT = {
  high: 'danger',
  medium: 'warning',
  low: 'info',
  default: 'default',
}

/**
 * Pending manager tasks — prop-driven.
 * items: [{ id, title, description, priority, href, count }]
 */
export default function PendingTasks({
  title = 'Pending tasks',
  items = [],
  loading = false,
  error = '',
  emptyMessage = 'Nothing pending right now.',
}) {
  return (
    <div className="flex h-full flex-col rounded-[18px] border border-slate-200 bg-white p-5 shadow-[0_8px_30px_rgb(0,0,0,0.04)] sm:p-6">
      <h3 className="mb-5 text-base font-semibold text-slate-900">{title}</h3>

      {loading ? (
        <div className="space-y-3">
          {Array.from({ length: 3 }).map((_, i) => (
            <div key={i} className="h-16 animate-pulse rounded-xl bg-slate-50" />
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
          <ListTodo className="h-8 w-8 text-slate-300" />
          <p className="text-sm text-slate-500">{emptyMessage}</p>
        </div>
      ) : null}

      {!loading && !error && items.length > 0 ? (
        <ul className="space-y-2">
          {items.map((task) => {
            const body = (
              <div className="flex items-start gap-3 rounded-xl border border-slate-100 bg-slate-50/40 p-3 transition hover:border-brand-200 hover:bg-white hover:shadow-sm">
                <div className="min-w-0 flex-1">
                  <div className="flex flex-wrap items-center gap-2">
                    <p className="text-sm font-semibold text-slate-900">{task.title}</p>
                    {task.priority ? (
                      <BaseBadge variant={PRIORITY_VARIANT[task.priority] || 'default'} size="sm">
                        {task.priority}
                      </BaseBadge>
                    ) : null}
                  </div>
                  {task.description ? (
                    <p className="mt-0.5 text-xs text-slate-500">{task.description}</p>
                  ) : null}
                </div>
                {task.count != null ? (
                  <span className="inline-flex h-7 min-w-7 items-center justify-center rounded-full bg-brand-100 px-2 text-xs font-bold text-brand-700">
                    {task.count}
                  </span>
                ) : null}
              </div>
            )

            return (
              <li key={task.id || task.title}>
                {task.href ? (
                  <Link to={task.href} className="block">
                    {body}
                  </Link>
                ) : (
                  body
                )}
              </li>
            )
          })}
        </ul>
      ) : null}
    </div>
  )
}
