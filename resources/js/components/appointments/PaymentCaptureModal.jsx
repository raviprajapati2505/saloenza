import React, { useEffect, useState } from 'react'
import BaseModal from '../ui/BaseModal.jsx'
import BaseButton from '../ui/BaseButton.jsx'
import BaseSelect from '../ui/BaseSelect.jsx'
import { PAYMENT_METHODS, balanceDue } from '../../lib/appointmentPayments.js'
import { useTenantFormatter } from '../../hooks/useTenantFormatter.js'

export default function PaymentCaptureModal({
  open,
  appointment,
  onClose,
  onCaptured,
  saving = false,
  onSubmit,
}) {
  const fmt = useTenantFormatter()
  const due = balanceDue(appointment)
  const [amount, setAmount] = useState('')
  const [method, setMethod] = useState('cash')
  const [error, setError] = useState('')

  useEffect(() => {
    if (!open) return
    setAmount(due > 0 ? String(due) : '')
    setMethod('cash')
    setError('')
  }, [open, due, appointment?.id])

  const handleSubmit = async (e) => {
    e.preventDefault()
    setError('')
    const value = Number(amount)
    if (!value || value <= 0) {
      setError('Enter a valid payment amount.')
      return
    }
    try {
      await onSubmit({ amount: value, payment_method: method })
      onCaptured?.()
      onClose()
    } catch (err) {
      setError(err?.response?.data?.message || err?.response?.data?.errors?.amount?.[0] || 'Failed to record payment.')
    }
  }

  return (
    <BaseModal
      open={open}
      onClose={onClose}
      title="Collect Payment"
      size="md"
      footer={(
        <>
          <BaseButton variant="secondary" onClick={onClose} disabled={saving}>Cancel</BaseButton>
          <BaseButton onClick={handleSubmit} disabled={saving || due <= 0}>
            {saving ? 'Recording…' : 'Record Payment'}
          </BaseButton>
        </>
      )}
    >
      <form onSubmit={handleSubmit} className="space-y-4">
        {error && (
          <div className="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">{error}</div>
        )}
        <div className="grid grid-cols-2 gap-3 text-sm">
          <div className="rounded-xl border border-slate-200 bg-slate-50 p-3">
            <p className="text-[10px] font-bold uppercase text-slate-400">Grand total</p>
            <p className="text-lg font-black text-slate-800">{fmt.money(appointment?.grand_total ?? 0)}</p>
          </div>
          <div className="rounded-xl border border-slate-200 bg-slate-50 p-3">
            <p className="text-[10px] font-bold uppercase text-slate-400">Balance due</p>
            <p className="text-lg font-black text-brand-700">{fmt.money(due)}</p>
          </div>
        </div>
        <BaseSelect
          label="Payment method"
          value={method}
          onChange={(e) => setMethod(e.target.value)}
          options={PAYMENT_METHODS.map((m) => ({ value: m.value, label: m.label }))}
        />
        <div>
          <label className="block text-sm font-medium text-slate-700 mb-1.5">Amount</label>
          <input
            type="number"
            min="0.01"
            step="0.01"
            value={amount}
            onChange={(e) => setAmount(e.target.value)}
            className="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500/20 focus:border-brand-400"
          />
        </div>
      </form>
    </BaseModal>
  )
}
