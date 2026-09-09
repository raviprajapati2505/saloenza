import React, { forwardRef, useId } from 'react'
import { ChevronDown } from 'lucide-react'
import { clsx } from 'clsx'
import { twMerge } from 'tailwind-merge'

export function cn(...inputs) {
  return twMerge(clsx(inputs))
}

const BaseSelect = forwardRef(({
  label,
  options = [],
  error,
  placeholder,
  className,
  id: externalId,
  ...props
}, ref) => {
  const autoId = useId()
  const id = externalId || autoId

  return (
    <div className={cn("w-full", className)}>
      {label && (
        <label htmlFor={id} className="block text-sm font-medium text-slate-700 mb-1.5">
          {label}
        </label>
      )}
      
      <div className={cn(
        "relative rounded-xl shadow-sm ring-1 ring-inset ring-slate-300 focus-within:ring-2 focus-within:ring-inset focus-within:ring-brand-500 bg-white",
        props.disabled && "bg-slate-50 opacity-80",
      )}>
        <select
          ref={ref}
          id={id}
          aria-invalid={!!error}
          className="block w-full appearance-none border-0 bg-transparent h-[42px] py-0 pl-3 pr-10 text-slate-900 focus:ring-0 sm:text-sm sm:leading-6 disabled:cursor-not-allowed disabled:text-slate-500"
          {...props}
        >
          {placeholder && (
            <option value="" disabled hidden>{placeholder}</option>
          )}
          {options.map((opt) => (
            <option key={opt.value} value={opt.value}>
              {opt.label}
            </option>
          ))}
        </select>
        <div className="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-3">
          <ChevronDown className="h-4 w-4 text-slate-400" aria-hidden="true" />
        </div>
      </div>

      {error && (
        <p className="mt-1.5 text-sm text-rose-500 font-medium">
          {error}
        </p>
      )}
    </div>
  )
})

BaseSelect.displayName = 'BaseSelect'
export default BaseSelect
