const DEFAULT_REGIONAL = {
  timezone: 'Asia/Kolkata',
  locale: 'en_IN',
  currency: 'INR',
  date_format: 'd M Y',
  time_format: '12h',
}

const CURRENCY_SYMBOLS = {
  INR: '₹',
  USD: '$',
  AED: 'د.إ',
  GBP: '£',
}

export function resolveRegional(auth) {
  return auth?.tenant?.regional ?? DEFAULT_REGIONAL
}

export function currencySymbol(auth) {
  const code = resolveRegional(auth).currency || 'INR'
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
      currency: regional.currency || 'INR',
      maximumFractionDigits: options.maximumFractionDigits ?? 0,
    }).format(amount)
  } catch {
    const symbol = currencySymbol(auth)
    return `${symbol}${amount.toLocaleString('en-IN')}`
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
    return date.toLocaleDateString('en-IN')
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
