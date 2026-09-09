import React from 'react'
import { getRoleDisplay, roleBadgeClassName } from '../../lib/roleDisplay.js'
import { clsx } from 'clsx'
import { twMerge } from 'tailwind-merge'

function cn(...inputs) {
  return twMerge(clsx(inputs))
}

export default function RoleBadge({
  auth,
  variant = 'light',
  className,
  showScope = true,
  size = 'sm',
}) {
  const { label, scopeLabel, tone } = getRoleDisplay(auth)
  const text = showScope && scopeLabel ? `${label} · ${scopeLabel}` : label

  return (
    <span
      className={cn(
        'inline-flex max-w-full items-center truncate rounded-full border font-semibold',
        size === 'xs' ? 'px-2 py-0.5 text-[10px]' : 'px-2.5 py-0.5 text-[11px]',
        roleBadgeClassName(tone, variant),
        className,
      )}
      title={text}
    >
      {text}
    </span>
  )
}
