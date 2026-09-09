import React from 'react'
import { clsx } from 'clsx'
import { twMerge } from 'tailwind-merge'

export function cn(...inputs) {
  return twMerge(clsx(inputs))
}

export default function BaseBadge({ 
  variant = 'default', 
  size = 'md', 
  className, 
  children 
}) {
  const baseStyles = 'inline-flex items-center font-semibold uppercase tracking-wider backdrop-blur-md rounded-full border transition-colors'
  
  const variants = {
    success: 'border-emerald-500/30 bg-emerald-500/10 text-emerald-600',
    warning: 'border-amber-500/30 bg-amber-500/10 text-amber-600',
    danger: 'border-rose-500/30 bg-rose-500/10 text-rose-600',
    info: 'border-sky-500/30 bg-sky-500/10 text-sky-600',
    default: 'border-slate-500/30 bg-slate-500/10 text-slate-600',
  }

  const sizes = {
    sm: 'px-2 py-0.5 text-[9px]',
    md: 'px-2.5 py-0.5 text-[10px]',
    lg: 'px-3 py-1 text-xs',
  }

  return (
    <span className={cn(baseStyles, variants[variant], sizes[size], className)}>
      {children}
    </span>
  )
}
