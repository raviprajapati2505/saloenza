import React from 'react'
import PageHeader from '../../components/ui/PageHeader.jsx'
import { useAuthStore } from '../../stores/auth'

export default function AffiliatePendingView() {
  const auth = useAuthStore()
  const status = auth.affiliatePartner?.status || 'pending'
  const displayName = auth.affiliatePartner?.display_name || auth.user?.name || 'Partner'

  const copy = {
    pending: {
      title: 'Application under review',
      body: 'Your affiliate application is pending platform admin approval. You will get full portal access once verified.',
    },
    suspended: {
      title: 'Account suspended',
      body: 'Your affiliate account is suspended. Contact the platform team for help restoring access.',
    },
    rejected: {
      title: 'Application rejected',
      body: 'Your affiliate application was rejected. Contact the platform team if you believe this was a mistake.',
    },
  }[status] || {
    title: 'Affiliate access unavailable',
    body: 'Your affiliate account is not active yet.',
  }

  return (
    <div className="space-y-6">
      <PageHeader title="Affiliate status" subtitle={`${displayName} · ${status}`} />
      <div className="rounded-2xl border border-amber-200 bg-amber-50 p-6">
        <h2 className="text-xl font-semibold text-amber-950">{copy.title}</h2>
        <p className="mt-2 text-sm text-amber-900/80">{copy.body}</p>
        <div className="mt-4 flex flex-wrap gap-3">
          <button
            type="button"
            onClick={() => void auth.fetchMe()}
            className="rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white"
          >
            Refresh status
          </button>
          <button
            type="button"
            onClick={() => void auth.logout()}
            className="rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700"
          >
            Sign out
          </button>
        </div>
      </div>
    </div>
  )
}
