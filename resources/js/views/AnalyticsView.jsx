import React, { useState, useEffect, useMemo } from 'react'
import { motion } from 'framer-motion'
import {
  TrendingUp, Users, Calendar, DollarSign, Star,
  ArrowUpRight, ArrowDownRight, Scissors, BarChart2,
  Activity, Target, Zap
} from 'lucide-react'
import {
  Chart as ChartJS,
  CategoryScale, LinearScale, BarElement, PointElement,
  LineElement, Title, Tooltip, Legend, Filler, ArcElement
} from 'chart.js'
import { Bar, Line, Doughnut } from 'react-chartjs-2'
import PageHeader from '../components/ui/PageHeader.jsx'
import { useTenantFormatter } from '../hooks/useTenantFormatter.js'

ChartJS.register(
  CategoryScale, LinearScale, BarElement, PointElement,
  LineElement, Title, Tooltip, Legend, Filler, ArcElement
)

// ─── Mock Data ────────────────────────────────────────────────
const MONTHS = ['Dec', 'Jan', 'Feb', 'Mar', 'Apr', 'May']

const MOCK_DATA = {
  kpis: {
    revenue:      { value: 3_42_500, change: +12.4, label: 'Total Revenue (May)', icon: DollarSign, color: 'teal' },
    appointments: { value: 486,      change: +8.2,  label: 'Appointments (May)',  icon: Calendar,  color: 'indigo' },
    customers:    { value: 124,      change: +5.6,  label: 'Active Customers',    icon: Users,     color: 'violet' },
    avgRating:    { value: 4.82,     change: +0.05, label: 'Avg Rating',          icon: Star,      color: 'amber' },
  },
  revenueMonthly:    [198000, 215000, 232000, 287000, 310000, 342500],
  appointmentsMonthly: [320,  345,    388,    420,    452,    486],
  topServices: [
    { name: 'Balayage Coloring', revenue: 92400, bookings: 66, share: 27.0 },
    { name: 'Haircut & Blowdry', revenue: 74500, bookings: 120, share: 21.7 },
    { name: 'Bridal Makeup',     revenue: 58000, bookings: 14, share: 16.9 },
    { name: 'Hair Spa',          revenue: 42300, bookings: 94, share: 12.3 },
    { name: 'Nail Art',          revenue: 38800, bookings: 88, share: 11.3 },
    { name: 'Beard Trim',        revenue: 36500, bookings: 182, share: 10.6 },
  ],
  branchRevenue: {
    labels: ['Downtown', 'Westside', 'Airport Rd', 'MG Road'],
    data:   [148000, 92500, 61000, 41000],
  },
  staffPerformance: [
    { name: 'Emma Watson',  revenue: 98500, appointments: 142, rating: 4.85 },
    { name: 'Sophia Loren', revenue: 82300, appointments: 118, rating: 4.95 },
    { name: 'Brad Pitt',    revenue: 71200, appointments: 96,  rating: 4.70 },
    { name: 'Liam Neeson',  revenue: 55400, appointments: 88,  rating: 4.90 },
    { name: 'Olivia Jones', revenue: 35100, appointments: 62,  rating: 4.75 },
  ]
}

// ─── Chart default styling ────────────────────────────────────
const tooltipStyle = {
  backgroundColor: '#1e293b',
  titleFont: { size: 11, weight: 'bold' },
  bodyFont: { size: 12 },
  padding: 10,
  cornerRadius: 8,
}

// ─── KPI Card ─────────────────────────────────────────────────
function KpiCard({ label, value, change, icon: Icon, color, delay, formatMoney }) {
  const colorMap = {
    teal:   { bg: 'bg-brand-50',   border: 'border-brand-100',   text: 'text-brand-600'   },
    indigo: { bg: 'bg-indigo-50', border: 'border-indigo-100', text: 'text-indigo-600' },
    violet: { bg: 'bg-violet-50', border: 'border-violet-100', text: 'text-violet-600' },
    amber:  { bg: 'bg-amber-50',  border: 'border-amber-100',  text: 'text-amber-600'  },
  }
  const c = colorMap[color]
  const isPositive = change >= 0

  return (
    <motion.div
      initial={{ opacity: 0, y: 16 }}
      animate={{ opacity: 1, y: 0 }}
      transition={{ delay }}
      className="bg-white border border-slate-200/80 rounded-2xl p-5 shadow-sm relative overflow-hidden group"
    >
      <div className="absolute -right-6 -top-6 w-20 h-20 bg-slate-50 rounded-full group-hover:scale-150 transition-transform duration-500" />
      <div className={`absolute right-4 top-4 p-2 rounded-xl border ${c.bg} ${c.border} ${c.text}`}>
        <Icon className="w-4 h-4" />
      </div>
      <p className="text-[10px] font-bold text-slate-400 uppercase tracking-wider relative">{label}</p>
      <h3 className="text-2xl font-black text-slate-900 mt-2 tracking-tight relative">
        {typeof value === 'number' && formatMoney
          ? formatMoney(value)
          : value}
      </h3>
      <div className={`mt-3 flex items-center gap-1 text-xs font-bold relative ${isPositive ? 'text-emerald-600' : 'text-rose-500'}`}>
        {isPositive ? <ArrowUpRight className="w-3.5 h-3.5" /> : <ArrowDownRight className="w-3.5 h-3.5" />}
        <span>{isPositive ? '+' : ''}{change}% vs last month</span>
      </div>
    </motion.div>
  )
}

// ─── Main View ───────────────────────────────────────────────
export default function AnalyticsView() {
  const fmt = useTenantFormatter()
  const [rangeLabel] = useState('May 2026')

  const revenueChartData = useMemo(() => ({
    labels: MONTHS,
    datasets: [{
      label: `Revenue (${fmt.symbol()})`,
      data: MOCK_DATA.revenueMonthly,
      borderColor: 'rgb(20, 184, 166)',
      backgroundColor: 'rgba(20, 184, 166, 0.08)',
      tension: 0.4,
      fill: true,
      pointBackgroundColor: 'rgb(20, 184, 166)',
      pointBorderColor: '#fff',
      pointRadius: 5,
      pointHoverRadius: 7,
    }],
  }), [fmt])

  const revenueChartOptions = useMemo(() => ({
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
      legend: { display: false },
      tooltip: {
        ...tooltipStyle,
        callbacks: {
          label: (ctx) => ` ${fmt.money(ctx.parsed.y)}`,
        },
      },
    },
    scales: {
      y: {
        grid: { color: '#f1f5f9' },
        ticks: {
          color: '#64748b',
          callback: (v) => {
            const amount = Number(v)
            if (amount >= 1000) {
              const sym = fmt.money(1000).replace(/[\d,.\s]/g, '')
              return `${sym}${Math.round(amount / 100) / 10}k`
            }
            return fmt.money(amount, { maximumFractionDigits: 0 })
          },
        },
      },
      x: { grid: { display: false }, ticks: { color: '#64748b' } },
    },
  }), [fmt])

  const apptChartData = useMemo(() => ({
    labels: MONTHS,
    datasets: [{
      label: 'Appointments',
      data: MOCK_DATA.appointmentsMonthly,
      backgroundColor: 'rgba(99, 102, 241, 0.8)',
      borderRadius: 8,
      borderSkipped: false,
    }]
  }), [])

  const apptChartOptions = useMemo(() => ({
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
      legend: { display: false },
      tooltip: tooltipStyle,
    },
    scales: {
      y: { grid: { color: '#f1f5f9' }, ticks: { color: '#64748b' } },
      x: { grid: { display: false }, ticks: { color: '#64748b' } },
    },
  }), [])

  const branchChartData = useMemo(() => ({
    labels: MOCK_DATA.branchRevenue.labels,
    datasets: [{
      data: MOCK_DATA.branchRevenue.data,
      backgroundColor: ['#cc0f67', '#6366f1', '#f59e0b', '#ec4899'],
      borderWidth: 2,
      borderColor: '#fff',
      hoverOffset: 6,
    }]
  }), [])

  const branchChartOptions = useMemo(() => ({
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
      legend: {
        position: 'bottom',
        labels: { padding: 14, font: { size: 11, weight: 'bold' }, color: '#64748b' }
      },
      tooltip: {
        ...tooltipStyle,
        callbacks: { label: (ctx) => ` ${fmt.money(ctx.parsed)}` },
      },
    },
    cutout: '65%',
  }), [fmt])

  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <PageHeader
          title="Analytics"
          subtitle="Monitor business performance, revenue trends, and service insights."
        />
        <span className="self-start sm:self-center px-3 py-1.5 bg-brand-50 border border-brand-100 text-brand-700 text-xs font-bold rounded-xl">
          📅 {rangeLabel}
        </span>
      </div>

      {/* KPI Cards */}
      <div className="grid grid-cols-2 lg:grid-cols-4 gap-4">
        {Object.entries(MOCK_DATA.kpis).map(([key, kpi], i) => (
          <KpiCard key={key} {...kpi} delay={i * 0.06} formatMoney={key === 'revenue' ? fmt.money : undefined} />
        ))}
      </div>

      {/* Charts Row 1: Revenue Trend + Appointments Bar */}
      <div className="grid grid-cols-1 xl:grid-cols-2 gap-6">
        <motion.div
          initial={{ opacity: 0, y: 16 }}
          animate={{ opacity: 1, y: 0 }}
          transition={{ delay: 0.25 }}
          className="bg-white border border-slate-200/80 rounded-2xl p-6 shadow-sm"
        >
          <div className="flex items-center justify-between mb-4">
            <div>
              <h4 className="text-sm font-bold text-slate-900 uppercase tracking-wider">Revenue Trend</h4>
              <p className="text-xs text-slate-500 mt-0.5">Monthly revenue over the last 6 months</p>
            </div>
            <div className="p-2 bg-brand-50 border border-brand-100 rounded-xl text-brand-600">
              <TrendingUp className="w-4 h-4" />
            </div>
          </div>
          <div className="h-56">
            <Line data={revenueChartData} options={revenueChartOptions} />
          </div>
        </motion.div>

        <motion.div
          initial={{ opacity: 0, y: 16 }}
          animate={{ opacity: 1, y: 0 }}
          transition={{ delay: 0.3 }}
          className="bg-white border border-slate-200/80 rounded-2xl p-6 shadow-sm"
        >
          <div className="flex items-center justify-between mb-4">
            <div>
              <h4 className="text-sm font-bold text-slate-900 uppercase tracking-wider">Appointments</h4>
              <p className="text-xs text-slate-500 mt-0.5">Monthly completed bookings volume</p>
            </div>
            <div className="p-2 bg-indigo-50 border border-indigo-100 rounded-xl text-indigo-600">
              <BarChart2 className="w-4 h-4" />
            </div>
          </div>
          <div className="h-56">
            <Bar data={apptChartData} options={apptChartOptions} />
          </div>
        </motion.div>
      </div>

      {/* Charts Row 2: Top Services + Branch Split + Staff Leaderboard */}
      <div className="grid grid-cols-1 xl:grid-cols-3 gap-6">
        {/* Top Services */}
        <motion.div
          initial={{ opacity: 0, y: 16 }}
          animate={{ opacity: 1, y: 0 }}
          transition={{ delay: 0.35 }}
          className="xl:col-span-2 bg-white border border-slate-200/80 rounded-2xl p-6 shadow-sm"
        >
          <div className="flex items-center justify-between mb-4">
            <div>
              <h4 className="text-sm font-bold text-slate-900 uppercase tracking-wider">Top Services</h4>
              <p className="text-xs text-slate-500 mt-0.5">Ranked by revenue contribution this month</p>
            </div>
            <div className="p-2 bg-amber-50 border border-amber-100 rounded-xl text-amber-600">
              <Scissors className="w-4 h-4" />
            </div>
          </div>
          <div className="space-y-3">
            {MOCK_DATA.topServices.map((svc, i) => (
              <div key={svc.name}>
                <div className="flex items-center justify-between text-xs mb-1.5">
                  <div className="flex items-center gap-2">
                    <span className="w-5 h-5 rounded-lg bg-brand-100 text-brand-700 text-[10px] font-black flex items-center justify-center">
                      {i + 1}
                    </span>
                    <span className="font-bold text-slate-800">{svc.name}</span>
                    <span className="text-slate-400 font-medium">· {svc.bookings} bookings</span>
                  </div>
                  <div className="text-right">
                    <span className="font-black text-slate-900">{fmt.money(svc.revenue)}</span>
                    <span className="text-slate-400 ml-1.5">{svc.share}%</span>
                  </div>
                </div>
                <div className="w-full bg-slate-100 rounded-full h-2 overflow-hidden">
                  <motion.div
                    initial={{ width: 0 }}
                    animate={{ width: `${svc.share}%` }}
                    transition={{ delay: 0.4 + i * 0.07, duration: 0.6, ease: 'easeOut' }}
                    className="h-full rounded-full"
                    style={{
                      background: ['#8f0a48','#6366f1','#f59e0b','#ec4899','#cc0f67','#8b5cf6'][i]
                    }}
                  />
                </div>
              </div>
            ))}
          </div>
        </motion.div>

        {/* Branch Revenue Split */}
        <motion.div
          initial={{ opacity: 0, y: 16 }}
          animate={{ opacity: 1, y: 0 }}
          transition={{ delay: 0.4 }}
          className="bg-white border border-slate-200/80 rounded-2xl p-6 shadow-sm"
        >
          <div className="mb-4">
            <h4 className="text-sm font-bold text-slate-900 uppercase tracking-wider">Revenue by Branch</h4>
            <p className="text-xs text-slate-500 mt-0.5">Distribution across locations</p>
          </div>
          <div className="h-48">
            <Doughnut data={branchChartData} options={branchChartOptions} />
          </div>
        </motion.div>
      </div>

      {/* Staff Leaderboard */}
      <motion.div
        initial={{ opacity: 0, y: 16 }}
        animate={{ opacity: 1, y: 0 }}
        transition={{ delay: 0.45 }}
        className="bg-white border border-slate-200/80 rounded-2xl p-6 shadow-sm"
      >
        <div className="flex items-center justify-between mb-4">
          <div>
            <h4 className="text-sm font-bold text-slate-900 uppercase tracking-wider">Staff Leaderboard</h4>
            <p className="text-xs text-slate-500 mt-0.5">Top performing staff members this month ranked by revenue</p>
          </div>
          <div className="p-2 bg-rose-50 border border-rose-100 rounded-xl text-rose-600">
            <Zap className="w-4 h-4" />
          </div>
        </div>
        <div className="overflow-x-auto">
          <table className="w-full text-xs">
            <thead>
              <tr className="border-b border-slate-100 text-slate-400 font-bold uppercase tracking-wider">
                <th className="pb-3 text-left">Rank</th>
                <th className="pb-3 text-left">Staff Member</th>
                <th className="pb-3 text-center">Appointments</th>
                <th className="pb-3 text-right">Revenue</th>
                <th className="pb-3 text-right">Rating</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-slate-100">
              {MOCK_DATA.staffPerformance.map((staff, i) => {
                const medals = ['🥇', '🥈', '🥉']
                return (
                  <tr key={staff.name} className="hover:bg-slate-50/30">
                    <td className="py-3.5 text-base">{medals[i] || `#${i + 1}`}</td>
                    <td className="py-3.5 font-bold text-slate-900">{staff.name}</td>
                    <td className="py-3.5 text-center font-semibold text-slate-600">{staff.appointments}</td>
                    <td className="py-3.5 text-right font-black text-slate-900">{fmt.money(staff.revenue)}</td>
                    <td className="py-3.5 text-right">
                      <span className="flex items-center justify-end gap-1 font-bold text-amber-600">
                        <Star className="w-3 h-3 fill-amber-400 text-amber-500" />
                        {staff.rating}
                      </span>
                    </td>
                  </tr>
                )
              })}
            </tbody>
          </table>
        </div>
      </motion.div>
    </div>
  )
}
