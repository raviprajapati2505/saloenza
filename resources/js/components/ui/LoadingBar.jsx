import React, { useEffect, useState } from 'react'
import { useLocation } from 'react-router-dom'
import { useIsFetching, useIsMutating } from '@tanstack/react-query'
import { motion, AnimatePresence } from 'framer-motion'

export default function LoadingBar() {
  const location = useLocation()
  const isFetching = useIsFetching()
  const isMutating = useIsMutating()
  const [loading, setLoading] = useState(false)
  const [progress, setProgress] = useState(0)

  useEffect(() => {
    // Trigger progress on route change
    setLoading(true)
    setProgress(30)

    const timer1 = setTimeout(() => setProgress(75), 120)
    const timer2 = setTimeout(() => {
      setProgress(100)
      setTimeout(() => setLoading(false), 200)
    }, 280)

    return () => {
      clearTimeout(timer1)
      clearTimeout(timer2)
    }
  }, [location.pathname])

  const isLoading = loading || isFetching > 0 || isMutating > 0

  return (
    <AnimatePresence>
      {isLoading && (
        <motion.div
          initial={{ opacity: 0 }}
          animate={{ opacity: 1 }}
          exit={{ opacity: 0 }}
          transition={{ duration: 0.15 }}
          className="fixed top-0 left-0 right-0 z-50 pointer-events-none h-1 bg-transparent"
        >
          <motion.div
            initial={{ width: '0%' }}
            animate={{ width: loading ? `${progress}%` : '100%' }}
            transition={{ duration: 0.2, ease: 'easeOut' }}
            className="h-full bg-gradient-to-r from-brand-500 via-emerald-400 to-brand-600 shadow-[0_0_10px_rgba(145,37,202,0.6)]"
          />
        </motion.div>
      )}
    </AnimatePresence>
  )
}
