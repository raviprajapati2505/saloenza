import React, { useMemo } from 'react'
import {
  Chart as ChartJS,
  CategoryScale,
  LinearScale,
  PointElement,
  LineElement,
  Filler,
  Tooltip,
  Legend,
} from 'chart.js'
import { Line } from 'react-chartjs-2'
import ChartCard from './ChartCard.jsx'
import { CHART_BRAND } from './brandColors.js'
import { formatMoneyDefault } from '../../lib/tenantFormatting.js'

ChartJS.register(
  CategoryScale,
  LinearScale,
  PointElement,
  LineElement,
  Filler,
  Tooltip,
  Legend,
)

/**
 * Revenue area chart — prop-driven series.
 * data: [{ label|date, amount|value }]
 */
export default function RevenueChart({
  title = 'Revenue overview',
  subtitle = 'Last 7 days',
  data = [],
  loading = false,
  error = '',
  formatMoney = formatMoneyDefault,
}) {
  const empty = !loading && !error && data.length === 0

  const chartData = useMemo(() => ({
    labels: data.map((d) => d.label || d.date),
    datasets: [
      {
        fill: true,
        label: 'Revenue',
        data: data.map((d) => Number(d.amount ?? d.value ?? 0)),
        borderColor: CHART_BRAND.primary,
        backgroundColor: CHART_BRAND.primarySoft,
        tension: 0.4,
        pointRadius: 0,
        pointHoverRadius: 6,
        pointBackgroundColor: '#fff',
        pointBorderColor: CHART_BRAND.primary,
        pointBorderWidth: 2,
      },
    ],
  }), [data])

  const options = useMemo(() => ({
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
      legend: { display: false },
      tooltip: {
        backgroundColor: '#0f172a',
        padding: 12,
        cornerRadius: 8,
        callbacks: {
          label(ctx) {
            const n = ctx.parsed.y
            return `Revenue: ${formatMoney(n)}`
          },
        },
      },
    },
    scales: {
      x: {
        grid: { display: false },
        ticks: { color: '#94a3b8', maxTicksLimit: 7, font: { family: "'Instrument Sans', sans-serif", size: 11 } },
      },
      y: {
        beginAtZero: true,
        grid: { color: '#f1f5f9' },
        border: { display: false },
        ticks: {
          color: '#94a3b8',
          font: { family: "'Instrument Sans', sans-serif", size: 11 },
          callback(value) {
            const amount = Number(value)
            if (amount >= 1000) {
              const sample = formatMoney(1000, { maximumFractionDigits: 0 })
              const sym = sample.replace(/[\d,.\s]/g, '') || ''
              const compact = Math.round(amount / 100) / 10
              return `${sym}${compact}k`
            }
            return formatMoney(amount, { maximumFractionDigits: 0 })
          },
        },
      },
    },
    interaction: { intersect: false, mode: 'index' },
  }), [formatMoney])

  return (
    <ChartCard
      title={title}
      subtitle={subtitle}
      loading={loading}
      empty={empty}
      error={error}
      emptyMessage="No revenue in this period."
      className="h-full"
    >
      <Line data={chartData} options={options} />
    </ChartCard>
  )
}
