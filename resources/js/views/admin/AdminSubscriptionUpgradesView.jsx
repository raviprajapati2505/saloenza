import React, { useCallback, useEffect, useMemo, useState } from 'react'
import { useSearchParams } from 'react-router-dom'
import { motion, AnimatePresence } from 'framer-motion'
import {
  CheckCircle2,
  HandCoins,
  Loader2,
  RefreshCw,
  Search,
  ShieldCheck,
  X,
  XCircle,
} from 'lucide-react'
import PageHeader from '../../components/ui/PageHeader.jsx'
import BaseButton from '../../components/ui/BaseButton.jsx'
import BaseInput from '../../components/ui/BaseInput.jsx'
import BaseSelect from '../../components/ui/BaseSelect.jsx'
import { useAuthStore } from '../../stores/auth'
import { pushToast } from '../../stores/toast.js'
import { fetchMasterList } from '../../lib/apiHelpers.js'
import { PLATFORM_PERMISSIONS } from '../../lib/platformPermissions.js'
import { formatPlanPrice, TRIAL_DURATION_OPTIONS } from '../../lib/subscriptionModules.js'
import FormToggle from '../../components/ui/FormToggle.jsx'
import { fetchOnboardingRecords } from '../../services/adminOnboardingService.js'
import {
  approveSubscriptionUpgrade,
  assignSalonSubscription,
  fetchSubscriptionUpgradeOrders,
  rejectSubscriptionUpgrade,
} from '../../services/adminSubscriptionService.js'
import { formatMoneyDefault } from '../../lib/tenantFormatting.js'

const PAYMENT_TYPES = [
  { value: 'cash', label: 'Cash' },
  { value: 'upi', label: 'UPI' },
  { value: 'card', label: 'Card' },
  { value: 'bank_transfer', label: 'Bank Transfer' },
  { value: 'online', label: 'Online' },
  { value: 'other', label: 'Other' },
]

function ApproveModal({ order, onClose, onApproved, token, canUpdate }) {
  const [form, setForm] = useState({
    payment_type: 'cash',
    amount: String(order?.amount ?? ''),
    transaction_id: order?.transaction_id || '',
    notes: order?.notes || '',
  })
  const [saving, setSaving] = useState(false)

  const submit = async (event) => {
    event.preventDefault()
    if (!canUpdate) return
    setSaving(true)
    try {
      await approveSubscriptionUpgrade(order.id, {
        payment_type: form.payment_type,
        amount: Number(form.amount),
        transaction_id: form.transaction_id || null,
        notes: form.notes || null,
      }, token)
      pushToast('Upgrade approved and plan activated.', 'success')
      onApproved()
      onClose()
    } catch (error) {
      pushToast(error?.response?.data?.message || 'Failed to approve upgrade.', 'error')
    } finally {
      setSaving(false)
    }
  }

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4">
      <form onSubmit={submit} className="w-full max-w-lg rounded-2xl bg-white p-6 shadow-xl">
        <div className="mb-4 flex items-start justify-between gap-3">
          <div>
            <h3 className="text-lg font-semibold text-slate-900">Approve offline upgrade</h3>
            <p className="text-sm text-slate-500">
              {order.saloon_name} → {order.to_plan?.name}
            </p>
          </div>
          <button type="button" onClick={onClose} className="rounded-lg p-2 text-slate-400 hover:bg-slate-100">
            <X className="h-5 w-5" />
          </button>
        </div>

        <div className="grid gap-4">
          <BaseSelect
            label="Payment type"
            value={form.payment_type}
            onChange={(e) => setForm((prev) => ({ ...prev, payment_type: e.target.value }))}
            options={PAYMENT_TYPES}
          />
          <BaseInput
            label="Amount collected"
            type="number"
            min="0"
            step="0.01"
            value={form.amount}
            onChange={(e) => setForm((prev) => ({ ...prev, amount: e.target.value }))}
            required
          />
          <BaseInput
            label="Transaction / receipt ID"
            value={form.transaction_id}
            onChange={(e) => setForm((prev) => ({ ...prev, transaction_id: e.target.value }))}
          />
          <div>
            <label className="mb-1.5 block text-sm font-medium text-slate-700">Notes</label>
            <textarea
              value={form.notes}
              onChange={(e) => setForm((prev) => ({ ...prev, notes: e.target.value }))}
              rows={3}
              className="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20"
            />
          </div>
        </div>

        <div className="mt-6 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end sm:gap-3">
          <BaseButton type="button" variant="secondary" className="w-full sm:w-auto" onClick={onClose}>Cancel</BaseButton>
          <BaseButton type="submit" className="w-full sm:w-auto" loading={saving} leftIcon={CheckCircle2}>Approve & activate</BaseButton>
        </div>
      </form>
    </div>
  )
}

function ManualAssignPanel({ salons, plans, token, onAssigned, canUpdate }) {
  const [form, setForm] = useState({
    saloon_id: '',
    subscription_plan_id: '',
    payment_type: 'cash',
    amount: '',
    transaction_id: '',
    notes: '',
    start_trial: false,
    trial_days: 15,
  })
  const [saving, setSaving] = useState(false)
  const selectedPlan = plans.find((plan) => String(plan.id) === String(form.subscription_plan_id))

  const submit = async (event) => {
    event.preventDefault()
    if (!canUpdate) return
    if (!form.saloon_id || !form.subscription_plan_id) {
      pushToast('Select a salon and subscription plan.', 'error')
      return
    }

    setSaving(true)
    try {
      await assignSalonSubscription(Number(form.saloon_id), {
        subscription_plan_id: Number(form.subscription_plan_id),
        payment_type: form.payment_type,
        amount: form.amount !== '' ? Number(form.amount) : undefined,
        transaction_id: form.transaction_id || undefined,
        notes: form.notes || undefined,
        start_trial: Boolean(form.start_trial),
        trial_days: form.start_trial ? Number(form.trial_days || 0) : 0,
      }, token)
      pushToast('Salon subscription updated manually.', 'success')
      setForm({
        saloon_id: '',
        subscription_plan_id: '',
        payment_type: 'cash',
        amount: '',
        transaction_id: '',
        notes: '',
        start_trial: false,
        trial_days: 15,
      })
      onAssigned()
    } catch (error) {
      pushToast(error?.response?.data?.message || 'Failed to assign subscription.', 'error')
    } finally {
      setSaving(false)
    }
  }

  return (
    <div className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
      <div className="mb-4 flex items-center gap-2">
        <HandCoins className="h-5 w-5 text-brand-600" />
        <div>
          <h3 className="text-base font-semibold text-slate-900">Manual salon upgrade</h3>
          <p className="text-sm text-slate-500">Assign a plan after collecting offline payment.</p>
        </div>
      </div>

      <form onSubmit={submit} className="grid gap-4 md:grid-cols-2">
        <BaseSelect
          label="Salon"
          value={form.saloon_id}
          onChange={(e) => setForm((prev) => ({ ...prev, saloon_id: e.target.value }))}
          placeholder="Select salon"
          options={salons.map((salon) => ({
            value: String(salon.id),
            label: salon.business_name || salon.name || `Salon #${salon.id}`,
          }))}
        />
        <BaseSelect
          label="Subscription plan"
          value={form.subscription_plan_id}
          onChange={(e) => {
            const planId = e.target.value
            const plan = plans.find((row) => String(row.id) === String(planId))
            const planTrial = Number(plan?.trial_days || 0)
            setForm((prev) => ({
              ...prev,
              subscription_plan_id: planId,
              start_trial: planTrial > 0,
              trial_days: planTrial > 0 ? planTrial : 15,
            }))
          }}
          placeholder="Select plan"
          options={plans.map((plan) => ({
            value: String(plan.id),
            label: `${plan.name} · ${formatPlanPrice(plan)}`,
          }))}
        />
        <BaseSelect
          label="Payment type"
          value={form.payment_type}
          onChange={(e) => setForm((prev) => ({ ...prev, payment_type: e.target.value }))}
          options={PAYMENT_TYPES}
        />
        <BaseInput
          label="Amount collected"
          type="number"
          min="0"
          step="0.01"
          value={form.amount}
          onChange={(e) => setForm((prev) => ({ ...prev, amount: e.target.value }))}
        />
        <BaseInput
          label="Transaction ID"
          value={form.transaction_id}
          onChange={(e) => setForm((prev) => ({ ...prev, transaction_id: e.target.value }))}
        />
        <BaseInput
          label="Notes"
          value={form.notes}
          onChange={(e) => setForm((prev) => ({ ...prev, notes: e.target.value }))}
        />
        <div className="md:col-span-2 flex items-center gap-4 rounded-xl border border-slate-200 bg-slate-50 px-4 py-3">
          <div className="flex-1">
            <p className="text-sm font-medium text-slate-700">Start with free trial</p>
            <p className="text-xs text-slate-500">
              {selectedPlan
                ? `Plan default: ${Number(selectedPlan.trial_days || 0) > 0 ? `${selectedPlan.trial_days} days` : 'no trial'}`
                : 'Optional trial for this assignment'}
            </p>
          </div>
          <FormToggle
            id="manual-start-trial"
            checked={Boolean(form.start_trial)}
            onChange={(checked) => setForm((prev) => ({
              ...prev,
              start_trial: checked,
              trial_days: checked
                ? (Number(prev.trial_days) > 0 ? prev.trial_days : (Number(selectedPlan?.trial_days) || 15))
                : 0,
            }))}
            label={form.start_trial ? 'Trial on' : 'Trial off'}
          />
        </div>
        {form.start_trial ? (
          <BaseSelect
            label="Trial duration"
            value={String(form.trial_days || 15)}
            onChange={(e) => setForm((prev) => ({ ...prev, trial_days: Number(e.target.value) }))}
            options={TRIAL_DURATION_OPTIONS.filter((option) => option.value > 0)}
          />
        ) : null}
        <div className="md:col-span-2 flex justify-end">
          <BaseButton type="submit" className="w-full sm:w-auto" loading={saving} leftIcon={ShieldCheck}>
            Assign plan
          </BaseButton>
        </div>
      </form>
    </div>
  )
}

export default function AdminSubscriptionUpgradesView() {
  const auth = useAuthStore()
  const [searchParams, setSearchParams] = useSearchParams()
  const canUpdate = auth.canPlatform(PLATFORM_PERMISSIONS.UPGRADE_REQUESTS_UPDATE)
  const [orders, setOrders] = useState([])
  const [plans, setPlans] = useState([])
  const [salons, setSalons] = useState([])
  const [loading, setLoading] = useState(true)
  const [statusFilter, setStatusFilter] = useState(searchParams.get('status') || 'pending')
  const [search, setSearch] = useState('')
  const [approveOrder, setApproveOrder] = useState(null)
  const [actionId, setActionId] = useState(null)

  useEffect(() => {
    const nextStatus = searchParams.get('status')
    if (nextStatus) setStatusFilter(nextStatus)
  }, [searchParams])

  const changeStatusFilter = (nextStatus) => {
    setStatusFilter(nextStatus)
    const params = new URLSearchParams()
    if (nextStatus && nextStatus !== 'all') params.set('status', nextStatus)
    setSearchParams(params, { replace: true })
  }

  const loadData = useCallback(async () => {
    if (!auth.token) return
    setLoading(true)
    try {
      const params = { page: 1, per_page: 50 }
      if (statusFilter !== 'all') params.status = statusFilter

      const [ordersResult, planRows, onboardingData] = await Promise.all([
        fetchSubscriptionUpgradeOrders(params, auth.token),
        fetchMasterList('/v1/subscription-plans', 'subscription_plans'),
        fetchOnboardingRecords({}, auth.token),
      ])

      setOrders(ordersResult.orders)
      setPlans(planRows)
      setSalons(Array.isArray(onboardingData.onboardings) ? onboardingData.onboardings : [])
    } catch (error) {
      pushToast(error?.response?.data?.message || 'Failed to load upgrade queue.', 'error')
      setOrders([])
    } finally {
      setLoading(false)
    }
  }, [auth.token, statusFilter])

  useEffect(() => {
    void loadData()
  }, [loadData])

  const filteredOrders = useMemo(() => {
    const q = search.toLowerCase()
    if (!q) return orders
    return orders.filter((order) =>
      (order.saloon_name || '').toLowerCase().includes(q)
      || (order.to_plan?.name || '').toLowerCase().includes(q)
      || (order.requested_by?.email || '').toLowerCase().includes(q),
    )
  }, [orders, search])

  const rejectOrder = async (order) => {
    if (!canUpdate) return
    setActionId(`reject-${order.id}`)
    try {
      await rejectSubscriptionUpgrade(order.id, 'Rejected by platform admin.', auth.token)
      pushToast('Upgrade request rejected.', 'success')
      await loadData()
    } catch (error) {
      pushToast(error?.response?.data?.message || 'Failed to reject upgrade.', 'error')
    } finally {
      setActionId(null)
    }
  }

  return (
    <div className="space-y-6">
      <PageHeader
        title="Subscription Upgrades"
        description="Approve offline upgrade requests or manually assign plans after payment collection."
        icon={HandCoins}
        actions={(
          <BaseButton variant="secondary" className="w-full sm:w-auto" leftIcon={RefreshCw} onClick={() => void loadData()}>
            Refresh
          </BaseButton>
        )}
      />

      {canUpdate ? (
        <ManualAssignPanel salons={salons} plans={plans} token={auth.token} onAssigned={loadData} canUpdate={canUpdate} />
      ) : null}

      <div className="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
        <div className="mb-4 flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
          <div className="relative max-w-md flex-1">
            <Search className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
            <input
              type="search"
              value={search}
              onChange={(e) => setSearch(e.target.value)}
              placeholder="Search salon, plan, or requester..."
              className="w-full rounded-xl border border-slate-200 py-2.5 pl-10 pr-4 text-sm outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20"
            />
          </div>
          <div className="flex flex-wrap gap-2">
            {['pending', 'awaiting_payment', 'completed', 'all'].map((status) => (
              <button
                key={status}
                type="button"
                onClick={() => changeStatusFilter(status)}
                className={`rounded-xl border px-3 py-2 text-xs font-semibold uppercase tracking-wide ${
                  statusFilter === status
                    ? 'border-brand-600 bg-brand-600 text-white'
                    : 'border-slate-200 bg-white text-slate-600 hover:border-slate-300'
                }`}
              >
                {status.replace('_', ' ')}
              </button>
            ))}
          </div>
        </div>

        {loading ? (
          <div className="flex items-center gap-2 py-16 text-sm text-slate-500">
            <Loader2 className="h-4 w-4 animate-spin text-brand-600" />
            Loading upgrade requests...
          </div>
        ) : filteredOrders.length === 0 ? (
          <div className="py-16 text-center text-sm text-slate-500">No upgrade orders found.</div>
        ) : (
          <div className="overflow-x-auto">
            <table className="min-w-full text-left text-sm">
              <thead>
                <tr className="border-b border-slate-200 text-xs uppercase tracking-wide text-slate-500">
                  <th className="px-3 py-3 font-semibold">Salon</th>
                  <th className="px-3 py-3 font-semibold">Plan change</th>
                  <th className="px-3 py-3 font-semibold">Amount</th>
                  <th className="px-3 py-3 font-semibold">Channel</th>
                  <th className="px-3 py-3 font-semibold">Status</th>
                  <th className="px-3 py-3 font-semibold text-right">Actions</th>
                </tr>
              </thead>
              <tbody>
                {filteredOrders.map((order) => (
                  <tr key={order.id} className="border-b border-slate-100 last:border-0">
                    <td className="px-3 py-4">
                      <p className="font-semibold text-slate-900">{order.saloon_name || `Salon #${order.saloon_id}`}</p>
                      <p className="text-xs text-slate-500">{order.requested_by?.email || 'Platform assigned'}</p>
                    </td>
                    <td className="px-3 py-4 text-slate-700">
                      {(order.from_plan?.name || '—')} → <span className="font-semibold">{order.to_plan?.name}</span>
                    </td>
                    <td className="px-3 py-4 text-slate-700">{formatMoneyDefault(order.amount || 0)}</td>
                    <td className="px-3 py-4 capitalize text-slate-700">{order.channel}</td>
                    <td className="px-3 py-4">
                      <span className="rounded-full bg-slate-100 px-2 py-0.5 text-xs font-semibold uppercase text-slate-700">
                        {order.status}
                      </span>
                    </td>
                    <td className="px-3 py-4">
                      <div className="flex justify-end gap-2">
                        {order.status === 'pending' && canUpdate ? (
                          <>
                            <BaseButton
                              size="sm"
                              leftIcon={CheckCircle2}
                              onClick={() => setApproveOrder(order)}
                            >
                              Approve
                            </BaseButton>
                            <BaseButton
                              size="sm"
                              variant="secondary"
                              leftIcon={XCircle}
                              loading={actionId === `reject-${order.id}`}
                              onClick={() => void rejectOrder(order)}
                            >
                              Reject
                            </BaseButton>
                          </>
                        ) : null}
                      </div>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </div>

      <AnimatePresence>
        {approveOrder && canUpdate ? (
          <ApproveModal
            order={approveOrder}
            token={auth.token}
            onClose={() => setApproveOrder(null)}
            onApproved={loadData}
            canUpdate={canUpdate}
          />
        ) : null}
      </AnimatePresence>
    </div>
  )
}
