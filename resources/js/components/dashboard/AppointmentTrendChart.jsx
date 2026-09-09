import React, { useMemo } from 'react'
import {
  Chart as ChartJS,
  CategoryScale,
  LinearScale,
  PointElement,
  LineElement,
  BarElement,
  Filler,
  Tooltip,
  Legend,
} from 'chart.js'
import { Line, Bar } from 'react-chartjs-2'
import ChartCard from './ChartCard.jsx'
import { CHART_BRAND } from './brandColors.js'

ChartJS.register(
  CategoryScale,
  LinearScale,
  PointElement,
  LineElement,
  BarElement,
  Filler,
  Tooltip,
  Legend,
)

function softFromColor(color, alpha = 0.12) {
  if (!color) return CHART_BRAND.primarySoft
  const match = color.match(/rgb\((\d+),\s*(\d+),\s*(\d+)\)/)
  if (match) return `rgba(${match[1]}, ${match[2]}, ${match[3]}, ${alpha})`
  return CHART_BRAND.primarySoft
}

/**
 * Reusable salon analytics chart.
 * data: [{ label, value }] or [{ label, revenue, appointments }]
 * type: 'line' | 'bar' | 'area'
 */
export default function AppointmentTrendChart({
  title = 'Appointment trend',
  subtitle = 'Last 7 days',
  data = [],
  loading = false,
  error = '',
  type = 'area',
  valueKey = 'value',
  valueLabel = 'Appointments',
  color = CHART_BRAND.primary,
}) {
  const empty = !loading && !error && data.length === 0
  const barAlpha = 0.72
  const fillColor = type === 'bar'
    ? softFromColor(color, barAlpha)
    : softFromColor(color, 0.14)

  const chartData = useMemo(() => ({
    labels: data.map((d) => d.label),
    datasets: [
      {
        label: valueLabel,
        data: data.map((d) => Number(d[valueKey] ?? d.value ?? 0)),
        borderColor: color,
        backgroundColor: fillColor,
        fill: type === 'area',
        tension: 0.4,
        pointRadius: type === 'bar' ? 0 : 3,
        pointHoverRadius: 6,
        pointBackgroundColor: '#fff',
        pointBorderColor: color,
        pointBorderWidth: 2,
        borderRadius: type === 'bar' ? 8 : 0,
        borderSkipped: false,
      },
    ],
  }), [data, valueKey, valueLabel, color, type, fillColor])

  const options = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
      legend: { display: false },
      tooltip: {
        backgroundColor: '#0f172a',
        padding: 12,
        cornerRadius: 8,
        titleFont: { family: "'Instrument Sans', sans-serif" },
        bodyFont: { family: "'Instrument Sans', sans-serif" },
      },
    },
    scales: {
      x: {
        grid: { display: false },
        ticks: { color: '#94a3b8', font: { family: "'Instrument Sans', sans-serif", size: 11 } },
      },
      y: {
        beginAtZero: true,
        grid: { color: '#f1f5f9' },
        border: { display: false },
        ticks: {
          precision: 0,
          color: '#94a3b8',
          font: { family: "'Instrument Sans', sans-serif", size: 11 },
        },
      },
    },
    interaction: { intersect: false, mode: 'index' },
  }

  const Chart = type === 'bar' ? Bar : Line

  return (
    <ChartCard
      title={title}
      subtitle={subtitle}
      loading={loading}
      empty={empty}
      error={error}
      emptyMessage="No appointment activity in this period."
    >
      <Chart data={chartData} options={options} />
    </ChartCard>
  )
}
