import React, { useEffect, useState } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import { Loader2, Lock, RefreshCw, LogOut, ArrowUpRight } from 'lucide-react'
import PageHeader from '../../components/ui/PageHeader.jsx'
import SubscriptionNoticePanel from '../../components/subscription/SubscriptionNoticePanel.jsx'
import { useAuthStore } from '../../stores/auth'
import { getDefaultAuthenticatedPath } from '../../lib/navigation.js'

export default function SubscriptionExpiredView() {
  const auth = useAuthStore()
  const router = useNavigate()
  const [checking, setChecking] = useState(false)
  const [lastCheckedAt, setLastCheckedAt] = useState(null)

  const salonName = auth.tenant?.name || auth.user?.name || 'Your salon'
  const expiredPlan = auth.tenant?.plan?.name || 'Free trial'

  const refresh = async ({ redirectIfActive = true } = {}) => {
    setChecking(true)
    try {
      const payload = await auth.fetchMe()
      setLastCheckedAt(new Date())
      const accessMode = payload?.tenant?.subscription_access_mode
      if (redirectIfActive && accessMode === 'full') {
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

  const actions = (
    <div className="flex w-full flex-col gap-2 sm:flex-row sm:flex-wrap lg:w-auto lg:flex-col lg:items-stretch">
      <Link
        to="/billing"
        className="inline-flex items-center justify-center gap-2 rounded-xl bg-brand-600 px-5 py-2.5 text-sm font-semibold text-white shadow-md shadow-brand-900/15 transition hover:bg-brand-500"
      >
        View plans &amp; upgrade
        <ArrowUpRight className="h-4 w-4" />
      </Link>
      <button
        type="button"
        disabled={checking}
        onClick={() => void refresh({ redirectIfActive: true })}
        className="inline-flex items-center justify-center gap-2 rounded-xl border border-white/20 bg-white/10 px-4 py-2.5 text-sm font-semibold text-white backdrop-blur-sm transition hover:bg-white/15 disabled:opacity-60"
      >
        {checking ? <Loader2 className="h-4 w-4 animate-spin" /> : <RefreshCw className="h-4 w-4" />}
        Refresh status
      </button>
    </div>
  )

  return (
    <div className="relative min-h-[70vh]">
      <div className="pointer-events-none select-none space-y-6 opacity-35 blur-[1px]" aria-hidden>
        <PageHeader title="Dashboard" subtitle="Workspace locked until upgrade" />
        <div className="grid gap-4 sm:grid-cols-3">
          {[1, 2, 3].map((item) => (
            <div key={item} className="h-28 rounded-2xl border border-slate-200 bg-white" />
          ))}
        </div>
      </div>

      <div className="absolute inset-0 flex items-start justify-center px-4 pt-8 sm:pt-14">
        <div className="w-full max-w-2xl space-y-4">
          <SubscriptionNoticePanel
            variant="hero"
            tone="locked"
            icon={Lock}
            eyebrow="Workspace locked"
            title="Upgrade required to continue"
            subtitle={
              <>
                Your <span className="font-medium text-white/95">{expiredPlan}</span> for{' '}
                <span className="font-medium text-white/95">{salonName}</span> has ended. Choose a paid plan on
                billing to unlock the portal.
              </>
            }
            action={actions}
            chips={[
              { label: 'Sign-in allowed', variant: 'success' },
              { label: 'Salon features locked', icon: Lock, variant: 'muted' },
              { label: 'Data preserved', variant: 'neutral' },
            ]}
            footnote="Status refreshes automatically every 20 seconds after you upgrade or renew."
          />

          <div className="flex flex-wrap items-center justify-between gap-3 px-1 text-xs text-slate-500">
            {lastCheckedAt ? (
              <span>Last checked {lastCheckedAt.toLocaleTimeString()}</span>
            ) : (
              <span>Checking subscription status…</span>
            )}
            <button
              type="button"
              onClick={() => void auth.logout().then(() => router('/login'))}
              className="inline-flex items-center gap-1.5 font-medium text-slate-600 hover:text-slate-900"
            >
              <LogOut className="h-3.5 w-3.5" />
              Sign out
            </button>
          </div>
        </div>
      </div>
    </div>
  )
}
