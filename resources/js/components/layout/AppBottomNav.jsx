import React, { useMemo } from 'react'
import { Link, useLocation } from 'react-router-dom'
import { motion } from 'framer-motion'
import { useAuthStore } from '../../stores/auth'
import { filterBottomNavItems } from '../../lib/navigation.js'
import { clsx } from 'clsx'
import { twMerge } from 'tailwind-merge'

function cn(...inputs) {
  return twMerge(clsx(inputs))
}

export default function AppBottomNav() {
  const location = useLocation()
  const auth = useAuthStore()
  const items = useMemo(() => filterBottomNavItems(auth), [auth])
  const isActive = (targetPath) => location.pathname.startsWith(targetPath)

  if (items.length === 0) {
    return null
  }

  return (
    <nav className="fixed bottom-0 left-0 right-0 z-40 pb-safe md:hidden">
      {/* Glassmorphism Background matching dark navbar & mobile menu */}
      <div className="absolute inset-0 bg-[#0A0F1C]/95 backdrop-blur-xl border-t border-slate-800/80 shadow-[0_-4px_25px_rgba(0,0,0,0.35)]" />

      <div className="relative flex justify-between items-center px-3 py-1.5">
        {items.map((item) => {
          const active = isActive(item.to)
          const Icon = item.icon

          return (
            <Link
              key={item.to}
              to={item.to}
              className={cn(
                "relative flex flex-col items-center justify-center w-full py-1.5 tap-highlight-transparent transition-all active:scale-95",
                active ? "text-brand-400" : "text-slate-400 hover:text-slate-200"
              )}
            >
              <div className="relative flex items-center justify-center w-10 h-7">
                {active && (
                  <motion.div
                    layoutId="bottomNavIndicator"
                    className="absolute inset-0 bg-brand-500/20 rounded-full border border-brand-500/30 shadow-[0_0_12px_rgba(204,15,103,0.3)]"
                    transition={{ type: "spring", stiffness: 350, damping: 25 }}
                  />
                )}
                <Icon
                  className={cn("relative z-10 w-5 h-5 transition-transform duration-300", active && "scale-110 text-brand-400")}
                  strokeWidth={active ? 2.5 : 2}
                />
              </div>
              <span className={cn(
                "mt-0.5 text-[10px] tracking-wide transition-colors duration-300",
                active ? "font-bold text-brand-400" : "font-medium text-slate-400"
              )}>
                {item.label}
              </span>
            </Link>
          )
        })}
      </div>
    </nav>
  )
}
