import React, { useEffect, useId, useMemo, useState } from 'react'
import { clsx } from 'clsx'
import { twMerge } from 'tailwind-merge'
import {
  DEFAULT_DIAL_CODE,
  combinePhoneNumber,
  dialCodeOptions,
  digitsOnly,
  splitPhoneNumber,
} from '../../lib/phoneNumber.js'

function cn(...inputs) {
  return twMerge(clsx(inputs))
}

/**
 * Phone / WhatsApp input with country dial-code dropdown.
 * Emits a full E.164-style value via onChange (e.g. +919876543210), or '' when national is empty.
 */
export default function PhoneWithCountryInput({
  label,
  value = '',
  onChange,
  error,
  required = false,
  optional = false,
  disabled = false,
  placeholder = 'Mobile number',
  id: externalId,
  className,
  defaultDial = DEFAULT_DIAL_CODE,
}) {
  const autoId = useId()
  const id = externalId || autoId
  const options = useMemo(() => dialCodeOptions(), [])

  const initial = useMemo(
    () => splitPhoneNumber(value, defaultDial),
    // Only seed from value when the control mounts / external value changes meaningfully.
    // eslint-disable-next-line react-hooks/exhaustive-deps
    [value],
  )

  const [dial, setDial] = useState(initial.dial || defaultDial)
  const [national, setNational] = useState(initial.national)

  useEffect(() => {
    const next = splitPhoneNumber(value, defaultDial)
    const currentCombined = combinePhoneNumber(dial, national)
    const incoming = String(value || '').trim()
    if (incoming === currentCombined) return
    setDial(next.dial || defaultDial)
    setNational(next.national)
  }, [value, defaultDial])

  const emit = (nextDial, nextNational) => {
    onChange?.(combinePhoneNumber(nextDial, nextNational))
  }

  const handleDialChange = (event) => {
    const nextDial = event.target.value
    setDial(nextDial)
    emit(nextDial, national)
  }

  const handleNationalChange = (event) => {
    const nextNational = digitsOnly(event.target.value).slice(0, 15)
    setNational(nextNational)
    emit(dial, nextNational)
  }

  return (
    <div className={cn('w-full', className)}>
      {label ? (
        <label htmlFor={id} className="mb-1.5 block text-sm font-medium text-slate-700">
          {label}
          {required ? <span className="ml-0.5 text-rose-500">*</span> : null}
          {optional && !required ? (
            <span className="ml-1 text-xs font-normal text-slate-400">(optional)</span>
          ) : null}
        </label>
      ) : null}

      <div
        className={cn(
          'flex overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-inset ring-slate-300 transition-shadow focus-within:ring-2 focus-within:ring-brand-500',
          disabled && 'bg-slate-50 opacity-80',
          error && 'ring-rose-300 focus-within:ring-rose-400',
        )}
      >
        <select
          aria-label={`${label || 'Phone'} country code`}
          value={dial}
          disabled={disabled}
          onChange={handleDialChange}
          className="max-w-[9.5rem] shrink-0 border-0 border-r border-slate-200 bg-slate-50 py-0 pl-2.5 pr-7 text-sm text-slate-800 focus:ring-0 disabled:cursor-not-allowed"
        >
          {options.map((option) => (
            <option key={`${option.value}-${option.label}`} value={option.value}>
              {option.shortLabel}
            </option>
          ))}
        </select>
        <input
          id={id}
          type="tel"
          inputMode="numeric"
          autoComplete="tel-national"
          disabled={disabled}
          placeholder={placeholder}
          value={national}
          onChange={handleNationalChange}
          className="block min-w-0 flex-1 border-0 bg-transparent py-2.5 pl-3 pr-3 text-sm text-slate-900 placeholder:text-slate-400 focus:ring-0 disabled:cursor-not-allowed"
        />
      </div>

      {error ? (
        <p className="mt-1.5 text-sm font-medium text-rose-500">{error}</p>
      ) : null}
    </div>
  )
}
