import React, { forwardRef } from 'react'
import { motion } from 'framer-motion'
import { Loader2 } from 'lucide-react'
import { clsx } from 'clsx'
import { twMerge } from 'tailwind-merge'

export function cn(...inputs) {
  return twMerge(clsx(inputs))
}

const BaseButton = forwardRef(({
  variant = 'primary',
  size = 'md',
  loading = false,
  disabled = false,
  className,
  children,
  leftIcon: LeftIcon,
  rightIcon: RightIcon,
  type = 'button',
  ...props
}, ref) => {
  const baseStyles = 'inline-flex items-center justify-center font-medium transition-all focus:outline-none focus:ring-2 focus:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed rounded-xl'
  
  const variants = {
    primary: 'bg-brand-600 text-white hover:bg-brand-500 focus:ring-brand-500 shadow-sm shadow-brand-500/20',
    secondary: 'bg-slate-100 text-slate-700 hover:bg-slate-200 focus:ring-slate-500 border border-slate-200',
    danger: 'bg-rose-600 text-white hover:bg-rose-500 focus:ring-rose-500 shadow-sm shadow-rose-500/20',
    ghost: 'bg-transparent text-slate-600 hover:bg-slate-100 hover:text-slate-900 focus:ring-slate-500',
  }

  const sizes = {
    sm: 'text-xs px-3 py-1.5 gap-1.5',
    md: 'text-sm px-4 py-2 gap-2',
    lg: 'text-base px-6 py-3 gap-2.5',
  }

  return (
    <motion.button
      ref={ref}
      type={type}
      disabled={disabled || loading}
      whileTap={{ scale: disabled || loading ? 1 : 0.97 }}
      className={cn(baseStyles, variants[variant], sizes[size], className)}
      {...props}
    >
      {loading ? (
        <Loader2 className={cn("animate-spin", size === 'sm' ? "w-3.5 h-3.5" : size === 'lg' ? "w-5 h-5" : "w-4 h-4")} />
      ) : LeftIcon && (
        <LeftIcon className={cn(size === 'sm' ? "w-3.5 h-3.5" : size === 'lg' ? "w-5 h-5" : "w-4 h-4")} />
      )}
      
      <span>{children}</span>

      {!loading && RightIcon && (
        <RightIcon className={cn(size === 'sm' ? "w-3.5 h-3.5" : size === 'lg' ? "w-5 h-5" : "w-4 h-4")} />
      )}
    </motion.button>
  )
})

BaseButton.displayName = 'BaseButton'
export default BaseButton
