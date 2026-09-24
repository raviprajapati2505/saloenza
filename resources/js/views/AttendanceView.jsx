import React, { useCallback, useEffect, useState } from 'react'
import { format, subDays } from 'date-fns'
import { Check, LogIn, LogOut, Plus, RefreshCw, X } from 'lucide-react'
import PageHeader from '../components/ui/PageHeader.jsx'
import BaseButton from '../components/ui/BaseButton.jsx'
import BaseInput from '../components/ui/BaseInput.jsx'
import BaseSelect from '../components/ui/BaseSelect.jsx'
import BaseBadge from '../components/ui/BaseBadge.jsx'
import { useAuthStore } from '../stores/auth'
import { TENANT_PERMISSIONS } from '../lib/tenantPermissions.js'
import { canMutate, subscriptionPageSubtitle } from '../lib/subscriptionModules.js'
import { pushToast } from '../stores/toast.js'
import {
  decideLeave,
  fetchAttendancePunches,
  fetchLeaveRequests,
  punchAttendance,
  requestLeave,
} from '../services/attendanceService.js'

const LEAVE_STATUS = {
  pending: 'warning',
  approved: 'success',
  rejected: 'danger',
}

export default function AttendanceView() {
  const auth = useAuthStore()
  const canPunch = canMutate(auth, TENANT_PERMISSIONS.ATTENDANCE_PUNCH)
  const canManage = canMutate(auth, TENANT_PERMISSIONS.ATTENDANCE_MANAGE)
  const canApproveLeave = canMutate(auth, TENANT_PERMISSIONS.LEAVE_APPROVE)

  const [loading, setLoading] = useState(true)
  const [error, setError] = useState('')
  const [punches, setPunches] = useState([])
  const [leaves, setLeaves] = useState([])
  const [saving, setSaving] = useState(false)
  const [from, setFrom] = useState(format(subDays(new Date(), 7), 'yyyy-MM-dd'))
  const [to, setTo] = useState(format(new Date(), 'yyyy-MM-dd'))
  const [leaveForm, setLeaveForm] = useState({
    from_date: format(new Date(), 'yyyy-MM-dd'),
    to_date: format(new Date(), 'yyyy-MM-dd'),
    type: 'annual',
    reason: '',
  })

  const load = useCallback(async () => {
    setLoading(true)
    setError('')
    try {
      const [punchRows, leaveRows] = await Promise.all([
        fetchAttendancePunches({ from, to }),
        fetchLeaveRequests(),
      ])
      setPunches(punchRows)
      setLeaves(leaveRows)
    } catch (err) {
      setError(err?.response?.data?.message || 'Unable to load attendance.')
    } finally {
      setLoading(false)
    }
  }, [from, to])

  useEffect(() => {
    void load()
  }, [load])

  const handlePunch = async (type) => {
    setSaving(true)
    try {
      await punchAttendance({ type })
      pushToast(type === 'in' ? 'Punched in.' : 'Punched out.', 'success')
      await load()
    } catch (err) {
      pushToast(err?.response?.data?.message || 'Punch failed.', 'error')
    } finally {
      setSaving(false)
    }
  }

  const handleRequestLeave = async () => {
    setSaving(true)
    try {
      await requestLeave({
        from_date: leaveForm.from_date,
        to_date: leaveForm.to_date,
        type: leaveForm.type,
        reason: leaveForm.reason || undefined,
      })
      pushToast('Leave requested.', 'success')
      setLeaveForm((f) => ({ ...f, reason: '' }))
      await load()
    } catch (err) {
      pushToast(err?.response?.data?.message || 'Unable to request leave.', 'error')
    } finally {
      setSaving(false)
    }
  }

  const handleDecide = async (id, status) => {
    setSaving(true)
    try {
      await decideLeave(id, { status })
      pushToast(status === 'approved' ? 'Leave approved.' : 'Leave rejected.', 'success')
      await load()
    } catch (err) {
      pushToast(err?.response?.data?.message || 'Decision failed.', 'error')
    } finally {
      setSaving(false)
    }
  }

  return (
    <div className="space-y-6">
      <PageHeader
        title="Attendance"
        subtitle={subscriptionPageSubtitle(auth, 'Punch in/out, leave requests, and punch history.')}
        actions={(
          <BaseButton variant="secondary" size="sm" leftIcon={RefreshCw} loading={loading} onClick={() => void load()}>
            Refresh
          </BaseButton>
        )}
      />

      {error ? <div className="rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-700">{error}</div> : null}

      {canPunch ? (
        <div className="flex flex-wrap gap-3 rounded-[18px] border border-slate-200 bg-white p-5 shadow-[0_8px_30px_rgb(0,0,0,0.04)]">
          <BaseButton leftIcon={LogIn} loading={saving} onClick={() => void handlePunch('in')}>Punch in</BaseButton>
          <BaseButton variant="secondary" leftIcon={LogOut} loading={saving} onClick={() => void handlePunch('out')}>Punch out</BaseButton>
        </div>
      ) : null}

      <div className="rounded-[18px] border border-slate-200 bg-white p-5 shadow-[0_8px_30px_rgb(0,0,0,0.04)]">
        <h3 className="text-base font-semibold text-slate-900">Request leave</h3>
        <div className="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
          <BaseInput label="From" type="date" value={leaveForm.from_date} onChange={(e) => setLeaveForm((f) => ({ ...f, from_date: e.target.value }))} />
          <BaseInput label="To" type="date" value={leaveForm.to_date} onChange={(e) => setLeaveForm((f) => ({ ...f, to_date: e.target.value }))} />
          <BaseSelect
            label="Type"
            value={leaveForm.type}
            onChange={(e) => setLeaveForm((f) => ({ ...f, type: e.target.value }))}
            options={[
              { value: 'annual', label: 'Annual' },
              { value: 'sick', label: 'Sick' },
              { value: 'unpaid', label: 'Unpaid' },
              { value: 'other', label: 'Other' },
            ]}
          />
          <BaseInput label="Reason" value={leaveForm.reason} onChange={(e) => setLeaveForm((f) => ({ ...f, reason: e.target.value }))} />
          <div className="flex items-end">
            <BaseButton leftIcon={Plus} loading={saving} onClick={() => void handleRequestLeave()}>Request</BaseButton>
          </div>
        </div>
      </div>

      <div className="overflow-hidden rounded-[18px] border border-slate-200 bg-white shadow-[0_8px_30px_rgb(0,0,0,0.04)]">
        <div className="border-b border-slate-100 px-5 py-4">
          <h3 className="text-base font-semibold text-slate-900">Leave requests</h3>
        </div>
        {leaves.length === 0 ? <p className="px-5 py-8 text-center text-sm text-slate-500">No leave requests.</p> : null}
        {leaves.length > 0 ? (
          <ul className="divide-y divide-slate-100">
            {leaves.map((row) => (
              <li key={row.id} className="flex flex-wrap items-center justify-between gap-3 px-5 py-4">
                <div>
                  <p className="font-medium text-slate-900">{row.user?.name || row.staff?.name || 'Staff'}</p>
                  <p className="text-xs text-slate-500">
                    {row.from_date} → {row.to_date} · {row.type}
                    {row.reason ? ` · ${row.reason}` : ''}
                  </p>
                </div>
                <div className="flex items-center gap-2">
                  <BaseBadge size="sm" variant={LEAVE_STATUS[row.status] || 'default'}>{row.status}</BaseBadge>
                  {(canApproveLeave || canManage) && row.status === 'pending' ? (
                    <>
                      <BaseButton size="sm" leftIcon={Check} loading={saving} onClick={() => void handleDecide(row.id, 'approved')}>Approve</BaseButton>
                      <BaseButton size="sm" variant="secondary" leftIcon={X} loading={saving} onClick={() => void handleDecide(row.id, 'rejected')}>Reject</BaseButton>
                    </>
                  ) : null}
                </div>
              </li>
            ))}
          </ul>
        ) : null}
      </div>

      <div className="overflow-hidden rounded-[18px] border border-slate-200 bg-white shadow-[0_8px_30px_rgb(0,0,0,0.04)]">
        <div className="flex flex-wrap items-end justify-between gap-3 border-b border-slate-100 px-5 py-4">
          <h3 className="text-base font-semibold text-slate-900">Punches</h3>
          <div className="flex flex-wrap gap-2">
            <BaseInput label="From" type="date" value={from} onChange={(e) => setFrom(e.target.value)} />
            <BaseInput label="To" type="date" value={to} onChange={(e) => setTo(e.target.value)} />
          </div>
        </div>
        {loading ? <div className="p-5 text-sm text-slate-500">Loading…</div> : null}
        {!loading && punches.length === 0 ? <p className="px-5 py-8 text-center text-sm text-slate-500">No punches in this range.</p> : null}
        {!loading && punches.length > 0 ? (
          <table className="min-w-full text-sm">
            <thead className="bg-slate-50 text-left text-xs uppercase tracking-wider text-slate-500">
              <tr>
                <th className="px-5 py-3">Staff</th>
                <th className="px-5 py-3">Type</th>
                <th className="px-5 py-3">Time</th>
                <th className="px-5 py-3">Method</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-slate-100">
              {punches.map((row) => (
                <tr key={row.id}>
                  <td className="px-5 py-3 font-medium text-slate-900">{row.user?.name || row.staff?.name || row.user_id}</td>
                  <td className="px-5 py-3">
                    <BaseBadge size="sm" variant={row.type === 'in' ? 'success' : 'info'}>{row.type}</BaseBadge>
                  </td>
                  <td className="px-5 py-3">{row.punched_at || row.created_at || '—'}</td>
                  <td className="px-5 py-3">{row.method || 'manual'}</td>
                </tr>
              ))}
            </tbody>
          </table>
        ) : null}
      </div>
    </div>
  )
}
