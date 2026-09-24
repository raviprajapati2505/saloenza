import React, { useCallback, useEffect, useState } from 'react'
import { RefreshCw, Save } from 'lucide-react'
import PageHeader from '../components/ui/PageHeader.jsx'
import BaseButton from '../components/ui/BaseButton.jsx'
import BaseInput from '../components/ui/BaseInput.jsx'
import BaseSelect from '../components/ui/BaseSelect.jsx'
import { useAuthStore } from '../stores/auth'
import { TENANT_PERMISSIONS } from '../lib/tenantPermissions.js'
import { canMutate, subscriptionPageSubtitle } from '../lib/subscriptionModules.js'
import { pushToast } from '../stores/toast.js'
import { fetchNoShowPolicy, updateNoShowPolicy } from '../services/noShowPolicyService.js'

const EMPTY = {
  is_enabled: false,
  apply_mode: 'repeat_offenders',
  min_no_shows: '2',
  window_days: '90',
  protection_type: 'deposit',
  deposit_type: 'percent',
  deposit_value: '20',
  fee_type: 'percent',
  fee_value: '100',
  cancel_cutoff_hours: '24',
  currency: '',
}

export default function NoShowPolicyView() {
  const auth = useAuthStore()
  const canManage = canMutate(auth, TENANT_PERMISSIONS.PAYMENTS_POLICY_MANAGE)

  const [loading, setLoading] = useState(true)
  const [error, setError] = useState('')
  const [form, setForm] = useState(EMPTY)
  const [saving, setSaving] = useState(false)

  const load = useCallback(async () => {
    setLoading(true)
    setError('')
    try {
      const policy = await fetchNoShowPolicy()
      if (policy) {
        setForm({
          is_enabled: Boolean(policy.is_enabled),
          apply_mode: policy.apply_mode || 'repeat_offenders',
          min_no_shows: String(policy.min_no_shows ?? 2),
          window_days: String(policy.window_days ?? 90),
          protection_type: policy.protection_type || 'deposit',
          deposit_type: policy.deposit_type || 'percent',
          deposit_value: String(policy.deposit_value ?? 0),
          fee_type: policy.fee_type || 'percent',
          fee_value: String(policy.fee_value ?? 100),
          cancel_cutoff_hours: String(policy.cancel_cutoff_hours ?? 24),
          currency: policy.currency || '',
        })
      }
    } catch (err) {
      setError(err?.response?.data?.message || 'Unable to load no-show policy.')
    } finally {
      setLoading(false)
    }
  }, [])

  useEffect(() => {
    void load()
  }, [load])

  const setField = (key, value) => setForm((current) => ({ ...current, [key]: value }))

  const handleSave = async () => {
    setSaving(true)
    try {
      await updateNoShowPolicy({
        is_enabled: Boolean(form.is_enabled),
        apply_mode: form.apply_mode,
        min_no_shows: Number(form.min_no_shows),
        window_days: Number(form.window_days),
        protection_type: form.protection_type,
        deposit_type: form.deposit_type,
        deposit_value: Number(form.deposit_value),
        fee_type: form.fee_type,
        fee_value: Number(form.fee_value),
        cancel_cutoff_hours: Number(form.cancel_cutoff_hours),
        currency: form.currency || undefined,
      })
      pushToast('No-show policy saved.', 'success')
      await load()
    } catch (err) {
      pushToast(err?.response?.data?.message || 'Unable to save policy.', 'error')
    } finally {
      setSaving(false)
    }
  }

  return (
    <div className="space-y-6">
      <PageHeader
        title="No-show policy"
        subtitle={subscriptionPageSubtitle(auth, 'Require deposits and charge fees for missed appointments.')}
        actions={(
          <BaseButton variant="secondary" size="sm" leftIcon={RefreshCw} loading={loading} onClick={() => void load()}>
            Refresh
          </BaseButton>
        )}
      />

      {error ? <div className="rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-700">{error}</div> : null}

      <div className="rounded-[18px] border border-slate-200 bg-white p-5 shadow-[0_8px_30px_rgb(0,0,0,0.04)]">
        {loading ? <div className="h-40 animate-pulse rounded-xl bg-slate-50" /> : (
          <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <BaseSelect
              label="Enabled"
              value={form.is_enabled ? '1' : '0'}
              onChange={(e) => setField('is_enabled', e.target.value === '1')}
              disabled={!canManage}
              options={[{ value: '1', label: 'Yes' }, { value: '0', label: 'No' }]}
            />
            <BaseSelect
              label="Who needs a deposit"
              value={form.apply_mode}
              onChange={(e) => setField('apply_mode', e.target.value)}
              disabled={!canManage}
              options={[
                { value: 'repeat_offenders', label: 'Repeat no-shows (threshold)' },
                { value: 'all_clients', label: 'All clients' },
                { value: 'high_risk', label: 'Flagged high-risk clients only' },
                { value: 'tagged_only', label: 'Clients tagged “no-show”' },
              ]}
            />
            <BaseInput label="Min no-shows" type="number" value={form.min_no_shows} disabled={!canManage || form.apply_mode !== 'repeat_offenders'} onChange={(e) => setField('min_no_shows', e.target.value)} />
            <BaseInput label="Lookback window (days)" type="number" value={form.window_days} disabled={!canManage || form.apply_mode !== 'repeat_offenders'} onChange={(e) => setField('window_days', e.target.value)} />
            <BaseSelect
              label="Protection type"
              value={form.protection_type}
              onChange={(e) => setField('protection_type', e.target.value)}
              disabled={!canManage}
              options={[
                { value: 'deposit', label: 'Deposit before visit' },
                { value: 'fee', label: 'Fee after no-show' },
                { value: 'both', label: 'Deposit + no-show fee' },
              ]}
            />
            <BaseInput label="Free cancel cutoff (hours)" type="number" value={form.cancel_cutoff_hours} disabled={!canManage} onChange={(e) => setField('cancel_cutoff_hours', e.target.value)} />
            <BaseSelect
              label="Deposit type"
              value={form.deposit_type}
              onChange={(e) => setField('deposit_type', e.target.value)}
              disabled={!canManage}
              options={[
                { value: 'percent', label: 'Percent of services' },
                { value: 'fixed', label: 'Fixed amount' },
              ]}
            />
            <BaseInput label="Deposit value" type="number" value={form.deposit_value} disabled={!canManage} onChange={(e) => setField('deposit_value', e.target.value)} />
            <BaseSelect
              label="No-show fee type"
              value={form.fee_type}
              onChange={(e) => setField('fee_type', e.target.value)}
              disabled={!canManage}
              options={[
                { value: 'percent', label: 'Percent of services' },
                { value: 'fixed', label: 'Fixed amount' },
              ]}
            />
            <BaseInput label="No-show fee value" type="number" value={form.fee_value} disabled={!canManage} onChange={(e) => setField('fee_value', e.target.value)} />
            <BaseInput label="Currency (optional)" value={form.currency} disabled={!canManage} onChange={(e) => setField('currency', e.target.value)} />
          </div>
        )}

        {canManage ? (
          <div className="mt-5">
            <BaseButton leftIcon={Save} loading={saving} onClick={() => void handleSave()}>Save policy</BaseButton>
          </div>
        ) : null}
      </div>

      <div className="rounded-[18px] border border-slate-200 bg-slate-50 p-5 text-sm text-slate-600 space-y-2">
        <h3 className="font-semibold text-slate-900">How staff use this</h3>
        <ol className="list-decimal space-y-1.5 pl-5">
          <li>Enable the policy and save. New bookings for matching clients get a <strong>deposit due</strong>.</li>
          <li>Open the appointment from <strong>Appointments</strong> or <strong>POS</strong> and use <strong>Mark deposit paid</strong> or <strong>Waive deposit</strong>.</li>
          <li>When you mark a visit as <strong>No-show</strong>, the fee is calculated automatically.</li>
          <li>On that same appointment, choose <strong>Charge no-show fee</strong> or <strong>Waive fee</strong>.</li>
        </ol>
      </div>
    </div>
  )
}
