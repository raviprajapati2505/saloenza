import confetti from 'canvas-confetti'
import React, { useEffect } from 'react'
import { useNavigate } from 'react-router-dom'
import BrandLogo from '../../components/ui/BrandLogo.jsx'
import { useAuthStore } from '../../stores/auth'
import { getPlatformBranding } from '../../stores/platformBranding.js'

export default function OnboardingComplete() {
  const router = useNavigate()
  const auth = useAuthStore()
  const portalName = getPlatformBranding()?.portal_name || 'Saloenza'
  const pending = Boolean(auth.tenant?.activation_pending)
  const requestedPlan = auth.tenant?.requested_plan?.name

  const goTo = (path) => {
    router(path)
  }

  useEffect(() => {
    void auth.fetchMe()
  }, [])

  useEffect(() => {
    if (pending) return
    confetti({
      particleCount: 120,
      spread: 80,
      origin: { y: 0.6 },
      colors: ['#10b981', '#34d399', '#a7f3d0', '#064e3b'],
    })
  }, [pending])

  if (pending) {
    return (
      <div className="min-h-screen bg-[#0F172A] px-4 py-10 sm:px-8">
        <div className="mx-auto max-w-3xl">
          <BrandLogo size="text-6xl" tone="onDark" />
          <div className="mt-10 rounded-3xl bg-white p-8 shadow-2xl sm:p-10">
            <h1 className="text-3xl font-semibold text-slate-900">Registration submitted</h1>
            <p className="mt-3 text-sm text-slate-600">
              Your salon setup is complete
              {requestedPlan ? ` and a request for ${requestedPlan} was sent to the platform team` : ''}.
              Features stay locked until a platform admin collects offline payment and activates your account.
            </p>
            <button
              type="button"
              className="mt-8 inline-flex rounded-xl bg-slate-900 px-5 py-3 text-sm font-semibold text-white"
              onClick={() => goTo('/activation-pending')}
            >
              View activation status
            </button>
          </div>
        </div>
      </div>
    )
  }

  return (
    <div className="min-h-screen bg-[#0F172A] px-4 py-10 font-[Inter,ui-sans-serif,system-ui] sm:px-8">
      <div className="mx-auto max-w-5xl">
        <div className="mb-10">
          <BrandLogo size="text-6xl" tone="onDark" />
          <p className="mt-2 text-sm text-white/65">Start your salon in one modern workspace</p>
        </div>

        <div className="rounded-3xl bg-white p-8 shadow-2xl sm:p-10">
          <div className="mx-auto flex max-w-xl flex-col items-center text-center">
            <div className="checkmark-wrap">
              <svg viewBox="0 0 72 72" className="h-20 w-20">
                <circle className="checkmark-circle" cx="36" cy="36" r="34" />
                <path className="checkmark-path" d="M20 37l11 11 21-23" />
              </svg>
            </div>

            <h1 className="mt-5 text-3xl font-semibold text-slate-900">Your salon is live on {portalName}!</h1>
            <p className="mt-2 text-sm text-slate-600">
              Your onboarding setup is complete. Start managing bookings and growing your business now.
            </p>
          </div>

          <div className="mt-8 grid gap-4 md:grid-cols-3">
            <button
              type="button"
              className="rounded-2xl border border-slate-200 p-5 text-left transition hover:border-slate-900 hover:bg-slate-900 hover:text-white"
              onClick={() => goTo('/appointments')}
            >
              <p className="text-sm font-semibold">Book First Appointment</p>
              <p className="mt-1 text-xs text-slate-500 hover:text-white/80">Create your first booking in seconds.</p>
            </button>

            <button
              type="button"
              className="rounded-2xl border border-slate-200 p-5 text-left transition hover:border-slate-900 hover:bg-slate-900 hover:text-white"
              onClick={() => goTo('/staff')}
            >
              <p className="text-sm font-semibold">Invite Your Team</p>
              <p className="mt-1 text-xs text-slate-500 hover:text-white/80">Add staff and assign their roles.</p>
            </button>

            <button
              type="button"
              className="rounded-2xl border border-slate-200 p-5 text-left transition hover:border-slate-900 hover:bg-slate-900 hover:text-white"
              onClick={() => goTo('/dashboard')}
            >
              <p className="text-sm font-semibold">View Dashboard</p>
              <p className="mt-1 text-xs text-slate-500 hover:text-white/80">See your live business overview.</p>
            </button>
          </div>
        </div>
      </div>
    </div>
  )
}
