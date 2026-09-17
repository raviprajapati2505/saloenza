import React from 'react'
import { clsx } from 'clsx'
import { twMerge } from 'tailwind-merge'
import { useAuthStore } from '../../stores/auth.js'
import { getPlatformBranding } from '../../stores/platformBranding.js'

function cn(...inputs) {
  return twMerge(clsx(inputs))
}

const SIZE_MAP = {
  sm: 'h-7',
  md: 'h-9',
  lg: 'h-12',
  xl: 'h-16',
  'text-4xl': 'h-10',
  'text-5xl': 'h-12',
  'text-6xl': 'h-16',
}

/**
 * Salon or platform brand mark.
 * Uses tenant branding when available, otherwise platform default.
 *
 * @param {'auto' | 'onDark' | 'onLight'} [tone]
 *   onDark — light plate so dark wordmarks stay readable on black/navy shells
 */
export default function BrandLogo({
  className = '',
  size = 'md',
  variant = 'full',
  alt,
  branding,
  tone = 'auto',
}) {
  const auth = useAuthStore()
  const resolvedBranding = branding || auth.tenant?.branding || getPlatformBranding() || null
  const logoSrc = resolvedBranding?.logo_url || '/images/glowsuite-logo.png'
  const logoAlt = alt || resolvedBranding?.portal_name || 'Saloenza'
  const heightClass = SIZE_MAP[size] || size
  const onDark = tone === 'onDark'

  if (variant === 'mark') {
    const mark = (
      <img
        src={logoSrc}
        alt={logoAlt}
        className={cn(
          'shrink-0 rounded-lg object-cover object-left',
          heightClass,
          'aspect-square',
          onDark ? 'bg-white' : null,
          className,
        )}
      />
    )

    if (!onDark) return mark

    return (
      <span className="inline-flex shrink-0 items-center justify-center rounded-xl bg-white p-1 shadow-sm ring-1 ring-white/80">
        {mark}
      </span>
    )
  }

  const image = (
    <img
      src={logoSrc}
      alt={logoAlt}
      className={cn('w-auto max-w-full object-contain object-left', heightClass, className)}
    />
  )

  if (!onDark) return image

  return (
    <span
      className={cn(
        'inline-flex max-w-full items-center rounded-2xl bg-white px-3 py-2 shadow-sm ring-1 ring-black/5',
      )}
    >
      {image}
    </span>
  )
}
