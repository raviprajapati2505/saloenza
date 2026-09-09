import React from 'react'
import { BarChart3 } from 'lucide-react'

/**
 * Reusable chart shell: title, subtitle, loading skeleton, empty & error states.
 * Pass the chart element as children when data is ready.
 */
export default function ChartCard({
  title,
  subtitle,
  loading = false,
  empty = false,
  emptyMessage = 'No data available for this period.',
  error = '',
  action,
  children,
  className = '',
  minHeight = 'min-h-[260px]',
}) {
  return (
    <div
      className={`flex h-full flex-col rounded-[18px] border border-slate-200 bg-white p-5 shadow-[0_8px_30px_rgb(0,0,0,0.04)] sm:p-6 ${className}`}
    >
      <div className="mb-5 flex items-start justify-between gap-3">
        <div className="min-w-0">
          {title ? <h3 className="text-base font-semibold text-slate-900">{title}</h3> : null}
          {subtitle ? <p className="mt-0.5 text-sm text-slate-500">{subtitle}</p> : null}
        </div>
        {action}
      </div>

      <div className={`relative flex-1 ${minHeight}`}>
        {loading ? (
          <div className="absolute inset-0 flex animate-pulse flex-col gap-3">
            <div className="h-full w-full rounded-xl bg-slate-50" />
          </div>
        ) : null}

        {!loading && error ? (
          <div className="absolute inset-0 flex flex-col items-center justify-center gap-2 rounded-xl border border-rose-100 bg-rose-50/50 px-4 text-center">
            <p className="text-sm font-medium text-rose-700">{error}</p>
          </div>
        ) : null}

        {!loading && !error && empty ? (
          <div className="absolute inset-0 flex flex-col items-center justify-center gap-2 rounded-xl border border-dashed border-slate-200 bg-slate-50/50 px-4 text-center">
            <BarChart3 className="h-8 w-8 text-slate-300" />
            <p className="text-sm text-slate-500">{emptyMessage}</p>
          </div>
        ) : null}

        {!loading && !error && !empty ? children : null}
      </div>
    </div>
  )
}
