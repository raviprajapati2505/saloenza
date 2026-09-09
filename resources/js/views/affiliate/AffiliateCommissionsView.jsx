import React, { useEffect, useState } from 'react'
import PageHeader from '../../components/ui/PageHeader.jsx'
import { fetchAffiliateCommissions } from '../../services/affiliatePortalService.js'

export default function AffiliateCommissionsView() {
  const [rows, setRows] = useState([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState('')

  useEffect(() => {
    let active = true
    async function load() {
      setLoading(true)
      setError('')
      try {
        const payload = await fetchAffiliateCommissions()
        if (!active) return
        setRows(payload.commissions?.data || payload.commissions || [])
      } catch (err) {
        if (!active) return
        setError(err?.response?.data?.message || 'Unable to load commissions.')
      } finally {
        if (active) setLoading(false)
      }
    }
    void load()
    return () => { active = false }
  }, [])

  return (
    <div className="space-y-6">
      <PageHeader title="Commissions" subtitle="Onboarding and renewal commissions are shown with lock and availability status." />
      {error ? <div className="rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-700">{error}</div> : null}
      <div className="grid gap-4">
        {rows.map((row) => (
          <div key={row.id} className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <div className="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
              <div>
                <p className="text-lg font-semibold text-slate-900">{row.saloon?.name || '-'}</p>
                <p className="text-sm text-slate-500">{row.type} commission · {row.status}</p>
              </div>
              <div className="text-right">
                <p className="text-lg font-semibold text-emerald-700">INR {Number(row.commission_amount || 0).toFixed(2)}</p>
                <p className="text-xs text-slate-500">Base INR {Number(row.base_amount || 0).toFixed(2)} @ {Number(row.commission_rate || 0).toFixed(2)}%</p>
              </div>
            </div>
            <div className="mt-3 text-xs text-slate-500">
              Locked until: {row.locked_until ? new Date(row.locked_until).toLocaleDateString() : '-'}
            </div>
          </div>
        ))}
        {loading ? <div className="rounded-2xl border border-slate-200 bg-white p-5 text-sm text-slate-500 shadow-sm">Loading commissions...</div> : null}
        {!loading && rows.length === 0 ? <div className="rounded-2xl border border-slate-200 bg-white p-5 text-sm text-slate-500 shadow-sm">No commissions yet.</div> : null}
      </div>
    </div>
  )
}
