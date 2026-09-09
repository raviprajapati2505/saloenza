import React from 'react'
import { motion } from 'framer-motion'

const TONES = {
  expired: {
    header: 'from-[#1a1225] via-slate-900 to-[#0f1419]',
    mesh: 'from-rose-500/25 via-brand-600/20 to-fuchsia-600/15',
    glowA: 'bg-rose-500/20',
    glowB: 'bg-brand-500/15',
    stripe: 'from-rose-400 via-brand-500 to-fuchsia-500',
    ring: 'from-rose-400/60 via-brand-500/50 to-fuchsia-400/40',
    iconGradient: 'from-rose-500/40 via-brand-600/35 to-fuchsia-600/30',
    iconRing: 'ring-white/20',
    badge: 'bg-white/10 text-white/95 ring-white/20 backdrop-blur-md',
    badgeDot: 'bg-rose-300',
  },
  locked: {
    header: 'from-[#1a1810] via-slate-900 to-[#0f1419]',
    mesh: 'from-amber-500/20 via-brand-600/18 to-orange-500/12',
    glowA: 'bg-amber-500/18',
    glowB: 'bg-brand-500/12',
    stripe: 'from-amber-400 via-brand-500 to-orange-400',
    ring: 'from-amber-400/55 via-brand-500/45 to-orange-400/35',
    iconGradient: 'from-amber-500/35 via-brand-600/30 to-orange-500/25',
    iconRing: 'ring-white/20',
    badge: 'bg-white/10 text-white/95 ring-white/20 backdrop-blur-md',
    badgeDot: 'bg-amber-300',
  },
  info: {
    header: 'from-[#151a25] via-slate-900 to-[#0f1419]',
    mesh: 'from-brand-500/22 via-violet-600/18 to-fuchsia-500/12',
    glowA: 'bg-brand-500/20',
    glowB: 'bg-violet-500/12',
    stripe: 'from-brand-400 via-violet-500 to-fuchsia-400',
    ring: 'from-brand-400/55 via-violet-500/45 to-fuchsia-400/35',
    iconGradient: 'from-brand-500/40 via-violet-600/30 to-fuchsia-500/25',
    iconRing: 'ring-white/20',
    badge: 'bg-white/10 text-white/95 ring-white/20 backdrop-blur-md',
    badgeDot: 'bg-brand-300',
  },
}

const CHIP_STYLES = {
  neutral: 'border-slate-200/90 bg-white/90 text-slate-700 shadow-[0_1px_3px_rgba(15,23,42,0.06)]',
  success: 'border-emerald-200/90 bg-gradient-to-b from-emerald-50 to-white text-emerald-800 shadow-[0_1px_3px_rgba(16,185,129,0.08)]',
  muted: 'border-slate-200/80 bg-slate-50/90 text-slate-500 shadow-sm',
  brand: 'border-brand-200/80 bg-gradient-to-b from-brand-50 to-white text-brand-800 shadow-[0_1px_3px_rgba(145,37,202,0.08)]',
}

function GradientFrame({ palette, children, className = '' }) {
  return (
    <div className={`rounded-2xl bg-gradient-to-br p-[1px] ${palette.ring} ${className}`}>
      <div className="h-full overflow-hidden rounded-[15px] bg-white">{children}</div>
    </div>
  )
}

/**
 * Premium subscription notice — brand mesh, gradient frame, refined depth.
 */
export default function SubscriptionNoticePanel({
  tone = 'info',
  variant = 'hero',
  icon: Icon,
  eyebrow,
  title,
  subtitle,
  description,
  chips = [],
  footnote,
  action,
  footerNote,
  className = '',
}) {
  const palette = TONES[tone] || TONES.info
  const isCompact = variant === 'banner'

  if (isCompact) {
    return (
      <motion.div
        initial={{ opacity: 0, y: -4 }}
        animate={{ opacity: 1, y: 0 }}
        transition={{ duration: 0.25, ease: 'easeOut' }}
        className={className}
      >
        <div className={`overflow-hidden rounded-xl bg-gradient-to-br p-[1px] ${palette.ring} shadow-[0_8px_30px_rgba(15,23,42,0.07)]`}>
          <div className="flex overflow-hidden rounded-[11px] bg-gradient-to-r from-slate-50/95 via-white to-brand-50/20">
            <div className={`w-1.5 shrink-0 bg-gradient-to-b ${palette.stripe}`} aria-hidden />
            <div className="flex flex-1 flex-col gap-4 p-4 sm:flex-row sm:items-center sm:justify-between sm:p-5">
              <div className="flex min-w-0 items-start gap-3.5">
                {Icon ? (
                  <div className={`relative flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br ${palette.iconGradient} shadow-lg shadow-brand-900/10 ring-1 ${palette.iconRing}`}>
                    <div className="absolute inset-0 rounded-xl bg-gradient-to-t from-black/10 to-transparent" aria-hidden />
                    <Icon className="relative h-5 w-5 text-white drop-shadow-sm" strokeWidth={2} />
                  </div>
                ) : null}
                <div className="min-w-0">
                  {eyebrow ? (
                    <p className="text-[11px] font-bold uppercase tracking-[0.14em] text-brand-700/80">{eyebrow}</p>
                  ) : null}
                  <p className="mt-0.5 text-base font-semibold leading-snug tracking-tight text-slate-900">{title}</p>
                  {(subtitle || description) ? (
                    <p className="mt-1.5 text-sm leading-relaxed text-slate-600">{subtitle || description}</p>
                  ) : null}
                </div>
              </div>
              {action ? <div className="shrink-0 sm:pl-2">{action}</div> : null}
            </div>
          </div>
        </div>
      </motion.div>
    )
  }

  return (
    <motion.div
      initial={{ opacity: 0, y: -10 }}
      animate={{ opacity: 1, y: 0 }}
      transition={{ duration: 0.32, ease: [0.22, 1, 0.36, 1] }}
      className={className}
    >
      <GradientFrame palette={palette}>
        {/* Header */}
        <div className={`relative overflow-hidden bg-gradient-to-br ${palette.header} px-6 py-7 text-white sm:px-8 sm:py-8`}>
          {/* Brand mesh overlay */}
          <div className={`pointer-events-none absolute inset-0 bg-gradient-to-tr ${palette.mesh} opacity-90`} aria-hidden />
          <div className={`pointer-events-none absolute -right-20 -top-20 h-56 w-56 rounded-full blur-3xl ${palette.glowA}`} aria-hidden />
          <div className={`pointer-events-none absolute -bottom-24 -left-16 h-48 w-48 rounded-full blur-3xl ${palette.glowB}`} aria-hidden />
          <div
            className="pointer-events-none absolute inset-0 opacity-[0.04]"
            style={{
              backgroundImage: 'radial-gradient(circle at 1px 1px, white 1px, transparent 0)',
              backgroundSize: '20px 20px',
            }}
            aria-hidden
          />
          <div className="pointer-events-none absolute inset-x-0 top-0 h-px bg-gradient-to-r from-transparent via-white/25 to-transparent" aria-hidden />

          <div className="relative flex flex-col gap-6 lg:flex-row lg:items-start lg:justify-between">
            <div className="flex min-w-0 gap-4 sm:gap-5">
              {Icon ? (
                <div className="relative shrink-0">
                  <div className={`absolute -inset-1 rounded-[18px] bg-gradient-to-br ${palette.stripe} opacity-40 blur-md`} aria-hidden />
                  <div className={`relative flex h-[3.75rem] w-[3.75rem] items-center justify-center rounded-2xl bg-gradient-to-br ${palette.iconGradient} shadow-xl shadow-black/25 ring-1 ${palette.iconRing}`}>
                    <div className="absolute inset-0 rounded-2xl bg-gradient-to-t from-black/15 to-white/10" aria-hidden />
                    <Icon className="relative h-7 w-7 text-white drop-shadow-md" strokeWidth={1.75} />
                  </div>
                </div>
              ) : null}
              <div className="min-w-0 pt-0.5">
                {eyebrow ? (
                  <span className={`inline-flex items-center gap-2 rounded-full px-3 py-1 text-[11px] font-bold uppercase tracking-[0.16em] ring-1 ${palette.badge}`}>
                    <span className={`h-1.5 w-1.5 rounded-full ${palette.badgeDot} shadow-[0_0_8px_currentColor]`} />
                    {eyebrow}
                  </span>
                ) : null}
                <h2 className="mt-3.5 bg-gradient-to-br from-white via-white to-slate-300 bg-clip-text text-xl font-bold tracking-tight text-transparent sm:text-2xl lg:text-[1.75rem] lg:leading-[1.2]">
                  {title}
                </h2>
                {subtitle ? (
                  <p className="mt-2.5 max-w-2xl text-sm leading-relaxed text-slate-300/95 sm:text-[15px]">
                    {subtitle}
                  </p>
                ) : null}
              </div>
            </div>

            {action ? (
              <div className="flex shrink-0 flex-col items-stretch gap-2.5 rounded-2xl border border-white/10 bg-white/[0.06] p-3 backdrop-blur-sm lg:min-w-[220px] lg:items-stretch">
                {action}
              </div>
            ) : null}
          </div>
        </div>

        {/* Body */}
        {(description || chips.length > 0 || footnote || footerNote) ? (
          <div className="relative border-t border-slate-100/80 bg-gradient-to-b from-slate-50 via-white to-brand-50/10 px-6 py-5 sm:px-8 sm:py-6">
            <div className="pointer-events-none absolute inset-x-8 top-0 h-px bg-gradient-to-r from-transparent via-brand-200/60 to-transparent" aria-hidden />

            {description ? (
              <p className="text-sm leading-relaxed text-slate-600 sm:text-[15px]">{description}</p>
            ) : null}

            {chips.length > 0 ? (
              <div className={`flex flex-wrap gap-2.5 ${description ? 'mt-4' : ''}`}>
                {chips.map((chip) => {
                  const ChipIcon = chip.icon
                  const chipClass = CHIP_STYLES[chip.variant || 'neutral']
                  return (
                    <span
                      key={chip.label}
                      className={`inline-flex items-center gap-2 rounded-full border px-3.5 py-1.5 text-xs font-semibold ${chipClass} ${chip.muted ? 'line-through opacity-75' : ''}`}
                    >
                      {ChipIcon ? (
                        <span className="flex h-5 w-5 items-center justify-center rounded-full bg-white/80 shadow-sm">
                          <ChipIcon className="h-3 w-3 shrink-0 opacity-70" />
                        </span>
                      ) : null}
                      {chip.label}
                    </span>
                  )
                })}
              </div>
            ) : null}

            {footnote ? (
              <p className={`flex items-start gap-2 text-xs leading-relaxed text-slate-500 sm:text-sm ${chips.length > 0 || description ? 'mt-4' : ''}`}>
                <span className="mt-1.5 h-1 w-1 shrink-0 rounded-full bg-brand-400/70" aria-hidden />
                {footnote}
              </p>
            ) : null}

            {footerNote ? (
              <p className="mt-4 rounded-xl border border-slate-100 bg-slate-50/80 px-3.5 py-2.5 text-xs leading-relaxed text-slate-500">
                {footerNote}
              </p>
            ) : null}
          </div>
        ) : null}
      </GradientFrame>
    </motion.div>
  )
}

export { CHIP_STYLES }
