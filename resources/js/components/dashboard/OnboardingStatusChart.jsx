import React, { useMemo } from 'react'
import {
  Chart as ChartJS,
  ArcElement,
  Tooltip,
  Legend,
} from 'chart.js'
import { Doughnut } from 'react-chartjs-2'
import ChartCard from './ChartCard.jsx'
import { CHART_BRAND } from './brandColors.js'

ChartJS.register(ArcElement, Tooltip, Legend)

const DEFAULT_SEGMENTS = [
  { key: 'completed', label: 'Completed', color: CHART_BRAND.primary },
  { key: 'pending', label: 'Pending', color: CHART_BRAND.secondary },
  { key: 'no_owner', label: 'No owner', color: CHART_BRAND.muted },
]

/**
 * Donut chart of salon onboarding statuses.
 * Pass `summary` like `{ completed, pending, no_owner }` from the onboarding API.
 */
export default function OnboardingStatusChart({
  title = 'Onboarding status',
  subtitle = 'Distribution across all salon records',
  summary = {},
  segments = DEFAULT_SEGMENTS,
  loading = false,
  error = '',
}) {
  const chartData = useMemo(() => {
    const values = segments.map((seg) => Number(summary?.[seg.key] ?? 0))
    return {
      values,
      total: values.reduce((sum, n) => sum + n, 0),
      data: {
        labels: segments.map((s) => s.label),
        datasets: [
          {
            data: values,
            backgroundColor: segments.map((s) => s.color),
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
          padding: 16,
          color: '#64748b',
          font: { family: "'Instrument Sans', sans-serif", size: 12 },
        },
      },
      tooltip: {
        backgroundColor: '#0f172a',
        padding: 12,
        cornerRadius: 8,
        titleFont: { family: "'Instrument Sans', sans-serif" },
        bodyFont: { family: "'Instrument Sans', sans-serif" },
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
      emptyMessage="No onboarding records to chart yet."
    >
      <div className="relative mx-auto h-full max-w-[280px]">
        <Doughnut data={chartData.data} options={options} />
        <div className="pointer-events-none absolute inset-0 flex flex-col items-center justify-center pb-10">
          <p className="text-2xl font-bold text-slate-900">{chartData.total}</p>
          <p className="text-[10px] font-semibold uppercase tracking-wider text-slate-400">Salons</p>
        </div>
      </div>
    </ChartCard>
  )
}
