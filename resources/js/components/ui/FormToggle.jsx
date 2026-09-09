import React, { useId } from 'react'
import { clsx } from 'clsx'
import { twMerge } from 'tailwind-merge'

function cn(...inputs) {
  return twMerge(clsx(inputs))
}

/**
 * A styled toggle switch consistent with the existing BaseInput / BaseButton design system.
 *
 * @param {{ checked: boolean, onChange: (checked: boolean) => void, label?: string, hideLabel?: boolean, disabled?: boolean, id?: string }} props
 */
export default function FormToggle({ checked, onChange, label, hideLabel = false, disabled = false, id: externalId }) {
  const autoId = useId()
  const id = externalId || autoId

  return (
    <div className="flex items-center gap-3">
      <button
        type="button"
        role="switch"
        id={id}
        aria-checked={checked}
        disabled={disabled}
        onClick={() => onChange(!checked)}
        className={cn(
          'relative inline-flex h-6 w-11 shrink-0 cursor-pointer items-center rounded-full border-2 border-transparent',
          'transition-colors duration-200 ease-in-out',
          'focus:outline-none focus:ring-2 focus:ring-brand-500 focus:ring-offset-2',
          'disabled:cursor-not-allowed disabled:opacity-50',
          checked ? 'bg-brand-600' : 'bg-slate-200',
        )}
      >
        <span
          className={cn(
            'pointer-events-none inline-block h-5 w-5 rounded-full bg-white shadow ring-0',
            'transition-transform duration-200 ease-in-out',
            checked ? 'translate-x-5' : 'translate-x-0',
          )}
        />
      </button>

      {label && (
        <label
          htmlFor={id}
          className={cn(
            'text-sm font-medium cursor-pointer select-none',
            hideLabel && 'sr-only',
            disabled ? 'text-slate-400' : 'text-slate-700',
          )}
        >
          {label}
        </label>
      )}
    </div>
  )
}
