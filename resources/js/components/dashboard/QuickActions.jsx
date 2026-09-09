import React from 'react'
import { Link } from 'react-router-dom'
import { motion } from 'framer-motion'
import { Zap } from 'lucide-react'
import { QUICK_ACTION_COLORS } from './brandColors.js'

/**
 * Prop-driven quick action grid.
 * actions: [{ key, label, description?, icon, to, color? }]
 */
export default function QuickActions({
  title = 'Quick actions',
  actions = [],
  loading = false,
  emptyMessage = 'No actions available for your role.',
}) {

  return (
    <div className="flex h-full flex-col rounded-[18px] border border-slate-200 bg-white p-5 shadow-[0_8px_30px_rgb(0,0,0,0.04)] sm:p-6">
      <h3 className="mb-5 text-base font-semibold text-slate-900">{title}</h3>

      {loading ? (
        <div className="grid grid-cols-2 gap-3">
          {Array.from({ length: 4 }).map((_, i) => (
            <div key={i} className="h-28 animate-pulse rounded-xl bg-slate-50" />
          ))}
        </div>
      ) : null}

      {!loading && actions.length === 0 ? (
        <div className="flex flex-1 flex-col items-center justify-center gap-2 rounded-xl border border-dashed border-slate-200 bg-slate-50/60 px-4 py-8 text-center">
          <Zap className="h-7 w-7 text-slate-300" />
          <p className="text-sm text-slate-500">{emptyMessage}</p>
        </div>
      ) : null}

      {!loading && actions.length > 0 ? (
        <div className="grid grid-cols-2 gap-3">
          {actions.map((action, idx) => {
            const Icon = action.icon
            const colors = QUICK_ACTION_COLORS[action.color] || QUICK_ACTION_COLORS.brand || QUICK_ACTION_COLORS.teal
            return (
              <Link key={action.key || action.to || idx} to={action.to} className="group block">
                <motion.div
                  whileHover={{ y: -2 }}
                  whileTap={{ scale: 0.97 }}
                  className={`flex h-full flex-col items-center justify-center rounded-xl border p-4 transition-shadow hover:shadow-sm ${colors.border} ${colors.bg}`}
                >
                  {Icon ? (
                    <div className={`mb-3 rounded-xl bg-white p-3 shadow-sm ${colors.text}`}>
                      <Icon className="h-5 w-5" />
                    </div>
                  ) : null}
                  <span className="text-center text-sm font-semibold text-slate-700 group-hover:text-slate-900">
                    {action.label}
                  </span>
                  {action.description ? (
                    <span className="mt-1 text-center text-[11px] text-slate-500">{action.description}</span>
                  ) : null}
                </motion.div>
              </Link>
            )
          })}
        </div>
      ) : null}
    </div>
  )
}
