import React, { useMemo } from 'react'
import { Chart as ChartJS, ArcElement, Tooltip, Legend } from 'chart.js'
import { Doughnut } from 'react-chartjs-2'
import ChartCard from './ChartCard.jsx'
import { CHART_BRAND } from './brandColors.js'

ChartJS.register(ArcElement, Tooltip, Legend)

const DEFAULT_SEGMENTS = [
  { key: 'scheduled', label: 'Scheduled', color: CHART_BRAND.tertiary },
  { key: 'confirmed', label: 'Confirmed', color: CHART_BRAND.primary },
  { key: 'in-progress', label: 'In progress', color: CHART_BRAND.secondary },
  { key: 'completed', label: 'Completed', color: 'rgb(192, 38, 140)' },
  { key: 'cancelled', label: 'Cancelled', color: CHART_BRAND.danger },
  { key: 'no-show', label: 'No show', color: CHART_BRAND.muted },
]

/**
 * Donut chart of appointment statuses.
 * summary: { scheduled, confirmed, 'in-progress', completed, cancelled, 'no-show', ... }
 */
export default function AppointmentStatusChart({
  title = 'Appointment status',
  subtitle = "Today's distribution",
  summary = {},
  segments = DEFAULT_SEGMENTS,
  loading = false,
  error = '',
}) {
  const chartData = useMemo(() => {
    const activeSegments = segments.filter((s) => Number(summary?.[s.key] ?? 0) > 0)
    const useSegments = activeSegments.length > 0 ? activeSegments : segments
    const values = useSegments.map((s) => Number(summary?.[s.key] ?? 0))
    return {
      total: values.reduce((sum, n) => sum + n, 0),
      data: {
        labels: useSegments.map((s) => s.label),
        datasets: [
          {
            data: values,
            backgroundColor: useSegments.map((s) => s.color),
            borderWidth: 0,
            hoverOffset: 6,
          },
        ],
      },
    }
  }, [summary, segments])

  const empty = !loading && !error && chartData.total === 0

  const options = {
    responsive: true,
    maintainAspectRatio: false,
    cutout: '68%',
    plugins: {
      legend: {
        position: 'bottom',
        labels: {
          usePointStyle: true,
          pointStyle: 'circle',
          padding: 14,
          color: '#64748b',
          font: { family: "'Instrument Sans', sans-serif", size: 11 },
        },
      },
      tooltip: {
        backgroundColor: '#0f172a',
        padding: 12,
        cornerRadius: 8,
      },
    },
  }

  return (
    <ChartCard
      title={title}
      subtitle={subtitle}
      loading={loading}
      empty={empty}
      error={error}
      emptyMessage="No appointments to chart today."
    >
      <div className="relative mx-auto h-full max-w-[260px]">
        <Doughnut data={chartData.data} options={options} />
        <div className="pointer-events-none absolute inset-0 flex flex-col items-center justify-center pb-10">
          <p className="text-2xl font-bold text-slate-900">{chartData.total}</p>
          <p className="text-[10px] font-semibold uppercase tracking-wider text-slate-400">Today</p>
        </div>
      </div>
    </ChartCard>
  )
}
