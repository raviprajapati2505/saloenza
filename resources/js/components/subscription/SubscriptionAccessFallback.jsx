import React from 'react'
import { Link } from 'react-router-dom'
import { AlertTriangle, Eye, Lock } from 'lucide-react'
import BaseButton from '../ui/BaseButton.jsx'

export default function SubscriptionAccessFallback({
  mode = 'error',
  title,
  message,
  showRenew = false,
}) {
  const isLocked = mode === 'locked'
  const isReadOnly = mode === 'read_only'

  const icon = isLocked ? Lock : isReadOnly ? Eye : AlertTriangle
  const Icon = icon
  const tone = isLocked
    ? 'border-amber-200 bg-amber-50 text-amber-950'
    : isReadOnly
      ? 'border-sky-200 bg-sky-50 text-sky-950'
      : 'border-rose-200 bg-rose-50 text-rose-950'

  const defaultTitle = isLocked
    ? 'Account locked'
    : isReadOnly
      ? 'View-only access'
      : 'Unable to load this page'

  const defaultMessage = isLocked
    ? 'Your free trial or onboarding period has ended. Upgrade to a paid plan to continue using the portal.'
    : isReadOnly
      ? 'Your subscription has expired. You can browse existing records, but changes are disabled until you renew.'
      : 'Something went wrong while loading data. Please try again or contact support if this continues.'

  return (
    <div className={`mx-auto flex max-w-lg flex-col items-center rounded-2xl border px-6 py-12 text-center shadow-sm ${tone}`}>
      <div className="mb-4 flex h-12 w-12 items-center justify-center rounded-2xl bg-white/70">
        <Icon className="h-6 w-6" />
      </div>
      <h2 className="text-lg font-bold">{title || defaultTitle}</h2>
      <p className="mt-2 text-sm leading-relaxed opacity-90">{message || defaultMessage}</p>
      <div className="mt-6 flex flex-wrap items-center justify-center gap-3">
        {showRenew ? (
          <Link to="/billing">
            <BaseButton size="sm">{isLocked ? 'Upgrade plan' : 'Renew subscription'}</BaseButton>
          </Link>
        ) : null}
        <Link to="/dashboard" className="text-sm font-semibold underline underline-offset-2 opacity-90 hover:opacity-100">
          Back to dashboard
        </Link>
      </div>
    </div>
  )
}
