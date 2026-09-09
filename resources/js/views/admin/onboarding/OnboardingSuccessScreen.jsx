import confetti from 'canvas-confetti'
import React, { useEffect } from 'react'
import { motion } from 'framer-motion'
import { Building2, CheckCircle2, Mail, MapPin, Plus, User } from 'lucide-react'
import BaseButton from '../../../components/ui/BaseButton.jsx'

/**
 * @param {{ data: import('../../../types/onboarding').OnboardingResponse, onCreateAnother: () => void }} props
 */
export default function OnboardingSuccessScreen({ data, onCreateAnother }) {
  const { salon, branch, owner } = data || {}

  useEffect(() => {
    confetti({
      particleCount: 140,
      spread: 90,
      origin: { y: 0.55 },
      colors: ['#10b981', '#34d399', '#a7f3d0', '#6366f1', '#c4b5fd'],
    })
  }, [])

  const cardVariants = {
    hidden: { opacity: 0, y: 20 },
    visible: (i) => ({
      opacity: 1,
      y: 0,
      transition: { delay: i * 0.12, duration: 0.4, ease: 'easeOut' },
    }),
  }

  return (
    <div className="flex flex-col items-center text-center">
      {/* Checkmark */}
      <motion.div
        initial={{ scale: 0 }}
        animate={{ scale: 1 }}
        transition={{ type: 'spring', stiffness: 260, damping: 20 }}
        className="mb-6 flex h-20 w-20 items-center justify-center rounded-full bg-brand-50"
      >
        <CheckCircle2 className="h-12 w-12 text-brand-500" strokeWidth={1.5} />
      </motion.div>

      <motion.h1
        initial={{ opacity: 0, y: 10 }}
        animate={{ opacity: 1, y: 0 }}
        transition={{ delay: 0.15 }}
        className="text-2xl font-bold text-slate-900"
      >
        Salon Onboarding Complete!
      </motion.h1>

      <motion.p
        initial={{ opacity: 0 }}
        animate={{ opacity: 1 }}
        transition={{ delay: 0.25 }}
        className="mt-2 max-w-sm text-sm text-slate-500"
      >
        Salon onboarding completed successfully. A welcome email has been sent to the owner.
      </motion.p>

      {/* Summary cards */}
      <div className="mt-8 grid w-full gap-4 text-left sm:grid-cols-3">
        {/* Salon */}
        <motion.div
          custom={0}
          variants={cardVariants}
          initial="hidden"
          animate="visible"
          className="rounded-2xl border border-brand-100 bg-brand-50 p-5"
        >
          <div className="mb-3 flex h-9 w-9 items-center justify-center rounded-xl bg-brand-100 text-brand-600">
            <Building2 className="h-5 w-5" />
          </div>
          <p className="text-xs font-semibold uppercase tracking-wide text-brand-600">Salon</p>
          <p className="mt-2 text-sm font-semibold text-slate-800">
            {salon?.business_name || '—'}
          </p>
          <p className="mt-1 text-xs text-slate-500">
            ID: <span className="font-mono font-medium text-slate-700">{salon?.id ?? '—'}</span>
          </p>
        </motion.div>

        {/* Branch */}
        <motion.div
          custom={1}
          variants={cardVariants}
          initial="hidden"
          animate="visible"
          className="rounded-2xl border border-indigo-100 bg-indigo-50 p-5"
        >
          <div className="mb-3 flex h-9 w-9 items-center justify-center rounded-xl bg-indigo-100 text-indigo-600">
            <MapPin className="h-5 w-5" />
          </div>
          <p className="text-xs font-semibold uppercase tracking-wide text-indigo-600">Branch</p>
          <p className="mt-2 text-sm font-semibold text-slate-800">{branch?.name || '—'}</p>
          <p className="mt-1 text-xs text-slate-500">
            ID: <span className="font-mono font-medium text-slate-700">{branch?.id ?? '—'}</span>
          </p>
        </motion.div>

        {/* Owner */}
        <motion.div
          custom={2}
          variants={cardVariants}
          initial="hidden"
          animate="visible"
          className="rounded-2xl border border-violet-100 bg-violet-50 p-5"
        >
          <div className="mb-3 flex h-9 w-9 items-center justify-center rounded-xl bg-violet-100 text-violet-600">
            <User className="h-5 w-5" />
          </div>
          <p className="text-xs font-semibold uppercase tracking-wide text-violet-600">Owner</p>
          <p className="mt-2 text-sm font-semibold text-slate-800">
            {[owner?.first_name, owner?.last_name].filter(Boolean).join(' ') || '—'}
          </p>
          <div className="mt-1 flex items-center gap-1 text-xs text-slate-500">
            <Mail className="h-3 w-3 shrink-0" />
            <span className="truncate">{owner?.email || '—'}</span>
          </div>
          <p className="mt-1 text-xs text-slate-500">
            ID: <span className="font-mono font-medium text-slate-700">{owner?.id ?? '—'}</span>
          </p>
        </motion.div>
      </div>

      <motion.div
        initial={{ opacity: 0, y: 10 }}
        animate={{ opacity: 1, y: 0 }}
        transition={{ delay: 0.5 }}
        className="mt-8"
      >
        <BaseButton
          variant="primary"
          className="w-full sm:w-auto"
          leftIcon={Plus}
          onClick={onCreateAnother}
        >
          Create Another Salon
        </BaseButton>
      </motion.div>
    </div>
  )
}
