/**
 * Saloenza dashboard accent map.
 * Keys kept for back-compat with existing `color: 'indigo' | 'amber' | …` props.
 * Decorative accents resolve to brand shades; rose/slate stay semantic.
 */
export const KPI_ICON_COLORS = {
  teal: 'text-brand-600 bg-gradient-to-br from-brand-50 to-brand-100/80',
  brand: 'text-brand-600 bg-gradient-to-br from-brand-50 to-brand-100/80',
  blue: 'text-brand-500 bg-gradient-to-br from-brand-50 to-brand-100/70',
  indigo: 'text-brand-700 bg-gradient-to-br from-brand-50 to-brand-100/80',
  violet: 'text-brand-800 bg-gradient-to-br from-brand-100 to-brand-50',
  amber: 'text-brand-500 bg-gradient-to-br from-brand-50 to-pink-50',
  emerald: 'text-brand-600 bg-gradient-to-br from-brand-50 to-brand-100/60',
  rose: 'text-rose-600 bg-gradient-to-br from-rose-50 to-rose-100/80',
  slate: 'text-slate-600 bg-gradient-to-br from-slate-50 to-slate-100/80',
}

export const WIDGET_ACCENT = {
  teal: 'bg-brand-50 text-brand-600 border-brand-100',
  brand: 'bg-brand-50 text-brand-600 border-brand-100',
  blue: 'bg-brand-50 text-brand-500 border-brand-100',
  indigo: 'bg-brand-50 text-brand-700 border-brand-100',
  violet: 'bg-brand-100 text-brand-800 border-brand-200',
  amber: 'bg-brand-50 text-brand-500 border-brand-100',
  emerald: 'bg-brand-50 text-brand-600 border-brand-100',
  rose: 'bg-rose-50 text-rose-600 border-rose-100',
  slate: 'bg-slate-50 text-slate-600 border-slate-100',
}

export const QUICK_ACTION_COLORS = {
  teal: { text: 'text-brand-600', bg: 'bg-brand-50', border: 'border-brand-100' },
  brand: { text: 'text-brand-600', bg: 'bg-brand-50', border: 'border-brand-100' },
  blue: { text: 'text-brand-500', bg: 'bg-brand-50', border: 'border-brand-100' },
  indigo: { text: 'text-brand-700', bg: 'bg-brand-50', border: 'border-brand-100' },
  violet: { text: 'text-brand-800', bg: 'bg-brand-50', border: 'border-brand-100' },
  amber: { text: 'text-brand-500', bg: 'bg-brand-50', border: 'border-brand-100' },
  emerald: { text: 'text-brand-600', bg: 'bg-brand-50', border: 'border-brand-100' },
  rose: { text: 'text-rose-600', bg: 'bg-rose-50', border: 'border-rose-100' },
  slate: { text: 'text-slate-600', bg: 'bg-slate-50', border: 'border-slate-100' },
}

/** Chart.js brand series */
export const CHART_BRAND = {
  primary: 'rgb(204, 15, 103)',
  primarySoft: 'rgba(204, 15, 103, 0.14)',
  primaryStrong: 'rgba(204, 15, 103, 0.72)',
  secondary: 'rgb(143, 10, 72)',
  secondarySoft: 'rgba(143, 10, 72, 0.14)',
  tertiary: 'rgb(244, 114, 163)',
  muted: 'rgb(148, 163, 184)',
  danger: 'rgb(244, 63, 94)',
  black: '#000000',
}
