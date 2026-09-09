import React, { useCallback, useEffect, useMemo, useState } from 'react'
import { Building2, CheckCircle2, Copy, Loader2, MapPin, Pencil, Plus, ShieldCheck, UserRound, X } from 'lucide-react'
import BaseButton from '../ui/BaseButton.jsx'
import BaseInput from '../ui/BaseInput.jsx'
import { useAuthStore } from '../../stores/auth'
import { TENANT_PERMISSIONS } from '../../lib/tenantPermissions.js'
import { createBranch, fetchBranches, updateBranch } from '../../services/branchService.js'

const emptyForm = {
  branch_name: '',
  business_address_1: '',
  business_address_2: '',
  city: '',
  state: '',
  area_pincode: '',
  country: 'India',
  is_active: true,
  template_branch_id: '',
  manager_enabled: false,
  manager_firstname: '',
  manager_lastname: '',
  manager_email: '',
  manager_phone: '',
  manager_password: '',
  manager_password_confirmation: '',
}

function formatLimit(value) {
  return value == null ? 'Unlimited' : String(value)
}

function BranchModal({ open, branch, branches, branchScoped = false, canSave = false, onClose, onSaved }) {
  const isEdit = Boolean(branch?.id)
  const [form, setForm] = useState(emptyForm)
  const [errors, setErrors] = useState({})
  const [saving, setSaving] = useState(false)

  useEffect(() => {
    if (!open) return
    if (isEdit) {
      setForm({
        ...emptyForm,
        branch_name: branch?.branch_name || '',
        business_address_1: branch?.business_address_1 || '',
        business_address_2: branch?.business_address_2 || '',
        city: branch?.city || '',
        state: branch?.state || '',
        area_pincode: branch?.area_pincode || '',
        country: branch?.country || 'India',
        is_active: Boolean(branch?.is_active),
      })
    } else {
      setForm(emptyForm)
    }
    setErrors({})
  }, [open, isEdit, branch])

  if (!open || !canSave) return null

  const handleSubmit = async (event) => {
    event.preventDefault()
    if (!canSave) return
    setSaving(true)
    setErrors({})

    try {
      const payload = {
        branch_name: form.branch_name.trim(),
        business_address_1: form.business_address_1.trim(),
        business_address_2: form.business_address_2.trim() || null,
        city: form.city.trim(),
        state: form.state.trim(),
        area_pincode: form.area_pincode.trim(),
        country: form.country.trim() || 'India',
        is_active: branchScoped && isEdit
          ? Boolean(branch?.is_active)
          : Boolean(form.is_active),
      }

      if (!isEdit) {
        payload.template_branch_id = form.template_branch_id ? Number(form.template_branch_id) : null
        if (form.manager_enabled) {
          payload.manager = {
            firstname: form.manager_firstname.trim(),
            lastname: form.manager_lastname.trim(),
            email: form.manager_email.trim(),
            phone: form.manager_phone.trim(),
            password: form.manager_password,
            password_confirmation: form.manager_password_confirmation,
            is_active: true,
          }
        }
      }

      await onSaved(payload, branch?.id)
      onClose()
    } catch (error) {
      const serverErrors = error?.response?.data?.errors || {}
      if (Object.keys(serverErrors).length > 0) {
        const mapped = {}
        for (const key in serverErrors) {
          mapped[key] = serverErrors[key]?.[0] || 'Invalid value.'
        }
        setErrors(mapped)
      } else {
        setErrors({ _general: error?.response?.data?.message || 'Unable to save branch.' })
      }
    } finally {
      setSaving(false)
    }
  }

  const templateOptions = branches.filter((item) => item.id !== branch?.id)

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center p-4">
      <div className="absolute inset-0 bg-slate-900/40 backdrop-blur-sm" onClick={onClose} />
      <div className="relative w-full max-w-3xl rounded-2xl bg-white shadow-2xl">
        <div className="flex items-center justify-between border-b border-slate-100 px-6 py-5">
          <div>
            <h3 className="text-lg font-bold text-slate-900">
              {isEdit ? (branchScoped ? 'Edit My Branch' : 'Edit Branch') : 'Create New Branch'}
            </h3>
            <p className="mt-1 text-sm text-slate-500">
              {isEdit
                ? (branchScoped
                  ? 'Update the name and address details for your assigned branch.'
                  : 'Update address, status, and operational branch details.')
                : 'Provision a branch, optionally clone from an existing branch, and create its manager login.'}
            </p>
          </div>
          <button type="button" onClick={onClose} className="rounded-lg p-2 text-slate-400 hover:bg-slate-100 hover:text-slate-600">
            <X className="h-5 w-5" />
          </button>
        </div>

        <form onSubmit={handleSubmit} className="max-h-[85vh] overflow-y-auto p-6">
          <fieldset disabled={!canSave} className="space-y-5 disabled:opacity-80">
          <div className="space-y-5">
            {errors._general ? (
              <div className="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">
                {errors._general}
              </div>
            ) : null}

            <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
              <BaseInput label="Branch Name" modelValue={form.branch_name} onUpdateModelValue={(value) => setForm((prev) => ({ ...prev, branch_name: value }))} error={errors.branch_name} required placeholder="e.g. Main Branch" />
              <BaseInput label="Country" modelValue={form.country} onUpdateModelValue={(value) => setForm((prev) => ({ ...prev, country: value }))} error={errors.country} placeholder="Enter country" />
            </div>

            <BaseInput label="Address Line 1" modelValue={form.business_address_1} onUpdateModelValue={(value) => setForm((prev) => ({ ...prev, business_address_1: value }))} error={errors.business_address_1} required placeholder="Street, area, landmark" />
            <BaseInput label="Address Line 2" modelValue={form.business_address_2} onUpdateModelValue={(value) => setForm((prev) => ({ ...prev, business_address_2: value }))} error={errors.business_address_2} placeholder="Apartment, suite, floor (optional)" />

            <div className="grid grid-cols-1 gap-4 md:grid-cols-3">
              <BaseInput label="City" modelValue={form.city} onUpdateModelValue={(value) => setForm((prev) => ({ ...prev, city: value }))} error={errors.city} required placeholder="Enter city" />
              <BaseInput label="State" modelValue={form.state} onUpdateModelValue={(value) => setForm((prev) => ({ ...prev, state: value }))} error={errors.state} required placeholder="Enter state" />
              <BaseInput label="Pincode" modelValue={form.area_pincode} onUpdateModelValue={(value) => setForm((prev) => ({ ...prev, area_pincode: value }))} error={errors.area_pincode} required placeholder="6-digit pincode" />
            </div>

            {!isEdit ? (
              <div className="rounded-2xl border border-slate-200 bg-slate-50/80 p-4">
                <div className="mb-3 flex items-center gap-2 text-sm font-semibold text-slate-900">
                  <Copy className="h-4 w-4 text-brand-600" />
                  Branch provisioning template
                </div>
                <label className="mb-1.5 block text-sm font-medium text-slate-700">Copy services, products, and custom branch roles from</label>
                <select
                  value={form.template_branch_id}
                  onChange={(event) => setForm((prev) => ({ ...prev, template_branch_id: event.target.value }))}
                  className="block w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm"
                >
                  <option value="">Use first existing branch automatically</option>
                  {templateOptions.map((item) => (
                    <option key={item.id} value={item.id}>{item.branch_name}</option>
                  ))}
                </select>
                {errors.template_branch_id ? <p className="mt-1 text-xs text-rose-500">{errors.template_branch_id}</p> : null}
              </div>
            ) : null}

            {!isEdit ? (
              <div className="rounded-2xl border border-slate-200 bg-white p-4">
                <label className="flex items-center gap-3 text-sm font-semibold text-slate-900">
                  <input
                    type="checkbox"
                    checked={form.manager_enabled}
                    onChange={(event) => setForm((prev) => ({ ...prev, manager_enabled: event.target.checked }))}
                    className="rounded border-slate-300 text-brand-600"
                  />
                  Create branch manager account now
                </label>

                {form.manager_enabled ? (
                  <div className="mt-4 space-y-4">
                    <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                      <BaseInput label="Manager First Name" modelValue={form.manager_firstname} onUpdateModelValue={(value) => setForm((prev) => ({ ...prev, manager_firstname: value }))} error={errors['manager.firstname']} required placeholder="Enter first name" />
                      <BaseInput label="Manager Last Name" modelValue={form.manager_lastname} onUpdateModelValue={(value) => setForm((prev) => ({ ...prev, manager_lastname: value }))} error={errors['manager.lastname']} required placeholder="Enter last name" />
                    </div>
                    <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                      <BaseInput label="Manager Email" type="email" modelValue={form.manager_email} onUpdateModelValue={(value) => setForm((prev) => ({ ...prev, manager_email: value }))} error={errors['manager.email']} required placeholder="manager@example.com" />
                      <BaseInput label="Manager Phone" modelValue={form.manager_phone} onUpdateModelValue={(value) => setForm((prev) => ({ ...prev, manager_phone: value }))} error={errors['manager.phone']} required placeholder="+91 98765 43210" />
                    </div>
                    <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                      <BaseInput label="Temporary Password" type="password" toggleableType modelValue={form.manager_password} onUpdateModelValue={(value) => setForm((prev) => ({ ...prev, manager_password: value }))} error={errors['manager.password']} placeholder="Create a temporary password" />
                      <BaseInput label="Confirm Password" type="password" toggleableType modelValue={form.manager_password_confirmation} onUpdateModelValue={(value) => setForm((prev) => ({ ...prev, manager_password_confirmation: value }))} error={errors['manager.password_confirmation']} placeholder="Re-enter password" />
                    </div>
                  </div>
                ) : null}
              </div>
            ) : null}

            {!branchScoped ? (
              <label className="flex items-center gap-3 rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm font-medium text-slate-700">
                <input
                  type="checkbox"
                  checked={form.is_active}
                  onChange={(event) => setForm((prev) => ({ ...prev, is_active: event.target.checked }))}
                  className="rounded border-slate-300 text-brand-600"
                />
                Active branch
              </label>
            ) : null}
          </div>

          <div className="mt-6 flex flex-col-reverse gap-2 border-t border-slate-100 pt-5 sm:flex-row sm:justify-end sm:gap-3">
            <BaseButton type="button" variant="secondary" className="w-full sm:w-auto" onClick={onClose}>Cancel</BaseButton>
            <BaseButton type="submit" className="w-full sm:w-auto" loading={saving} leftIcon={saving ? Loader2 : CheckCircle2} disabled={!canSave}>
              {isEdit ? 'Save Changes' : 'Create Branch'}
            </BaseButton>
          </div>
          </fieldset>
        </form>
      </div>
    </div>
  )
}

export default function BranchManagementPanel({ hideIntro = false } = {}) {
  const auth = useAuthStore()
  const isBranchScoped = Boolean(auth.isBranchScoped)
  const lockedBranchId = auth.user?.branch_id != null ? String(auth.user.branch_id) : ''
  const canView = auth.can(TENANT_PERMISSIONS.BRANCHES_VIEW)
  const canCreate = auth.can(TENANT_PERMISSIONS.BRANCHES_CREATE) && !isBranchScoped
  const canUpdate = auth.can(TENANT_PERMISSIONS.BRANCHES_UPDATE)
  const [branches, setBranches] = useState([])
  const [limits, setLimits] = useState(isBranchScoped ? null : auth.subscriptionLimits)
  const [canCreateBranch, setCanCreateBranch] = useState(false)
  const [loading, setLoading] = useState(true)
  const [message, setMessage] = useState(null)
  const [modalState, setModalState] = useState({ open: false, branch: null })

  const loadBranches = useCallback(async () => {
    if (!canView) {
      setLoading(false)
      return
    }

    setLoading(true)
    try {
      const data = await fetchBranches()
      setBranches(data.branches)
      if (!isBranchScoped) {
        setLimits(data.limits)
        setCanCreateBranch(data.canCreateBranch)
      } else {
        setLimits(null)
        setCanCreateBranch(false)
      }
    } catch (error) {
      setMessage({
        type: 'error',
        text: error?.response?.data?.message || 'Unable to load branches.',
      })
    } finally {
      setLoading(false)
    }
  }, [canView, isBranchScoped])

  useEffect(() => {
    void loadBranches()
  }, [loadBranches])

  const assignedBranch = useMemo(() => {
    if (!isBranchScoped) return null
    if (lockedBranchId) {
      return branches.find((branch) => String(branch.id) === lockedBranchId) || branches[0] || null
    }
    return branches[0] || null
  }, [branches, isBranchScoped, lockedBranchId])

  const usageText = useMemo(() => {
    const used = limits?.branches_used ?? branches.length
    const max = limits?.max_branches
    return `${used} / ${formatLimit(max)} branches used`
  }, [branches.length, limits])

  const handleSaved = async (payload, branchId) => {
    if (branchId) {
      const saved = await updateBranch(branchId, payload)
      setBranches((prev) => prev.map((item) => (item.id === saved.id ? { ...item, ...saved } : item)))
      setMessage({ type: 'success', text: isBranchScoped ? 'Your branch details were updated.' : 'Branch updated successfully.' })
      return
    }

    const result = await createBranch(payload)
    setBranches((prev) => [result.branch, ...prev])
    await auth.fetchMe()
    await loadBranches()

    const summary = []
    if (result.manager) summary.push('manager account created')
    if (result.clonedCatalogItems > 0) summary.push(`${result.clonedCatalogItems} offerings copied`)
    if (result.clonedRoles > 0) summary.push(`${result.clonedRoles} custom roles copied`)

    setMessage({
      type: 'success',
      text: summary.length > 0
        ? `Branch created successfully, ${summary.join(', ')}.`
        : 'Branch created successfully.',
    })
  }

  return (
    <div className="space-y-6">
      {!canView ? (
        <div className="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
          You do not currently have branch-management view access.
        </div>
      ) : null}

      {!hideIntro ? (
        <div className="flex flex-col gap-4 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm md:flex-row md:items-center md:justify-between">
          <div>
            <h3 className="text-base font-bold text-slate-900">
              {isBranchScoped ? 'My Branch' : 'Branch Management'}
            </h3>
            <p className="mt-1 text-sm text-slate-500">
              {isBranchScoped
                ? 'View and update details for your assigned branch only.'
                : 'Create and manage your salon branches based on your current subscription limit.'}
            </p>
          </div>
          {canCreate ? (
            <BaseButton
              leftIcon={Plus}
              onClick={() => setModalState({ open: true, branch: null })}
              disabled={!canCreateBranch}
            >
              Add Branch
            </BaseButton>
          ) : null}
        </div>
      ) : canCreate ? (
        <div className="flex justify-end">
          <BaseButton
            leftIcon={Plus}
            onClick={() => setModalState({ open: true, branch: null })}
            disabled={!canCreateBranch}
          >
            Add Branch
          </BaseButton>
        </div>
      ) : null}

      {!isBranchScoped ? (
        <div className="grid gap-4 md:grid-cols-3">
          <div className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <p className="text-xs font-semibold uppercase tracking-wider text-slate-400">Plan Capacity</p>
            <p className="mt-2 text-2xl font-black text-slate-900">{formatLimit(limits?.max_branches)}</p>
            <p className="mt-1 text-sm text-slate-500">Maximum allowed branches on this subscription.</p>
          </div>
          <div className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <p className="text-xs font-semibold uppercase tracking-wider text-slate-400">Current Usage</p>
            <p className="mt-2 text-2xl font-black text-brand-700">{limits?.branches_used ?? branches.length}</p>
            <p className="mt-1 text-sm text-slate-500">{usageText}</p>
          </div>
          <div className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <p className="text-xs font-semibold uppercase tracking-wider text-slate-400">Provisioning</p>
            <p className="mt-2 text-sm font-semibold text-slate-800">New branches inherit access setup</p>
            <p className="mt-1 text-sm text-slate-500">Custom branch roles and offerings can be copied from an existing branch template.</p>
          </div>
        </div>
      ) : null}

      {!isBranchScoped && !canCreateBranch ? (
        <div className="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
          Branch limit reached for the current subscription. Existing branches can still be edited, but creating a new branch requires a higher plan.
        </div>
      ) : null}

      {message ? (
        <div className={`rounded-xl px-4 py-3 text-sm ${message.type === 'success' ? 'border border-emerald-200 bg-emerald-50 text-emerald-800' : 'border border-rose-200 bg-rose-50 text-rose-700'}`}>
          {message.text}
        </div>
      ) : null}

      {isBranchScoped ? (
        <div className="rounded-2xl border border-slate-200 bg-white shadow-sm">
          {loading ? (
            <div className="flex items-center justify-center gap-2 px-5 py-12 text-sm text-slate-500">
              <Loader2 className="h-4 w-4 animate-spin text-brand-600" />
              Loading your branch...
            </div>
          ) : assignedBranch ? (
            <div className="flex flex-col gap-4 px-5 py-5 lg:flex-row lg:items-start lg:justify-between">
              <div className="space-y-3">
                <div className="flex items-center gap-2">
                  <span className="inline-flex h-9 w-9 items-center justify-center rounded-xl bg-brand-50 text-brand-700">
                    <Building2 className="h-4 w-4" />
                  </span>
                  <div>
                    <p className="font-semibold text-slate-900">{assignedBranch.branch_name}</p>
                    <p className="text-sm text-slate-500">{assignedBranch.city}, {assignedBranch.state}</p>
                  </div>
                </div>

                <p className="flex items-start gap-2 text-sm text-slate-600">
                  <MapPin className="mt-0.5 h-4 w-4 shrink-0 text-slate-400" />
                  <span>
                    {assignedBranch.business_address_1}
                    {assignedBranch.business_address_2 ? `, ${assignedBranch.business_address_2}` : ''}
                    {assignedBranch.area_pincode ? `, ${assignedBranch.area_pincode}` : ''}
                    {assignedBranch.country ? `, ${assignedBranch.country}` : ''}
                  </span>
                </p>

                <span className={`inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold ${assignedBranch.is_active ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-600'}`}>
                  {assignedBranch.is_active ? 'Active' : 'Inactive'}
                </span>
              </div>

              {canUpdate ? (
                <BaseButton variant="secondary" size="sm" leftIcon={Pencil} onClick={() => setModalState({ open: true, branch: assignedBranch })}>
                  Edit Branch Details
                </BaseButton>
              ) : null}
            </div>
          ) : (
            <div className="px-5 py-14 text-center text-slate-500">
              <Building2 className="mx-auto mb-3 h-10 w-10 text-slate-300" />
              <p className="font-semibold text-slate-700">No branch assigned</p>
              <p className="mt-1 text-sm">Ask your salon owner to assign you to a branch.</p>
            </div>
          )}
        </div>
      ) : (
        <div className="rounded-2xl border border-slate-200 bg-white shadow-sm">
          <div className="border-b border-slate-100 px-5 py-4">
            <h4 className="text-sm font-semibold text-slate-900">Your branches</h4>
          </div>

          {loading ? (
            <div className="flex items-center justify-center gap-2 px-5 py-12 text-sm text-slate-500">
              <Loader2 className="h-4 w-4 animate-spin text-brand-600" />
              Loading branches...
            </div>
          ) : branches.length > 0 ? (
            <div className="divide-y divide-slate-100">
              {branches.map((branch) => (
                <div key={branch.id} className="flex flex-col gap-4 px-5 py-4 lg:flex-row lg:items-start lg:justify-between">
                  <div className="space-y-3">
                    <div className="flex items-center gap-2">
                      <span className="inline-flex h-9 w-9 items-center justify-center rounded-xl bg-brand-50 text-brand-700">
                        <Building2 className="h-4 w-4" />
                      </span>
                      <div>
                        <p className="font-semibold text-slate-900">{branch.branch_name}</p>
                        <p className="text-sm text-slate-500">{branch.city}, {branch.state}</p>
                      </div>
                    </div>

                    <div className="grid gap-2 text-sm text-slate-600 md:grid-cols-2">
                      <p className="flex items-start gap-2"><MapPin className="mt-0.5 h-4 w-4 text-slate-400" /> {branch.business_address_1}{branch.business_address_2 ? `, ${branch.business_address_2}` : ''}, {branch.area_pincode}</p>
                      <p className="flex items-center gap-2"><ShieldCheck className="h-4 w-4 text-slate-400" /> {branch.catalog_items_count ?? 0} offerings provisioned</p>
                      <p className="flex items-center gap-2"><UserRound className="h-4 w-4 text-slate-400" /> {branch.users_count ?? 0} users assigned</p>
                      <p className="text-sm">
                        <span className={`inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold ${branch.is_active ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-600'}`}>
                          {branch.is_active ? 'Active' : 'Inactive'}
                        </span>
                      </p>
                    </div>

                    {branch.manager ? (
                      <div className="rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-700">
                        <span className="font-semibold">Manager:</span> {branch.manager.name} ({branch.manager.email})
                      </div>
                    ) : null}
                  </div>

                  {canUpdate ? (
                    <BaseButton variant="secondary" size="sm" leftIcon={Pencil} onClick={() => setModalState({ open: true, branch })}>
                      Edit Branch
                    </BaseButton>
                  ) : null}
                </div>
              ))}
            </div>
          ) : (
            <div className="px-5 py-14 text-center text-slate-500">
              <Building2 className="mx-auto mb-3 h-10 w-10 text-slate-300" />
              <p className="font-semibold text-slate-700">No branches created yet</p>
              <p className="mt-1 text-sm">Your first branch will appear here once it is provisioned.</p>
            </div>
          )}
        </div>
      )}

      <BranchModal
        open={modalState.open}
        branch={modalState.branch}
        branches={branches}
        branchScoped={isBranchScoped}
        canSave={modalState.branch ? canUpdate : canCreate}
        onClose={() => setModalState({ open: false, branch: null })}
        onSaved={handleSaved}
      />
    </div>
  )
}
