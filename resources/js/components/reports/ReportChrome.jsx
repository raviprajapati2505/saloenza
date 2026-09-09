import React from 'react'
import { RefreshCw } from 'lucide-react'
import BaseButton from '../ui/BaseButton.jsx'

/**
 * Shared report filter bar: date range + optional slot for extra filters.
 */
export function ReportFilters({
  from,
  to,
  onFromChange,
  onToChange,
  onApply,
  onRefresh,
  loading = false,
  children,
  applyLabel = 'Apply',
}) {
  return (
    <form
      className="flex flex-col gap-3 rounded-[18px] border border-slate-200 bg-white p-4 shadow-sm sm:flex-row sm:flex-wrap sm:items-end"
      onSubmit={(e) => {
        e.preventDefault()
        onApply?.()
      }}
    >
      {from !== undefined ? (
        <div>
          <label htmlFor="report-filter-from" className="mb-1 block text-xs font-medium text-slate-600">From</label>
          <input
            id="report-filter-from"
            type="date"
            value={from}
            onChange={(e) => onFromChange?.(e.target.value)}
            className="rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-700 focus:border-brand-400 focus:outline-none focus:ring-2 focus:ring-brand-500/20"
          />
        </div>
      ) : null}
      {to !== undefined ? (
        <div>
          <label htmlFor="report-filter-to" className="mb-1 block text-xs font-medium text-slate-600">To</label>
          <input
            id="report-filter-to"
            type="date"
            value={to}
            onChange={(e) => onToChange?.(e.target.value)}
            className="rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-700 focus:border-brand-400 focus:outline-none focus:ring-2 focus:ring-brand-500/20"
          />
        </div>
      ) : null}
      {children}
      <div className="flex flex-wrap gap-2">
        {onApply ? (
          <BaseButton type="submit" size="sm" disabled={loading}>
            {applyLabel}
          </BaseButton>
        ) : null}
        {onRefresh ? (
          <BaseButton
            type="button"
            variant="secondary"
            size="sm"
            leftIcon={RefreshCw}
            disabled={loading}
            onClick={onRefresh}
          >
            Refresh
          </BaseButton>
        ) : null}
      </div>
    </form>
  )
}

export function ReportTabs({ tabs = [], active, onChange }) {
  return (
    <div className="flex flex-wrap gap-2 border-b border-slate-200 pb-px">
      {tabs.map((tab) => {
        const isActive = tab.key === active
        return (
          <button
            key={tab.key}
            type="button"
            onClick={() => onChange?.(tab.key)}
            className={`rounded-t-xl px-4 py-2.5 text-sm font-semibold transition ${
              isActive
                ? 'border border-b-white border-slate-200 bg-white text-brand-700 -mb-px'
                : 'text-slate-500 hover:text-slate-800'
            }`}
          >
            {tab.label}
            {tab.count != null ? (
              <span className={`ml-2 rounded-full px-1.5 py-0.5 text-[10px] font-bold ${
                isActive ? 'bg-brand-100 text-brand-700' : 'bg-slate-100 text-slate-500'
              }`}
              >
                {tab.count}
              </span>
            ) : null}
          </button>
        )
      })}
    </div>
  )
}

export function ReportKpiGrid({ items = [], loading = false }) {
  if (loading) {
    return (
      <div className="grid grid-cols-2 gap-3 xl:grid-cols-4">
        {Array.from({ length: 4 }).map((_, i) => (
          <div key={i} className="h-24 animate-pulse rounded-2xl bg-slate-100" />
        ))}
      </div>
    )
  }

  return (
    <div className="grid grid-cols-2 gap-3 xl:grid-cols-4">
      {items.map((item) => (
        <div
          key={item.key || item.label}
          className="rounded-[18px] border border-slate-200 bg-white p-4 shadow-[0_8px_30px_rgb(0,0,0,0.04)]"
        >
          <p className="text-[10px] font-semibold uppercase tracking-wider text-slate-400">{item.label}</p>
          <p className="mt-2 text-2xl font-bold tracking-tight text-slate-900">{item.value}</p>
          {item.hint ? <p className="mt-1 text-xs text-slate-400">{item.hint}</p> : null}
        </div>
      ))}
    </div>
  )
}

export function ReportTable({
  columns = [],
  rows = [],
  loading = false,
  emptyMessage = 'No records found.',
  getRowKey = (row, i) => row.id ?? i,
}) {
  return (
    <div className="overflow-hidden rounded-[18px] border border-slate-200 bg-white shadow-sm">
      <div className="overflow-x-auto">
        <table className="w-full min-w-[640px] text-left text-sm">
          <thead>
            <tr className="border-b border-slate-100 bg-slate-50/80 text-[11px] font-semibold uppercase tracking-wider text-slate-400">
              {columns.map((col) => (
                <th key={col.key} className="px-4 py-3 font-semibold">
                  {col.label}
                </th>
              ))}
            </tr>
          </thead>
          <tbody className="divide-y divide-slate-50">
            {loading ? (
              Array.from({ length: 5 }).map((_, i) => (
                <tr key={i}>
                  <td colSpan={columns.length} className="px-4 py-3">
                    <div className="h-4 animate-pulse rounded bg-slate-100" />
                  </td>
                </tr>
              ))
            ) : rows.length === 0 ? (
              <tr>
                <td colSpan={columns.length} className="px-4 py-12 text-center text-sm text-slate-500">
                  {emptyMessage}
                </td>
              </tr>
            ) : (
              rows.map((row, index) => (
                <tr key={getRowKey(row, index)} className="hover:bg-slate-50/80">
                  {columns.map((col) => (
                    <td key={col.key} className="px-4 py-3 text-slate-700">
                      {col.render ? col.render(row) : row[col.key]}
                    </td>
                  ))}
                </tr>
              ))
            )}
          </tbody>
        </table>
      </div>
    </div>
  )
}

export function StatusChips({ options = [], value, onChange }) {
  return (
    <div className="flex flex-wrap gap-2">
      {options.map((opt) => {
        const active = value === opt.value
        return (
          <button
            key={opt.value}
            type="button"
            onClick={() => onChange?.(opt.value)}
            className={`rounded-full border px-3 py-1.5 text-xs font-semibold transition ${
              active
                ? 'border-brand-300 bg-brand-50 text-brand-700'
                : 'border-slate-200 bg-white text-slate-600 hover:border-slate-300'
            }`}
          >
            {opt.label}
          </button>
        )
      })}
    </div>
  )
}
