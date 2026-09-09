import React from 'react'
import { motion } from 'framer-motion'
import { clsx } from 'clsx'
import { twMerge } from 'tailwind-merge'

function cn(...inputs) {
  return twMerge(clsx(inputs))
}

export default function StaffOnDuty({ staff = [], loading = false }) {
  
  if (loading) {
    return (
      <div className="rounded-2xl border border-slate-200 bg-white shadow-[0_8px_30px_rgb(0,0,0,0.04)] p-6 animate-pulse h-full">
        <div className="h-5 w-32 bg-slate-100 rounded mb-6"></div>
        <div className="space-y-4">
          {[1, 2, 3, 4].map(i => (
            <div key={i} className="flex items-center gap-3">
              <div className="h-10 w-10 rounded-full bg-slate-100"></div>
              <div className="flex-1">
                <div className="h-4 w-24 bg-slate-100 rounded mb-1"></div>
                <div className="h-3 w-32 bg-slate-100 rounded"></div>
              </div>
            </div>
          ))}
        </div>
      </div>
    )
  }

  return (
    <div className="rounded-2xl border border-slate-200 bg-white shadow-[0_8px_30px_rgb(0,0,0,0.04)] flex flex-col h-full">
      <div className="px-6 py-5 border-b border-slate-100">
        <h3 className="text-base font-semibold text-slate-900">Staff On Duty</h3>
      </div>
      
      <div className="flex-1 p-6 flex flex-col gap-5 overflow-y-auto max-h-[400px]">
        {staff.length > 0 ? staff.map((member, idx) => (
          <motion.div 
            key={member.id}
            initial={{ opacity: 0, x: -10 }}
            animate={{ opacity: 1, x: 0 }}
            transition={{ delay: idx * 0.1 }}
            className="flex items-center gap-4 group cursor-pointer hover:bg-slate-50 p-2 -m-2 rounded-xl transition-colors"
          >
            <div className="relative">
              <div className="h-10 w-10 rounded-full bg-gradient-to-tr from-brand-500 to-brand-400 flex items-center justify-center text-white font-bold text-sm shadow-sm ring-2 ring-white">
                {member.name.charAt(0)}
              </div>
              <span className={cn(
                "absolute bottom-0 right-0 h-3 w-3 rounded-full border-2 border-white",
                member.status === 'Available' ? "bg-brand-500" : "bg-brand-700"
              )} />
            </div>
            
            <div className="flex-1 min-w-0">
              <p className="text-sm font-semibold text-slate-900 truncate">{member.name}</p>
              <p className="text-xs text-slate-500 truncate mt-0.5">
                {member.status === 'Available' ? 'Available' : `With ${member.currentCustomer}`}
              </p>
            </div>
          </motion.div>
        )) : (
          <div className="flex-1 flex items-center justify-center text-center">
            <p className="text-sm text-slate-500">No staff currently clocked in.</p>
          </div>
        )}
      </div>
    </div>
  )
}
