import { api } from '../lib/api'
import { fetchMasterList, parseItem } from '../lib/apiHelpers'

export async function fetchBillingConfig() {
  const response = await api.get('/v1/billing/config')
  return response.data?.data?.billing ?? response.data?.billing ?? null
}

export async function fetchTenantSubscription() {
  const response = await api.get('/v1/subscription')
  const data = response.data?.data ?? response.data
  return data
}

export async function fetchPublicPlans() {
  return fetchMasterList('/v1/subscription-plans', 'subscription_plans')
}

export async function upgradeSubscription(subscriptionPlanId) {
  const response = await api.post('/v1/subscription/upgrade', {
    subscription_plan_id: subscriptionPlanId,
  })
  return response.data?.data ?? response.data
}

export async function checkoutSubscription(subscriptionPlanId, notes = null) {
  const response = await api.post('/v1/subscription/checkout', {
    subscription_plan_id: subscriptionPlanId,
    notes,
  })
  return response.data?.data ?? response.data
}

export async function confirmSubscriptionCheckout(payload) {
  const response = await api.post('/v1/subscription/checkout/confirm', payload)
  return response.data?.data ?? response.data
}

export function trialDaysRemaining(trialEndsAt) {
  if (!trialEndsAt) return null
  const diffMs = new Date(trialEndsAt).getTime() - Date.now()
  const days = Math.ceil(diffMs / (1000 * 60 * 60 * 24))
  return days > 0 ? days : 0
}

/** Signed day count until renewal_due_at / ends_at (negative = past due). */
export function periodDaysRemaining(iso) {
  if (!iso) return null
  const diffMs = new Date(iso).getTime() - Date.now()
  return Math.ceil(diffMs / (1000 * 60 * 60 * 24))
}

export function subscriptionPeriodHeadline({ accessMode, lifecycle, status, renewalDueAt, trialEndsAt }) {
  const dueAt = renewalDueAt || trialEndsAt
  const days = periodDaysRemaining(dueAt)

  if (accessMode === 'locked') {
    if (days != null && days < 0) {
      return { title: 'Trial ended', detail: `Ended ${formatRenewalDueShort(dueAt)}`, tone: 'locked' }
    }
    return { title: 'Account locked', detail: 'Upgrade to a paid plan to restore access.', tone: 'locked' }
  }

  if (accessMode === 'read_only' || lifecycle === 'expired') {
    return {
      title: 'Subscription expired',
      detail: days != null && days < 0
        ? `Expired ${Math.abs(days)} day${Math.abs(days) === 1 ? '' : 's'} ago · ${formatRenewalDueShort(dueAt)}`
        : formatRenewalDueShort(dueAt),
      tone: 'expired',
    }
  }

  if (status === 'trialing' || (trialEndsAt && days != null && days >= 0)) {
    return {
      title: days === 0 ? 'Trial ends today' : `Trial ends in ${days} day${days === 1 ? '' : 's'}`,
      detail: `Ends on ${formatRenewalDueShort(trialEndsAt || dueAt)}`,
      tone: 'trial',
    }
  }

  if (days != null && days <= 7) {
    return {
      title: days <= 0 ? 'Renewal due today' : `Renews in ${days} day${days === 1 ? '' : 's'}`,
      detail: `Renews on ${formatRenewalDueShort(dueAt)}`,
      tone: 'warning',
    }
  }

  if (days != null && days <= 30) {
    return {
      title: `Renews in ${days} days`,
      detail: `Next renewal on ${formatRenewalDueShort(dueAt)}`,
      tone: 'info',
    }
  }

  if (dueAt) {
    return {
      title: 'Active subscription',
      detail: `Renews on ${formatRenewalDueShort(dueAt)}`,
      tone: 'active',
    }
  }

  return { title: 'Active subscription', detail: null, tone: 'active' }
}

function formatRenewalDueShort(iso) {
  if (!iso) return '—'
  return new Date(iso).toLocaleDateString('en-IN', {
    day: 'numeric',
    month: 'short',
    year: 'numeric',
  })
}

export function usagePercent(used, max) {
  if (max == null) return 0
  if (max <= 0) return 100
  return Math.min(100, Math.round((used / max) * 100))
}

export function isUpgradePlan(currentPlan, candidatePlan) {
  if (!currentPlan || !candidatePlan) return false
  if (Number(candidatePlan.id) === Number(currentPlan.id)) return false
  const currentOrder = Number(currentPlan.sort_order ?? 0)
  const candidateOrder = Number(candidatePlan.sort_order ?? 0)
  if (candidateOrder > currentOrder) return true
  return Number(candidatePlan.price ?? 0) > Number(currentPlan.price ?? 0)
}

export function loadRazorpayScript() {
  return new Promise((resolve) => {
    if (typeof window !== 'undefined' && window.Razorpay) {
      resolve(true)
      return
    }

    const script = document.createElement('script')
    script.src = 'https://checkout.razorpay.com/v1/checkout.js'
    script.onload = () => resolve(true)
    script.onerror = () => resolve(false)
    document.body.appendChild(script)
  })
}

export async function openRazorpayCheckout(checkout, authUser, onSuccess, onError) {
  const loaded = await loadRazorpayScript()
  if (!loaded || !window.Razorpay) {
    onError?.(new Error('Unable to load Razorpay checkout.'))
    return
  }

  const rzp = new window.Razorpay({
    key: checkout.razorpay_key_id,
    amount: Math.round(Number(checkout.amount || 0) * 100),
    currency: checkout.currency || 'INR',
    name: checkout.company_name || checkout.portal_name || 'Saloenza',
    description: checkout.description || 'Subscription plan upgrade',
    order_id: checkout.razorpay_order_id,
    prefill: {
      name: authUser?.name || '',
      email: authUser?.email || '',
    },
    theme: { color: checkout.primary_color || '#cc0f67' },
    handler: (response) => onSuccess?.(response),
    modal: {
      ondismiss: () => onError?.(new Error('Payment cancelled.')),
    },
  })

  rzp.open()
}

export function openStripeCheckout(checkout) {
  if (!checkout?.checkout_url) {
    throw new Error('Stripe checkout URL is missing.')
  }
  if (checkout.order_id) {
    sessionStorage.setItem('salonos_pending_upgrade_order_id', String(checkout.order_id))
  }
  window.location.href = checkout.checkout_url
}

export async function confirmStripeReturn(sessionId, upgradeOrderId) {
  return confirmSubscriptionCheckout({
    upgrade_order_id: upgradeOrderId,
    stripe_session_id: sessionId,
  })
}

export async function processCheckoutResult(checkout, authUser, planName, callbacks = {}) {
  const { onSuccess, onError, confirmOrderId } = callbacks

  if (checkout?.mode === 'stripe') {
    openStripeCheckout(checkout)
    return { redirected: true }
  }

  if (checkout?.mode === 'razorpay') {
    await new Promise((resolve, reject) => {
      openRazorpayCheckout(
        checkout,
        authUser,
        async (paymentResponse) => {
          try {
            const result = await confirmSubscriptionCheckout({
              upgrade_order_id: confirmOrderId ?? checkout.order_id,
              razorpay_payment_id: paymentResponse.razorpay_payment_id,
              razorpay_order_id: paymentResponse.razorpay_order_id,
              razorpay_signature: paymentResponse.razorpay_signature,
            })
            onSuccess?.(result, planName)
            resolve(result)
          } catch (error) {
            reject(error)
          }
        },
        reject,
      )
    })
    return { redirected: false }
  }

  return { manual: true, message: checkout?.message }
}
