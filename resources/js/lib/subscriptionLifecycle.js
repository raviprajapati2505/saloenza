export const SUBSCRIPTION_LIFECYCLE_LABELS = {
  activation_pending: 'Activation pending',
  locked: 'Locked',
  expired: 'Expired',
  expiring_critical: 'Expiring ≤ 7 days',
  expiring_soon: 'Expiring ≤ 15 days',
  expiring_month: 'Expiring ≤ 30 days',
  active: 'Active',
}

export const SUBSCRIPTION_LIFECYCLE_VARIANTS = {
  activation_pending: 'warning',
  locked: 'danger',
  expired: 'danger',
  expiring_critical: 'danger',
  expiring_soon: 'warning',
  expiring_month: 'warning',
  active: 'success',
}

export function lifecycleLabel(lifecycle) {
  return SUBSCRIPTION_LIFECYCLE_LABELS[lifecycle] || lifecycle || '—'
}

export function lifecycleVariant(lifecycle) {
  return SUBSCRIPTION_LIFECYCLE_VARIANTS[lifecycle] || 'default'
}

export function formatRenewalDue(iso) {
  if (!iso) return '—'
  return new Date(iso).toLocaleDateString('en-IN', {
    day: 'numeric',
    month: 'short',
    year: 'numeric',
  })
}

export function formatDaysRemaining(days) {
  if (days == null) return '—'
  if (days < 0) return `Expired ${Math.abs(days)}d ago`
  if (days === 0) return 'Ends today'
  return `${days} day${days === 1 ? '' : 's'} left`
}
