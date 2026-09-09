import React, { useState } from 'react'
import { Link, useNavigate, useSearchParams } from 'react-router-dom'
import BaseInput from '../../components/ui/BaseInput.jsx'
import { useAuthStore } from '../../stores/auth'
import BrandLogo from '../../components/ui/BrandLogo.jsx'
import { getDefaultAuthenticatedPath } from '../../lib/navigation.js'
import { getLoginWelcomeMessage } from '../../lib/roleDisplay.js'
import { getPlatformBranding } from '../../stores/platformBranding.js'
import { pushToast } from '../../stores/toast.js'

export default function LoginView() {
  const router = useNavigate()
  const [searchParams] = useSearchParams()
  const auth = useAuthStore()
  const platformBranding = getPlatformBranding()
  const portalName = platformBranding?.portal_name || 'Glowsuite'

  const [submitting, setSubmitting] = useState(false)
  const [authError, setAuthError] = useState('')
  const [errors, setErrors] = useState({ login: '', password: '' })
  const [form, setForm] = useState({ login: '', password: '', remember: false })

  const isEmailOrPhone = (value) => {
    const trimmed = value.trim()
    return /\S+@\S+\.\S+/.test(trimmed) || /^\+?[0-9\s\-()]{7,20}$/.test(trimmed)
  }

  const validateLogin = () => {
    setErrors((prev) => ({
      ...prev,
      login: isEmailOrPhone(form.login) ? '' : 'Enter a valid email address or phone number.',
    }))
  }

  const validate = () => {
    const next = {
      login: isEmailOrPhone(form.login) ? '' : 'Enter a valid email address or phone number.',
      password: form.password.length >= 8 ? '' : 'Password must be at least 8 characters.',
    }
    setErrors(next)
    return !next.login && !next.password
  }

  const submit = async (e) => {
    e.preventDefault()
    if (submitting) return
    setAuthError('')
    if (!validate()) return

    setSubmitting(true)
    try {
      const response = await auth.login(
        form.login.trim(),
        form.password.trim(),
        form.remember ? 'web-remember' : 'web',
      )
      const data = response?.data || response || {}
      const payload = data.data || data
      const shouldOnboard = Boolean(payload.should_onboard ?? data.should_onboard)
      const needs2fa = Boolean(
        data.requires_2fa || data.two_factor_required || data.next_step === '2fa',
      )
      if (needs2fa) {
        router('/verify-2fa')
        return
      }

      const sessionAuth = {
        isAuthenticated: true,
        isSystemAdmin: Boolean(payload.is_system_admin ?? data.is_system_admin),
        shouldOnboard,
        permissions: Array.isArray(payload.permissions)
          ? payload.permissions.map((entry) => (typeof entry === 'string' ? entry : entry?.code)).filter(Boolean)
          : (auth.permissions || []),
        user: payload.user || data.user || auth.user,
        role: payload.role ?? data.role ?? auth.role,
        workspace: payload.workspace
          || (payload.affiliate_partner ? 'affiliate' : null)
          || (payload.role?.scope === 'affiliate' ? 'affiliate' : null)
          || auth.workspace
          || 'tenant',
        affiliatePartner: payload.affiliate_partner || auth.affiliatePartner || null,
        can: (code) => {
          if (!code) return true
          if (sessionAuth.isSystemAdmin) return true
          return sessionAuth.permissions.includes(code)
        },
        canAny: (codes) => {
          if (!codes?.length) return true
          if (sessionAuth.isSystemAdmin) return true
          return codes.some((code) => sessionAuth.permissions.includes(code))
        },
        canPlatform: (code) => sessionAuth.can(code),
        canAffiliate: (code) => sessionAuth.can(code),
      }

      pushToast(getLoginWelcomeMessage(sessionAuth), 'success', { duration: 6000 })

      const redirectTo = searchParams.get('redirect')
      if (redirectTo && redirectTo.startsWith('/') && !redirectTo.startsWith('//')) {
        router(redirectTo)
        return
      }

      router(getDefaultAuthenticatedPath(sessionAuth))
    } catch (error) {
      if (error?.response?.data?.message) {
        setAuthError(error.response.data.message)
      } else if (!error?.response) {
        setAuthError('Unable to connect to the server. Please check your connection and try again.')
      } else {
        setAuthError('Invalid credentials. Please try again.')
      }
    } finally {
      setSubmitting(false)
    }
  }

  return (
    <div className="min-h-screen bg-black text-white">
      <div className="grid min-h-screen lg:grid-cols-2">
        {/* Desktop brand pane */}
        <section className="relative hidden overflow-hidden lg:flex lg:flex-col lg:justify-between p-12 xl:p-16">
          <div
            aria-hidden="true"
            className="pointer-events-none absolute inset-0"
            style={{
              background:
                'radial-gradient(ellipse 80% 60% at 20% 30%, rgba(224,34,154,0.28), transparent 55%), radial-gradient(ellipse 70% 50% at 80% 80%, rgba(145,37,202,0.22), transparent 50%)',
            }}
          />
          <div
            aria-hidden="true"
            className="pointer-events-none absolute -right-24 -top-24 h-80 w-80 rounded-full bg-brand-500/10 blur-3xl"
          />
          <div
            aria-hidden="true"
            className="pointer-events-none absolute -bottom-16 -left-16 h-72 w-72 rounded-full bg-brand-700/15 blur-3xl"
          />

          <div className="relative z-10">
            <BrandLogo size="xl" className="max-w-[280px]" />
          </div>

          <div className="relative z-10 max-w-lg">
            <h1 className="text-4xl font-semibold leading-tight tracking-tight xl:text-5xl">
              Salon operations,{' '}
              <span className="bg-gradient-to-r from-brand-400 to-brand-700 bg-clip-text text-transparent">
                beautifully simple.
              </span>
            </h1>
            <p className="mt-5 text-base leading-relaxed text-white/60">
              Sign in to manage appointments, staff, billing, and your salon workspace.
            </p>
          </div>

          <p className="relative z-10 text-xs tracking-wide text-white/35">
            {portalName} · Salon Management System
          </p>
        </section>

        {/* Form pane */}
        <section className="relative flex items-center justify-center px-4 py-10 sm:px-8">
          <div
            aria-hidden="true"
            className="pointer-events-none absolute inset-0 lg:hidden"
            style={{
              background:
                'radial-gradient(ellipse 90% 50% at 50% 0%, rgba(224,34,154,0.18), transparent 55%)',
            }}
          />

          <div className="relative z-10 w-full max-w-md">
            <div className="mb-8 flex justify-center lg:hidden">
              <BrandLogo size="lg" className="max-w-[220px]" />
            </div>

            <div className="rounded-2xl border border-white/10 bg-white p-6 shadow-2xl shadow-black/40 sm:p-8">
              <h2 className="text-2xl font-bold tracking-tight text-slate-900">Welcome back</h2>
              <p className="mt-1.5 text-sm text-slate-500">
                Sign in to continue to your workspace.
              </p>

              {authError ? (
                <div className="mt-4 rounded-xl border border-rose-200 bg-rose-50 px-3 py-2 text-sm text-rose-700">
                  {authError}
                </div>
              ) : null}

              <form className="mt-6 space-y-4" onSubmit={submit}>
                <BaseInput
                  id="login"
                  modelValue={form.login}
                  onUpdateModelValue={(v) => setForm((f) => ({ ...f, login: v }))}
                  label="Email or Phone"
                  type="text"
                  autocomplete="username"
                  placeholder="you@example.com or +91 98765 43210"
                  error={errors.login}
                  onBlur={validateLogin}
                />

                <BaseInput
                  id="password"
                  modelValue={form.password}
                  onUpdateModelValue={(v) => setForm((f) => ({ ...f, password: v }))}
                  label="Password"
                  type="password"
                  autocomplete="current-password"
                  placeholder="Enter your password"
                  error={errors.password}
                  toggleableType
                />

                <div className="flex items-center justify-between pt-0.5">
                  <label className="inline-flex items-center gap-2 text-sm text-slate-600">
                    <input
                      checked={form.remember}
                      onChange={(e) => setForm((f) => ({ ...f, remember: e.target.checked }))}
                      type="checkbox"
                      className="h-4 w-4 rounded border-slate-300 text-brand-600 focus:ring-brand-500"
                    />
                    Remember me
                  </label>

                  <Link
                    to="/forgot-password"
                    className="text-sm font-medium text-brand-600 hover:text-brand-700"
                  >
                    Forgot password?
                  </Link>
                </div>

                <button
                  type="submit"
                  disabled={submitting}
                  className="inline-flex w-full items-center justify-center rounded-xl bg-gradient-to-r from-brand-500 to-brand-700 px-4 py-3 text-sm font-semibold text-white shadow-lg shadow-brand-500/25 transition hover:from-brand-400 hover:to-brand-600 disabled:cursor-not-allowed disabled:opacity-70"
                >
                  {submitting ? (
                    <svg className="mr-2 h-4 w-4 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                      <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="3" />
                      <path className="opacity-90" fill="currentColor" d="M4 12a8 8 0 018-8v3a5 5 0 00-5 5H4z" />
                    </svg>
                  ) : null}
                  {submitting ? 'Signing in...' : 'Sign In'}
                </button>
              </form>

              <div className="mt-6 space-y-2 border-t border-slate-100 pt-5 text-center text-sm text-slate-600">
                <p>
                  New salon?{' '}
                  <Link to="/register" className="font-semibold text-brand-600 hover:text-brand-700">
                    Start free trial
                  </Link>
                </p>
                <p>
                  Become a partner?{' '}
                  <Link to="/affiliate/apply" className="font-semibold text-brand-600 hover:text-brand-700">
                    Apply as affiliate
                  </Link>
                </p>
              </div>
            </div>
          </div>
        </section>
      </div>
    </div>
  )
}
