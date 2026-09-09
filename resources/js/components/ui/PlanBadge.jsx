import React from 'react'
import { clsx } from 'clsx'
import { twMerge } from 'tailwind-merge'

function cn(...inputs) {
  return twMerge(clsx(inputs))
}

export default function PlanBadge({ plan = 'Free', className }) {
  const normalized = String(plan || 'Free').trim()
  const label = normalized ? normalized.charAt(0).toUpperCase() + normalized.slice(1).toLowerCase() : 'Free'
  const key = label.toLowerCase()

  const config = {
    enterprise: 'border-brand-500/30 bg-brand-500/10 text-brand-400 shadow-[0_0_10px_rgba(145,37,202,0.15)]',
    pro: 'border-amber-500/30 bg-amber-500/10 text-amber-400 shadow-[0_0_10px_rgba(245,158,11,0.15)]',
    free: 'border-slate-500/30 bg-slate-500/10 text-slate-300 shadow-[0_0_10px_rgba(100,116,139,0.15)]'
  }

  const defaultStyle = 'border-slate-500/30 bg-slate-500/10 text-slate-300'
  const appliedStyle = config[key] || defaultStyle

  return (
    <span className={cn(
      "inline-flex items-center rounded-full border px-2.5 py-0.5 text-[10px] font-semibold tracking-wider uppercase backdrop-blur-md transition-all duration-300",
      appliedStyle,
      className
    )}>
      {label}
    </span>
  )
}
