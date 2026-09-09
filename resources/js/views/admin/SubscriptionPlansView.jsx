import axios from 'axios'
import React, { useCallback, useEffect, useMemo, useState } from 'react'
import { motion, AnimatePresence } from 'framer-motion'
import {
  CreditCard,
  Plus,
  Search,
  Edit2,
  Trash2,
  AlertTriangle,
  CheckCircle2,
  X,
  Layers,
} from 'lucide-react'
import PageHeader from '../../components/ui/PageHeader.jsx'
import BaseButton from '../../components/ui/BaseButton.jsx'
import BaseInput from '../../components/ui/BaseInput.jsx'
import BaseSelect from '../../components/ui/BaseSelect.jsx'
import FormToggle from '../../components/ui/FormToggle.jsx'
import { useAuthStore } from '../../stores/auth'
import { PLATFORM_PERMISSIONS } from '../../lib/platformPermissions.js'
import { getSubscriptionModuleCatalog, formatLimit, formatPlanPrice, TRIAL_DURATION_OPTIONS, formatTrialDays } from '../../lib/subscriptionModules.js'

const API_BASE_URL = import.meta.env.VITE_API_BASE_URL || 'http://localhost:8000/api'

const BILLING_INTERVALS = [
  { value: 'trial', label: 'Trial' },
  { value: 'monthly', label: 'Monthly' },
  { value: 'quarterly', label: 'Quarterly' },
  { value: 'yearly', label: 'Yearly' },
]

const EMPTY_FORM = {
  name: '',
  slug: '',
  description: '',
  price: 0,
  billing_interval: 'monthly',
  trial_days: 0,
  max_branches: 1,
  max_staff: 5,
  unlimited_branches: false,
  unlimited_staff: false,
  modules: ['appointments', 'customers', 'staff', 'catalog', 'settings'],
  is_active: true,
  is_public: true,
  sort_order: 0,
}

function slugify(value) {
  return String(value || '')
    .trim()
    .toLowerCase()
    .replace(/[^a-z0-9]+/g, '-')
    .replace(/^-+|-+$/g, '')
}

export default function SubscriptionPlansView() {
  const auth = useAuthStore()
  const canCreate = auth.canPlatform(PLATFORM_PERMISSIONS.SUBSCRIPTION_PLANS_CREATE)
  const canUpdate = auth.canPlatform(PLATFORM_PERMISSIONS.SUBSCRIPTION_PLANS_UPDATE)
  const canDelete = auth.canPlatform(PLATFORM_PERMISSIONS.SUBSCRIPTION_PLANS_DELETE)

  const [plans, setPlans] = useState([])
  const [isLoading, setIsLoading] = useState(true)
  const [search, setSearch] = useState('')
  const [successMessage, setSuccessMessage] = useState('')
  const [isModalOpen, setIsModalOpen] = useState(false)
  const [editingPlan, setEditingPlan] = useState(null)
  const [form, setForm] = useState(EMPTY_FORM)
  const [formErrors, setFormErrors] = useState({})
  const [isSaving, setIsSaving] = useState(false)
  const [deleteModal, setDeleteModal] = useState({ isOpen: false, plan: null })
  const [deleteError, setDeleteError] = useState('')
  const [isDeleting, setIsDeleting] = useState(false)
  const [slugTouched, setSlugTouched] = useState(false)

  const fetchPlans = useCallback(async () => {
    setIsLoading(true)
    try {
      const response = await axios.get(`${API_BASE_URL}/v1/subscription-plans`, {
        params: { page: 1, per_page: 100 },
        headers: {
          ...auth.authHeaders(),
          Accept: 'application/json',
        },
      })
      const raw = response.data?.data?.subscription_plans
      setPlans(Array.isArray(raw) ? raw : Array.isArray(raw?.data) ? raw.data : [])
    } catch (error) {
      console.error('Failed to fetch subscription plans:', error)
      setPlans([])
    } finally {
      setIsLoading(false)
    }
  }, [auth])

  useEffect(() => {
    void fetchPlans()
  }, [fetchPlans])

  const filteredPlans = useMemo(() => {
    const q = search.toLowerCase()
    return plans.filter((plan) =>
      plan.name.toLowerCase().includes(q) || plan.slug.toLowerCase().includes(q),
    )
  }, [plans, search])

  const openCreateModal = () => {
    if (!canCreate) return
    setEditingPlan(null)
    setForm(EMPTY_FORM)
    setFormErrors({})
    setSlugTouched(false)
    setIsModalOpen(true)
  }

  const openEditModal = (plan) => {
    if (!canUpdate) return
    setEditingPlan(plan)
    setForm({
      name: plan.name,
      slug: plan.slug,
      description: plan.description || '',
      price: plan.price ?? 0,
      billing_interval: plan.billing_interval || 'monthly',
      trial_days: plan.trial_days ?? 0,
      max_branches: plan.max_branches ?? 1,
      max_staff: plan.max_staff ?? 5,
      unlimited_branches: plan.max_branches == null,
      unlimited_staff: plan.max_staff == null,
      modules: plan.modules || [],
      is_active: plan.is_active ?? true,
      is_public: plan.is_public ?? true,
      sort_order: plan.sort_order ?? 0,
    })
    setFormErrors({})
    setSlugTouched(true)
    setIsModalOpen(true)
  }

  const closeModal = () => setIsModalOpen(false)

  const updateForm = (patch) => {
    setForm((prev) => {
      const next = { ...prev, ...patch }
      if (!slugTouched && patch.name !== undefined) {
        next.slug = slugify(patch.name)
      }
      return next
    })
  }

  const toggleModule = (moduleKey) => {
    setForm((prev) => {
      const modules = prev.modules.includes(moduleKey)
        ? prev.modules.filter((key) => key !== moduleKey)
        : [...prev.modules, moduleKey]
      return { ...prev, modules }
    })
  }

  const buildPayload = () => ({
    name: form.name.trim(),
    slug: form.slug.trim(),
    description: form.description.trim() || null,
    price: Number(form.price) || 0,
    billing_interval: form.billing_interval,
    trial_days: Number(form.trial_days) || 0,
    max_branches: form.unlimited_branches ? null : Number(form.max_branches),
    max_staff: form.unlimited_staff ? null : Number(form.max_staff),
    modules: form.modules,
    is_active: form.is_active,
    is_public: form.is_public,
    sort_order: Number(form.sort_order) || 0,
  })

  const savePlan = async (event) => {
    event.preventDefault()
    if (editingPlan ? !canUpdate : !canCreate) return
    setFormErrors({})

    if (!form.name.trim()) {
      setFormErrors({ name: 'Plan name is required.' })
      return
    }
    if (!form.slug.trim()) {
      setFormErrors({ slug: 'Plan slug is required.' })
      return
    }
    if (form.modules.length === 0) {
      setFormErrors({ modules: 'Select at least one module.' })
      return
    }

    setIsSaving(true)
    try {
      const payload = buildPayload()
      const headers = { ...auth.authHeaders(), Accept: 'application/json' }

      if (editingPlan) {
        const response = await axios.put(`${API_BASE_URL}/v1/subscription-plans/${editingPlan.id}`, payload, { headers })
        const updated = response.data.data?.subscription_plan || response.data.data
        setPlans((prev) => prev.map((plan) => (plan.id === updated.id ? updated : plan)))
        setSuccessMessage('Subscription plan updated successfully.')
      } else {
        const response = await axios.post(`${API_BASE_URL}/v1/subscription-plans`, payload, { headers })
        const created = response.data.data?.subscription_plan || response.data.data
        setPlans((prev) => [...prev, created].sort((a, b) => (a.sort_order ?? 0) - (b.sort_order ?? 0)))
        setSuccessMessage('Subscription plan created successfully.')
      }

      setTimeout(() => setSuccessMessage(''), 3000)
      closeModal()
    } catch (error) {
      const serverErrors = error?.response?.data?.errors || {}
      if (Object.keys(serverErrors).length > 0) {
        const mapped = {}
        for (const key of Object.keys(serverErrors)) {
          mapped[key] = serverErrors[key][0]
        }
        setFormErrors(mapped)
      } else {
        setFormErrors({ _general: error?.response?.data?.message || 'Failed to save subscription plan.' })
      }
    } finally {
      setIsSaving(false)
    }
  }

  const confirmDelete = (plan) => {
    if (!canDelete) return
    setDeleteModal({ isOpen: true, plan })
  }

  const handleDelete = async () => {
    if (!canDelete || !deleteModal.plan) return
    setIsDeleting(true)
    setDeleteError('')
    try {
      await axios.delete(`${API_BASE_URL}/v1/subscription-plans/${deleteModal.plan.id}`, {
        headers: { ...auth.authHeaders(), Accept: 'application/json' },
      })
      setPlans((prev) => prev.filter((plan) => plan.id !== deleteModal.plan.id))
      setSuccessMessage('Subscription plan deleted successfully.')
      setTimeout(() => setSuccessMessage(''), 3000)
      setDeleteModal({ isOpen: false, plan: null })
    } catch (error) {
      setDeleteError(error?.response?.data?.message || 'Failed to delete subscription plan.')
    } finally {
      setIsDeleting(false)
    }
  }

  return (
    <div className="space-y-6">
      <PageHeader
        title="Subscription Plans"
        description="Define salon plans with module access, branch limits, and staff limits."
        icon={CreditCard}
        actions={canCreate ? (
          <BaseButton leftIcon={Plus} onClick={openCreateModal}>
            New Plan
          </BaseButton>
        ) : null}
      />

      <AnimatePresence>
        {successMessage ? (
          <motion.div
            initial={{ opacity: 0, y: -8 }}
            animate={{ opacity: 1, y: 0 }}
            exit={{ opacity: 0, y: -8 }}
            className="flex items-center gap-2 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800"
          >
            <CheckCircle2 className="h-4 w-4 shrink-0" />
            {successMessage}
          </motion.div>
        ) : null}
      </AnimatePresence>

      <div className="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
        <div className="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
          <div className="relative max-w-md flex-1">
            <Search className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
            <input
              type="search"
              value={search}
              onChange={(e) => setSearch(e.target.value)}
              placeholder="Search plans..."
              className="w-full rounded-xl border border-slate-200 py-2.5 pl-10 pr-4 text-sm outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20"
            />
          </div>
        </div>

        {isLoading ? (
          <div className="py-16 text-center text-sm text-slate-500">Loading subscription plans...</div>
        ) : filteredPlans.length === 0 ? (
          <div className="py-16 text-center">
            <Layers className="mx-auto h-10 w-10 text-slate-300" />
            <p className="mt-3 text-sm font-medium text-slate-600">No subscription plans found</p>
            {canCreate ? <BaseButton className="mt-4" size="sm" leftIcon={Plus} onClick={openCreateModal}>
              Create first plan
            </BaseButton> : null}
          </div>
        ) : (
          <div className="overflow-x-auto">
            <table className="min-w-full text-left text-sm">
              <thead>
                <tr className="border-b border-slate-200 text-xs uppercase tracking-wide text-slate-500">
                  <th className="px-3 py-3 font-semibold">Plan</th>
                  <th className="px-3 py-3 font-semibold">Price</th>
                  <th className="px-3 py-3 font-semibold">Limits</th>
                  <th className="px-3 py-3 font-semibold">Modules</th>
                  <th className="px-3 py-3 font-semibold">Status</th>
                  <th className="px-3 py-3 font-semibold text-right">Actions</th>
                </tr>
              </thead>
              <tbody>
                {filteredPlans.map((plan) => (
                  <tr key={plan.id} className="border-b border-slate-100 last:border-0">
                    <td className="px-3 py-4">
                      <p className="font-semibold text-slate-900">{plan.name}</p>
                      <p className="text-xs text-slate-500">{plan.slug}</p>
                    </td>
                    <td className="px-3 py-4 text-slate-700">{formatPlanPrice(plan)}</td>
                    <td className="px-3 py-4 text-slate-700">
                      <p>Branches: {formatLimit(plan.max_branches)}</p>
                      <p>Staff: {formatLimit(plan.max_staff)}</p>
                    </td>
                    <td className="px-3 py-4">
                      <div className="flex max-w-xs flex-wrap gap-1">
                        {(plan.modules || []).slice(0, 4).map((module) => (
                          <span key={module} className="rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-semibold uppercase text-slate-600">
                            {module}
                          </span>
                        ))}
                        {(plan.modules || []).length > 4 ? (
                          <span className="rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-semibold text-slate-500">
                            +{(plan.modules || []).length - 4}
                          </span>
                        ) : null}
                      </div>
                    </td>
                    <td className="px-3 py-4">
                      <div className="flex flex-col gap-1">
                        <span className={`inline-flex w-fit rounded-full px-2 py-0.5 text-xs font-semibold ${plan.is_active ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-600'}`}>
                          {plan.is_active ? 'Active' : 'Inactive'}
                        </span>
                        <span className={`inline-flex w-fit rounded-full px-2 py-0.5 text-xs font-semibold ${plan.is_public ? 'bg-brand-50 text-brand-700' : 'bg-amber-50 text-amber-700'}`}>
                          {plan.is_public ? 'Public' : 'Hidden'}
                        </span>
                      </div>
                    </td>
                    <td className="px-3 py-4">
                      <div className="flex justify-end gap-2">
                        {canUpdate ? (
                        <button type="button" onClick={() => openEditModal(plan)} className="rounded-lg p-2 text-slate-500 hover:bg-slate-100 hover:text-brand-700">
                          <Edit2 className="h-4 w-4" />
                        </button>
                        ) : null}
                        {canDelete ? (
                        <button type="button" onClick={() => confirmDelete(plan)} className="rounded-lg p-2 text-slate-500 hover:bg-rose-50 hover:text-rose-600">
                          <Trash2 className="h-4 w-4" />
                        </button>
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
        {isModalOpen && (editingPlan ? canUpdate : canCreate) ? (
          <motion.div
            initial={{ opacity: 0 }}
            animate={{ opacity: 1 }}
            exit={{ opacity: 0 }}
            className="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4"
          >
            <motion.form
              initial={{ opacity: 0, scale: 0.98, y: 8 }}
              animate={{ opacity: 1, scale: 1, y: 0 }}
              exit={{ opacity: 0, scale: 0.98, y: 8 }}
              onSubmit={savePlan}
              className="max-h-[90vh] w-full max-w-3xl overflow-y-auto rounded-2xl bg-white p-6 shadow-xl"
            >
              <div className="mb-5 flex items-start justify-between gap-4">
                <div>
                  <h3 className="text-lg font-semibold text-slate-900">
                    {editingPlan ? 'Edit Subscription Plan' : 'Create Subscription Plan'}
                  </h3>
                  <p className="text-sm text-slate-500">Configure modules and usage limits for salon tenants.</p>
                </div>
                <button type="button" onClick={closeModal} className="rounded-lg p-2 text-slate-400 hover:bg-slate-100 hover:text-slate-700">
                  <X className="h-5 w-5" />
                </button>
              </div>

              {formErrors._general ? (
                <div className="mb-4 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">
                  {formErrors._general}
                </div>
              ) : null}

              <div className="grid gap-4 sm:grid-cols-2">
                <BaseInput
                  label="Plan Name"
                  required
                  value={form.name}
                  onChange={(e) => updateForm({ name: e.target.value })}
                  error={formErrors.name}
                />
                <BaseInput
                  label="Slug"
                  required
                  value={form.slug}
                  onChange={(e) => {
                    setSlugTouched(true)
                    updateForm({ slug: slugify(e.target.value) })
                  }}
                  error={formErrors.slug}
                />
                <div className="sm:col-span-2">
                  <label className="mb-1.5 block text-sm font-medium text-slate-700">Description</label>
                  <textarea
                    value={form.description}
                    onChange={(e) => updateForm({ description: e.target.value })}
                    rows={3}
                    className="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20"
                  />
                </div>
                <BaseInput
                  label="Price"
                  type="number"
                  min="0"
                  step="0.01"
                  value={form.price}
                  onChange={(e) => updateForm({ price: e.target.value })}
                  error={formErrors.price}
                />
                <BaseSelect
                  label="Billing Interval"
                  value={form.billing_interval}
                  onChange={(e) => updateForm({ billing_interval: e.target.value })}
                  options={BILLING_INTERVALS}
                  error={formErrors.billing_interval}
                />
                <BaseSelect
                  label="Trial Duration"
                  value={String(form.trial_days ?? 0)}
                  onChange={(e) => updateForm({ trial_days: Number(e.target.value) })}
                  options={TRIAL_DURATION_OPTIONS.map((option) => ({
                    value: String(option.value),
                    label: option.label,
                  }))}
                  error={formErrors.trial_days}
                />
                <p className="col-span-full -mt-2 text-xs text-slate-500">
                  Default free-trial length for this plan. Onboarding can still override per salon.
                  Current: {formatTrialDays(form.trial_days)}.
                </p>
                <BaseInput
                  label="Sort Order"
                  type="number"
                  min="0"
                  value={form.sort_order}
                  onChange={(e) => updateForm({ sort_order: e.target.value })}
                  error={formErrors.sort_order}
                />
                <div>
                  <BaseInput
                    label="Max Branches"
                    type="number"
                    min="1"
                    disabled={form.unlimited_branches}
                    value={form.max_branches}
                    onChange={(e) => updateForm({ max_branches: e.target.value })}
                    error={formErrors.max_branches}
                  />
                  <label className="mt-2 flex items-center gap-2 text-sm text-slate-600">
                    <input
                      type="checkbox"
                      checked={form.unlimited_branches}
                      onChange={(e) => updateForm({ unlimited_branches: e.target.checked })}
                    />
                    Unlimited branches
                  </label>
                </div>
                <div>
                  <BaseInput
                    label="Max Staff"
                    type="number"
                    min="1"
                    disabled={form.unlimited_staff}
                    value={form.max_staff}
                    onChange={(e) => updateForm({ max_staff: e.target.value })}
                    error={formErrors.max_staff}
                  />
                  <label className="mt-2 flex items-center gap-2 text-sm text-slate-600">
                    <input
                      type="checkbox"
                      checked={form.unlimited_staff}
                      onChange={(e) => updateForm({ unlimited_staff: e.target.checked })}
                    />
                    Unlimited staff
                  </label>
                </div>
              </div>

              <div className="mt-5">
                <p className="mb-2 text-sm font-medium text-slate-700">Included Modules</p>
                {formErrors.modules ? (
                  <p className="mb-2 text-xs text-rose-600">{formErrors.modules}</p>
                ) : null}
                <div className="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                  {getSubscriptionModuleCatalog().map((module) => (
                    <label key={module.key} className="flex items-center gap-2 rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-700">
                      <input
                        type="checkbox"
                        checked={form.modules.includes(module.key)}
                        onChange={() => toggleModule(module.key)}
                      />
                      {module.label}
                    </label>
                  ))}
                </div>
              </div>

              <div className="mt-5 flex flex-wrap gap-4">
                <FormToggle
                  id="plan-is_active"
                  checked={form.is_active}
                  onChange={(checked) => updateForm({ is_active: checked })}
                  label={form.is_active ? 'Active' : 'Inactive'}
                />
                <FormToggle
                  id="plan-is_public"
                  checked={form.is_public}
                  onChange={(checked) => updateForm({ is_public: checked })}
                  label={form.is_public ? 'Visible during onboarding' : 'Hidden from onboarding'}
                />
              </div>

              <div className="mt-6 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end sm:gap-3">
                <BaseButton type="button" variant="secondary" className="w-full sm:w-auto" onClick={closeModal}>
                  Cancel
                </BaseButton>
                <BaseButton type="submit" className="w-full sm:w-auto" loading={isSaving}>
                  {editingPlan ? 'Save Changes' : 'Create Plan'}
                </BaseButton>
              </div>
            </motion.form>
          </motion.div>
        ) : null}
      </AnimatePresence>

      <AnimatePresence>
        {deleteModal.isOpen && canDelete ? (
          <motion.div
            initial={{ opacity: 0 }}
            animate={{ opacity: 1 }}
            exit={{ opacity: 0 }}
            className="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4"
          >
            <motion.div
              initial={{ opacity: 0, scale: 0.98 }}
              animate={{ opacity: 1, scale: 1 }}
              exit={{ opacity: 0, scale: 0.98 }}
              className="w-full max-w-md rounded-2xl bg-white p-6 shadow-xl"
            >
              <div className="mb-4 flex items-center gap-3 text-rose-600">
                <AlertTriangle className="h-5 w-5" />
                <h3 className="text-lg font-semibold text-slate-900">Delete plan?</h3>
              </div>
              <p className="text-sm text-slate-600">
                Delete <strong>{deleteModal.plan?.name}</strong>? This cannot be undone if salons are still assigned to it.
              </p>
              {deleteError ? (
                <p className="mt-3 text-sm text-rose-600">{deleteError}</p>
              ) : null}
              <div className="mt-6 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end sm:gap-3">
                <BaseButton type="button" variant="secondary" className="w-full sm:w-auto" onClick={() => setDeleteModal({ isOpen: false, plan: null })}>
                  Cancel
                </BaseButton>
                <BaseButton type="button" variant="danger" className="w-full sm:w-auto" loading={isDeleting} onClick={handleDelete}>
                  Delete
                </BaseButton>
              </div>
            </motion.div>
          </motion.div>
        ) : null}
      </AnimatePresence>
    </div>
  )
}
