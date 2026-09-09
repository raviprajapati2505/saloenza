import React, { useEffect, useState } from 'react'
import PageHeader from '../../components/ui/PageHeader.jsx'
import { createAffiliateWithdrawal, fetchAffiliateDashboard, fetchAffiliateWithdrawals } from '../../services/affiliatePortalService.js'

export default function AffiliateWithdrawalsView() {
  const [rows, setRows] = useState([])
  const [availableAmount, setAvailableAmount] = useState(0)
  const [amount, setAmount] = useState('')
  const [notes, setNotes] = useState('')
  const [loading, setLoading] = useState(true)
  const [saving, setSaving] = useState(false)
  const [error, setError] = useState('')

  async function load() {
    setLoading(true)
    setError('')
    try {
      const [withdrawals, dashboard] = await Promise.all([
        fetchAffiliateWithdrawals(),
        fetchAffiliateDashboard(),
      ])
      setRows(withdrawals.withdrawals?.data || withdrawals.withdrawals || [])
      setAvailableAmount(Number(dashboard.summary?.available_commission || 0))
    } catch (err) {
      setError(err?.response?.data?.message || 'Unable to load withdrawals.')
    } finally {
      setLoading(false)
    }
  }

  useEffect(() => {
    void load()
  }, [])

  const submit = async (event) => {
    event.preventDefault()
    setSaving(true)
    setError('')
    try {
      await createAffiliateWithdrawal({
        amount: Number(amount),
        notes: notes || null,
      })
      setAmount('')
      setNotes('')
      await load()
    } catch (err) {
      setError(err?.response?.data?.message || 'Unable to submit withdrawal request.')
    } finally {
      setSaving(false)
    }
  }

  return (
    <div className="space-y-6">
      <PageHeader title="Withdrawals" subtitle="Withdraw only released commissions after the lock period expires." />
      {error ? <div className="rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-700">{error}</div> : null}

      <div className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        <p className="text-sm text-slate-500">Available to withdraw</p>
        <p className="mt-2 text-3xl font-semibold text-slate-900">INR {availableAmount.toFixed(2)}</p>
        <form className="mt-5 grid gap-4 md:grid-cols-[180px,1fr,auto]" onSubmit={submit}>
          <input
            value={amount}
            onChange={(event) => setAmount(event.target.value)}
            type="number"
            min="1"
            step="0.01"
            placeholder="Amount"
            className="rounded-xl border border-slate-200 px-3 py-2 text-sm outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20"
          />
          <input
            value={notes}
            onChange={(event) => setNotes(event.target.value)}
            type="text"
            placeholder="Notes for payout"
            className="rounded-xl border border-slate-200 px-3 py-2 text-sm outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20"
          />
          <button
            type="submit"
            disabled={saving || !amount}
            className="rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white disabled:cursor-not-allowed disabled:opacity-60"
          >
            {saving ? 'Submitting...' : 'Request Withdrawal'}
          </button>
        </form>
      </div>

      <div className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <table className="min-w-full divide-y divide-slate-200 text-sm">
          <thead className="bg-slate-50">
            <tr>
              <th className="px-4 py-3 text-left font-semibold text-slate-600">Amount</th>
              <th className="px-4 py-3 text-left font-semibold text-slate-600">Status</th>
              <th className="px-4 py-3 text-left font-semibold text-slate-600">Requested</th>
              <th className="px-4 py-3 text-left font-semibold text-slate-600">Paid</th>
              <th className="px-4 py-3 text-left font-semibold text-slate-600">Reference</th>
              <th className="px-4 py-3 text-left font-semibold text-slate-600">Notes</th>
            </tr>
          </thead>
          <tbody className="divide-y divide-slate-100">
            {rows.map((row) => (
              <tr key={row.id}>
                <td className="px-4 py-3 font-medium text-slate-900">INR {Number(row.amount || 0).toFixed(2)}</td>
                <td className="px-4 py-3 text-slate-600">{row.status}</td>
                <td className="px-4 py-3 text-slate-600">{row.requested_at ? new Date(row.requested_at).toLocaleDateString() : '-'}</td>
                <td className="px-4 py-3 text-slate-600">{row.paid_at ? new Date(row.paid_at).toLocaleDateString() : '-'}</td>
                <td className="px-4 py-3 text-slate-600">{row.payout_reference || '-'}</td>
                <td className="px-4 py-3 text-slate-600">{row.notes || '-'}</td>
              </tr>
            ))}
          </tbody>
        </table>
        {loading ? <div className="p-4 text-sm text-slate-500">Loading withdrawals...</div> : null}
        {!loading && rows.length === 0 ? <div className="p-4 text-sm text-slate-500">No withdrawal requests yet.</div> : null}
      </div>
    </div>
  )
}
