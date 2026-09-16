import React, { useState } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import BaseInput from '../../components/ui/BaseInput.jsx'
import BrandLogo from '../../components/ui/BrandLogo.jsx'
import { api } from '../../lib/api'
import { getPlatformBranding } from '../../stores/platformBranding.js'

const USE_MOCK_AUTH = import.meta.env.VITE_USE_MOCK_AUTH === 'true'

export default function ForgotPasswordView() {
  const router = useNavigate()
  const platformBranding = getPlatformBranding()
  const portalName = platformBranding?.portal_name || 'Saloenza'
  const [step, setStep] = useState('request') // request, verify, reset
  const [login, setLogin] = useState('')
  const [loginError, setLoginError] = useState('')
  const [otp, setOtp] = useState('')
  const [otpError, setOtpError] = useState('')
  const [verificationToken, setVerificationToken] = useState('')
  const [newPassword, setNewPassword] = useState('')
  const [newPasswordConfirmation, setNewPasswordConfirmation] = useState('')
  const [resetErrors, setResetErrors] = useState({ password: '', confirmation: '' })
  const [submitting, setSubmitting] = useState(false)
  const [success, setSuccess] = useState(false)
  const [errorMessage, setErrorMessage] = useState('')
  const [otpCode, setOtpCode] = useState('')
  const [deliveryChannel, setDeliveryChannel] = useState('')

  const isEmailOrPhone = (value) => {
    const trimmed = value.trim()
    return /\S+@\S+\.\S+/.test(trimmed) || /^\+?[0-9\s\-()]{7,20}$/.test(trimmed)
  }

  const requestOtp = async (event) => {
    event.preventDefault()
    setErrorMessage('')
    setOtpCode('')
    setDeliveryChannel('')

    const nextLoginError = isEmailOrPhone(login) ? '' : 'Enter a valid email or phone number.'
    setLoginError(nextLoginError)
    if (nextLoginError) return

    setSubmitting(true)
    try {
      if (USE_MOCK_AUTH) {
        await new Promise((resolve) => setTimeout(resolve, 400))
        setOtpCode('0101')
        setDeliveryChannel(login.includes('@') ? 'email' : 'sms')
      } else {
        const response = await api.post('/v1/public/forgot-password/request-otp', {
          login: login.trim(),
        })
        setOtpCode(response?.data?.data?.otp_code || '')
        setDeliveryChannel(response?.data?.data?.channel || '')
      }
      setStep('verify')
    } catch (error) {
      setErrorMessage(error?.response?.data?.message || 'Unable to send OTP right now.')
    } finally {
      setSubmitting(false)
    }
  }

  const verifyOtp = async (event) => {
    event.preventDefault()
    setErrorMessage('')
    setOtpError(otp.length >= 4 ? '' : 'Enter a valid OTP.')
    if (otp.length < 4) return

    setSubmitting(true)
    try {
      if (USE_MOCK_AUTH && otp === '0101') {
        await new Promise((resolve) => setTimeout(resolve, 400))
        setVerificationToken('mock_token_123')
      } else {
        const response = await api.post('/v1/public/forgot-password/verify-otp', {
          login: login.trim(),
          otp,
        })
        setVerificationToken(response?.data?.data?.verification_token || '')
      }
      setStep('reset')
    } catch (error) {
      setErrorMessage(error?.response?.data?.message || 'Invalid OTP. Please try again.')
    } finally {
      setSubmitting(false)
    }
  }

  const resetPassword = async (event) => {
    event.preventDefault()
    setErrorMessage('')
    
    const nextErrors = {
      password: newPassword.length >= 8 ? '' : 'Password must be at least 8 characters.',
      confirmation: newPassword === newPasswordConfirmation ? '' : 'Passwords do not match.',
    }
    setResetErrors(nextErrors)
    if (nextErrors.password || nextErrors.confirmation) return

    setSubmitting(true)
    try {
      if (USE_MOCK_AUTH) {
        await new Promise((resolve) => setTimeout(resolve, 400))
      } else {
        await api.post('/v1/public/forgot-password/reset-password', {
          login: login.trim(),
          verification_token: verificationToken,
          new_password: newPassword,
          new_password_confirmation: newPasswordConfirmation,
        })
      }
      setSuccess(true)
      setTimeout(() => {
        router('/login')
      }, 3000)
    } catch (error) {
      setErrorMessage(error?.response?.data?.message || 'Unable to reset password.')
    } finally {
      setSubmitting(false)
    }
  }

  return (
    <div className="min-h-screen bg-[#0F172A] px-4 py-8 font-[Inter,ui-sans-serif,system-ui] sm:px-6 lg:px-8">
      <div className="mx-auto w-full max-w-md">
        <div className="mb-10">
          <BrandLogo size="text-6xl" />
        </div>

        <div className="mb-6 rounded-2xl border border-white/15 bg-white/5 p-4 text-white/85">
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
              Your stylist profile, appointments, and settings stay secure. Enter your email or phone and we will send a password reset OTP.
            </p>
          </div>
        </div>

        <div className="rounded-2xl bg-white p-6 shadow-2xl sm:p-8">
          <h1 className="text-2xl font-bold text-slate-900">
            {step === 'request' && 'Forgot password'}
            {step === 'verify' && 'Verify OTP'}
            {step === 'reset' && 'Reset password'}
          </h1>
          <p className="mt-1 text-sm text-slate-500">
            {step === 'request' && 'We will send a 4-digit OTP to your email or phone.'}
            {step === 'verify' && `Enter the OTP sent to your ${deliveryChannel === 'sms' ? 'phone' : 'email'}.`}
            {step === 'reset' && 'Create a new secure password for your account.'}
          </p>

          {success ? (
            <div className="mt-5 rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-emerald-800">
              <div className="flex items-center gap-2">
                <svg xmlns="http://www.w3.org/2000/svg" className="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                  <path strokeLinecap="round" strokeLinejoin="round" d="M5 13l4 4L19 7" />
                </svg>
                <p className="text-sm font-semibold">Password reset successfully!</p>
              </div>
              <p className="mt-1 text-sm">You will be redirected to the login page momentarily.</p>
            </div>
          ) : null}

          {errorMessage && !success ? (
            <div className="mt-4 rounded-xl border border-rose-200 bg-rose-50 px-3 py-2 text-sm text-rose-700">
              {errorMessage}
            </div>
          ) : null}


          {!success && step === 'request' ? (
            <form className="mt-5 space-y-4" onSubmit={requestOtp}>
              <BaseInput
                modelValue={login}
                onUpdateModelValue={setLogin}
                label="Email or Phone"
                type="text"
                placeholder="you@example.com or +91 98765 43210"
                error={loginError}
              />
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
                {submitting ? 'Sending...' : 'Send OTP'}
              </button>
            </form>
          ) : null}

          {!success && step === 'verify' ? (
            <form className="mt-5 space-y-4" onSubmit={verifyOtp}>
              <BaseInput
                modelValue={otp}
                onUpdateModelValue={setOtp}
                label="4-Digit OTP"
                type="text"
                placeholder="Enter 4-digit OTP"
                error={otpError}
              />
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
                {submitting ? 'Verifying...' : 'Verify OTP'}
              </button>
            </form>
          ) : null}

          {!success && step === 'reset' ? (
            <form className="mt-5 space-y-4" onSubmit={resetPassword}>
              <BaseInput
                modelValue={newPassword}
                onUpdateModelValue={setNewPassword}
                label="New Password"
                type="password"
                placeholder="At least 8 characters"
                error={resetErrors.password}
                toggleableType
              />
              <BaseInput
                modelValue={newPasswordConfirmation}
                onUpdateModelValue={setNewPasswordConfirmation}
                label="Confirm New Password"
                type="password"
                placeholder="Must match new password"
                error={resetErrors.confirmation}
                toggleableType
              />
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
                {submitting ? 'Resetting...' : 'Reset Password'}
              </button>
            </form>
          ) : null}
          
          <div className="mt-5 flex justify-center">
            <Link to="/login" className="text-sm font-medium text-slate-600 hover:text-slate-900">
              Back to login
            </Link>
          </div>
        </div>

        <div className="mt-6 rounded-xl border border-white/15 bg-white/5 px-4 py-3 text-center text-xs text-white/65">
          Protected by {portalName} security layer
        </div>
      </div>
    </div>
  )
}
