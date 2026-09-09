import React from 'react'
import { Link } from 'react-router-dom'
import { AlertCircle, ArrowUpRight, CreditCard, Eye, HandCoins, Lock } from 'lucide-react'
import BaseButton from '../ui/BaseButton.jsx'
import SubscriptionNoticePanel from './SubscriptionNoticePanel.jsx'
import { formatPlanPrice } from '../../lib/subscriptionModules.js'
import { formatDaysRemaining, formatRenewalDue } from '../../lib/subscriptionLifecycle.js'

export default function SubscriptionRenewalAlert({
  variant = 'hero',
  plan,
  renewalDueAt,
  daysRemaining,
  billing,
  canRenew = false,
  renewing = false,
  onRenew,
  showBillingLink = false,
}) {
  if (!plan) return null

  const useGateway = billing?.gateway_configured
  const renewLabel = useGateway ? `Renew ${plan.name}` : `Request renewal · ${plan.name}`
  const expiredDetail = daysRemaining != null && daysRemaining < 0
    ? `Expired ${Math.abs(daysRemaining)} day${Math.abs(daysRemaining) === 1 ? '' : 's'} ago`
    : null

  const actionButton = canRenew && onRenew ? (
    <BaseButton
      size={variant === 'hero' ? 'lg' : 'md'}
      className="w-full shadow-md shadow-brand-900/10 lg:w-auto"
      leftIcon={useGateway ? CreditCard : HandCoins}
      loading={renewing}
      onClick={onRenew}
    >
      {renewLabel}
    </BaseButton>
  ) : showBillingLink ? (
    <Link
      to="/billing"
      className="inline-flex w-full items-center justify-center gap-2 rounded-xl bg-brand-600 px-5 py-2.5 text-sm font-semibold text-white shadow-md shadow-brand-900/15 transition hover:bg-brand-500 lg:w-auto lg:px-6 lg:py-3 lg:text-base"
    >
      Go to billing
      <ArrowUpRight className="h-4 w-4" />
    </Link>
  ) : null

  const dueChipLabel = renewalDueAt
    ? `${expiredDetail || formatDaysRemaining(daysRemaining)}${!expiredDetail && renewalDueAt ? ` · ${formatRenewalDue(renewalDueAt)}` : ''}`
    : null

  if (variant === 'banner') {
    return (
      <SubscriptionNoticePanel
        variant="banner"
        tone="expired"
        icon={AlertCircle}
        eyebrow="Subscription expired"
        title={`Renew ${plan.name} to restore full access`}
        subtitle={
          <>
            View-only mode is active — create, edit, and delete are disabled
            {expiredDetail ? ` · ${expiredDetail}` : renewalDueAt ? ` · ${formatRenewalDue(renewalDueAt)}` : ''}.
          </>
        }
        action={actionButton}
      />
    )
  }

  return (
    <SubscriptionNoticePanel
      variant="hero"
      tone="expired"
      icon={AlertCircle}
      eyebrow="Action required"
      title="Your subscription has expired"
      subtitle={
        <>
          Renew <span className="font-medium text-white/95">{plan.name}</span> ({formatPlanPrice(plan)}) to restore
          full workspace access for your team.
        </>
      }
      action={actionButton}
      chips={[
        ...(dueChipLabel ? [{ label: dueChipLabel, variant: 'neutral' }] : []),
        { label: 'View data', icon: Eye, variant: 'success' },
        { label: 'Create · edit · delete', icon: Lock, variant: 'muted', muted: true },
      ]}
      footnote={
        useGateway
          ? 'Complete payment below — your plan reactivates as soon as payment is confirmed.'
          : 'Submit a renewal request below — your platform admin will reactivate after offline payment.'
      }
      footerNote={billing?.manual_instructions && !useGateway ? billing.manual_instructions : null}
    />
  )
}
