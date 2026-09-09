import React from 'react'
import { ChevronLeft, ChevronRight } from 'lucide-react'

/**
 * Shared list footer: total count, optional page-size, prev/next.
 * Always renders when total > 0 so users can see record counts even on a single page.
 */
export default function ListPagination({
  page = 1,
  pageSize = 10,
  total = 0,
  onPageChange,
  onPageSizeChange,
  label = 'items',
  pageSizeOptions = [5, 10, 25, 50, 100],
}) {
  if (total <= 0) return null

  const totalPages = Math.max(1, Math.ceil(total / pageSize))
  const currentPage = Math.min(Math.max(1, page), totalPages)
  const start = Math.min((currentPage - 1) * pageSize + 1, total)
  const end = Math.min(currentPage * pageSize, total)

  return (
    <div className="flex shrink-0 flex-col gap-3 border-t border-slate-100 bg-slate-50/80 p-4 sm:flex-row sm:items-center sm:justify-between">
      <div className="flex items-center justify-between gap-3 sm:justify-start">
        {typeof onPageSizeChange === 'function' ? (
          <>
            <span className="text-sm font-medium text-slate-500">Rows per page:</span>
            <select
              value={pageSize}
              onChange={(e) => onPageSizeChange(Number(e.target.value))}
              className="cursor-pointer rounded-lg border border-slate-200 bg-white px-2 py-1 text-sm font-medium text-slate-700 shadow-sm outline-none focus:border-brand-500"
            >
              {pageSizeOptions.map((n) => (
                <option key={n} value={n}>{n}</option>
              ))}
            </select>
          </>
        ) : (
          <span className="text-sm font-medium text-slate-500 sm:hidden">
            {start}–{end} of {total} {label}
          </span>
        )}
      </div>

      <div className="flex items-center justify-between gap-4 sm:justify-end">
        <span className="hidden text-sm font-medium text-slate-500 sm:inline">
          {start}–{end} of {total} {label}
        </span>
        <span className="text-sm font-medium text-slate-500 sm:hidden">
          Page {currentPage} of {totalPages}
        </span>
        <div className="flex items-center gap-1">
          <button
            type="button"
            disabled={currentPage <= 1}
            onClick={() => onPageChange?.(Math.max(currentPage - 1, 1))}
            className="rounded-md p-1.5 text-slate-500 transition-colors hover:bg-slate-200 hover:text-slate-800 disabled:cursor-not-allowed disabled:opacity-30 disabled:hover:bg-transparent"
            aria-label="Previous page"
          >
            <ChevronLeft className="h-4 w-4" />
          </button>
          <button
            type="button"
            disabled={currentPage >= totalPages}
            onClick={() => onPageChange?.(Math.min(currentPage + 1, totalPages))}
            className="rounded-md p-1.5 text-slate-500 transition-colors hover:bg-slate-200 hover:text-slate-800 disabled:cursor-not-allowed disabled:opacity-30 disabled:hover:bg-transparent"
            aria-label="Next page"
          >
            <ChevronRight className="h-4 w-4" />
          </button>
        </div>
      </div>
    </div>
  )
}
