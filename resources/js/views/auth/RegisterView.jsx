import React, { useMemo, useState } from 'react'
import { Link, useNavigate, useSearchParams } from 'react-router-dom'
import BaseInput from '../../components/ui/BaseInput.jsx'
import BrandLogo from '../../components/ui/BrandLogo.jsx'
import { useAuthStore } from '../../stores/auth'
import { api } from '../../lib/api.js'

export default function RegisterView() {
  const router = useNavigate()
  const [searchParams] = useSearchParams()
  const auth = useAuthStore()
  const affiliateCode = searchParams.get('affiliate') || ''
  const referralCode = searchParams.get('ref') || ''
  const [submitting, setSubmitting] = useState(false)
  const [submitError, setSubmitError] = useState('')
  const [errors, setErrors] = useState({
    salon_name: '',
    name: '',
    email: '',
    phone: '',
    affiliate_code: '',
    referral_code: '',
    password: '',
    password_confirmation: '',
    terms: '',
  })
  const [form, setForm] = useState({
    salon_name: '',
    name: '',
    email: '',
    phone: '',
    affiliate_code: affiliateCode,
    referral_code: referralCode,
    password: '',
    password_confirmation: '',
    terms: false,
  })

  const strengthScore = useMemo(() => {
    let score = 0
    if (form.password.length >= 8) score += 1
    if (/[A-Z]/.test(form.password)) score += 1
    if (/[0-9]/.test(form.password)) score += 1
    if (/[^A-Za-z0-9]/.test(form.password)) score += 1
    return score
  }, [form.password])

  const strengthLabel = useMemo(() => {
    if (strengthScore <= 1) return 'Weak'
    if (strengthScore <= 3) return 'Fair'
    return 'Strong'
  }, [strengthScore])

  const strengthPercent = useMemo(() => {
    if (!form.password) return 0
    if (strengthScore <= 1) return 34
    if (strengthScore <= 3) return 67
    return 100
  }, [form.password, strengthScore])

  const strengthClass = useMemo(() => {
    if (strengthScore <= 1) return 'text-slate-400'
    if (strengthScore <= 3) return 'text-slate-600'
    return 'text-slate-900'
  }, [strengthScore])

  const strengthBarClass = useMemo(() => {
    if (strengthScore <= 1) return 'bg-slate-300'
    if (strengthScore <= 3) return 'bg-slate-500'
    return 'bg-slate-900'
  }, [strengthScore])

  const updateField = (key, value) => {
    setForm((previous) => ({ ...previous, [key]: value }))
    setErrors((previous) => ({ ...previous, [key]: '' }))
  }

  const validate = () => {
    const cleanEmail = form.email.trim()
    const cleanPhone = form.phone.trim()
    const nextErrors = {
      salon_name: form.salon_name.trim() ? '' : 'Salon name is required.',
      name: form.name.trim() ? '' : 'Your name is required.',
      email: /\S+@\S+\.\S+/.test(cleanEmail) ? '' : 'Valid email is required.',
      phone: /^\+?[0-9\s\-()]{7,20}$/.test(cleanPhone) ? '' : 'Valid phone is required.',
      affiliate_code: '',
      referral_code: '',
      password: form.password.length >= 8 ? '' : 'Password must be at least 8 characters.',
      password_confirmation: form.password === form.password_confirmation ? '' : 'Passwords do not match.',
      terms: form.terms ? '' : 'Please accept terms to continue.',
    }

    setErrors(nextErrors)
    return Object.values(nextErrors).every((value) => !value)
  }

  const submit = async (event) => {
    event.preventDefault()
    setSubmitError('')

    if (!validate()) return

    setSubmitting(true)
    try {
      await api.post('/v1/public/register', {
        salon_name: form.salon_name.trim(),
        name: form.name.trim(),
        email: form.email.trim().toLowerCase(),
        phone: form.phone.trim(),
        affiliate_code: form.affiliate_code.trim() || null,
        referral_code: form.referral_code.trim() || null,
        password: form.password,
        password_confirmation: form.password_confirmation,
        terms: form.terms,
      })

      await auth.login(form.email.trim().toLowerCase(), form.password)

      router('/onboarding')
    } catch (error) {
      const serverErrors = error?.response?.data?.errors || {}
      if (Object.keys(serverErrors).length > 0) {
        setErrors((previous) => ({
          ...previous,
          salon_name: serverErrors.salon_name?.[0] || '',
          name: serverErrors.name?.[0] || '',
          email: serverErrors.email?.[0] || '',
          phone: serverErrors.phone?.[0] || '',
          affiliate_code: serverErrors.affiliate_code?.[0] || '',
          referral_code: serverErrors.referral_code?.[0] || '',
          password: serverErrors.password?.[0] || '',
          password_confirmation: serverErrors.password_confirmation?.[0] || '',
          terms: serverErrors.terms?.[0] || '',
        }))
      }
      setSubmitError(error?.response?.data?.message || 'Unable to create account right now.')
    } finally {
      setSubmitting(false)
    }
  }

  return (
    <div className="min-h-[calc(100vh-9rem)] bg-[#0F172A] px-4 py-8 font-[Inter,ui-sans-serif,system-ui] sm:px-6 lg:px-8">
      <div className="mx-auto flex w-full max-w-md flex-col items-center">

      <div className="mb-10">
  <BrandLogo size="text-6xl" />
</div>


        <div className="mb-6 w-full rounded-2xl border border-white/15 bg-white/5 p-4 text-white/85">
          <div className="flex items-center gap-4">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 180 120" className="h-20 w-24 shrink-0">
              <circle cx="90" cy="26" r="14" fill="#ffffff" opacity="0.95" />
              <path d="M76 24c3-8 25-10 28 0-3-3-8-5-14-5s-11 2-14 5z" fill="#0f172a" />
              <rect x="69" y="42" width="42" height="40" rx="14" fill="#ffffff" opacity="0.9" />
              <rect x="62" y="52" width="56" height="34" rx="12" fill="none" stroke="#ffffff" strokeOpacity="0.45" strokeWidth="2" />
              <path d="M74 68h32M82 60h16" stroke="#0f172a" strokeWidth="3" strokeLinecap="round" />
              <path d="M52 56l16 11M128 56l-16 11" stroke="#ffffff" strokeWidth="6" strokeLinecap="round" />
            </svg>
            <p className="text-sm leading-6">
              Build your brand, manage bookings, and track salon growth with a clean, black-and-white control panel.
            </p>
          </div>
        </div>

        <div className="w-full rounded-2xl bg-white p-6 shadow-2xl sm:p-8">
          <h1 className="text-2xl font-bold text-slate-900">Start your free trial</h1>
          <p className="mt-1 text-sm text-slate-500">Create your salon workspace in minutes.</p>

          {submitError ? (
            <div className="mt-4 rounded-xl border border-rose-200 bg-rose-50 px-3 py-2 text-sm text-rose-700">
              {submitError}
            </div>
          ) : null}

          <form className="mt-5 space-y-4" onSubmit={submit}>
            <BaseInput modelValue={form.salon_name} onUpdateModelValue={(value) => updateField('salon_name', value)} label="Salon Name" placeholder="e.g. Your Salon Name" error={errors.salon_name} />
            <BaseInput modelValue={form.name} onUpdateModelValue={(value) => updateField('name', value)} label="Your Name" placeholder="Enter your full name" error={errors.name} />
            <BaseInput modelValue={form.email} onUpdateModelValue={(value) => updateField('email', value)} label="Email" type="email" placeholder="you@example.com" error={errors.email} />
            <BaseInput modelValue={form.phone} onUpdateModelValue={(value) => updateField('phone', value)} label="Phone" type="tel" placeholder="+91 98765 43210" error={errors.phone} />
            <BaseInput modelValue={form.affiliate_code} onUpdateModelValue={(value) => updateField('affiliate_code', value.toUpperCase())} label="Affiliate Code (Optional)" placeholder="e.g. AFF-XXXXXXXX" error={errors.affiliate_code} />
            <BaseInput modelValue={form.referral_code} onUpdateModelValue={(value) => updateField('referral_code', value.toUpperCase())} label="Salon Referral Code (Optional)" placeholder="e.g. SALON2026" error={errors.referral_code} />
            <BaseInput modelValue={form.password} onUpdateModelValue={(value) => updateField('password', value)} label="Password" type="password" placeholder="Create a password" error={errors.password} toggleableType />
            <BaseInput
              modelValue={form.password_confirmation}
              onUpdateModelValue={(value) => updateField('password_confirmation', value)}
              label="Confirm Password"
              type="password"
              placeholder="Re-enter password"
              error={errors.password_confirmation}
              toggleableType
            />

            <div>
              <div className="mb-2 flex items-center justify-between text-xs text-slate-500">
                <span>Password strength</span>
                <span className={`font-medium ${strengthClass}`}>{strengthLabel}</span>
              </div>
              <div className="h-2 w-full overflow-hidden rounded-full bg-slate-200">
                <div className={`h-full rounded-full transition-all ${strengthBarClass}`} style={{ width: `${strengthPercent}%` }} />
              </div>
            </div>

            <label className="inline-flex items-start gap-2 text-sm text-slate-600">
              <input
                checked={form.terms}
                onChange={(event) => updateField('terms', event.target.checked)}
                type="checkbox"
                className="mt-0.5 h-4 w-4 rounded border-slate-300 text-slate-900 focus:ring-slate-900"
              />
              <span>I agree to the Terms of Service and Privacy Policy.</span>
            </label>
            {errors.terms ? <p className="text-xs text-rose-600">{errors.terms}</p> : null}

            <button
              type="submit"
              disabled={submitting}
              className="inline-flex w-full items-center justify-center rounded-xl bg-slate-900 px-4 py-3 text-sm font-semibold text-white transition hover:bg-black disabled:cursor-not-allowed disabled:opacity-70"
            >
              {submitting ? (
                <svg className="mr-2 h-4 w-4 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                  <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="3" />
                  <path className="opacity-90" fill="currentColor" d="M4 12a8 8 0 018-8v3a5 5 0 00-5 5H4z" />
                </svg>
              ) : null}
              {submitting ? 'Creating account...' : 'Create Account'}
            </button>
          </form>

          <p className="mt-5 text-center text-sm text-slate-600">
            Already have an account?{' '}
            <Link to="/login" className="font-semibold text-slate-900 hover:text-slate-700">
              Sign in
            </Link>
            {' · '}
            Become a partner?{' '}
            <Link to="/affiliate/apply" className="font-semibold text-slate-900 hover:text-slate-700">
              Apply as affiliate
            </Link>
          </p>
        </div>
      </div>
    </div>
  )
}
