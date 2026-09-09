import React, { useEffect, useState } from 'react'
import { Copy } from 'lucide-react'
import PageHeader from '../../components/ui/PageHeader.jsx'
import { copyTextToClipboard } from '../../lib/clipboard.js'
import { fetchAffiliateDashboard } from '../../services/affiliatePortalService.js'
import { pushToast } from '../../stores/toast.js'

function SummaryCard({ label, value, hint }) {
  return (
    <div className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
      <p className="text-sm text-slate-500">{label}</p>
      <p className="mt-2 text-2xl font-semibold text-slate-900">{value}</p>
      {hint ? <p className="mt-1 text-xs text-slate-400">{hint}</p> : null}
    </div>
  )
}

export default function AffiliateDashboardView() {
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState('')
  const [data, setData] = useState(null)

  useEffect(() => {
    let active = true
    async function load() {
      setLoading(true)
      setError('')
      try {
        const payload = await fetchAffiliateDashboard()
        if (!active) return
        setData(payload)
      } catch (err) {
        if (!active) return
        setError(err?.response?.data?.message || 'Unable to load affiliate dashboard.')
      } finally {
        if (active) setLoading(false)
      }
    }
    void load()
    return () => { active = false }
  }, [])

  const summary = data?.summary || {}
  const partner = data?.affiliate_partner || {}

  const copyLink = async () => {
    const link = partner.signup_url
    if (!link) {
      pushToast('Signup link is not available yet.', 'error')
      return
    }

    const copied = await copyTextToClipboard(link)
    pushToast(copied ? 'Signup link copied.' : 'Unable to copy link.', copied ? 'success' : 'error')
  }

  return (
    <div className="space-y-6">
      <PageHeader
        title="Affiliate Dashboard"
        subtitle="Track referred salons, locked commissions, available earnings, and payout readiness."
      />

      {error ? <div className="rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-700">{error}</div> : null}

      <div className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        <div className="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
          <div>
            <p className="text-xs uppercase tracking-wide text-slate-500">Partner profile</p>
            <h2 className="mt-1 text-xl font-semibold text-slate-900">{partner.display_name || partner.code || 'Affiliate Partner'}</h2>
            <p className="mt-1 text-sm text-slate-500">
              Referral code: <span className="font-medium text-slate-700">{partner.code || '-'}</span>
            </p>
            <p className="mt-1 text-xs text-slate-500">
              Onboarding {partner.onboarding_commission_rate}% · Renewal {partner.renewal_commission_rate}% · Lock {partner.commission_lock_days} days
            </p>
            <p className="mt-1 text-xs text-slate-500">
              Payout method: <span className="font-medium text-slate-700">{partner.payout_method || 'Not set'}</span>
            </p>
          </div>
          <div className="rounded-xl bg-slate-50 px-4 py-3 text-sm text-slate-600">
            <p>Share link</p>
            <p className="mt-1 break-all font-medium text-slate-900">{partner.signup_url || '-'}</p>
            <button
              type="button"
              onClick={copyLink}
              className="mt-3 inline-flex items-center gap-1.5 rounded-lg bg-slate-900 px-3 py-1.5 text-xs font-semibold text-white"
            >
              <Copy className="h-3.5 w-3.5" /> Copy link
            </button>
          </div>
        </div>
      </div>

      <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
        <SummaryCard label="Referred salons" value={summary.referrals_count ?? 0} />
        <SummaryCard label="Locked commission" value={`INR ${Number(summary.locked_commission || 0).toFixed(2)}`} hint="Becomes withdrawable after the lock period." />
        <SummaryCard label="Available commission" value={`INR ${Number(summary.available_commission || 0).toFixed(2)}`} hint="Ready for withdrawal request." />
        <SummaryCard label="Requested commission" value={`INR ${Number(summary.requested_commission || 0).toFixed(2)}`} />
        <SummaryCard label="Paid commission" value={`INR ${Number(summary.paid_commission || 0).toFixed(2)}`} />
        <SummaryCard label="Active attributed salons" value={summary.saloons_count ?? 0} />
      </div>

      <div className="grid gap-6 xl:grid-cols-2">
        <div className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
          <h3 className="text-lg font-semibold text-slate-900">Recent commissions</h3>
          {loading ? <p className="mt-4 text-sm text-slate-500">Loading...</p> : null}
          <div className="mt-4 space-y-3">
            {(data?.recent_commissions || []).map((row) => (
              <div key={row.id} className="rounded-xl border border-slate-100 p-3">
                <div className="flex items-center justify-between gap-3">
                  <div>
                    <p className="font-medium text-slate-900">{row.saloon?.name || `Salon #${row.saloon_id}`}</p>
                    <p className="text-xs uppercase tracking-wide text-slate-500">{row.type} · {row.status}</p>
                  </div>
                  <p className="font-semibold text-emerald-700">INR {Number(row.commission_amount || 0).toFixed(2)}</p>
                </div>
              </div>
            ))}
            {!loading && (data?.recent_commissions || []).length === 0 ? <p className="text-sm text-slate-500">No commissions yet.</p> : null}
          </div>
        </div>

        <div className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
          <h3 className="text-lg font-semibold text-slate-900">Recent referrals</h3>
          {loading ? <p className="mt-4 text-sm text-slate-500">Loading...</p> : null}
          <div className="mt-4 space-y-3">
            {(data?.recent_referrals || []).map((row) => (
              <div key={row.id} className="rounded-xl border border-slate-100 p-3">
                <p className="font-medium text-slate-900">{row.saloon?.name || 'New salon'}</p>
                <p className="text-sm text-slate-500">{row.owner?.name || '-'} · {row.owner?.email || '-'}</p>
              </div>
            ))}
            {!loading && (data?.recent_referrals || []).length === 0 ? <p className="text-sm text-slate-500">No referrals tracked yet.</p> : null}
          </div>
        </div>
      </div>
    </div>
  )
}
