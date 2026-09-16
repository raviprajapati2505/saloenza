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
 */
export default function BrandLogo({
  className = '',
  size = 'md',
  variant = 'full',
  alt,
  branding,
}) {
  const auth = useAuthStore()
  const resolvedBranding = branding || auth.tenant?.branding || getPlatformBranding() || null
  const logoSrc = resolvedBranding?.logo_url || '/images/glowsuite-logo.png'
  const logoAlt = alt || resolvedBranding?.portal_name || 'Saloenza'
  const heightClass = SIZE_MAP[size] || size

  if (variant === 'mark') {
    return (
      <img
        src={logoSrc}
        alt={logoAlt}
        className={cn(
          'shrink-0 rounded-lg object-cover object-left',
          heightClass,
          'aspect-square',
          className,
        )}
      />
    )
  }

  return (
    <img
      src={logoSrc}
      alt={logoAlt}
      className={cn('w-auto max-w-full object-contain object-left', heightClass, className)}
    />
  )
}
