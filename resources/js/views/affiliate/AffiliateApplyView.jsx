import React, { useMemo, useState } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import BaseInput from '../../components/ui/BaseInput.jsx'
import BrandLogo from '../../components/ui/BrandLogo.jsx'
import { api } from '../../lib/api.js'

export default function AffiliateApplyView() {
  const router = useNavigate()
  const [submitting, setSubmitting] = useState(false)
  const [submitError, setSubmitError] = useState('')
  const [done, setDone] = useState(false)
  const [errors, setErrors] = useState({})
  const [form, setForm] = useState({
    name: '',
    email: '',
    phone: '',
    display_name: '',
    password: '',
    password_confirmation: '',
    payout_method: 'bank_transfer',
    notes: '',
    terms: false,
  })

  const updateField = (key, value) => {
    setForm((prev) => ({ ...prev, [key]: value }))
    setErrors((prev) => ({ ...prev, [key]: '' }))
  }

  const validate = () => {
    const next = {
      name: form.name.trim() ? '' : 'Full name is required.',
      email: /\S+@\S+\.\S+/.test(form.email.trim()) ? '' : 'Enter a valid email.',
      phone: form.phone.trim().length >= 7 ? '' : 'Enter a valid phone number.',
      password: form.password.length >= 8 ? '' : 'Password must be at least 8 characters.',
      password_confirmation: form.password === form.password_confirmation ? '' : 'Passwords do not match.',
      terms: form.terms ? '' : 'Please accept the terms.',
    }
    setErrors(next)
    return Object.values(next).every((value) => !value)
  }

  const submit = async (event) => {
    event.preventDefault()
    if (submitting) return
    setSubmitError('')
    if (!validate()) return

    setSubmitting(true)
    try {
      await api.post('/v1/public/affiliate/apply', {
        name: form.name.trim(),
        email: form.email.trim().toLowerCase(),
        phone: form.phone.trim(),
        display_name: form.display_name.trim() || form.name.trim(),
        password: form.password,
        password_confirmation: form.password_confirmation,
        payout_method: form.payout_method || null,
        notes: form.notes.trim() || null,
        terms: true,
      })
      setDone(true)
    } catch (error) {
      const serverErrors = error?.response?.data?.errors || {}
      setErrors({
        name: serverErrors.name?.[0] || '',
        email: serverErrors.email?.[0] || '',
        phone: serverErrors.phone?.[0] || '',
        password: serverErrors.password?.[0] || '',
        password_confirmation: serverErrors.password_confirmation?.[0] || '',
        terms: serverErrors.terms?.[0] || '',
      })
      setSubmitError(error?.response?.data?.message || 'Unable to submit application.')
    } finally {
      setSubmitting(false)
    }
  }

  const strength = useMemo(() => {
    let score = 0
    if (form.password.length >= 8) score += 1
    if (/[A-Z]/.test(form.password)) score += 1
    if (/[0-9]/.test(form.password)) score += 1
    if (/[^A-Za-z0-9]/.test(form.password)) score += 1
    return score
  }, [form.password])

  if (done) {
    return (
      <div className="min-h-screen bg-[#0F172A] text-white">
        <div className="mx-auto flex min-h-screen max-w-lg flex-col justify-center px-6 py-12">
          <BrandLogo />
          <div className="mt-8 rounded-3xl bg-white p-8 text-slate-900 shadow-xl">
            <h1 className="text-2xl font-semibold">Application submitted</h1>
            <p className="mt-3 text-sm text-slate-600">
              Thanks for applying. A platform admin will review your affiliate application.
              Once approved, sign in with the same email and password to open your affiliate portal.
            </p>
            <button
              type="button"
              onClick={() => router('/login')}
              className="mt-6 inline-flex w-full items-center justify-center rounded-xl bg-slate-900 px-4 py-3 text-sm font-semibold text-white"
            >
              Go to login
            </button>
          </div>
        </div>
      </div>
    )
  }

  return (
    <div className="min-h-screen bg-[#0F172A] text-white">
      <div className="mx-auto grid min-h-screen max-w-6xl gap-8 px-6 py-10 lg:grid-cols-2 lg:items-center">
        <section className="hidden lg:block">
          <BrandLogo />
          <h1 className="mt-10 text-4xl font-semibold tracking-tight">Become an affiliate partner</h1>
          <p className="mt-4 max-w-md text-sm text-white/70">
            Apply to refer salons, earn onboarding and renewal commissions, and manage payouts from your partner portal.
          </p>
        </section>

        <section className="rounded-3xl bg-white p-6 text-slate-900 shadow-xl sm:p-8">
          <div className="mb-6 lg:hidden"><BrandLogo /></div>
          <h2 className="text-2xl font-semibold">Affiliate application</h2>
          <p className="mt-1 text-sm text-slate-500">Submit your details for admin verification.</p>

          {submitError ? (
            <div className="mt-4 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">{submitError}</div>
          ) : null}

          <form onSubmit={submit} className="mt-6 space-y-4">
            <BaseInput label="Full name" modelValue={form.name} onUpdateModelValue={(v) => updateField('name', v)} error={errors.name} placeholder="Enter full name" />
            <BaseInput label="Business / display name" modelValue={form.display_name} onUpdateModelValue={(v) => updateField('display_name', v)} placeholder="Optional business name" />
            <BaseInput label="Email" type="email" modelValue={form.email} onUpdateModelValue={(v) => updateField('email', v)} error={errors.email} placeholder="you@example.com" />
            <BaseInput label="Phone" modelValue={form.phone} onUpdateModelValue={(v) => updateField('phone', v)} error={errors.phone} placeholder="+91 98765 43210" />
            <BaseInput label="Password" type="password" modelValue={form.password} onUpdateModelValue={(v) => updateField('password', v)} error={errors.password} toggleableType placeholder="Create a password" />
            <p className="text-xs text-slate-500">Strength: {strength <= 1 ? 'Weak' : strength <= 3 ? 'Fair' : 'Strong'}</p>
            <BaseInput label="Confirm password" type="password" modelValue={form.password_confirmation} onUpdateModelValue={(v) => updateField('password_confirmation', v)} error={errors.password_confirmation} toggleableType placeholder="Re-enter password" />
            <BaseInput label="Notes for admin (optional)" modelValue={form.notes} onUpdateModelValue={(v) => updateField('notes', v)} placeholder="Optional notes" />

            <label className="inline-flex items-start gap-2 text-sm text-slate-600">
              <input
                type="checkbox"
                checked={form.terms}
                onChange={(e) => updateField('terms', e.target.checked)}
                className="mt-1 h-4 w-4 rounded border-slate-300"
              />
              <span>I agree to the affiliate partner terms and confirm the details above are accurate.</span>
            </label>
            {errors.terms ? <p className="text-xs text-rose-600">{errors.terms}</p> : null}

            <button
              type="submit"
              disabled={submitting}
              className="inline-flex w-full items-center justify-center rounded-xl bg-slate-900 px-4 py-3 text-sm font-semibold text-white disabled:opacity-70"
            >
              {submitting ? 'Submitting...' : 'Submit application'}
            </button>
          </form>

          <p className="mt-5 text-center text-sm text-slate-600">
            Already approved?{' '}
            <Link to="/login" className="font-semibold text-slate-900 hover:text-slate-700">Sign in</Link>
          </p>
        </section>
      </div>
    </div>
  )
}
