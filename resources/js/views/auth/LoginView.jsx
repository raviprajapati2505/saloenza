import React, { useState } from 'react'
import { Link, useNavigate, useSearchParams } from 'react-router-dom'
import { motion } from 'framer-motion'
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
  const portalName = platformBranding?.portal_name || 'Saloenza'

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
    <div className="relative min-h-screen overflow-hidden bg-[#fff7fa] text-slate-900">
      <div
        aria-hidden="true"
        className="pointer-events-none absolute inset-0"
        style={{
          background:
            'radial-gradient(ellipse 90% 70% at 0% 0%, rgba(204,15,103,0.16), transparent 55%), radial-gradient(ellipse 70% 55% at 100% 100%, rgba(143,10,72,0.12), transparent 50%), linear-gradient(165deg, #fff7fa 0%, #ffffff 48%, #fce7ef 100%)',
        }}
      />
      <div
        aria-hidden="true"
        className="pointer-events-none absolute inset-0 opacity-[0.35]"
        style={{
          backgroundImage:
            'url("data:image/svg+xml,%3Csvg width=\'60\' height=\'60\' viewBox=\'0 0 60 60\' xmlns=\'http://www.w3.org/2000/svg\'%3E%3Cg fill=\'none\' fill-rule=\'evenodd\'%3E%3Cg fill=\'%23cc0f67\' fill-opacity=\'0.06\'%3E%3Cpath d=\'M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z\'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E")',
        }}
      />

      <div className="relative z-10 grid min-h-screen lg:grid-cols-[1.05fr_0.95fr]">
        <section className="relative hidden flex-col justify-between px-12 py-14 xl:px-16 xl:py-16 lg:flex">
          <motion.div
            initial={{ opacity: 0, y: 12 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ duration: 0.45, ease: 'easeOut' }}
          >
            <BrandLogo size="xl" className="max-w-[280px]" />
          </motion.div>

          <motion.div
            className="max-w-xl"
            initial={{ opacity: 0, y: 18 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ duration: 0.5, delay: 0.08, ease: 'easeOut' }}
          >
            <p className="text-sm font-semibold tracking-[0.2em] text-brand-600 uppercase">
              {portalName}
            </p>
            <h1 className="mt-4 text-4xl font-semibold leading-[1.15] tracking-tight text-slate-900 xl:text-5xl">
              Run your salon with{' '}
              <span className="bg-gradient-to-r from-brand-500 to-brand-700 bg-clip-text text-transparent">
                clarity
              </span>
              .
            </h1>
            <p className="mt-5 max-w-md text-base leading-relaxed text-slate-600">
              Appointments, staff, and billing in one calm workspace built for busy salon days.
            </p>
          </motion.div>

          <motion.p
            className="text-xs tracking-wide text-slate-400"
            initial={{ opacity: 0 }}
            animate={{ opacity: 1 }}
            transition={{ duration: 0.4, delay: 0.2 }}
          >
            Salon Management System
          </motion.p>
        </section>

        <section className="relative flex items-center justify-center px-4 py-10 sm:px-8 lg:px-12">
          <motion.div
            className="w-full max-w-[420px]"
            initial={{ opacity: 0, y: 16 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ duration: 0.45, delay: 0.05, ease: 'easeOut' }}
          >
            <div className="mb-8 flex justify-center lg:hidden">
              <BrandLogo size="lg" className="max-w-[220px]" />
            </div>

            <div className="rounded-[1.75rem] border border-brand-100/80 bg-white/90 p-6 shadow-[0_24px_60px_-28px_rgba(143,10,72,0.35)] backdrop-blur-sm sm:p-8">
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
                  placeholder="you@example.com or +974 5555 5555"
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
          </motion.div>
        </section>
      </div>
    </div>
  )
}
