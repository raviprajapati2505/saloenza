const ALLOWED_CURRENCIES = new Set(['QAR', 'USD'])

const DEFAULT_REGIONAL = {
  timezone: 'Asia/Qatar',
  locale: 'en_QA',
  currency: 'QAR',
  date_format: 'd M Y',
  time_format: '12h',
}

const CURRENCY_SYMBOLS = {
  QAR: 'ر.ق',
  USD: '$',
}

function normalizeCurrency(code) {
  const upper = String(code || '').toUpperCase()
  return ALLOWED_CURRENCIES.has(upper) ? upper : DEFAULT_REGIONAL.currency
}

export function resolveRegional(auth) {
  const regional = auth?.tenant?.regional ?? DEFAULT_REGIONAL
  return {
    ...DEFAULT_REGIONAL,
    ...regional,
    currency: normalizeCurrency(regional.currency),
  }
}

export function currencySymbol(auth) {
  const code = resolveRegional(auth).currency
  return CURRENCY_SYMBOLS[code] || code
}

export function formatMoney(value, auth, options = {}) {
  const regional = resolveRegional(auth)
  const amount = Number(value ?? 0)

  if (Number.isNaN(amount)) {
    return options.fallback ?? '—'
  }

  try {
    return new Intl.NumberFormat(regional.locale.replace('_', '-'), {
      style: 'currency',
      currency: regional.currency,
      maximumFractionDigits: options.maximumFractionDigits ?? 0,
    }).format(amount)
  } catch {
    const symbol = currencySymbol(auth)
    return `${symbol}${amount.toLocaleString('en-US')}`
  }
}

export function formatDate(value, auth, options = {}) {
  if (!value) return options.fallback ?? '—'

  const regional = resolveRegional(auth)
  const date = value instanceof Date ? value : new Date(value)

  if (Number.isNaN(date.getTime())) {
    return options.fallback ?? '—'
  }

  try {
    return new Intl.DateTimeFormat(regional.locale.replace('_', '-'), {
      dateStyle: options.dateStyle ?? 'medium',
      timeStyle: options.timeStyle,
      timeZone: regional.timezone,
    }).format(date)
  } catch {
    return date.toLocaleDateString('en-US')
  }
}

/** Format money using platform defaults when no auth context is available. */
export function formatMoneyDefault(value, options = {}) {
  return formatMoney(value, null, options)
}

export function formatPlanPrice(plan, auth) {
  if (!plan) return '—'
  const price = Number(plan.price || 0)
  if (price <= 0) return 'Free'
  const interval = plan.billing_interval ? ` / ${plan.billing_interval}` : ''
  return `${formatMoney(price, auth)}${interval}`
}

/** Convenience bundle for views that format many values. */
export function createTenantFormatter(auth) {
  return {
    money: (value, options) => formatMoney(value, auth, options),
    date: (value, options) => formatDate(value, auth, options),
    symbol: () => currencySymbol(auth),
    planPrice: (plan) => formatPlanPrice(plan, auth),
  }
}
