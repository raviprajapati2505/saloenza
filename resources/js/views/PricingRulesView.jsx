import React, { useCallback, useEffect, useState } from 'react'
import { Plus, RefreshCw, Search } from 'lucide-react'
import PageHeader from '../components/ui/PageHeader.jsx'
import BaseButton from '../components/ui/BaseButton.jsx'
import BaseInput from '../components/ui/BaseInput.jsx'
import BaseSelect from '../components/ui/BaseSelect.jsx'
import BaseBadge from '../components/ui/BaseBadge.jsx'
import { useAuthStore } from '../stores/auth'
import { TENANT_PERMISSIONS } from '../lib/tenantPermissions.js'
import { canMutate, subscriptionPageSubtitle } from '../lib/subscriptionModules.js'
import { useTenantFormatter } from '../hooks/useTenantFormatter.js'
import { fetchMasterList } from '../lib/apiHelpers'
import { pushToast } from '../stores/toast.js'
import {
  createPricingRule,
  fetchPricingRules,
  resolvePricing,
  updatePricingRule,
} from '../services/pricingRulesService.js'

const DAY_OPTIONS = [
  { value: '1', label: 'Mon' },
  { value: '2', label: 'Tue' },
  { value: '3', label: 'Wed' },
  { value: '4', label: 'Thu' },
  { value: '5', label: 'Fri' },
  { value: '6', label: 'Sat' },
  { value: '0', label: 'Sun' },
]

export default function PricingRulesView() {
  const auth = useAuthStore()
  const fmt = useTenantFormatter()
  const canManage = canMutate(auth, TENANT_PERMISSIONS.PRICING_RULES_MANAGE)

  const [loading, setLoading] = useState(true)
  const [error, setError] = useState('')
  const [rules, setRules] = useState([])
  const [services, setServices] = useState([])
  const [saving, setSaving] = useState(false)
  const [resolveResult, setResolveResult] = useState(null)

  const [form, setForm] = useState({
    name: '',
    adjustment_type: 'percent_off',
    adjustment_value: '10',
    time_start: '10:00',
    time_end: '16:00',
    days_of_week: ['1', '2', '3', '4', '5'],
    priority: '10',
  })
  const [resolveForm, setResolveForm] = useState({
    service_id: '',
    datetime: '',
    list_price: '',
  })
  const [editingId, setEditingId] = useState(null)

  const load = useCallback(async () => {
    setLoading(true)
    setError('')
    try {
      const [ruleRows, serviceRows] = await Promise.all([
        fetchPricingRules(),
        fetchMasterList('/v1/services', 'services').catch(() => []),
      ])
      setRules(ruleRows)
      setServices(serviceRows || [])
    } catch (err) {
      setError(err?.response?.data?.message || 'Unable to load pricing rules.')
    } finally {
      setLoading(false)
    }
  }, [])

  useEffect(() => {
    void load()
  }, [load])

  const toggleDay = (day) => {
    setForm((current) => ({
      ...current,
      days_of_week: current.days_of_week.includes(day)
        ? current.days_of_week.filter((d) => d !== day)
        : [...current.days_of_week, day],
    }))
  }

  const handleSave = async () => {
    if (!form.name.trim()) return
    setSaving(true)
    try {
      const payload = {
        name: form.name.trim(),
        adjustment_type: form.adjustment_type,
        adjustment_value: Number(form.adjustment_value),
        time_start: form.time_start || undefined,
        time_end: form.time_end || undefined,
        days_of_week: form.days_of_week.map(Number),
        priority: Number(form.priority || 0),
        is_active: true,
      }
      if (editingId) {
        await updatePricingRule(editingId, payload)
        pushToast('Rule updated.', 'success')
      } else {
        await createPricingRule(payload)
        pushToast('Rule created.', 'success')
      }
      setEditingId(null)
      setForm({
        name: '',
        adjustment_type: 'percent_off',
        adjustment_value: '10',
        time_start: '10:00',
        time_end: '16:00',
        days_of_week: ['1', '2', '3', '4', '5'],
        priority: '10',
      })
      await load()
    } catch (err) {
      pushToast(err?.response?.data?.message || 'Unable to save rule.', 'error')
    } finally {
      setSaving(false)
    }
  }

  const startEdit = (rule) => {
    setEditingId(rule.id)
    setForm({
      name: rule.name || '',
      adjustment_type: rule.adjustment_type || 'percent_off',
      adjustment_value: String(rule.adjustment_value ?? 0),
      time_start: rule.time_start || '',
      time_end: rule.time_end || '',
      days_of_week: (rule.days_of_week || []).map(String),
      priority: String(rule.priority ?? 0),
    })
  }

  const handleResolve = async () => {
    if (!resolveForm.service_id) return
    setSaving(true)
    try {
      const result = await resolvePricing({
        service_id: Number(resolveForm.service_id),
        datetime: resolveForm.datetime || undefined,
        list_price: resolveForm.list_price !== '' ? Number(resolveForm.list_price) : undefined,
      })
      setResolveResult(result)
    } catch (err) {
      setResolveResult(null)
      pushToast(err?.response?.data?.message || 'Resolve failed.', 'error')
    } finally {
      setSaving(false)
    }
  }

  return (
    <div className="space-y-6">
      <PageHeader
        title="Pricing rules"
        subtitle={subscriptionPageSubtitle(auth, 'Daypart percent-off rules and price resolve tester.')}
        actions={(
          <BaseButton variant="secondary" size="sm" leftIcon={RefreshCw} loading={loading} onClick={() => void load()}>
            Refresh
          </BaseButton>
        )}
      />

      {error ? <div className="rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-700">{error}</div> : null}

      {canManage ? (
        <div className="rounded-[18px] border border-slate-200 bg-white p-5 shadow-[0_8px_30px_rgb(0,0,0,0.04)]">
          <h3 className="text-base font-semibold text-slate-900">{editingId ? 'Edit rule' : 'Create daypart rule'}</h3>
          <div className="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <BaseInput label="Name" value={form.name} onChange={(e) => setForm((f) => ({ ...f, name: e.target.value }))} />
            <BaseSelect
              label="Adjustment"
              value={form.adjustment_type}
              onChange={(e) => setForm((f) => ({ ...f, adjustment_type: e.target.value }))}
              options={[
                { value: 'percent_off', label: 'Percent off' },
                { value: 'percent_on', label: 'Percent on' },
                { value: 'fixed_off', label: 'Fixed off' },
              ]}
            />
            <BaseInput label="Value" type="number" value={form.adjustment_value} onChange={(e) => setForm((f) => ({ ...f, adjustment_value: e.target.value }))} />
            <BaseInput label="Priority" type="number" value={form.priority} onChange={(e) => setForm((f) => ({ ...f, priority: e.target.value }))} />
            <BaseInput label="Time start" type="time" value={form.time_start} onChange={(e) => setForm((f) => ({ ...f, time_start: e.target.value }))} />
            <BaseInput label="Time end" type="time" value={form.time_end} onChange={(e) => setForm((f) => ({ ...f, time_end: e.target.value }))} />
          </div>
          <div className="mt-3 flex flex-wrap gap-2">
            {DAY_OPTIONS.map((day) => (
              <button
                key={day.value}
                type="button"
                onClick={() => toggleDay(day.value)}
                className={`rounded-lg px-3 py-1.5 text-xs font-medium ${form.days_of_week.includes(day.value) ? 'bg-brand-50 text-brand-700' : 'bg-slate-100 text-slate-600'}`}
              >
                {day.label}
              </button>
            ))}
          </div>
          <div className="mt-4">
            <BaseButton leftIcon={Plus} loading={saving} onClick={() => void handleSave()}>
              {editingId ? 'Update rule' : 'Create rule'}
            </BaseButton>
          </div>
        </div>
      ) : null}

      <div className="overflow-hidden rounded-[18px] border border-slate-200 bg-white shadow-[0_8px_30px_rgb(0,0,0,0.04)]">
        <div className="border-b border-slate-100 px-5 py-4">
          <h3 className="text-base font-semibold text-slate-900">Rules</h3>
        </div>
        {loading ? <div className="p-5 text-sm text-slate-500">Loading…</div> : null}
        {!loading && rules.length === 0 ? <p className="px-5 py-8 text-center text-sm text-slate-500">No pricing rules yet.</p> : null}
        {rules.length > 0 ? (
          <ul className="divide-y divide-slate-100">
            {rules.map((rule) => (
              <li key={rule.id} className="flex flex-wrap items-center justify-between gap-3 px-5 py-4">
                <div>
                  <p className="font-medium text-slate-900">{rule.name}</p>
                  <p className="text-xs text-slate-500">
                    {rule.adjustment_type} {rule.adjustment_value}
                    {rule.time_start ? ` · ${rule.time_start}-${rule.time_end}` : ''}
                    {rule.days_of_week?.length ? ` · days ${rule.days_of_week.join(',')}` : ''}
                  </p>
                </div>
                <div className="flex items-center gap-2">
                  <BaseBadge size="sm" variant={rule.is_active ? 'success' : 'default'}>{rule.is_active ? 'Active' : 'Off'}</BaseBadge>
                  {canManage ? (
                    <BaseButton size="sm" variant="secondary" onClick={() => startEdit(rule)}>Edit</BaseButton>
                  ) : null}
                </div>
              </li>
            ))}
          </ul>
        ) : null}
      </div>

      <div className="rounded-[18px] border border-slate-200 bg-white p-5 shadow-[0_8px_30px_rgb(0,0,0,0.04)]">
        <h3 className="text-base font-semibold text-slate-900">Test resolve</h3>
        <div className="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
          <BaseSelect
            label="Service"
            value={resolveForm.service_id}
            onChange={(e) => setResolveForm((f) => ({ ...f, service_id: e.target.value }))}
            options={[{ value: '', label: 'Select service' }, ...services.map((s) => ({ value: String(s.id), label: s.name }))]}
          />
          <BaseInput label="Datetime" type="datetime-local" value={resolveForm.datetime} onChange={(e) => setResolveForm((f) => ({ ...f, datetime: e.target.value }))} />
          <BaseInput label="List price override" type="number" value={resolveForm.list_price} onChange={(e) => setResolveForm((f) => ({ ...f, list_price: e.target.value }))} />
          <div className="flex items-end">
            <BaseButton leftIcon={Search} loading={saving} onClick={() => void handleResolve()}>Resolve</BaseButton>
          </div>
        </div>
        {resolveResult ? (
          <div className="mt-4 rounded-xl bg-slate-50 p-4 text-sm text-slate-700">
            <p>List: <strong>{fmt.money(resolveResult.list_price ?? 0)}</strong></p>
            <p>Final: <strong>{fmt.money(resolveResult.final_price ?? 0)}</strong></p>
            <p>Adjustment: {fmt.money(resolveResult.adjustment_amount ?? 0)}</p>
            <p>Rule: {resolveResult.pricing_rule?.name || resolveResult.pricing_rule_id || 'none'}</p>
          </div>
        ) : null}
      </div>
    </div>
  )
}
