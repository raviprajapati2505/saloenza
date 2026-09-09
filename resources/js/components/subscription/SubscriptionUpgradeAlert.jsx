import React from 'react'
import { Link } from 'react-router-dom'
import { ArrowUpRight, CreditCard, HandCoins, Lock, Sparkles } from 'lucide-react'
import BaseButton from '../ui/BaseButton.jsx'
import SubscriptionNoticePanel from './SubscriptionNoticePanel.jsx'

export default function SubscriptionUpgradeAlert({
  plan,
  billing,
  canUpgrade = false,
  upgrading = false,
  onBrowsePlans,
  showBillingLink = false,
}) {
  const useGateway = billing?.gateway_configured
  const planLabel = plan?.name || 'Free trial'

  const actionButton = canUpgrade && onBrowsePlans ? (
    <BaseButton
      size="lg"
      className="w-full shadow-md shadow-brand-900/10 lg:w-auto"
      leftIcon={useGateway ? CreditCard : HandCoins}
      loading={upgrading}
      onClick={onBrowsePlans}
    >
      {useGateway ? 'Browse paid plans' : 'Request a paid plan'}
    </BaseButton>
  ) : showBillingLink ? (
    <Link
      to="/billing"
      className="inline-flex w-full items-center justify-center gap-2 rounded-xl bg-brand-600 px-6 py-3 text-base font-semibold text-white shadow-md shadow-brand-900/15 transition hover:bg-brand-500 lg:w-auto"
    >
      View billing & plans
      <ArrowUpRight className="h-4 w-4" />
    </Link>
  ) : null

  return (
    <SubscriptionNoticePanel
      variant="hero"
      tone="locked"
      icon={Lock}
      eyebrow="Free trial ended"
      title="Upgrade to unlock your salon"
      subtitle={
        <>
          Your <span className="font-medium text-white/95">{planLabel}</span> has ended. Select a paid plan to
          restore appointments, staff, customers, and daily operations.
        </>
      }
      action={actionButton}
      chips={[
        { label: 'Billing & plan selection', icon: Sparkles, variant: 'brand' },
        { label: 'Salon features locked', icon: Lock, variant: 'muted' },
      ]}
      footnote={
        useGateway
          ? 'Choose a plan below and pay online — activation is instant after payment.'
          : 'Choose a plan below and submit a request — admin will activate after offline payment.'
      }
      footerNote={billing?.manual_instructions && !useGateway ? billing.manual_instructions : null}
    />
  )
}
