import React from 'react'
import { motion } from 'framer-motion'
import { Sparkles } from 'lucide-react'

export default function PageSpinner({ label = 'Loading page...', fullScreen = false }) {
  const content = (
    <div className="flex flex-col items-center justify-center p-8 text-center space-y-4">
      <div className="relative flex items-center justify-center">
        {/* Animated outer glowing ring */}
        <motion.div
          animate={{ rotate: 360 }}
          transition={{ duration: 2, repeat: Infinity, ease: 'linear' }}
          className="w-14 h-14 rounded-full border-2 border-slate-200 border-t-brand-500 border-r-emerald-500 shadow-md"
        />
        {/* Center icon */}
        <div className="absolute inset-0 flex items-center justify-center">
          <Sparkles className="w-5 h-5 text-brand-600 animate-pulse" />
        </div>
      </div>
      {label && (
        <p className="text-xs font-semibold tracking-wider text-slate-500 uppercase animate-pulse">
          {label}
        </p>
      )}
    </div>
  )

  if (fullScreen) {
    return (
      <div className="min-h-[60vh] w-full flex items-center justify-center bg-slate-50/50 backdrop-blur-sm rounded-2xl">
        {content}
      </div>
    )
  }

  return content
}
