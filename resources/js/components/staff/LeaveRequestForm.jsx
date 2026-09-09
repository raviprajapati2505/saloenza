import React, { useState } from 'react'
import BaseModal from '../ui/BaseModal.jsx'
import BaseButton from '../ui/BaseButton.jsx'
import BaseSelect from '../ui/BaseSelect.jsx'
import BaseInput from '../ui/BaseInput.jsx'
import axios from 'axios'

export default function LeaveRequestForm({ open, onClose, staffId, onLeaveRequested }) {
  const [submitting, setSubmitting] = useState(false)
  const [error, setError] = useState('')
  const [form, setForm] = useState({
    leaveType: 'casual',
    startDate: '',
    endDate: '',
    reason: '',
  })

  const [validationErrors, setValidationErrors] = useState({
    startDate: '',
    endDate: '',
    reason: '',
  })

  // Leave balance database mockup / configuration
  const leaveBalances = {
    casual: { label: 'Casual Leave', total: 10, used: 4, remaining: 6 },
    sick: { label: 'Sick Leave', total: 7, used: 2, remaining: 5 },
    privilege: { label: 'Privilege Leave', total: 15, used: 3, remaining: 12 },
    unpaid: { label: 'Unpaid Leave', total: 30, used: 0, remaining: 30 },
  }

  const validate = () => {
    const nextErrors = {
      startDate: form.startDate ? '' : 'Start date is required.',
      endDate: form.endDate ? '' : 'End date is required.',
      reason: form.reason.trim().length >= 10 ? '' : 'Reason must be at least 10 characters long.',
    }

    if (form.startDate && form.endDate && new Date(form.startDate) > new Date(form.endDate)) {
      nextErrors.endDate = 'End date cannot be before the start date.'
    }

    setValidationErrors(nextErrors)
    return !nextErrors.startDate && !nextErrors.endDate && !nextErrors.reason
  }

  const submit = async (e) => {
    e.preventDefault()
    setError('')
    if (!validate()) return

    setSubmitting(true)
    try {
      // Send POST to /v1/leave-requests
      const response = await axios.post('/v1/leave-requests', {
        staff_id: staffId,
        leave_type: form.leaveType,
        start_date: form.startDate,
        end_date: form.endDate,
        reason: form.reason.trim(),
      })

      // Trigger success callback
      onLeaveRequested?.(response.data?.data || response.data || {
        id: Math.floor(Math.random() * 1000),
        leave_type: form.leaveType,
        start_date: form.startDate,
        end_date: form.endDate,
        reason: form.reason.trim(),
        status: 'pending',
        created_at: new Date().toISOString()
      })

      // Reset form and close
      setForm({
        leaveType: 'casual',
        startDate: '',
        endDate: '',
        reason: '',
      })
      onClose()
    } catch (err) {
      setError(err?.response?.data?.message || 'Unable to submit leave request. Please try again.')
    } finally {
      setSubmitting(false)
    }
  }

  const selectedBalance = leaveBalances[form.leaveType]

  return (
    <BaseModal
      open={open}
      onClose={onClose}
      title="Apply for Leave"
      size="md"
      footer={
        <>
          <BaseButton variant="secondary" className="w-full sm:w-auto" onClick={onClose} disabled={submitting}>
            Cancel
          </BaseButton>
          <BaseButton variant="primary" className="w-full sm:w-auto" onClick={submit} loading={submitting}>
            Submit Request
          </BaseButton>
        </>
      }
    >
      <form className="space-y-5" onSubmit={submit}>
        {error && (
          <div className="rounded-xl border border-rose-200 bg-rose-50 px-3 py-2 text-sm text-rose-700">
            {error}
          </div>
        )}

        {/* Leave Balances Header Cards */}
        <div>
          <label className="block text-sm font-semibold text-slate-700 mb-2">
            Leave Balances
          </label>
          <div className="grid grid-cols-2 sm:grid-cols-4 gap-2.5">
            {Object.entries(leaveBalances).map(([key, balance]) => {
              const isActive = form.leaveType === key
              return (
                <button
                  type="button"
                  key={key}
                  onClick={() => setForm((prev) => ({ ...prev, leaveType: key }))}
                  className={`flex flex-col items-center justify-center p-3 rounded-xl border text-center transition-all ${
                    isActive
                      ? 'border-brand-500 bg-brand-500/5 ring-1 ring-brand-500'
                      : 'border-slate-200 bg-slate-50/50 hover:bg-slate-50 hover:border-slate-300'
                  }`}
                >
                  <span className="text-[10px] uppercase font-bold text-slate-400 mb-1 tracking-wider">
                    {balance.label.split(' ')[0]}
                  </span>
                  <span className="text-xl font-extrabold text-slate-800">
                    {balance.remaining}
                  </span>
                  <span className="text-[9px] font-medium text-slate-400 mt-0.5">
                    of {balance.total} days
                  </span>
                </button>
              )
            })}
          </div>
        </div>

        {/* Leave Type Select */}
        <BaseSelect
          label="Leave Type"
          value={form.leaveType}
          onChange={(e) => setForm((prev) => ({ ...prev, leaveType: e.target.value }))}
          options={[
            { value: 'casual', label: 'Casual Leave' },
            { value: 'sick', label: 'Sick Leave' },
            { value: 'privilege', label: 'Privilege / Earned Leave' },
            { value: 'unpaid', label: 'Unpaid Leave' },
          ]}
        />

        {/* Date Inputs */}
        <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <BaseInput
            label="Start Date"
            type="date"
            modelValue={form.startDate}
            onUpdateModelValue={(v) => setForm((prev) => ({ ...prev, startDate: v }))}
            error={validationErrors.startDate}
            required
          />
          <BaseInput
            label="End Date"
            type="date"
            modelValue={form.endDate}
            onUpdateModelValue={(v) => setForm((prev) => ({ ...prev, endDate: v }))}
            error={validationErrors.endDate}
            required
          />
        </div>

        {/* Selected Balance Info Tip */}
        <div className="rounded-xl border border-slate-100 bg-slate-50/50 p-3.5 text-xs text-slate-500 flex items-center justify-between">
          <div>
            <span className="font-semibold text-slate-700">Type details:</span> You have{' '}
            <strong className="text-slate-800 font-bold">{selectedBalance.used}</strong> days used and{' '}
            <strong className="text-slate-800 font-bold">{selectedBalance.remaining}</strong> days left.
          </div>
        </div>

        {/* Reason Textarea */}
        <div className="w-full">
          <label className="block text-sm font-medium text-slate-700 mb-1.5">
            Reason for Leave <span className="text-rose-500 ml-0.5">*</span>
          </label>
          <textarea
            value={form.reason}
            rows="3"
            onChange={(e) => setForm((prev) => ({ ...prev, reason: e.target.value }))}
            className={`block w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-900 placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:border-transparent transition shadow-sm ${
              validationErrors.reason ? 'border-rose-500 ring-rose-500' : ''
            }`}
            placeholder="Please detail why you are applying for leave (min 10 characters)..."
          />
          {validationErrors.reason && (
            <p className="mt-1.5 text-sm text-rose-500 font-medium">{validationErrors.reason}</p>
          )}
        </div>
      </form>
    </BaseModal>
  )
}
