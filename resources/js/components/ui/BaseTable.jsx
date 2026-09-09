import React, { useState } from 'react'
import { ChevronUp, ChevronDown } from 'lucide-react'
import { clsx } from 'clsx'
import { twMerge } from 'tailwind-merge'

export function cn(...inputs) {
  return twMerge(clsx(inputs))
}

export default function BaseTable({
  columns = [],
  rows = [],
  loading = false,
  emptyState,
  className
}) {
  const [sortConfig, setSortConfig] = useState({ key: null, direction: 'asc' })

  const requestSort = (key) => {
    let direction = 'asc'
    if (sortConfig.key === key && sortConfig.direction === 'asc') {
      direction = 'desc'
    }
    setSortConfig({ key, direction })
  }

  const sortedRows = React.useMemo(() => {
    if (!sortConfig.key) return rows
    const sortableItems = [...rows]
    sortableItems.sort((a, b) => {
      if (a[sortConfig.key] < b[sortConfig.key]) return sortConfig.direction === 'asc' ? -1 : 1
      if (a[sortConfig.key] > b[sortConfig.key]) return sortConfig.direction === 'asc' ? 1 : -1
      return 0
    })
    return sortableItems
  }, [rows, sortConfig])

  return (
    <div className={cn("w-full overflow-hidden rounded-xl ring-1 ring-slate-200 shadow-sm bg-white", className)}>
      <div className="overflow-x-auto">
        <table className="min-w-full divide-y divide-slate-200">
          <thead className="bg-slate-50 border-b border-slate-200">
            <tr>
              {columns.map((column) => (
                <th
                  key={column.key}
                  scope="col"
                  className={cn(
                    "px-6 py-3.5 text-left text-xs font-semibold tracking-wide text-slate-500 uppercase",
                    column.sortable && "cursor-pointer hover:bg-slate-100 transition-colors select-none group"
                  )}
                  onClick={() => column.sortable && requestSort(column.key)}
                >
                  <div className="flex items-center gap-1.5">
                    {column.label}
                    {column.sortable && (
                      <span className="flex flex-col items-center">
                        <ChevronUp className={cn("h-2.5 w-2.5 -mb-0.5", sortConfig.key === column.key && sortConfig.direction === 'asc' ? "text-brand-600" : "text-slate-300 group-hover:text-slate-400")} />
                        <ChevronDown className={cn("h-2.5 w-2.5 -mt-0.5", sortConfig.key === column.key && sortConfig.direction === 'desc' ? "text-brand-600" : "text-slate-300 group-hover:text-slate-400")} />
                      </span>
                    )}
                  </div>
                </th>
              ))}
            </tr>
          </thead>
          
          <tbody className="divide-y divide-slate-100 bg-white">
            {loading ? (
              // Loading Skeleton
              Array.from({ length: 5 }).map((_, i) => (
                <tr key={i} className="animate-pulse">
                  {columns.map((col, j) => (
                    <td key={j} className="whitespace-nowrap px-6 py-4">
                      <div className="h-4 bg-slate-100 rounded w-3/4"></div>
                    </td>
                  ))}
                </tr>
              ))
            ) : sortedRows.length > 0 ? (
              // Data Rows
              sortedRows.map((row, i) => (
                <tr key={i} className="hover:bg-slate-50/50 transition-colors">
                  {columns.map((column) => (
                    <td key={column.key} className="whitespace-nowrap px-6 py-4 text-sm text-slate-700">
                      {column.render ? column.render(row) : row[column.key]}
                    </td>
                  ))}
                </tr>
              ))
            ) : (
              // Empty State
              <tr>
                <td colSpan={columns.length} className="px-6 py-12 text-center">
                  {emptyState || (
                    <span className="text-sm text-slate-500">No data available.</span>
                  )}
                </td>
              </tr>
            )}
          </tbody>
        </table>
      </div>
    </div>
  )
}
