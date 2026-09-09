import axios from 'axios'
import React, { useRef, useState } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import BaseInput from '../../components/ui/BaseInput.jsx'
import { useAuthStore } from '../../stores/auth'
import { getDefaultAuthenticatedPath } from '../../lib/navigation.js'
import { getPlatformBranding } from '../../stores/platformBranding.js'

const USE_MOCK_AUTH = import.meta.env.VITE_USE_MOCK_AUTH === 'true'

export default function TwoFactorView() {
  const router = useNavigate()
  const auth = useAuthStore()
  const platformBranding = getPlatformBranding()
  const portalName = platformBranding?.portal_name || 'Glowsuite'
  const otpRefs = useRef([])
  const [otp, setOtp] = useState(['', '', '', '', '', ''])
  const [useBackupCode, setUseBackupCode] = useState(false)
  const [backupCode, setBackupCode] = useState('')
  const [backupCodeError, setBackupCodeError] = useState('')
  const [submitting, setSubmitting] = useState(false)
  const [errorMessage, setErrorMessage] = useState('')

  const otpCode = () => otp.join('')

  const onOtpInput = (index, event) => {
    const value = String(event.target.value || '').replace(/\D/g, '').slice(0, 1)
    setOtp((previous) => {
      const nextValue = [...previous]
      nextValue[index] = value
      return nextValue
    })

    if (value && index < otp.length - 1) {
      requestAnimationFrame(() => {
        otpRefs.current[index + 1]?.focus()
      })
    }
  }

  const onBackspace = (index, event) => {
    if (!otp[index] && index > 0) {
      event.preventDefault()
      otpRefs.current[index - 1]?.focus()
    }
  }

  const handlePaste = (event) => {
    if (useBackupCode) return

    const pasted = (event.clipboardData?.getData('text') || '').replace(/\D/g, '').slice(0, 6)
    if (!pasted) return

    event.preventDefault()
    const nextOtp = [...otp]
    pasted.split('').forEach((char, index) => {
      nextOtp[index] = char
    })
    setOtp(nextOtp)

    const focusIndex = Math.min(pasted.length, 5)
    requestAnimationFrame(() => {
      otpRefs.current[focusIndex]?.focus()
    })
  }

  const toggleBackupCode = () => {
    setUseBackupCode((previous) => !previous)
    setErrorMessage('')
    setBackupCodeError('')
  }

  const submit = async (event) => {
    event.preventDefault()
    setErrorMessage('')
    setBackupCodeError('')

    if (useBackupCode && !backupCode.trim()) {
      setBackupCodeError('Backup code is required.')
      return
    }

    if (!useBackupCode && otpCode().length !== 6) {
      setErrorMessage('Please enter the 6-digit code.')
      return
    }

    setSubmitting(true)
    try {
      if (USE_MOCK_AUTH) {
        await new Promise((resolve) => setTimeout(resolve, 350))
      } else {
        await axios.post('/v1/public/2fa/verify', {
          code: useBackupCode ? null : otpCode(),
          backup_code: useBackupCode ? backupCode.trim() : null,
        })
      }
      router(getDefaultAuthenticatedPath(auth))
    } catch (error) {
      setErrorMessage(error?.response?.data?.message || 'Invalid verification code.')
    } finally {
      setSubmitting(false)
    }
  }

  return (
    <div className="min-h-screen bg-[#0F172A] px-4 py-8 font-[Inter,ui-sans-serif,system-ui] sm:px-6 lg:px-8">
      <div className="mx-auto w-full max-w-md">
        <div className="mb-6 flex items-center gap-3 text-white">
          <div className="rounded-xl bg-slate-200 p-2 ring-1 ring-slate-300">
            <svg xmlns="http://www.w3.org/2000/svg" className="h-6 w-6 text-slate-900" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8">
              <circle cx="6" cy="6" r="3.5" />
              <circle cx="18" cy="6" r="3.5" />
              <path d="M8.8 8.8L15.2 15.2M15.2 8.8L8.8 15.2M12 12l7 7M12 12l-7 7" />
            </svg>
          </div>
          <div>
            <p className="text-2xl font-semibold tracking-tight">{portalName}</p>
            <p className="text-xs text-white/65">Secure verification for your salon workspace</p>
          </div>
        </div>

        <div className="rounded-2xl bg-white p-6 shadow-2xl sm:p-8">
          <h1 className="text-2xl font-bold text-slate-900">Two-factor verification</h1>
          <p className="mt-1 text-sm text-slate-500">Enter the 6-digit code from your authenticator app.</p>

          {errorMessage ? (
            <div className="mt-4 rounded-xl border border-rose-200 bg-rose-50 px-3 py-2 text-sm text-rose-700">
              {errorMessage}
            </div>
          ) : null}

          <form className="mt-5 space-y-4" onSubmit={submit}>
            {!useBackupCode ? (
              <div>
                <label className="mb-2 block text-sm font-medium text-slate-700">OTP Code</label>
                <div className="flex justify-between gap-2 sm:gap-3" onPaste={handlePaste}>
                  {otp.map((value, index) => (
                    <input
                      key={index}
                      ref={(element) => {
                        otpRefs.current[index] = element
                      }}
                      value={value}
                      maxLength={1}
                      inputMode="numeric"
                      autoComplete="one-time-code"
                      className="h-12 w-10 rounded-xl border border-slate-300 text-center text-lg font-semibold text-slate-900 focus:border-slate-900 focus:outline-none focus:ring-2 focus:ring-slate-200 sm:w-12"
                      onChange={(event) => onOtpInput(index, event)}
                      onKeyDown={(event) => {
                        if (event.key === 'Backspace') {
                          onBackspace(index, event)
                        }
                      }}
                    />
                  ))}
                </div>
              </div>
            ) : (
              <div>
                <BaseInput
                  modelValue={backupCode}
                  onUpdateModelValue={setBackupCode}
                  label="Backup Code"
                  placeholder="Enter your backup code"
                  error={backupCodeError}
                />
              </div>
            )}

            <button
              type="button"
              className="text-sm font-medium text-slate-900 hover:text-slate-700"
              onClick={toggleBackupCode}
            >
              {useBackupCode ? 'Use authenticator code instead' : 'Use backup code'}
            </button>

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
              {submitting ? 'Verifying...' : 'Verify'}
            </button>

            <Link to="/login" className="block text-center text-sm font-medium text-slate-500 hover:text-slate-700">
              Back to Login
            </Link>
          </form>
        </div>

        <div className="mt-6 rounded-xl border border-white/15 bg-white/5 px-4 py-3 text-center text-xs text-white/65">
          Protected by {portalName} security layer
        </div>
      </div>
    </div>
  )
}
