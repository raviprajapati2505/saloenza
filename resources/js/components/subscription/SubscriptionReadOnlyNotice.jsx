import React from 'react'
import { useLocation } from 'react-router-dom'
import { useAuthStore } from '../../stores/auth'
import SubscriptionRenewalAlert from './SubscriptionRenewalAlert.jsx'

/**
 * Global read-only subscription notice (single instance in AppLayout).
 * Page-level duplicate banners were removed — context lives here only.
 */
export default function SubscriptionReadOnlyNotice() {
  const auth = useAuthStore()
  const location = useLocation()

  if (!auth.isSubscriptionReadOnly) return null
  if (auth.grantsAllPermissions || auth.workspace === 'affiliate') return null
  if (location.pathname.startsWith('/billing')) return null

  const plan = auth.tenant?.plan
  const summary = auth.tenant?.subscription_summary ?? {}

  return (
    <SubscriptionRenewalAlert
      variant="banner"
      plan={plan}
      renewalDueAt={summary.renewal_due_at ?? auth.tenant?.subscription?.ends_at}
      daysRemaining={summary.days_remaining}
      canRenew={false}
      showBillingLink={auth.can('settings.view')}
    />
  )
}
