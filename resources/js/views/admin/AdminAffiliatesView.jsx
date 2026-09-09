import React, { useCallback, useEffect, useState } from 'react'
import { useSearchParams } from 'react-router-dom'
import { Copy, Loader2, Plus, RefreshCw, X } from 'lucide-react'
import PageHeader from '../../components/ui/PageHeader.jsx'
import BaseButton from '../../components/ui/BaseButton.jsx'
import BaseInput from '../../components/ui/BaseInput.jsx'
import { useAuthStore } from '../../stores/auth'
import { PLATFORM_PERMISSIONS } from '../../lib/platformPermissions.js'
import { copyTextToClipboard } from '../../lib/clipboard.js'
import { pushToast } from '../../stores/toast.js'
import {
  approveAdminAffiliate,
  approveAdminAffiliateWithdrawal,
  createAdminAffiliate,
  fetchAdminAffiliates,
  fetchAdminAffiliateWithdrawals,
  markAdminAffiliateWithdrawalPaid,
  rejectAdminAffiliate,
  rejectAdminAffiliateWithdrawal,
  updateAdminAffiliate,
} from '../../services/affiliatePortalService.js'

const emptyForm = {
  name: '',
  email: '',
  phone: '',
  password: '',
  password_confirmation: '',
  display_name: '',
  code: '',
  onboarding_commission_rate: '12',
  renewal_commission_rate: '5',
  commission_lock_days: '30',
  payout_method: 'bank_transfer',
  notes: '',
  status: 'active',
}

function CreateAffiliateModal({ onClose, onCreated }) {
  const [form, setForm] = useState(emptyForm)
  const [saving, setSaving] = useState(false)

  const update = (key, value) => setForm((prev) => ({ ...prev, [key]: value }))

  const submit = async (event) => {
    event.preventDefault()
    setSaving(true)
    try {
      await createAdminAffiliate({
        name: form.name.trim(),
        email: form.email.trim().toLowerCase(),
        phone: form.phone.trim(),
        password: form.password,
        password_confirmation: form.password_confirmation,
        display_name: form.display_name.trim() || form.name.trim(),
        code: form.code.trim() || null,
        onboarding_commission_rate: Number(form.onboarding_commission_rate),
        renewal_commission_rate: Number(form.renewal_commission_rate),
        commission_lock_days: Number(form.commission_lock_days),
        payout_method: form.payout_method || null,
        notes: form.notes || null,
        status: form.status,
      })
      pushToast('Affiliate partner created.', 'success')
      onCreated()
      onClose()
    } catch (error) {
      pushToast(error?.response?.data?.message || 'Failed to create affiliate.', 'error')
    } finally {
      setSaving(false)
    }
  }

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4">
      <form onSubmit={submit} className="max-h-[90vh] w-full max-w-2xl overflow-y-auto rounded-2xl bg-white p-6 shadow-xl">
        <div className="mb-4 flex items-start justify-between gap-3">
          <div>
            <h3 className="text-lg font-semibold text-slate-900">Create affiliate partner</h3>
            <p className="text-sm text-slate-500">Set commission rates and lock period during onboarding.</p>
          </div>
          <button type="button" onClick={onClose} className="rounded-lg p-2 text-slate-400 hover:bg-slate-100">
            <X className="h-5 w-5" />
          </button>
        </div>

        <div className="grid gap-4 md:grid-cols-2">
          <BaseInput label="Full name" modelValue={form.name} onUpdateModelValue={(v) => update('name', v)} required placeholder="Enter full name" />
          <BaseInput label="Display name" modelValue={form.display_name} onUpdateModelValue={(v) => update('display_name', v)} placeholder="Optional display name" />
          <BaseInput label="Email" type="email" modelValue={form.email} onUpdateModelValue={(v) => update('email', v)} required placeholder="name@example.com" />
          <BaseInput label="Phone" modelValue={form.phone} onUpdateModelValue={(v) => update('phone', v)} required placeholder="+91 98765 43210" />
          <BaseInput label="Password" type="password" modelValue={form.password} onUpdateModelValue={(v) => update('password', v)} required toggleableType placeholder="Create a password" />
          <BaseInput label="Confirm password" type="password" modelValue={form.password_confirmation} onUpdateModelValue={(v) => update('password_confirmation', v)} required toggleableType placeholder="Re-enter password" />
          <BaseInput label="Referral code (optional)" modelValue={form.code} onUpdateModelValue={(v) => update('code', String(v).toUpperCase())} placeholder="e.g. AFF-XXXX" />
          <BaseInput label="Payout method" modelValue={form.payout_method} onUpdateModelValue={(v) => update('payout_method', v)} placeholder="e.g. Bank transfer" />
          <BaseInput label="Onboarding commission %" type="number" min="0" max="100" step="0.01" modelValue={form.onboarding_commission_rate} onUpdateModelValue={(v) => update('onboarding_commission_rate', v)} />
          <BaseInput label="Renewal commission %" type="number" min="0" max="100" step="0.01" modelValue={form.renewal_commission_rate} onUpdateModelValue={(v) => update('renewal_commission_rate', v)} />
          <BaseInput label="Commission lock days" type="number" min="0" max="365" modelValue={form.commission_lock_days} onUpdateModelValue={(v) => update('commission_lock_days', v)} />
          <div>
            <label className="mb-1.5 block text-sm font-medium text-slate-700">Status</label>
            <select
              value={form.status}
              onChange={(e) => update('status', e.target.value)}
              className="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20"
            >
              <option value="active">Active</option>
              <option value="pending">Pending</option>
              <option value="suspended">Suspended</option>
              <option value="rejected">Rejected</option>
            </select>
          </div>
          <div className="md:col-span-2">
            <label className="mb-1.5 block text-sm font-medium text-slate-700">Notes</label>
            <textarea
              value={form.notes}
              onChange={(e) => update('notes', e.target.value)}
              rows={3}
              className="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20"
            />
          </div>
        </div>

        <div className="mt-6 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end sm:gap-3">
          <BaseButton type="button" variant="secondary" className="w-full sm:w-auto" onClick={onClose}>Cancel</BaseButton>
          <BaseButton type="submit" className="w-full sm:w-auto" loading={saving} leftIcon={Plus}>Create partner</BaseButton>
        </div>
      </form>
    </div>
  )
}

function EditAffiliateModal({ partner, onClose, onUpdated }) {
  const [form, setForm] = useState({
    name: partner.user?.name || '',
    email: partner.user?.email || '',
    phone: partner.user?.phone || '',
    display_name: partner.display_name || '',
    code: partner.code || '',
    onboarding_commission_rate: String(partner.onboarding_commission_rate ?? 12),
    renewal_commission_rate: String(partner.renewal_commission_rate ?? 5),
    commission_lock_days: String(partner.commission_lock_days ?? 30),
    payout_method: partner.payout_method || '',
    notes: partner.notes || '',
    status: partner.status || 'active',
  })
  const [saving, setSaving] = useState(false)

  const update = (key, value) => setForm((prev) => ({ ...prev, [key]: value }))

  const submit = async (event) => {
    event.preventDefault()
    setSaving(true)
    try {
      await updateAdminAffiliate(partner.id, {
        name: form.name.trim(),
        email: form.email.trim().toLowerCase(),
        phone: form.phone.trim(),
        display_name: form.display_name.trim(),
        code: form.code.trim(),
        onboarding_commission_rate: Number(form.onboarding_commission_rate),
        renewal_commission_rate: Number(form.renewal_commission_rate),
        commission_lock_days: Number(form.commission_lock_days),
        payout_method: form.payout_method || null,
        notes: form.notes || null,
        status: form.status,
      })
      pushToast('Affiliate partner updated.', 'success')
      onUpdated()
      onClose()
    } catch (error) {
      pushToast(error?.response?.data?.message || 'Failed to update affiliate.', 'error')
    } finally {
      setSaving(false)
    }
  }

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4">
      <form onSubmit={submit} className="max-h-[90vh] w-full max-w-2xl overflow-y-auto rounded-2xl bg-white p-6 shadow-xl">
        <div className="mb-4 flex items-start justify-between gap-3">
          <div>
            <h3 className="text-lg font-semibold text-slate-900">Edit affiliate partner</h3>
            <p className="text-sm text-slate-500">{partner.code}</p>
          </div>
          <button type="button" onClick={onClose} className="rounded-lg p-2 text-slate-400 hover:bg-slate-100">
            <X className="h-5 w-5" />
          </button>
        </div>

        <div className="grid gap-4 md:grid-cols-2">
          <BaseInput label="Full name" modelValue={form.name} onUpdateModelValue={(v) => update('name', v)} placeholder="Enter full name" />
          <BaseInput label="Display name" modelValue={form.display_name} onUpdateModelValue={(v) => update('display_name', v)} placeholder="Optional display name" />
          <BaseInput label="Email" type="email" modelValue={form.email} onUpdateModelValue={(v) => update('email', v)} placeholder="name@example.com" />
          <BaseInput label="Phone" modelValue={form.phone} onUpdateModelValue={(v) => update('phone', v)} placeholder="+91 98765 43210" />
          <BaseInput label="Referral code" modelValue={form.code} onUpdateModelValue={(v) => update('code', String(v).toUpperCase())} placeholder="e.g. AFF-XXXX" />
          <BaseInput label="Payout method" modelValue={form.payout_method} onUpdateModelValue={(v) => update('payout_method', v)} placeholder="e.g. Bank transfer" />
          <BaseInput label="Onboarding commission %" type="number" modelValue={form.onboarding_commission_rate} onUpdateModelValue={(v) => update('onboarding_commission_rate', v)} />
          <BaseInput label="Renewal commission %" type="number" modelValue={form.renewal_commission_rate} onUpdateModelValue={(v) => update('renewal_commission_rate', v)} />
          <BaseInput label="Commission lock days" type="number" modelValue={form.commission_lock_days} onUpdateModelValue={(v) => update('commission_lock_days', v)} />
          <div>
            <label className="mb-1.5 block text-sm font-medium text-slate-700">Status</label>
            <select
              value={form.status}
              onChange={(e) => update('status', e.target.value)}
              className="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20"
            >
              <option value="active">Active</option>
              <option value="pending">Pending</option>
              <option value="suspended">Suspended</option>
              <option value="rejected">Rejected</option>
            </select>
          </div>
        </div>

        <div className="mt-6 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end sm:gap-3">
          <BaseButton type="button" variant="secondary" className="w-full sm:w-auto" onClick={onClose}>Cancel</BaseButton>
          <BaseButton type="submit" className="w-full sm:w-auto" loading={saving}>Save changes</BaseButton>
        </div>
      </form>
    </div>
  )
}

export default function AdminAffiliatesView() {
  const auth = useAuthStore()
  const [searchParams, setSearchParams] = useSearchParams()
  const canCreate = auth.canPlatform(PLATFORM_PERMISSIONS.AFFILIATES_CREATE)
  const canUpdate = auth.canPlatform(PLATFORM_PERMISSIONS.AFFILIATES_UPDATE)

  const initialTab = searchParams.get('tab') === 'withdrawals' ? 'withdrawals' : 'partners'
  const initialStatus = searchParams.get('status') || 'all'

  const [tab, setTab] = useState(initialTab)
  const [rows, setRows] = useState([])
  const [withdrawals, setWithdrawals] = useState([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState('')
  const [showCreate, setShowCreate] = useState(false)
  const [editing, setEditing] = useState(null)
  const [statusFilter, setStatusFilter] = useState(initialStatus)
  const [actionId, setActionId] = useState('')

  useEffect(() => {
    const nextTab = searchParams.get('tab') === 'withdrawals' ? 'withdrawals' : 'partners'
    const nextStatus = searchParams.get('status') || 'all'
    setTab(nextTab)
    setStatusFilter(nextStatus)
  }, [searchParams])

  const syncQuery = (nextTab, nextStatus) => {
    const params = new URLSearchParams()
    if (nextTab && nextTab !== 'partners') params.set('tab', nextTab)
    if (nextStatus && nextStatus !== 'all') params.set('status', nextStatus)
    setSearchParams(params, { replace: true })
  }

  const changeTab = (nextTab) => {
    setTab(nextTab)
    if (nextTab === 'withdrawals') {
      const withdrawalStatuses = ['pending', 'approved', 'rejected', 'paid']
      const nextStatus = withdrawalStatuses.includes(statusFilter) ? statusFilter : 'pending'
      setStatusFilter(nextStatus)
      syncQuery(nextTab, nextStatus)
      return
    }
    const partnerStatuses = ['pending', 'active', 'suspended', 'rejected']
    const nextStatus = partnerStatuses.includes(statusFilter) ? statusFilter : 'all'
    setStatusFilter(nextStatus)
    syncQuery(nextTab, nextStatus)
  }

  const changeStatusFilter = (nextStatus) => {
    setStatusFilter(nextStatus)
    syncQuery(tab, nextStatus)
  }

  const loadPartners = useCallback(async () => {
    const params = {}
    if (statusFilter !== 'all') params.status = statusFilter
    const payload = await fetchAdminAffiliates(params)
    setRows(payload.affiliate_partners || [])
  }, [statusFilter])

  const loadWithdrawals = useCallback(async () => {
    const params = {}
    if (statusFilter !== 'all') params.status = statusFilter
    const payload = await fetchAdminAffiliateWithdrawals(params)
    setWithdrawals(payload.withdrawals || [])
  }, [statusFilter])

  const load = useCallback(async () => {
    setLoading(true)
    setError('')
    try {
      await Promise.all([loadPartners(), loadWithdrawals()])
    } catch (err) {
      setError(err?.response?.data?.message || 'Unable to load affiliate data.')
    } finally {
      setLoading(false)
    }
  }, [loadPartners, loadWithdrawals])

  useEffect(() => {
    void load()
  }, [load])

  const copyLink = async (url) => {
    if (!url) {
      pushToast('Signup link is not available yet.', 'error')
      return
    }

    const copied = await copyTextToClipboard(url)
    pushToast(copied ? 'Signup link copied.' : 'Unable to copy link.', copied ? 'success' : 'error')
  }

  const handleApprove = async (row) => {
    setActionId(`approve-${row.id}`)
    try {
      await approveAdminAffiliate(row.id)
      pushToast('Affiliate approved.', 'success')
      await loadPartners()
    } catch (error) {
      pushToast(error?.response?.data?.message || 'Unable to approve affiliate.', 'error')
    } finally {
      setActionId('')
    }
  }

  const handleReject = async (row) => {
    const reason = window.prompt('Rejection reason (optional):', 'Application rejected by admin.') || 'Application rejected by admin.'
    setActionId(`reject-${row.id}`)
    try {
      await rejectAdminAffiliate(row.id, { reason })
      pushToast('Affiliate rejected.', 'success')
      await loadPartners()
    } catch (error) {
      pushToast(error?.response?.data?.message || 'Unable to reject affiliate.', 'error')
    } finally {
      setActionId('')
    }
  }

  const pendingCount = rows.filter((row) => row.status === 'pending').length

  return (
    <div className="space-y-6">
      <PageHeader
        title="Affiliate Partners"
        subtitle="Review public applications, create verified partners, and settle withdrawals."
        actions={(
          <div className="flex w-full flex-col gap-2 sm:w-auto sm:flex-row">
            <BaseButton variant="secondary" className="w-full sm:w-auto" leftIcon={RefreshCw} onClick={() => void load()}>Refresh</BaseButton>
            {canCreate ? (
              <BaseButton className="w-full sm:w-auto" leftIcon={Plus} onClick={() => setShowCreate(true)}>Add affiliate</BaseButton>
            ) : null}
          </div>
        )}
      />

      {error ? <div className="rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-700">{error}</div> : null}

      <div className="flex flex-wrap gap-2">
        <button
          type="button"
          onClick={() => changeTab('partners')}
          className={`rounded-xl px-4 py-2 text-sm font-medium ${tab === 'partners' ? 'bg-slate-900 text-white' : 'bg-slate-100 text-slate-600'}`}
        >
          Partners{pendingCount > 0 ? ` (${pendingCount} pending)` : ''}
        </button>
        <button
          type="button"
          onClick={() => changeTab('withdrawals')}
          className={`rounded-xl px-4 py-2 text-sm font-medium ${tab === 'withdrawals' ? 'bg-slate-900 text-white' : 'bg-slate-100 text-slate-600'}`}
        >
          Withdrawals
        </button>
      </div>

      {tab === 'partners' ? (
        <>
          <div className="flex flex-wrap gap-2">
            {['all', 'pending', 'active', 'suspended', 'rejected'].map((status) => (
              <button
                key={status}
                type="button"
                onClick={() => changeStatusFilter(status)}
                className={`rounded-lg px-3 py-1.5 text-xs font-semibold capitalize ${statusFilter === status ? 'bg-brand-600 text-white' : 'bg-slate-100 text-slate-600'}`}
              >
                {status}
              </button>
            ))}
          </div>
        <div className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
          <table className="min-w-full divide-y divide-slate-200 text-sm">
            <thead className="bg-slate-50">
              <tr>
                <th className="px-4 py-3 text-left font-semibold text-slate-600">Partner</th>
                <th className="px-4 py-3 text-left font-semibold text-slate-600">Code</th>
                <th className="px-4 py-3 text-left font-semibold text-slate-600">Rates</th>
                <th className="px-4 py-3 text-left font-semibold text-slate-600">Lock</th>
                <th className="px-4 py-3 text-left font-semibold text-slate-600">Referrals</th>
                <th className="px-4 py-3 text-left font-semibold text-slate-600">Actions</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-slate-100">
              {rows.map((row) => (
                <tr key={row.id}>
                  <td className="px-4 py-3">
                    <p className="font-medium text-slate-900">{row.display_name || row.user?.name || '-'}</p>
                    <p className="text-xs text-slate-500">{row.user?.email || '-'}</p>
                    <p className="mt-1 text-[11px] uppercase tracking-wide text-slate-400">{row.status}</p>
                  </td>
                  <td className="px-4 py-3 font-medium text-slate-900">{row.code}</td>
                  <td className="px-4 py-3 text-slate-600">
                    Onboard {row.onboarding_commission_rate}% · Renew {row.renewal_commission_rate}%
                  </td>
                  <td className="px-4 py-3 text-slate-600">{row.commission_lock_days} days</td>
                  <td className="px-4 py-3 text-slate-600">{row.referrals_count ?? 0}</td>
                  <td className="px-4 py-3">
                    <div className="flex flex-wrap gap-2">
                      {row.status === 'active' ? (
                        <button
                          type="button"
                          onClick={() => copyLink(row.signup_url)}
                          className="inline-flex items-center gap-1 rounded-lg bg-slate-100 px-2.5 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-200"
                        >
                          <Copy className="h-3.5 w-3.5" /> Copy link
                        </button>
                      ) : null}
                      {canUpdate && (row.status === 'pending' || row.status === 'rejected' || row.status === 'suspended') ? (
                        <button
                          type="button"
                          disabled={actionId === `approve-${row.id}`}
                          onClick={() => void handleApprove(row)}
                          className="rounded-lg bg-emerald-50 px-2.5 py-1.5 text-xs font-medium text-emerald-700 hover:bg-emerald-100 disabled:opacity-50"
                        >
                          Approve
                        </button>
                      ) : null}
                      {canUpdate && (row.status === 'pending' || row.status === 'active') ? (
                        <button
                          type="button"
                          disabled={actionId === `reject-${row.id}`}
                          onClick={() => void handleReject(row)}
                          className="rounded-lg bg-rose-50 px-2.5 py-1.5 text-xs font-medium text-rose-700 hover:bg-rose-100 disabled:opacity-50"
                        >
                          Reject
                        </button>
                      ) : null}
                      {canUpdate ? (
                        <button
                          type="button"
                          onClick={() => setEditing(row)}
                          className="rounded-lg bg-brand-50 px-2.5 py-1.5 text-xs font-medium text-brand-700 hover:bg-brand-100"
                        >
                          Edit
                        </button>
                      ) : null}
                    </div>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
          {loading ? (
            <div className="flex items-center gap-2 p-4 text-sm text-slate-500">
              <Loader2 className="h-4 w-4 animate-spin" /> Loading affiliates...
            </div>
          ) : null}
          {!loading && rows.length === 0 ? <div className="p-4 text-sm text-slate-500">No affiliate partners found.</div> : null}
        </div>
        </>
      ) : (
        <>
          <div className="flex flex-wrap gap-2">
            {['all', 'pending', 'approved', 'rejected', 'paid'].map((status) => (
              <button
                key={status}
                type="button"
                onClick={() => changeStatusFilter(status)}
                className={`rounded-lg px-3 py-1.5 text-xs font-semibold capitalize ${statusFilter === status ? 'bg-brand-600 text-white' : 'bg-slate-100 text-slate-600'}`}
              >
                {status}
              </button>
            ))}
          </div>
        <div className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
          <table className="min-w-full divide-y divide-slate-200 text-sm">
            <thead className="bg-slate-50">
              <tr>
                <th className="px-4 py-3 text-left font-semibold text-slate-600">Partner</th>
                <th className="px-4 py-3 text-left font-semibold text-slate-600">Amount</th>
                <th className="px-4 py-3 text-left font-semibold text-slate-600">Status</th>
                <th className="px-4 py-3 text-left font-semibold text-slate-600">Requested</th>
                <th className="px-4 py-3 text-left font-semibold text-slate-600">Actions</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-slate-100">
              {withdrawals.map((row) => (
                <tr key={row.id}>
                  <td className="px-4 py-3">
                    <p className="font-medium text-slate-900">{row.affiliate_partner?.display_name || row.affiliate_partner?.code || '-'}</p>
                    <p className="text-xs text-slate-500">{row.affiliate_partner?.user?.email || '-'}</p>
                  </td>
                  <td className="px-4 py-3 font-medium text-slate-900">INR {Number(row.amount || 0).toFixed(2)}</td>
                  <td className="px-4 py-3 text-slate-600">{row.status}</td>
                  <td className="px-4 py-3 text-slate-600">{row.requested_at ? new Date(row.requested_at).toLocaleDateString() : '-'}</td>
                  <td className="px-4 py-3">
                    {canUpdate && (row.status === 'pending' || row.status === 'approved') ? (
                      <div className="flex flex-wrap gap-2">
                        {row.status === 'pending' ? (
                          <button
                            type="button"
                            onClick={async () => {
                              try {
                                await approveAdminAffiliateWithdrawal(row.id)
                                pushToast('Withdrawal approved.', 'success')
                                await loadWithdrawals()
                              } catch (err) {
                                pushToast(err?.response?.data?.message || 'Approve failed.', 'error')
                              }
                            }}
                            className="rounded-lg bg-brand-50 px-2.5 py-1.5 text-xs font-medium text-brand-700 hover:bg-brand-100"
                          >
                            Approve
                          </button>
                        ) : null}
                        <button
                          type="button"
                          onClick={async () => {
                            const reference = window.prompt('Payout reference / transaction ID (optional)')
                            try {
                              await markAdminAffiliateWithdrawalPaid(row.id, {
                                payout_reference: reference || null,
                              })
                              pushToast('Marked as paid.', 'success')
                              await loadWithdrawals()
                            } catch (err) {
                              pushToast(err?.response?.data?.message || 'Mark paid failed.', 'error')
                            }
                          }}
                          className="rounded-lg bg-emerald-50 px-2.5 py-1.5 text-xs font-medium text-emerald-700 hover:bg-emerald-100"
                        >
                          Mark paid
                        </button>
                        <button
                          type="button"
                          onClick={async () => {
                            const reason = window.prompt('Rejection reason:', 'Rejected by admin.') || 'Rejected by admin.'
                            try {
                              await rejectAdminAffiliateWithdrawal(row.id, { reason })
                              pushToast('Withdrawal rejected.', 'success')
                              await loadWithdrawals()
                            } catch (err) {
                              pushToast(err?.response?.data?.message || 'Reject failed.', 'error')
                            }
                          }}
                          className="rounded-lg bg-rose-50 px-2.5 py-1.5 text-xs font-medium text-rose-700 hover:bg-rose-100"
                        >
                          Reject
                        </button>
                      </div>
                    ) : (
                      <span className="text-xs text-slate-400">—</span>
                    )}
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
          {loading ? (
            <div className="flex items-center gap-2 p-4 text-sm text-slate-500">
              <Loader2 className="h-4 w-4 animate-spin" /> Loading withdrawals...
            </div>
          ) : null}
          {!loading && withdrawals.length === 0 ? <div className="p-4 text-sm text-slate-500">No withdrawal requests found.</div> : null}
        </div>
        </>
      )}

      {showCreate ? <CreateAffiliateModal onClose={() => setShowCreate(false)} onCreated={() => void load()} /> : null}
      {editing ? <EditAffiliateModal partner={editing} onClose={() => setEditing(null)} onUpdated={() => void load()} /> : null}
    </div>
  )
}
