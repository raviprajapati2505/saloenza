import React, { useEffect, useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { Loader2 } from 'lucide-react'
import PageHeader from '../../components/ui/PageHeader.jsx'
import { useAuthStore } from '../../stores/auth'
import { formatPlanPrice } from '../../lib/subscriptionModules.js'
import { getDefaultAuthenticatedPath } from '../../lib/navigation.js'

export default function ActivationPendingView() {
  const auth = useAuthStore()
  const router = useNavigate()
  const [checking, setChecking] = useState(false)
  const [lastCheckedAt, setLastCheckedAt] = useState(null)

  const salonName = auth.tenant?.name || auth.user?.name || 'Your salon'
  const requestedPlan = auth.tenant?.requested_plan || null
  const activationStatus = auth.tenant?.activation_status || 'pending_activation'

  const refresh = async ({ redirectIfActive = true } = {}) => {
    setChecking(true)
    try {
      const payload = await auth.fetchMe()
      setLastCheckedAt(new Date())
      if (redirectIfActive && !payload?.tenant?.activation_pending) {
        router(getDefaultAuthenticatedPath({
          ...auth,
          tenant: payload?.tenant ?? auth.tenant,
          shouldOnboard: payload?.should_onboard ?? auth.shouldOnboard,
          workspace: payload?.workspace ?? auth.workspace,
        }))
      }
    } finally {
      setChecking(false)
    }
  }

  useEffect(() => {
    void refresh({ redirectIfActive: true })
    const timer = window.setInterval(() => {
      void refresh({ redirectIfActive: true })
    }, 20000)
    return () => window.clearInterval(timer)
  }, [])

  return (
    <div className="relative min-h-[70vh]">
      <div className="pointer-events-none select-none space-y-6 opacity-40 blur-[1px]" aria-hidden>
        <PageHeader title="Dashboard" subtitle="Workspace locked until activation" />
        <div className="grid gap-4 sm:grid-cols-3">
          {[1, 2, 3].map((item) => (
            <div key={item} className="h-28 rounded-2xl border border-slate-200 bg-white" />
          ))}
        </div>
      </div>

      <div className="absolute inset-0 flex items-start justify-center pt-10 sm:pt-16">
        <div className="mx-4 w-full max-w-xl rounded-2xl border border-amber-200 bg-amber-50 p-6 shadow-xl">
          <p className="text-xs font-semibold uppercase tracking-wide text-amber-700">
            {String(activationStatus).replaceAll('_', ' ')}
          </p>
          <h2 className="mt-2 text-xl font-semibold text-amber-950">Account under review</h2>
          <p className="mt-2 text-sm text-amber-900/80">
            <span className="font-medium text-amber-950">{salonName}</span> is registered
            {requestedPlan?.name ? (
              <>
                {' '}with a request for <span className="font-medium text-amber-950">{requestedPlan.name}</span>
                {requestedPlan.price != null ? ` (${formatPlanPrice(requestedPlan)})` : ''}
              </>
            ) : null}
            . A platform admin will contact you, collect offline payment, and activate your salon.
            You can sign in, but all features stay locked until then.
          </p>

          <ul className="mt-4 list-disc space-y-1 pl-5 text-sm text-amber-900/80">
            <li>No online payment link is required</li>
            <li>Keep using this login after activation</li>
            <li>Status refreshes automatically every 20 seconds</li>
          </ul>

          {lastCheckedAt ? (
            <p className="mt-3 text-[11px] text-amber-800/70">
              Last checked {lastCheckedAt.toLocaleTimeString()}
            </p>
          ) : null}

          <div className="mt-5 flex flex-wrap gap-3">
            <button
              type="button"
              disabled={checking}
              onClick={() => void refresh({ redirectIfActive: true })}
              className="inline-flex items-center gap-2 rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white disabled:opacity-60"
            >
              {checking ? <Loader2 className="h-4 w-4 animate-spin" /> : null}
              Refresh status
            </button>
            <button
              type="button"
              onClick={() => void auth.logout().then(() => router('/login'))}
              className="rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700"
            >
              Sign out
            </button>
          </div>
        </div>
      </div>
    </div>
  )
}
