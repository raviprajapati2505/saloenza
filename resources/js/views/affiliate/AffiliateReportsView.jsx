import React, { useEffect, useState } from 'react'
import PageHeader from '../../components/ui/PageHeader.jsx'
import { fetchAffiliateReports } from '../../services/affiliatePortalService.js'

function Stat({ label, value }) {
  return (
    <div className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
      <p className="text-sm text-slate-500">{label}</p>
      <p className="mt-2 text-2xl font-semibold text-slate-900">{value}</p>
    </div>
  )
}

export default function AffiliateReportsView() {
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState('')
  const [data, setData] = useState(null)
  const [from, setFrom] = useState('')
  const [to, setTo] = useState('')

  async function load(params = {}) {
    setLoading(true)
    setError('')
    try {
      const payload = await fetchAffiliateReports(params)
      setData(payload)
    } catch (err) {
      setError(err?.response?.data?.message || 'Unable to load affiliate reports.')
    } finally {
      setLoading(false)
    }
  }

  useEffect(() => {
    void load()
  }, [])

  const totals = data?.totals || {}
  const byType = data?.by_type || {}
  const monthly = data?.monthly || []
  const topSaloons = data?.top_saloons || []

  return (
    <div className="space-y-6">
      <PageHeader
        title="Reports"
        subtitle="Commission and referral performance across onboarding and renewal events."
      />

      <form
        className="flex flex-wrap items-end gap-3 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm"
        onSubmit={(event) => {
          event.preventDefault()
          void load({
            from: from || undefined,
            to: to || undefined,
          })
        }}
      >
        <div>
          <label className="mb-1 block text-xs font-medium text-slate-600">From</label>
          <input type="date" value={from} onChange={(e) => setFrom(e.target.value)} className="rounded-xl border border-slate-200 px-3 py-2 text-sm" />
        </div>
        <div>
          <label className="mb-1 block text-xs font-medium text-slate-600">To</label>
          <input type="date" value={to} onChange={(e) => setTo(e.target.value)} className="rounded-xl border border-slate-200 px-3 py-2 text-sm" />
        </div>
        <button type="submit" className="rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white">
          Apply
        </button>
      </form>

      {error ? <div className="rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-700">{error}</div> : null}

      <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
        <Stat label="Referrals in range" value={totals.referrals_count ?? 0} />
        <Stat label="Commission events" value={totals.commission_count ?? 0} />
        <Stat label="Commission earned" value={`INR ${Number(totals.commission_amount || 0).toFixed(2)}`} />
        <Stat label="Sales base amount" value={`INR ${Number(totals.base_amount || 0).toFixed(2)}`} />
      </div>

      <div className="grid gap-4 md:grid-cols-2">
        <div className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
          <h3 className="text-lg font-semibold text-slate-900">By type</h3>
          <div className="mt-4 space-y-3 text-sm">
            <div className="flex items-center justify-between">
              <span>Onboarding</span>
              <span className="font-medium">{byType.onboarding?.count ?? 0} · INR {Number(byType.onboarding?.amount || 0).toFixed(2)}</span>
            </div>
            <div className="flex items-center justify-between">
              <span>Renewal</span>
              <span className="font-medium">{byType.renewal?.count ?? 0} · INR {Number(byType.renewal?.amount || 0).toFixed(2)}</span>
            </div>
          </div>
        </div>

        <div className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
          <h3 className="text-lg font-semibold text-slate-900">Top salons</h3>
          <div className="mt-4 space-y-3">
            {topSaloons.map((row) => (
              <div key={row.saloon_id} className="flex items-center justify-between text-sm">
                <span className="text-slate-700">{row.saloon_name || `Salon #${row.saloon_id}`}</span>
                <span className="font-medium text-emerald-700">INR {Number(row.commission_amount || 0).toFixed(2)}</span>
              </div>
            ))}
            {!loading && topSaloons.length === 0 ? <p className="text-sm text-slate-500">No salon earnings in this range.</p> : null}
          </div>
        </div>
      </div>

      <div className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <table className="min-w-full divide-y divide-slate-200 text-sm">
          <thead className="bg-slate-50">
            <tr>
              <th className="px-4 py-3 text-left font-semibold text-slate-600">Month</th>
              <th className="px-4 py-3 text-left font-semibold text-slate-600">Events</th>
              <th className="px-4 py-3 text-left font-semibold text-slate-600">Onboarding</th>
              <th className="px-4 py-3 text-left font-semibold text-slate-600">Renewal</th>
              <th className="px-4 py-3 text-left font-semibold text-slate-600">Total</th>
            </tr>
          </thead>
          <tbody className="divide-y divide-slate-100">
            {monthly.map((row) => (
              <tr key={row.month}>
                <td className="px-4 py-3 font-medium text-slate-900">{row.month}</td>
                <td className="px-4 py-3 text-slate-600">{row.count}</td>
                <td className="px-4 py-3 text-slate-600">INR {Number(row.onboarding_amount || 0).toFixed(2)}</td>
                <td className="px-4 py-3 text-slate-600">INR {Number(row.renewal_amount || 0).toFixed(2)}</td>
                <td className="px-4 py-3 font-medium text-emerald-700">INR {Number(row.amount || 0).toFixed(2)}</td>
              </tr>
            ))}
          </tbody>
        </table>
        {loading ? <div className="p-4 text-sm text-slate-500">Loading reports...</div> : null}
        {!loading && monthly.length === 0 ? <div className="p-4 text-sm text-slate-500">No commission activity in this range.</div> : null}
      </div>
    </div>
  )
}
