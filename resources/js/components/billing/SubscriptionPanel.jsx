import React, { useCallback, useEffect, useMemo, useState } from 'react'
import { motion, AnimatePresence } from 'framer-motion'
import {
  ArrowUpRight,
  CheckCircle2,
  Clock,
  Crown,
  CreditCard,
  HandCoins,
  Layers,
  Loader2,
  Sparkles,
  Users,
  Building2,
  AlertCircle,
} from 'lucide-react'
import BaseButton from '../ui/BaseButton.jsx'
import { useAuthStore } from '../../stores/auth'
import { pushToast } from '../../stores/toast.js'
import {
  getSubscriptionModuleCatalog,
  formatLimit,
  formatPlanPrice,
  canRenewSubscription,
  canRenewCurrentPlan,
} from '../../lib/subscriptionModules.js'
import {
  checkoutSubscription,
  confirmStripeReturn,
  fetchPublicPlans,
  fetchTenantSubscription,
  processCheckoutResult,
  subscriptionPeriodHeadline,
  trialDaysRemaining,
  upgradeSubscription,
  usagePercent,
} from '../../services/subscriptionService.js'
import { formatDaysRemaining, formatRenewalDue, lifecycleLabel, lifecycleVariant } from '../../lib/subscriptionLifecycle.js'
import BaseBadge from '../ui/BaseBadge.jsx'
import SubscriptionRenewalAlert from '../subscription/SubscriptionRenewalAlert.jsx'
import SubscriptionUpgradeAlert from '../subscription/SubscriptionUpgradeAlert.jsx'

function UsageBar({ label, used, max, icon: Icon }) {
  const percent = usagePercent(used, max)
  const atLimit = max != null && used >= max

  return (
    <div>
      <div className="mb-1.5 flex items-center justify-between text-xs">
        <span className="flex items-center gap-1.5 font-medium text-slate-600">
          {Icon ? <Icon className="h-3.5 w-3.5 text-slate-400" /> : null}
          {label}
        </span>
        <span className={`font-semibold ${atLimit ? 'text-amber-700' : 'text-slate-700'}`}>
          {used}/{formatLimit(max)}
        </span>
      </div>
      <div className="h-2 overflow-hidden rounded-full bg-slate-100">
        <div
          className={`h-full rounded-full transition-all ${atLimit ? 'bg-amber-500' : 'bg-brand-500'}`}
          style={{ width: `${max == null ? Math.min(used * 10, 100) : percent}%` }}
        />
      </div>
    </div>
  )
}

function PlanCard({ plan, eligibility, currentPlanId, upgrading, billing, canUpgrade, onUpgrade, renewEligible = false }) {
  const isCurrent = Number(plan.id) === Number(currentPlanId)
  const canSwitch = eligibility?.allowed !== false
  const isBlockedDowngrade = eligibility?.is_downgrade && eligibility?.allowed === false
  const useGateway = billing?.gateway_configured
  const renewAction = renewEligible && isCurrent
  const actionLabel = renewAction
    ? useGateway
      ? billing?.driver === 'stripe'
        ? `Pay with Stripe · Renew ${plan.name}`
        : `Pay & renew ${plan.name}`
      : `Request renewal · ${plan.name}`
    : useGateway
      ? billing?.driver === 'stripe'
        ? `Pay with Stripe · ${plan.name}`
        : eligibility?.is_downgrade
          ? `Downgrade to ${plan.name}`
          : `Pay & upgrade to ${plan.name}`
      : eligibility?.is_downgrade
        ? `Request downgrade to ${plan.name}`
        : `Request ${plan.name}`

  return (
    <div
      className={`rounded-2xl border p-5 transition ${
        isCurrent
          ? 'border-brand-500 bg-brand-50/60 ring-2 ring-brand-500/15'
          : isBlockedDowngrade
            ? 'border-amber-200 bg-amber-50/30'
            : 'border-slate-200 bg-white hover:border-brand-300'
      }`}
    >
      <div className="flex items-start justify-between gap-3">
        <div>
          <p className="text-base font-bold text-slate-900">{plan.name}</p>
          <p className="mt-1 text-xs text-slate-500">{plan.description || 'Salon subscription plan'}</p>
        </div>
        <p className="text-sm font-bold text-brand-700">{formatPlanPrice(plan)}</p>
      </div>

      <div className="mt-4 grid grid-cols-2 gap-2 text-xs text-slate-600">
        <span>Branches: {formatLimit(plan.max_branches)}</span>
        <span>Staff: {formatLimit(plan.max_staff)}</span>
      </div>

      <div className="mt-3 flex flex-wrap gap-1">
        {(plan.modules || []).slice(0, 5).map((module) => (
          <span
            key={module}
            className="rounded-full border border-slate-200 bg-slate-50 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-slate-600"
          >
            {module}
          </span>
        ))}
      </div>

      <div className="mt-4 space-y-3">
        {isCurrent && !renewAction ? (
          <span className="inline-flex items-center gap-1 rounded-full bg-brand-100 px-3 py-1 text-xs font-semibold text-brand-800">
            <CheckCircle2 className="h-3.5 w-3.5" /> Current plan
          </span>
        ) : null}
        {isCurrent && renewAction ? (
          <>
            <div className="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 ring-1 ring-slate-900/[0.03]">
              <p className="flex items-center gap-1.5 text-xs font-semibold text-slate-700">
                <AlertCircle className="h-4 w-4 shrink-0 text-rose-500" />
                Expired — renew to restore access
              </p>
            </div>
            {canUpgrade ? (
              <BaseButton
                size="sm"
                className="w-full"
                leftIcon={useGateway ? CreditCard : HandCoins}
                loading={upgrading === plan.id}
                onClick={() => onUpgrade(plan, { renew: true })}
              >
                {actionLabel}
              </BaseButton>
            ) : null}
          </>
        ) : null}
        {!isCurrent ? (
          <>
            {!canSwitch && eligibility?.message ? (
              <div className="rounded-xl border border-amber-200 bg-amber-50 px-3 py-2.5 text-xs text-amber-900">
                <p className="flex items-start gap-1.5 font-semibold">
                  <AlertCircle className="mt-0.5 h-3.5 w-3.5 shrink-0" />
                  {isBlockedDowngrade ? 'Downgrade not available' : 'Plan change not available'}
                </p>
                <p className="mt-1 leading-relaxed text-amber-800">{eligibility.message}</p>
              </div>
            ) : null}
            {canUpgrade ? (
              <BaseButton
                size="sm"
                className="w-full"
                leftIcon={useGateway ? CreditCard : HandCoins}
                loading={upgrading === plan.id}
                disabled={!canSwitch}
                onClick={() => onUpgrade(plan)}
              >
                {actionLabel}
              </BaseButton>
            ) : null}
          </>
        ) : null}
      </div>
    </div>
  )
}

export default function SubscriptionPanel() {
  const auth = useAuthStore()
  const canUpgrade = canRenewSubscription(auth)

  const [loading, setLoading] = useState(true)
  const [upgrading, setUpgrading] = useState(null)
  const [subscriptionData, setSubscriptionData] = useState(null)
  const [plans, setPlans] = useState([])

  const loadData = useCallback(async () => {
    setLoading(true)
    try {
      const [subscription, availablePlans] = await Promise.all([
        fetchTenantSubscription(),
        fetchPublicPlans(),
      ])
      setSubscriptionData(subscription)
      setPlans(availablePlans)
    } catch (error) {
      console.error('Failed to load subscription data:', error)
      pushToast(error?.response?.data?.message || 'Unable to load subscription details.', 'error')
    } finally {
      setLoading(false)
    }
  }, [])

  useEffect(() => {
    void loadData()
  }, [loadData])

  useEffect(() => {
    if (!canUpgrade) return

    const params = new URLSearchParams(window.location.search)
    const sessionId = params.get('session_id')
    const upgradeState = params.get('upgrade')

    if (upgradeState !== 'success' || !sessionId) {
      return
    }

    const orderId = sessionStorage.getItem('salonos_pending_upgrade_order_id')
      || subscriptionData?.pending_upgrade_order?.id

    if (!orderId) {
      return
    }

    let active = true

    async function finalizeStripeReturn() {
      try {
        await confirmStripeReturn(sessionId, Number(orderId))
        sessionStorage.removeItem('salonos_pending_upgrade_order_id')
        await auth.fetchMe()
        if (!active) return
        await loadData()
        pushToast('Payment successful. Your subscription plan is now active.', 'success')
        params.delete('session_id')
        params.delete('upgrade')
        const next = `${window.location.pathname}${params.toString() ? `?${params.toString()}` : ''}`
        window.history.replaceState({}, '', next)
      } catch (error) {
        if (!active) return
        pushToast(error?.response?.data?.message || 'Unable to confirm Stripe payment.', 'error')
      }
    }

    void finalizeStripeReturn()

    return () => { active = false }
  }, [auth, canUpgrade, loadData, subscriptionData?.pending_upgrade_order?.id])

  const billing = subscriptionData?.billing ?? null
  const pendingOrder = subscriptionData?.pending_upgrade_order ?? null
  const currentPlan = subscriptionData?.plan ?? auth.tenant?.plan
  const limits = subscriptionData?.limits ?? auth.subscriptionLimits ?? {}
  const modules = subscriptionData?.modules ?? auth.subscriptionModules ?? []
  const trialEndsAt = subscriptionData?.trial_ends_at ?? auth.tenant?.trial_ends_at
  const trialDays = trialDaysRemaining(trialEndsAt)
  const status = subscriptionData?.subscription?.status ?? auth.tenant?.subscription?.status
  const accessMode = subscriptionData?.access_mode ?? auth.tenant?.subscription_access_mode
  const summary = subscriptionData?.subscription_summary ?? auth.tenant?.subscription_summary ?? {}
  const renewalDueAt = summary.renewal_due_at
    ?? subscriptionData?.subscription?.ends_at
    ?? auth.tenant?.subscription?.ends_at

  const periodInfo = useMemo(() => subscriptionPeriodHeadline({
    accessMode,
    lifecycle: summary.lifecycle,
    status,
    renewalDueAt,
    trialEndsAt,
  }), [accessMode, summary.lifecycle, status, renewalDueAt, trialEndsAt])

  const periodToneClass = {
    locked: 'border-amber-200 bg-amber-50 text-amber-950',
    expired: 'border-rose-200 bg-rose-50 text-rose-950',
    trial: 'border-brand-200 bg-brand-50 text-brand-950',
    warning: 'border-amber-200 bg-amber-50 text-amber-950',
    info: 'border-sky-200 bg-sky-50 text-sky-950',
    active: 'border-emerald-200 bg-emerald-50 text-emerald-950',
  }[periodInfo.tone] || 'border-slate-200 bg-slate-50 text-slate-900'

  const upgradeOptions = useMemo(() => {
    const options = subscriptionData?.plan_options
    if (Array.isArray(options) && options.length > 0) {
      return options.filter((row) => !row.eligibility?.is_current)
    }

    return plans
      .filter((plan) => Number(plan.id) !== Number(currentPlan?.id))
      .map((plan) => ({
        plan,
        eligibility: { allowed: true, is_downgrade: false, blockers: [], message: null },
      }))
  }, [subscriptionData?.plan_options, plans, currentPlan?.id])

  const renewEligible = useMemo(() => canRenewCurrentPlan(auth, {
    accessMode,
    lifecycle: summary.lifecycle,
    status,
    plan: currentPlan,
  }), [auth, accessMode, summary.lifecycle, status, currentPlan])

  const upgradeEligible = accessMode === 'locked'

  const plansSectionRef = React.useRef(null)
  const scrollToPlans = useCallback(() => {
    plansSectionRef.current?.scrollIntoView({ behavior: 'smooth', block: 'start' })
  }, [])

  const renewPlanOption = useMemo(() => {
    if (!renewEligible || !currentPlan) return null

    const fromApi = subscriptionData?.plan_options?.find(
      (row) => Number(row.plan.id) === Number(currentPlan.id),
    )

    return fromApi ?? {
      plan: currentPlan,
      eligibility: { allowed: true, is_downgrade: false, is_current: false, blockers: [], message: null },
    }
  }, [renewEligible, currentPlan, subscriptionData?.plan_options])

  const displayPlanOptions = useMemo(() => {
    let options = upgradeOptions
    if (renewPlanOption && !options.some((row) => Number(row.plan.id) === Number(currentPlan?.id))) {
      options = [renewPlanOption, ...options]
    }
    if (upgradeEligible) {
      options = options.filter(({ plan }) => {
        const slug = String(plan?.slug || plan?.name || '').toLowerCase()
        return slug !== 'free' && slug !== 'free-trial' && Number(plan?.price || 0) > 0
      })
    }
    return options
  }, [renewPlanOption, upgradeOptions, currentPlan?.id, upgradeEligible])

  const moduleLabels = useMemo(() => {
    const labelMap = Object.fromEntries(getSubscriptionModuleCatalog().map((row) => [row.key, row.label]))
    return modules.map((key) => labelMap[key] || key)
  }, [modules])

  const handleUpgrade = async (plan, { renew = false } = {}) => {
    if (!canUpgrade) {
      pushToast('You do not have permission to change the subscription plan.', 'error')
      return
    }

    const option = displayPlanOptions.find((row) => Number(row.plan.id) === Number(plan.id))
    if (option?.eligibility?.allowed === false) {
      pushToast(option.eligibility.message || 'This plan change is not allowed with your current usage.', 'warning')
      return
    }

    if (pendingOrder) {
      pushToast('An upgrade request is already pending for this salon.', 'warning')
      return
    }

    setUpgrading(plan.id)

    try {
      if (billing?.allow_instant_upgrade) {
        await upgradeSubscription(plan.id)
        await auth.fetchMe()
        await loadData()
        pushToast(
          renew
            ? `${plan.name} renewed. Full access is restored.`
            : `Upgraded to ${plan.name}. New modules and limits are now active.`,
          'success',
        )
        return
      }

      const result = await checkoutSubscription(plan.id)
      const checkout = result.checkout
      const order = result.upgrade_order

      const processed = await processCheckoutResult(checkout, auth.user, plan.name, {
        confirmOrderId: order?.id,
        onSuccess: async () => {
          await auth.fetchMe()
          await loadData()
          pushToast(
            renew
              ? `Payment successful. ${plan.name} is renewed.`
              : `Payment successful. Upgraded to ${plan.name}.`,
            'success',
          )
        },
        onError: (error) => {
          throw error
        },
      })

      if (processed?.redirected) {
        return
      }

      if (!processed?.manual) {
        return
      }

      await loadData()
      pushToast(
        checkout?.message || (renew
          ? `Renewal request submitted for ${plan.name}. Platform admin will confirm after offline payment.`
          : `Upgrade request submitted for ${plan.name}. Platform admin will confirm after offline payment.`),
        'success',
      )
    } catch (error) {
      pushToast(error?.response?.data?.message || error?.message || (renew
        ? 'Failed to start subscription renewal.'
        : 'Failed to start subscription upgrade.'), 'error')
    } finally {
      setUpgrading(null)
    }
  }

  if (loading) {
    return (
      <div className="flex items-center gap-2 rounded-2xl border border-slate-200 bg-white p-6 text-sm text-slate-500 shadow-sm">
        <Loader2 className="h-4 w-4 animate-spin text-brand-600" />
        Loading subscription details...
      </div>
    )
  }

  return (
    <div className="space-y-6">
      {upgradeEligible && !pendingOrder ? (
        <SubscriptionUpgradeAlert
          plan={currentPlan}
          billing={billing}
          canUpgrade={canUpgrade}
          onBrowsePlans={scrollToPlans}
        />
      ) : null}

      {renewEligible && currentPlan && !pendingOrder ? (
        <SubscriptionRenewalAlert
          variant="hero"
          plan={currentPlan}
          renewalDueAt={renewalDueAt}
          daysRemaining={summary.days_remaining}
          billing={billing}
          canRenew={canUpgrade}
          renewing={upgrading === currentPlan.id}
          onRenew={() => handleUpgrade(currentPlan, { renew: true })}
        />
      ) : null}

      {pendingOrder ? (
        <div className="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
          <p className="flex items-center gap-2 font-semibold">
            <AlertCircle className="h-4 w-4" />
            Upgrade pending: {pendingOrder.to_plan?.name || 'selected plan'}
          </p>
          <p className="mt-1 text-xs text-amber-800">
            {pendingOrder.status === 'awaiting_payment'
              ? 'Complete payment to activate the new plan.'
              : billing?.manual_instructions || 'Waiting for platform admin to confirm offline payment.'}
          </p>
        </div>
      ) : null}

      {!billing?.gateway_configured && billing?.manual_instructions ? (
        <div className="rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-700">
          <p className="flex items-center gap-2 font-semibold text-slate-900">
            <HandCoins className="h-4 w-4 text-brand-600" />
            Manual billing mode
          </p>
          <p className="mt-1 text-xs text-slate-600">{billing.manual_instructions}</p>
        </div>
      ) : null}

      <div className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div className="border-b border-slate-100 bg-gradient-to-r from-brand-600 to-brand-500 px-6 py-5 text-white">
          <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
              <p className="text-xs font-semibold uppercase tracking-wider text-brand-100">Current Subscription</p>
              <h2 className="mt-1 flex items-center gap-2 text-2xl font-black">
                <Crown className="h-6 w-6" />
                {currentPlan?.name || 'No active plan'}
              </h2>
              <p className="mt-1 text-sm text-brand-50">
                {formatPlanPrice(currentPlan)}
                {status ? ` · ${status.replace('_', ' ')}` : ''}
                {summary.lifecycle_label ? ` · ${summary.lifecycle_label}` : ''}
                {billing?.gateway_configured ? ' · Online payments enabled' : ' · Offline approval required'}
                {billing?.driver === 'stripe' ? ' (Stripe)' : billing?.driver === 'razorpay' ? ' (Razorpay)' : ''}
              </p>
            </div>
            <div className={`rounded-xl border px-4 py-3 text-sm max-w-xs ${periodToneClass}`}>
              <div className="flex items-center gap-2">
                <Clock className="h-4 w-4 shrink-0" />
                <p className="font-semibold">{periodInfo.title}</p>
              </div>
              {periodInfo.detail ? (
                <p className="mt-1 text-xs opacity-90">{periodInfo.detail}</p>
              ) : null}
              {summary.lifecycle ? (
                <div className="mt-2">
                  <BaseBadge variant={lifecycleVariant(summary.lifecycle)} size="sm">
                    {lifecycleLabel(summary.lifecycle)}
                  </BaseBadge>
                </div>
              ) : null}
              {accessMode === 'read_only' ? (
                <p className="mt-2 text-xs opacity-90">
                  View-only mode — renew below to restore create, edit, and delete actions.
                </p>
              ) : null}
              {accessMode === 'locked' ? (
                <p className="mt-2 text-xs opacity-90">
                  Portal locked — choose a paid plan below to continue.
                </p>
              ) : null}
            </div>
          </div>
        </div>

        {(renewalDueAt || trialEndsAt) ? (
          <div className="border-b border-slate-100 bg-slate-50/80 px-6 py-4">
            <dl className="grid grid-cols-1 gap-3 sm:grid-cols-3 text-sm">
              <div>
                <dt className="text-xs font-semibold uppercase tracking-wide text-slate-500">Plan status</dt>
                <dd className="mt-1 font-semibold text-slate-900 capitalize">{status?.replace('_', ' ') || '—'}</dd>
              </div>
              <div>
                <dt className="text-xs font-semibold uppercase tracking-wide text-slate-500">
                  {status === 'trialing' ? 'Trial ends' : accessMode === 'read_only' ? 'Expired on' : 'Renews on'}
                </dt>
                <dd className="mt-1 font-semibold text-slate-900">
                  {formatRenewalDue(status === 'trialing' ? trialEndsAt : renewalDueAt)}
                </dd>
              </div>
              <div>
                <dt className="text-xs font-semibold uppercase tracking-wide text-slate-500">Time remaining</dt>
                <dd className="mt-1 font-semibold text-slate-900">
                  {formatDaysRemaining(summary.days_remaining ?? (trialDays != null ? trialDays : null))}
                </dd>
              </div>
            </dl>
          </div>
        ) : null}

        <div className="grid gap-6 p-6 lg:grid-cols-2">
          <div className="space-y-4">
            <h3 className="text-sm font-semibold text-slate-900">Usage limits</h3>
            <UsageBar label="Branches" used={limits.branches_used ?? 0} max={limits.max_branches} icon={Building2} />
            <UsageBar label="Staff members" used={limits.staff_used ?? 0} max={limits.max_staff} icon={Users} />
          </div>

          <div>
            <h3 className="mb-3 flex items-center gap-2 text-sm font-semibold text-slate-900">
              <Layers className="h-4 w-4 text-brand-600" />
              Included modules
            </h3>
            {moduleLabels.length > 0 ? (
              <div className="flex flex-wrap gap-2">
                {moduleLabels.map((label) => (
                  <span
                    key={label}
                    className="rounded-full border border-brand-100 bg-brand-50 px-3 py-1 text-xs font-semibold text-brand-800"
                  >
                    {label}
                  </span>
                ))}
              </div>
            ) : (
              <p className="text-sm text-slate-500">No modules are active on this plan.</p>
            )}
          </div>
        </div>
      </div>

      {displayPlanOptions.length > 0 && !pendingOrder ? (
        <div ref={plansSectionRef}>
          <div className="mb-4 flex items-center gap-2">
            <Sparkles className="h-5 w-5 text-amber-500" />
            <div>
              <h3 className="text-lg font-semibold text-slate-900">
                {upgradeEligible ? 'Choose a paid plan' : renewEligible ? 'Or switch plans' : 'Available plans'}
              </h3>
              <p className="text-sm text-slate-500">
                {canUpgrade
                  ? billing?.gateway_configured
                    ? upgradeEligible || renewEligible
                      ? 'Select a plan and pay online to activate.'
                      : 'Pay online to upgrade instantly.'
                    : upgradeEligible || renewEligible
                      ? 'Submit a request — platform admin will activate after offline payment.'
                      : 'Submit a request. Platform admin will upgrade after offline payment.'
                  : 'Contact your salon admin to change the subscription plan.'}
              </p>
            </div>
          </div>

          <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            <AnimatePresence>
              {displayPlanOptions.map(({ plan, eligibility }) => (
                <motion.div
                  key={plan.id}
                  initial={{ opacity: 0, y: 8 }}
                  animate={{ opacity: 1, y: 0 }}
                  exit={{ opacity: 0, y: 8 }}
                >
                  <PlanCard
                    plan={plan}
                    eligibility={eligibility}
                    currentPlanId={currentPlan?.id}
                    upgrading={upgrading}
                    billing={billing}
                    canUpgrade={canUpgrade}
                    renewEligible={renewEligible}
                    onUpgrade={handleUpgrade}
                  />
                </motion.div>
              ))}
            </AnimatePresence>
          </div>
        </div>
      ) : null}
    </div>
  )
}
