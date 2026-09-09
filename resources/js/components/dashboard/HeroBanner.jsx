import React from 'react'
import { CalendarDays, RefreshCw } from 'lucide-react'
import { motion } from 'framer-motion'

function formatLongDate(date = new Date()) {
  return date.toLocaleDateString('en-IN', {
    weekday: 'long',
    day: 'numeric',
    month: 'long',
    year: 'numeric',
  })
}

const BRAND_TONE = {
  shell: 'from-black via-[#1a0614] to-brand-700',
  blobA: 'bg-brand-500/40',
  blobB: 'bg-brand-700/35',
  chip: 'text-white/90',
  greeting: 'text-brand-100/90',
  miniLabel: 'text-brand-100/85',
}

function MiniStat({ label, value, loading, labelClass }) {
  return (
    <div className="rounded-2xl border border-white/15 bg-white/10 px-4 py-3 backdrop-blur-md shadow-[inset_0_1px_0_rgba(255,255,255,0.12)]">
      <p className={`text-[10px] font-semibold uppercase tracking-wider ${labelClass}`}>{label}</p>
      <p className="mt-1 text-xl font-bold tracking-tight text-white">
        {loading ? (
          <span className="inline-block h-6 w-10 animate-pulse rounded bg-white/20" />
        ) : (
          value
        )}
      </p>
    </div>
  )
}

/**
 * Premium Glowsuite brand hero for all dashboards.
 * `tone` is accepted for back-compat but always renders the brand palette.
 */
export default function HeroBanner({
  title = 'Dashboard',
  greeting,
  subtitle,
  dateLabel,
  summaryCards = [],
  loading = false,
  onRefresh,
  refreshing = false,
  actions,
  tone: _tone = 'brand',
}) {
  const t = BRAND_TONE

  return (
    <motion.section
      initial={{ opacity: 0, y: 12 }}
      animate={{ opacity: 1, y: 0 }}
      transition={{ duration: 0.35 }}
      className={`relative overflow-hidden rounded-[20px] bg-gradient-to-br ${t.shell} p-6 text-white shadow-[0_20px_50px_rgba(224,34,154,0.18)] sm:p-8`}
    >
      <div className={`pointer-events-none absolute -right-16 -top-20 h-64 w-64 rounded-full ${t.blobA} blur-3xl`} />
      <div className={`pointer-events-none absolute -bottom-24 left-1/3 h-56 w-56 rounded-full ${t.blobB} blur-3xl`} />
      <div className="pointer-events-none absolute inset-0 bg-[linear-gradient(120deg,transparent_40%,rgba(255,255,255,0.05)_50%,transparent_60%)]" />
      <div
        aria-hidden="true"
        className="pointer-events-none absolute inset-0 opacity-90"
        style={{
          background:
            'radial-gradient(ellipse 70% 60% at 15% 20%, rgba(224,34,154,0.35), transparent 55%), radial-gradient(ellipse 60% 50% at 90% 85%, rgba(145,37,202,0.4), transparent 50%)',
        }}
      />

      <div className="relative flex flex-col gap-6 lg:flex-row lg:items-start lg:justify-between">
        <div className="min-w-0 flex-1">
          <div className={`inline-flex items-center gap-2 rounded-full border border-white/15 bg-white/10 px-3 py-1 text-xs font-medium backdrop-blur-sm ${t.chip}`}>
            <CalendarDays className="h-3.5 w-3.5" />
            {dateLabel || formatLongDate()}
          </div>
          <h1 className="mt-4 text-2xl font-bold tracking-tight sm:text-3xl">{title}</h1>
          {greeting ? (
            <p className={`mt-1.5 text-base font-medium ${t.greeting}`}>{greeting}</p>
          ) : null}
          {subtitle ? (
            <p className="mt-2 max-w-2xl text-sm leading-relaxed text-white/70">{subtitle}</p>
          ) : null}
        </div>

        <div className="flex shrink-0 flex-wrap items-center gap-2">
          {onRefresh ? (
            <button
              type="button"
              onClick={onRefresh}
              disabled={refreshing || loading}
              className="inline-flex items-center gap-1.5 rounded-xl border border-white/20 bg-white/10 px-3 py-1.5 text-xs font-medium text-white backdrop-blur-sm transition hover:bg-white/20 disabled:cursor-not-allowed disabled:opacity-50"
            >
              <RefreshCw className={`h-3.5 w-3.5 ${refreshing || loading ? 'animate-spin' : ''}`} />
              Refresh
            </button>
          ) : null}
          {actions}
        </div>
      </div>

      {summaryCards.length > 0 ? (
        <div className="relative mt-8 grid grid-cols-2 gap-3 sm:grid-cols-4">
          {summaryCards.map((card) => (
            <MiniStat
              key={card.key || card.label}
              label={card.label}
              value={card.value}
              loading={loading || card.loading}
              labelClass={t.miniLabel}
            />
          ))}
        </div>
      ) : null}
    </motion.section>
  )
}
