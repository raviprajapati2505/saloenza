export const PAYMENT_METHODS = [
  { value: 'cash', label: 'Cash' },
  { value: 'card', label: 'Card' },
  { value: 'upi', label: 'UPI' },
  { value: 'bank_transfer', label: 'Bank transfer' },
  { value: 'other', label: 'Other' },
]

export const PAYMENT_STATUS_LABELS = {
  unpaid: 'Pending payment',
  partial: 'Partial',
  paid: 'Paid',
  refunded: 'Refunded',
}

export const PAYMENT_STATUS_VARIANT = {
  unpaid: 'warning',
  partial: 'info',
  paid: 'success',
  refunded: 'danger',
}

export function paymentMethodLabel(value) {
  return PAYMENT_METHODS.find((m) => m.value === value)?.label ?? value ?? '—'
}

export function collectedAmount(appt) {
  return Number(appt?.amount_paid ?? 0) || 0
}

export function balanceDue(appt) {
  const total = Number(appt?.grand_total ?? appt?.price ?? 0) || 0
  return Math.max(total - collectedAmount(appt), 0)
}

export function isRevenueEligible(appt) {
  if (!appt) return false
  if (['cancelled', 'no-show'].includes(appt.status)) return false
  return collectedAmount(appt) > 0 || appt.payment_status === 'paid'
}

export function revenueAmount(appt) {
  if (!appt || ['cancelled', 'no-show'].includes(appt.status)) return 0
  return collectedAmount(appt)
}
