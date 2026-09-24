import React, { useCallback, useEffect, useState } from 'react'
import { format } from 'date-fns'
import { Lock, Plus, RefreshCw, Upload } from 'lucide-react'
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
  createCommissionAssignment,
  createCommissionPeriod,
  createCommissionRule,
  createCommissionScheme,
  fetchCommissionAssignments,
  fetchCommissionPeriods,
  fetchCommissionRules,
  fetchCommissionSchemes,
  lockCommissionPeriod,
  migrateFlatRatesToScheme,
} from '../services/commissionService.js'

const TABS = [
  { key: 'schemes', label: 'Schemes' },
  { key: 'assignments', label: 'Assignments' },
  { key: 'periods', label: 'Payout periods' },
]

const CALC_OPTIONS = [
  { value: 'percent_of_revenue', label: 'Percent of revenue' },
  { value: 'percent_of_net', label: 'Percent of net' },
  { value: 'fixed_per_line', label: 'Fixed per line' },
  { value: 'fixed_per_minute', label: 'Fixed per minute' },
]

export default function CommissionsView() {
  const auth = useAuthStore()
  const fmt = useTenantFormatter()
  const canManage = canMutate(auth, TENANT_PERMISSIONS.COMMISSIONS_MANAGE)
  const canApprove = canMutate(auth, TENANT_PERMISSIONS.COMMISSIONS_APPROVE)

  const [tab, setTab] = useState('schemes')
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState('')
  const [schemes, setSchemes] = useState([])
  const [assignments, setAssignments] = useState([])
  const [periods, setPeriods] = useState([])
  const [staff, setStaff] = useState([])
  const [selectedSchemeId, setSelectedSchemeId] = useState('')
  const [rules, setRules] = useState([])
  const [saving, setSaving] = useState(false)

  const [schemeForm, setSchemeForm] = useState({ name: '', is_default: false })
  const [ruleForm, setRuleForm] = useState({
    name: '',
    applies_to: 'all',
    calc_type: 'percent_of_revenue',
    rate_value: '10',
    priority: '0',
  })
  const [assignForm, setAssignForm] = useState({ user_id: '', scheme_id: '', effective_from: '' })
  const [periodForm, setPeriodForm] = useState({
    period_start: format(new Date(new Date().getFullYear(), new Date().getMonth(), 1), 'yyyy-MM-dd'),
    period_end: format(new Date(), 'yyyy-MM-dd'),
    notes: '',
  })

  const load = useCallback(async () => {
    setLoading(true)
    setError('')
    try {
      const [schemeRows, assignmentRows, periodRows, staffRows] = await Promise.all([
        fetchCommissionSchemes(),
        fetchCommissionAssignments(),
        fetchCommissionPeriods(),
        fetchMasterList('/v1/staff', 'staff').catch(() => []),
      ])
      setSchemes(schemeRows)
      setAssignments(assignmentRows)
      setPeriods(periodRows)
      setStaff(staffRows || [])
      setSelectedSchemeId((current) => current || (schemeRows[0] ? String(schemeRows[0].id) : ''))
    } catch (err) {
      setError(err?.response?.data?.message || 'Unable to load commissions.')
    } finally {
      setLoading(false)
    }
  }, [])

  useEffect(() => {
    void load()
  }, [load])

  useEffect(() => {
    if (!selectedSchemeId) {
      setRules([])
      return
    }
    void fetchCommissionRules(selectedSchemeId)
      .then(setRules)
      .catch(() => setRules([]))
  }, [selectedSchemeId])

  const handleCreateScheme = async () => {
    if (!schemeForm.name.trim()) return
    setSaving(true)
    try {
      await createCommissionScheme({
        name: schemeForm.name.trim(),
        is_default: Boolean(schemeForm.is_default),
        is_active: true,
      })
      pushToast('Scheme created.', 'success')
      setSchemeForm({ name: '', is_default: false })
      await load()
    } catch (err) {
      pushToast(err?.response?.data?.message || 'Unable to create scheme.', 'error')
    } finally {
      setSaving(false)
    }
  }

  const handleMigrate = async () => {
    setSaving(true)
    try {
      await migrateFlatRatesToScheme()
      pushToast('Flat rates imported into a scheme.', 'success')
      await load()
    } catch (err) {
      pushToast(err?.response?.data?.message || 'Unable to import flat rates.', 'error')
    } finally {
      setSaving(false)
    }
  }

  const handleCreateRule = async () => {
    if (!selectedSchemeId || !ruleForm.name.trim()) return
    setSaving(true)
    try {
      await createCommissionRule(selectedSchemeId, {
        name: ruleForm.name.trim(),
        applies_to: ruleForm.applies_to,
        calc_type: ruleForm.calc_type,
        rate_value: Number(ruleForm.rate_value),
        priority: Number(ruleForm.priority || 0),
        is_active: true,
      })
      pushToast('Rule added.', 'success')
      setRuleForm({ name: '', applies_to: 'all', calc_type: 'percent_of_revenue', rate_value: '10', priority: '0' })
      const next = await fetchCommissionRules(selectedSchemeId)
      setRules(next)
    } catch (err) {
      pushToast(err?.response?.data?.message || 'Unable to create rule.', 'error')
    } finally {
      setSaving(false)
    }
  }

  const handleAssign = async () => {
    if (!assignForm.user_id || !assignForm.scheme_id) return
    setSaving(true)
    try {
      await createCommissionAssignment({
        user_id: Number(assignForm.user_id),
        scheme_id: Number(assignForm.scheme_id),
        effective_from: assignForm.effective_from || undefined,
      })
      pushToast('Staff assigned to scheme.', 'success')
      setAssignForm({ user_id: '', scheme_id: '', effective_from: '' })
      await load()
    } catch (err) {
      pushToast(err?.response?.data?.message || 'Unable to assign staff.', 'error')
    } finally {
      setSaving(false)
    }
  }

  const handleCreatePeriod = async () => {
    setSaving(true)
    try {
      await createCommissionPeriod({
        period_start: periodForm.period_start,
        period_end: periodForm.period_end,
        notes: periodForm.notes || undefined,
      })
      pushToast('Payout period created.', 'success')
      await load()
    } catch (err) {
      pushToast(err?.response?.data?.message || 'Unable to create period.', 'error')
    } finally {
      setSaving(false)
    }
  }

  const handleLockPeriod = async (id) => {
    setSaving(true)
    try {
      await lockCommissionPeriod(id)
      pushToast('Period locked.', 'success')
      await load()
    } catch (err) {
      pushToast(err?.response?.data?.message || 'Unable to lock period.', 'error')
    } finally {
      setSaving(false)
    }
  }

  return (
    <div className="space-y-6">
      <PageHeader
        title="Commissions"
        subtitle={subscriptionPageSubtitle(auth, 'Schemes, staff assignments, and payout periods.')}
        actions={(
          <div className="flex flex-wrap gap-2">
            {canManage ? (
              <BaseButton variant="secondary" size="sm" leftIcon={Upload} loading={saving} onClick={() => void handleMigrate()}>
                Import flat rates
              </BaseButton>
            ) : null}
            <BaseButton variant="secondary" size="sm" leftIcon={RefreshCw} loading={loading} onClick={() => void load()}>
              Refresh
            </BaseButton>
          </div>
        )}
      />

      <div className="flex flex-wrap gap-2 border-b border-slate-200 pb-2">
        {TABS.map((item) => (
          <button
            key={item.key}
            type="button"
            onClick={() => setTab(item.key)}
            className={`rounded-lg px-3 py-2 text-sm font-medium ${tab === item.key ? 'bg-brand-50 text-brand-700' : 'text-slate-600 hover:bg-slate-50'}`}
          >
            {item.label}
          </button>
        ))}
      </div>

      {error ? <div className="rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-700">{error}</div> : null}

      {tab === 'schemes' ? (
        <div className="space-y-5">
          {canManage ? (
            <div className="rounded-[18px] border border-slate-200 bg-white p-5 shadow-[0_8px_30px_rgb(0,0,0,0.04)]">
              <h3 className="text-base font-semibold text-slate-900">Create scheme</h3>
              <div className="mt-4 grid gap-3 sm:grid-cols-3">
                <BaseInput label="Name" value={schemeForm.name} onChange={(e) => setSchemeForm((f) => ({ ...f, name: e.target.value }))} />
                <BaseSelect
                  label="Default"
                  value={schemeForm.is_default ? '1' : '0'}
                  onChange={(e) => setSchemeForm((f) => ({ ...f, is_default: e.target.value === '1' }))}
                  options={[{ value: '0', label: 'No' }, { value: '1', label: 'Yes' }]}
                />
                <div className="flex items-end">
                  <BaseButton leftIcon={Plus} loading={saving} onClick={() => void handleCreateScheme()}>Create</BaseButton>
                </div>
              </div>
            </div>
          ) : null}

          <div className="rounded-[18px] border border-slate-200 bg-white p-5 shadow-[0_8px_30px_rgb(0,0,0,0.04)]">
            <div className="flex flex-wrap items-end gap-3">
              <BaseSelect
                label="Scheme"
                value={selectedSchemeId}
                onChange={(e) => setSelectedSchemeId(e.target.value)}
                options={schemes.map((s) => ({ value: String(s.id), label: s.name }))}
              />
            </div>

            {loading ? <div className="mt-4 h-24 animate-pulse rounded-xl bg-slate-50" /> : null}

            {!loading && schemes.length === 0 ? (
              <p className="mt-4 text-sm text-slate-500">No schemes yet.</p>
            ) : null}

            {!loading && selectedSchemeId ? (
              <div className="mt-5 space-y-4">
                <div className="overflow-x-auto">
                  <table className="min-w-full text-sm">
                    <thead className="bg-slate-50 text-left text-xs uppercase tracking-wider text-slate-500">
                      <tr>
                        <th className="px-3 py-2">Rule</th>
                        <th className="px-3 py-2">Applies</th>
                        <th className="px-3 py-2">Calc</th>
                        <th className="px-3 py-2 text-right">Rate</th>
                      </tr>
                    </thead>
                    <tbody className="divide-y divide-slate-100">
                      {rules.map((rule) => (
                        <tr key={rule.id}>
                          <td className="px-3 py-3 font-medium text-slate-900">{rule.name}</td>
                          <td className="px-3 py-3">{rule.applies_to}</td>
                          <td className="px-3 py-3">{rule.calc_type}</td>
                          <td className="px-3 py-3 text-right">{rule.rate_value}</td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                  {rules.length === 0 ? <p className="py-6 text-center text-sm text-slate-500">No rules on this scheme.</p> : null}
                </div>

                {canManage ? (
                  <div className="grid gap-3 border-t border-slate-100 pt-4 sm:grid-cols-2 lg:grid-cols-5">
                    <BaseInput label="Rule name" value={ruleForm.name} onChange={(e) => setRuleForm((f) => ({ ...f, name: e.target.value }))} />
                    <BaseSelect
                      label="Applies to"
                      value={ruleForm.applies_to}
                      onChange={(e) => setRuleForm((f) => ({ ...f, applies_to: e.target.value }))}
                      options={[
                        { value: 'all', label: 'All' },
                        { value: 'service', label: 'Service' },
                        { value: 'product', label: 'Product' },
                      ]}
                    />
                    <BaseSelect
                      label="Calc type"
                      value={ruleForm.calc_type}
                      onChange={(e) => setRuleForm((f) => ({ ...f, calc_type: e.target.value }))}
                      options={CALC_OPTIONS}
                    />
                    <BaseInput label="Rate" type="number" value={ruleForm.rate_value} onChange={(e) => setRuleForm((f) => ({ ...f, rate_value: e.target.value }))} />
                    <div className="flex items-end">
                      <BaseButton leftIcon={Plus} loading={saving} onClick={() => void handleCreateRule()}>Add rule</BaseButton>
                    </div>
                  </div>
                ) : null}
              </div>
            ) : null}
          </div>
        </div>
      ) : null}

      {tab === 'assignments' ? (
        <div className="space-y-5">
          {canManage ? (
            <div className="rounded-[18px] border border-slate-200 bg-white p-5 shadow-[0_8px_30px_rgb(0,0,0,0.04)]">
              <h3 className="text-base font-semibold text-slate-900">Assign staff</h3>
              <div className="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <BaseSelect
                  label="Staff"
                  value={assignForm.user_id}
                  onChange={(e) => setAssignForm((f) => ({ ...f, user_id: e.target.value }))}
                  options={[{ value: '', label: 'Select staff' }, ...staff.map((m) => ({ value: String(m.id), label: m.name }))]}
                />
                <BaseSelect
                  label="Scheme"
                  value={assignForm.scheme_id}
                  onChange={(e) => setAssignForm((f) => ({ ...f, scheme_id: e.target.value }))}
                  options={[{ value: '', label: 'Select scheme' }, ...schemes.map((s) => ({ value: String(s.id), label: s.name }))]}
                />
                <BaseInput label="Effective from" type="date" value={assignForm.effective_from} onChange={(e) => setAssignForm((f) => ({ ...f, effective_from: e.target.value }))} />
                <div className="flex items-end">
                  <BaseButton leftIcon={Plus} loading={saving} onClick={() => void handleAssign()}>Assign</BaseButton>
                </div>
              </div>
            </div>
          ) : null}

          <div className="overflow-hidden rounded-[18px] border border-slate-200 bg-white shadow-[0_8px_30px_rgb(0,0,0,0.04)]">
            {loading ? <div className="space-y-3 p-5">{Array.from({ length: 4 }).map((_, i) => <div key={i} className="h-12 animate-pulse rounded-xl bg-slate-50" />)}</div> : null}
            {!loading && assignments.length === 0 ? <p className="px-5 py-10 text-center text-sm text-slate-500">No assignments yet.</p> : null}
            {!loading && assignments.length > 0 ? (
              <table className="min-w-full text-sm">
                <thead className="bg-slate-50 text-left text-xs uppercase tracking-wider text-slate-500">
                  <tr>
                    <th className="px-5 py-3">Staff</th>
                    <th className="px-5 py-3">Scheme</th>
                    <th className="px-5 py-3">From</th>
                    <th className="px-5 py-3">Override %</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-slate-100">
                  {assignments.map((row) => (
                    <tr key={row.id}>
                      <td className="px-5 py-3 font-medium text-slate-900">{row.user?.name || row.user_id}</td>
                      <td className="px-5 py-3">{row.scheme?.name || row.scheme_id}</td>
                      <td className="px-5 py-3">{row.effective_from || '—'}</td>
                      <td className="px-5 py-3">{row.override_percent ?? '—'}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            ) : null}
          </div>
        </div>
      ) : null}

      {tab === 'periods' ? (
        <div className="space-y-5">
          {canManage ? (
            <div className="rounded-[18px] border border-slate-200 bg-white p-5 shadow-[0_8px_30px_rgb(0,0,0,0.04)]">
              <h3 className="text-base font-semibold text-slate-900">Create payout period</h3>
              <div className="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <BaseInput label="Start" type="date" value={periodForm.period_start} onChange={(e) => setPeriodForm((f) => ({ ...f, period_start: e.target.value }))} />
                <BaseInput label="End" type="date" value={periodForm.period_end} onChange={(e) => setPeriodForm((f) => ({ ...f, period_end: e.target.value }))} />
                <BaseInput label="Notes" value={periodForm.notes} onChange={(e) => setPeriodForm((f) => ({ ...f, notes: e.target.value }))} />
                <div className="flex items-end">
                  <BaseButton leftIcon={Plus} loading={saving} onClick={() => void handleCreatePeriod()}>Create period</BaseButton>
                </div>
              </div>
            </div>
          ) : null}

          <div className="overflow-hidden rounded-[18px] border border-slate-200 bg-white shadow-[0_8px_30px_rgb(0,0,0,0.04)]">
            {!loading && periods.length === 0 ? <p className="px-5 py-10 text-center text-sm text-slate-500">No payout periods yet.</p> : null}
            {!loading && periods.length > 0 ? (
              <table className="min-w-full text-sm">
                <thead className="bg-slate-50 text-left text-xs uppercase tracking-wider text-slate-500">
                  <tr>
                    <th className="px-5 py-3">Period</th>
                    <th className="px-5 py-3">Status</th>
                    <th className="px-5 py-3 text-right">Total</th>
                    <th className="px-5 py-3" />
                  </tr>
                </thead>
                <tbody className="divide-y divide-slate-100">
                  {periods.map((period) => (
                    <tr key={period.id}>
                      <td className="px-5 py-3">{period.period_start} → {period.period_end}</td>
                      <td className="px-5 py-3"><BaseBadge size="sm" variant={period.status === 'locked' ? 'success' : 'info'}>{period.status}</BaseBadge></td>
                      <td className="px-5 py-3 text-right font-medium">{fmt.money(period.total_commission ?? 0)}</td>
                      <td className="px-5 py-3 text-right">
                        {canApprove && period.status === 'open' ? (
                          <BaseButton size="sm" variant="secondary" leftIcon={Lock} loading={saving} onClick={() => void handleLockPeriod(period.id)}>
                            Lock
                          </BaseButton>
                        ) : null}
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            ) : null}
          </div>
        </div>
      ) : null}
    </div>
  )
}
