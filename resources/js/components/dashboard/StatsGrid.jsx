import React from 'react'
import KpiCard from './KpiCard.jsx'

/**
 * Renders a responsive grid of KPI cards from a dynamic items array.
 *
 * @param {Array<{
 *   key?: string,
 *   title: string,
 *   value: string|number,
 *   change?: string,
 *   trend?: 'up'|'down',
 *   icon?: import('lucide-react').LucideIcon,
 *   color?: string,
 * }>} items
 */
export default function StatsGrid({
  items = [],
  loading = false,
  columns = 'grid-cols-1 sm:grid-cols-2 xl:grid-cols-4',
  emptyMessage = 'No metrics available.',
}) {
  if (!loading && items.length === 0) {
    return (
      <div className="rounded-2xl border border-dashed border-slate-200 bg-white px-6 py-10 text-center text-sm text-slate-500">
        {emptyMessage}
      </div>
    )
  }

  const skeletonCount = loading && items.length === 0 ? 4 : items.length

  return (
    <div className={`grid gap-4 ${columns}`}>
      {(loading && items.length === 0
        ? Array.from({ length: skeletonCount }, (_, i) => ({ key: `skel-${i}`, title: '', value: '' }))
        : items
      ).map((item, index) => (
        <KpiCard
          key={item.key || item.title || index}
          title={item.title}
          value={item.value}
          change={item.change}
          trend={item.trend}
          icon={item.icon}
          color={item.color}
          loading={loading}
          delay={index * 0.05}
        />
      ))}
    </div>
  )
}
