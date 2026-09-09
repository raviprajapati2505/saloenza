import React, { forwardRef, useId, useState } from 'react'
import { clsx } from 'clsx'
import { twMerge } from 'tailwind-merge'
import { Eye, EyeOff } from 'lucide-react'

export function cn(...inputs) {
  return twMerge(clsx(inputs))
}

const BaseInput = forwardRef(({
  label,
  error,
  hint,
  required,
  prefix: Prefix,
  suffix: Suffix,
  className,
  type = 'text',
  id: externalId,
  modelValue,
  onUpdateModelValue,
  onChange,
  value,
  toggleableType,
  ...props
}, ref) => {
  const autoId = useId()
  const id = externalId || autoId
  const [showPassword, setShowPassword] = useState(false)

  const isPasswordType = type === 'password'
  const isControlled = modelValue !== undefined || value !== undefined
  const resolvedValue = isControlled
    ? (modelValue !== undefined ? modelValue : value) ?? ''
    : undefined

  const handleChange = (e) => {
    if (onUpdateModelValue) {
      onUpdateModelValue(e.target.value)
    }
    if (onChange) {
      onChange(e)
    }
  }

  return (
    <div className={cn("w-full", className)}>
      {label && (
        <label htmlFor={id} className="block text-sm font-medium text-slate-700 mb-1.5">
          {label} {required && <span className="text-rose-500 ml-0.5">*</span>}
        </label>
      )}
      
      <div className="relative flex items-center rounded-xl overflow-hidden shadow-sm ring-1 ring-inset ring-slate-300 focus-within:ring-2 focus-within:ring-inset focus-within:ring-brand-500 transition-shadow bg-white">
        {Prefix && (
          <div className="pl-3 flex items-center pointer-events-none text-slate-400">
            {typeof Prefix === 'string' ? Prefix : <Prefix className="h-4 w-4" />}
          </div>
        )}
        
        <input
          ref={ref}
          id={id}
          type={isPasswordType && showPassword ? 'text' : type}
          {...(isControlled ? { value: resolvedValue } : {})}
          onChange={handleChange}
          aria-invalid={!!error}
          className={cn(
            "block w-full border-0 bg-transparent py-2.5 text-slate-900 placeholder:text-slate-400 focus:ring-0 sm:text-sm sm:leading-6",
            Prefix ? "pl-2" : "pl-3",
            (Suffix || (isPasswordType && toggleableType)) ? "pr-2" : "pr-3"
          )}
          {...props}
        />

        {(Suffix || (isPasswordType && toggleableType)) && (
          <div className={cn(
            "pr-3 flex items-center text-slate-400",
            Suffix ? "pointer-events-none" : "pointer-events-auto"
          )}>
            {Suffix ? (
              typeof Suffix === 'string' ? Suffix : <Suffix className="h-4 w-4" />
            ) : (
              <button
                type="button"
                onClick={() => setShowPassword(!showPassword)}
                className="focus:outline-none hover:text-slate-600 transition-colors cursor-pointer flex items-center justify-center p-0.5 rounded-md hover:bg-slate-100"
                tabIndex={-1}
                aria-label={showPassword ? "Hide password" : "Show password"}
              >
                {showPassword ? <EyeOff className="h-4 w-4" /> : <Eye className="h-4 w-4" />}
              </button>
            )}
          </div>
        )}
      </div>

      {error && (
        <p className="mt-1.5 text-sm text-rose-500 font-medium">
          {error}
        </p>
      )}
      {hint && !error && (
        <p className="mt-1.5 text-sm text-slate-500">
          {hint}
        </p>
      )}
    </div>
  )
})

BaseInput.displayName = 'BaseInput'
export default BaseInput
