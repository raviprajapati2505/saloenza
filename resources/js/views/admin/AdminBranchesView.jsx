import React, { useCallback, useEffect, useMemo, useState } from 'react'
import {
  Building2,
  CheckCircle2,
  ChevronLeft,
  ChevronRight,
  Loader2,
  MapPin,
  Pencil,
  Plus,
  Search,
  UserRound,
  X,
} from 'lucide-react'
import PageHeader from '../../components/ui/PageHeader.jsx'
import BaseButton from '../../components/ui/BaseButton.jsx'
import BaseInput from '../../components/ui/BaseInput.jsx'
import { useAuthStore } from '../../stores/auth'
import { PLATFORM_PERMISSIONS } from '../../lib/platformPermissions.js'
import { fetchMasterList } from '../../lib/apiHelpers.js'
import { formatLimit } from '../../lib/subscriptionModules.js'
import {
  createAdminBranch,
  fetchAdminBranches,
  fetchSalonBranchCapacity,
  updateAdminBranch,
} from '../../services/adminBranchService.js'

function salonLabel(salon) {
  if (!salon) return 'Unknown salon'
  return salon.business_name || salon.name || `Salon #${salon.id}`
}

const emptyForm = {
  saloon_id: '',
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

function BranchFormModal({
  open,
  branch,
  saloons,
  initialSalonId = '',
  onClose,
  onSaved,
  canCreate,
  canUpdate,
}) {
  const isEdit = Boolean(branch?.id)
  const [form, setForm] = useState(emptyForm)
  const [errors, setErrors] = useState({})
  const [saving, setSaving] = useState(false)
  const [salonCapacity, setSalonCapacity] = useState(null)
  const [capacityLoading, setCapacityLoading] = useState(false)
  const [templateBranches, setTemplateBranches] = useState([])

  useEffect(() => {
    if (!open) return

    if (isEdit) {
      setForm({
        ...emptyForm,
        saloon_id: branch?.saloon_id ? String(branch.saloon_id) : '',
        branch_name: branch?.branch_name || '',
        business_address_1: branch?.business_address_1 || '',
        business_address_2: branch?.business_address_2 || '',
        city: branch?.city || '',
        state: branch?.state || '',
        area_pincode: branch?.area_pincode || '',
        country: branch?.country || 'India',
        is_active: Boolean(branch?.is_active),
      })
      setSalonCapacity(null)
      setTemplateBranches([])
    } else {
      setForm({
        ...emptyForm,
        saloon_id: initialSalonId ? String(initialSalonId) : '',
      })
      setSalonCapacity(null)
      setTemplateBranches([])
    }
    setErrors({})
  }, [open, isEdit, branch, initialSalonId])

  useEffect(() => {
    if (!open || isEdit || !form.saloon_id) {
      setSalonCapacity(null)
      setTemplateBranches([])
      return
    }

    let cancelled = false
    setCapacityLoading(true)

    void Promise.all([
      fetchSalonBranchCapacity(Number(form.saloon_id)),
      fetchAdminBranches({ saloon_id: Number(form.saloon_id), page: 1, per_page: 100 }),
    ])
      .then(([capacity, branchData]) => {
        if (cancelled) return
        setSalonCapacity(capacity)
        setTemplateBranches(branchData.branches || [])
      })
      .catch(() => {
        if (cancelled) return
        setSalonCapacity(null)
        setTemplateBranches([])
      })
      .finally(() => {
        if (!cancelled) setCapacityLoading(false)
      })

    return () => {
      cancelled = true
    }
  }, [open, isEdit, form.saloon_id])

  if (!open) return null

  const templateOptions = templateBranches

  const atBranchLimit = salonCapacity?.can_create_branch === false
  const usageText = salonCapacity?.limits
    ? `${salonCapacity.limits.branches_used} / ${formatLimit(salonCapacity.limits.max_branches)} branches used`
    : null

  const handleSubmit = async (event) => {
    event.preventDefault()
    if (isEdit && !canUpdate) return
    if (!isEdit && !canCreate) return
    if (!isEdit && atBranchLimit) return

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
        is_active: Boolean(form.is_active),
      }

      if (!isEdit) {
        payload.saloon_id = Number(form.saloon_id)
        payload.template_branch_id = form.template_branch_id
          ? Number(form.template_branch_id)
          : null
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

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center p-4">
      <div className="absolute inset-0 bg-slate-900/40 backdrop-blur-sm" onClick={onClose} />
      <div className="relative w-full max-w-3xl rounded-2xl bg-white shadow-2xl">
        <div className="flex items-center justify-between border-b border-slate-100 px-6 py-5">
          <div>
            <h3 className="text-lg font-bold text-slate-900">
              {isEdit ? 'Update Branch' : 'Create Branch for Salon'}
            </h3>
            <p className="mt-1 text-sm text-slate-500">
              Platform can create branches on behalf of salons, but the salon branch limit is still enforced.
            </p>
          </div>
          <button
            type="button"
            onClick={onClose}
            className="rounded-lg p-2 text-slate-400 hover:bg-slate-100 hover:text-slate-600"
          >
            <X className="h-5 w-5" />
          </button>
        </div>

        <form onSubmit={handleSubmit} className="max-h-[85vh] overflow-y-auto p-6">
          <div className="space-y-5">
            {errors._general ? (
              <div className="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">
                {errors._general}
              </div>
            ) : null}

            {!isEdit ? (
              <div>
                <label className="mb-1.5 block text-sm font-medium text-slate-700">Salon / Tenant</label>
                <select
                  value={form.saloon_id}
                  onChange={(event) => setForm((prev) => ({
                    ...prev,
                    saloon_id: event.target.value,
                    template_branch_id: '',
                  }))}
                  className="block w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm"
                  required
                >
                  <option value="">Select salon</option>
                  {saloons.map((salon) => (
                    <option key={salon.id} value={salon.id}>{salonLabel(salon)}</option>
                  ))}
                </select>
                {errors.saloon_id ? <p className="mt-1 text-xs text-rose-500">{errors.saloon_id}</p> : null}
                {saloons.length === 0 ? (
                  <p className="mt-1 text-xs text-amber-600">
                    No salons available to select. Check that salons exist and salon listing access is granted.
                  </p>
                ) : null}
                {form.saloon_id ? (
                  <div className="mt-3 rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-700">
                    {capacityLoading ? (
                      <p className="flex items-center gap-2 text-slate-500">
                        <Loader2 className="h-4 w-4 animate-spin text-brand-600" />
                        Checking salon branch capacity...
                      </p>
                    ) : salonCapacity ? (
                      <>
                        <p>
                          <span className="font-semibold">Plan:</span>{' '}
                          {salonCapacity.plan?.name || 'No active plan'}
                        </p>
                        <p className="mt-1">
                          <span className="font-semibold">Branch usage:</span> {usageText}
                        </p>
                      </>
                    ) : (
                      <p className="text-slate-500">Unable to load branch capacity for this salon.</p>
                    )}
                  </div>
                ) : null}
                {atBranchLimit ? (
                  <div className="mt-3 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                    Branch limit reached for this salon&apos;s current subscription plan.
                    Upgrade the salon plan before creating another branch.
                  </div>
                ) : null}
              </div>
            ) : (
              <div className="rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-700">
                <span className="font-semibold">Salon:</span>{' '}
                {branch?.saloon?.name || salonLabel(branch?.saloon) || `Salon #${branch?.saloon_id}`}
              </div>
            )}

            <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
              <BaseInput
                label="Branch Name"
                modelValue={form.branch_name}
                onUpdateModelValue={(value) => setForm((prev) => ({ ...prev, branch_name: value }))}
                error={errors.branch_name}
                required
              />
              <BaseInput
                label="Country"
                modelValue={form.country}
                onUpdateModelValue={(value) => setForm((prev) => ({ ...prev, country: value }))}
                error={errors.country}
              />
            </div>

            <BaseInput
              label="Address Line 1"
              modelValue={form.business_address_1}
              onUpdateModelValue={(value) => setForm((prev) => ({ ...prev, business_address_1: value }))}
              error={errors.business_address_1}
              required
            />
            <BaseInput
              label="Address Line 2"
              modelValue={form.business_address_2}
              onUpdateModelValue={(value) => setForm((prev) => ({ ...prev, business_address_2: value }))}
              error={errors.business_address_2}
            />

            <div className="grid grid-cols-1 gap-4 md:grid-cols-3">
              <BaseInput label="City" modelValue={form.city} onUpdateModelValue={(value) => setForm((prev) => ({ ...prev, city: value }))} error={errors.city} required />
              <BaseInput label="State" modelValue={form.state} onUpdateModelValue={(value) => setForm((prev) => ({ ...prev, state: value }))} error={errors.state} required />
              <BaseInput label="Pincode" modelValue={form.area_pincode} onUpdateModelValue={(value) => setForm((prev) => ({ ...prev, area_pincode: value }))} error={errors.area_pincode} required />
            </div>

            {!isEdit && form.saloon_id ? (
              <div>
                <label className="mb-1.5 block text-sm font-medium text-slate-700">
                  Copy offerings / custom roles from
                </label>
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
                      <BaseInput label="Manager First Name" modelValue={form.manager_firstname} onUpdateModelValue={(value) => setForm((prev) => ({ ...prev, manager_firstname: value }))} error={errors['manager.firstname']} required />
                      <BaseInput label="Manager Last Name" modelValue={form.manager_lastname} onUpdateModelValue={(value) => setForm((prev) => ({ ...prev, manager_lastname: value }))} error={errors['manager.lastname']} required />
                    </div>
                    <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                      <BaseInput label="Manager Email" type="email" modelValue={form.manager_email} onUpdateModelValue={(value) => setForm((prev) => ({ ...prev, manager_email: value }))} error={errors['manager.email']} required />
                      <BaseInput label="Manager Phone" modelValue={form.manager_phone} onUpdateModelValue={(value) => setForm((prev) => ({ ...prev, manager_phone: value }))} error={errors['manager.phone']} required />
                    </div>
                    <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                      <BaseInput label="Temporary Password" type="password" toggleableType modelValue={form.manager_password} onUpdateModelValue={(value) => setForm((prev) => ({ ...prev, manager_password: value }))} error={errors['manager.password']} />
                      <BaseInput label="Confirm Password" type="password" toggleableType modelValue={form.manager_password_confirmation} onUpdateModelValue={(value) => setForm((prev) => ({ ...prev, manager_password_confirmation: value }))} error={errors['manager.password_confirmation']} />
                    </div>
                  </div>
                ) : null}
              </div>
            ) : null}

            <label className="flex items-center gap-3 rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm font-medium text-slate-700">
              <input
                type="checkbox"
                checked={form.is_active}
                onChange={(event) => setForm((prev) => ({ ...prev, is_active: event.target.checked }))}
                className="rounded border-slate-300 text-brand-600"
              />
              Active branch
            </label>
          </div>

          <div className="mt-6 flex flex-col-reverse gap-2 border-t border-slate-100 pt-5 sm:flex-row sm:justify-end sm:gap-3">
            <BaseButton type="button" variant="secondary" className="w-full sm:w-auto" onClick={onClose}>Cancel</BaseButton>
            <BaseButton
              type="submit"
              className="w-full sm:w-auto"
              loading={saving}
              leftIcon={saving ? Loader2 : CheckCircle2}
              disabled={!isEdit && atBranchLimit}
            >
              {isEdit ? 'Save Changes' : 'Create Branch'}
            </BaseButton>
          </div>
        </form>
      </div>
    </div>
  )
}

export default function AdminBranchesView() {
  const auth = useAuthStore()
  const canCreate = auth.canPlatform(PLATFORM_PERMISSIONS.BRANCHES_CREATE) || auth.grantsAllPermissions
  const canUpdate = auth.canPlatform(PLATFORM_PERMISSIONS.BRANCHES_UPDATE) || auth.grantsAllPermissions

  const [branches, setBranches] = useState([])
  const [saloons, setSaloons] = useState([])
  const [saloonsLoading, setSaloonsLoading] = useState(true)
  const [loading, setLoading] = useState(true)
  const [search, setSearch] = useState('')
  const [debouncedSearch, setDebouncedSearch] = useState('')
  const [salonFilter, setSalonFilter] = useState('')
  const [page, setPage] = useState(1)
  const [perPage, setPerPage] = useState(25)
  const [meta, setMeta] = useState({ current_page: 1, per_page: 25, total: 0, last_page: 1 })
  const [filterCapacity, setFilterCapacity] = useState(null)
  const [filterCapacityLoading, setFilterCapacityLoading] = useState(false)
  const [message, setMessage] = useState(null)
  const [modalState, setModalState] = useState({ open: false, branch: null })

  useEffect(() => {
    const timer = setTimeout(() => setDebouncedSearch(search.trim()), search ? 300 : 0)
    return () => clearTimeout(timer)
  }, [search])

  useEffect(() => {
    setPage(1)
  }, [debouncedSearch, salonFilter, perPage])

  const loadBranches = useCallback(async () => {
    setLoading(true)
    try {
      const data = await fetchAdminBranches({
        search: debouncedSearch || undefined,
        saloon_id: salonFilter ? Number(salonFilter) : undefined,
        page,
        per_page: perPage,
      })
      setBranches(data.branches)
      setMeta(data.meta ?? { current_page: page, per_page: perPage, total: data.branches.length, last_page: 1 })
    } catch (error) {
      setMessage({
        type: 'error',
        text: error?.response?.data?.message || 'Unable to load platform branches.',
      })
    } finally {
      setLoading(false)
    }
  }, [salonFilter, debouncedSearch, page, perPage])

  const loadSaloons = useCallback(async () => {
    setSaloonsLoading(true)
    try {
      const rows = await fetchMasterList('/v1/saloons', 'saloons', { is_active: 1 })
      setSaloons(rows)
    } catch (error) {
      setSaloons([])
      setMessage({
        type: 'error',
        text: error?.response?.data?.message || 'Unable to load salons for branch assignment.',
      })
    } finally {
      setSaloonsLoading(false)
    }
  }, [])

  const loadFilterCapacity = useCallback(async () => {
    if (!salonFilter) {
      setFilterCapacity(null)
      return
    }

    setFilterCapacityLoading(true)
    try {
      const data = await fetchSalonBranchCapacity(Number(salonFilter))
      setFilterCapacity(data)
    } catch {
      setFilterCapacity(null)
    } finally {
      setFilterCapacityLoading(false)
    }
  }, [salonFilter])

  useEffect(() => {
    void loadBranches()
  }, [loadBranches])

  useEffect(() => {
    void loadSaloons()
  }, [loadSaloons])

  useEffect(() => {
    void loadFilterCapacity()
  }, [loadFilterCapacity])

  const filteredSalonAtLimit = filterCapacity?.can_create_branch === false
  const filteredSalonUsage = useMemo(() => {
    if (!filterCapacity?.limits) return null
    return `${filterCapacity.limits.branches_used} / ${formatLimit(filterCapacity.limits.max_branches)} branches used`
  }, [filterCapacity])

  const handleSaved = async (payload, branchId) => {
    if (branchId) {
      const saved = await updateAdminBranch(branchId, payload)
      setBranches((prev) => prev.map((item) => (item.id === saved.id ? { ...item, ...saved } : item)))
      setMessage({ type: 'success', text: 'Branch updated successfully.' })
      return
    }

    const result = await createAdminBranch(payload)
    setMessage({
      type: 'success',
      text: result.manager
        ? 'Branch created for salon with manager account.'
        : 'Branch created for salon successfully.',
    })
    await Promise.all([loadBranches(), loadFilterCapacity()])
  }

  return (
    <div className="space-y-6">
      <PageHeader
        title="Platform Branches"
        subtitle="Full cross-tenant branch listing. Create or update branches on behalf of salons while respecting each salon plan's branch limit."
        actions={canCreate ? (
          <BaseButton
            leftIcon={Plus}
            disabled={filteredSalonAtLimit}
            onClick={() => setModalState({ open: true, branch: null })}
          >
            Create Branch
          </BaseButton>
        ) : null}
      />

      <div className="flex flex-col gap-3 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm md:flex-row md:items-center">
        <div className="relative flex-1">
          <Search className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
          <input
            value={search}
            onChange={(event) => setSearch(event.target.value)}
            placeholder="Search branch or salon name..."
            className="w-full rounded-xl border border-slate-300 py-2.5 pl-10 pr-3 text-sm"
          />
        </div>
        <select
          value={salonFilter}
          onChange={(event) => setSalonFilter(event.target.value)}
          className="rounded-xl border border-slate-300 px-3 py-2.5 text-sm"
          disabled={saloonsLoading}
        >
          <option value="">All salons</option>
          {saloons.map((salon) => (
            <option key={salon.id} value={salon.id}>{salonLabel(salon)}</option>
          ))}
        </select>
      </div>

      {salonFilter ? (
        <div className={`rounded-xl px-4 py-3 text-sm ${filteredSalonAtLimit ? 'border border-amber-200 bg-amber-50 text-amber-800' : 'border border-slate-200 bg-slate-50 text-slate-700'}`}>
          {filterCapacityLoading ? (
            <p className="flex items-center gap-2">
              <Loader2 className="h-4 w-4 animate-spin text-brand-600" />
              Checking selected salon branch capacity...
            </p>
          ) : filterCapacity ? (
            <p>
              <span className="font-semibold">{filterCapacity.saloon_name}</span>
              {' · '}
              Plan: {filterCapacity.plan?.name || 'No active plan'}
              {' · '}
              {filteredSalonUsage}
              {filteredSalonAtLimit ? ' · Branch limit reached. Upgrade this salon plan to create another branch.' : ''}
            </p>
          ) : (
            <p>Unable to load branch capacity for the selected salon.</p>
          )}
        </div>
      ) : null}

      {message ? (
        <div className={`rounded-xl px-4 py-3 text-sm ${message.type === 'success' ? 'border border-emerald-200 bg-emerald-50 text-emerald-800' : 'border border-rose-200 bg-rose-50 text-rose-700'}`}>
          {message.text}
        </div>
      ) : null}

      <div className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div className="border-b border-slate-100 px-5 py-4">
          <h4 className="text-sm font-semibold text-slate-900">All salon branches</h4>
        </div>

        {loading ? (
          <div className="flex items-center justify-center gap-2 px-5 py-12 text-sm text-slate-500">
            <Loader2 className="h-4 w-4 animate-spin text-brand-600" />
            Loading branches...
          </div>
        ) : branches.length > 0 ? (
          <>
            <div className="overflow-x-auto">
              <table className="min-w-full divide-y divide-slate-100 text-sm">
                <thead className="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">
                  <tr>
                    <th className="px-5 py-3">Salon</th>
                    <th className="px-5 py-3">Branch</th>
                    <th className="px-5 py-3">Location</th>
                    <th className="px-5 py-3">Manager</th>
                    <th className="px-5 py-3">Status</th>
                    <th className="px-5 py-3">Counts</th>
                    <th className="px-5 py-3" />
                  </tr>
                </thead>
                <tbody className="divide-y divide-slate-100">
                  {branches.map((branch) => (
                    <tr key={branch.id} className="align-top">
                      <td className="px-5 py-4">
                        <div className="flex items-start gap-2">
                          <Building2 className="mt-0.5 h-4 w-4 text-brand-600" />
                          <div>
                            <p className="font-semibold text-slate-900">{salonLabel(branch.saloon) || `Salon #${branch.saloon_id}`}</p>
                            <p className="text-xs text-slate-500">Tenant ID: {branch.saloon_id}</p>
                          </div>
                        </div>
                      </td>
                      <td className="px-5 py-4">
                        <p className="font-semibold text-slate-900">{branch.branch_name}</p>
                        <p className="text-xs text-slate-500">Branch ID: {branch.id}</p>
                      </td>
                      <td className="px-5 py-4 text-slate-600">
                        <p className="flex items-start gap-2">
                          <MapPin className="mt-0.5 h-4 w-4 text-slate-400" />
                          <span>
                            {branch.business_address_1}
                            {branch.business_address_2 ? `, ${branch.business_address_2}` : ''}
                            <br />
                            {branch.city}, {branch.state} {branch.area_pincode}
                          </span>
                        </p>
                      </td>
                      <td className="px-5 py-4 text-slate-600">
                        {branch.manager ? (
                          <p className="flex items-start gap-2">
                            <UserRound className="mt-0.5 h-4 w-4 text-slate-400" />
                            <span>
                              {branch.manager.name}
                              <br />
                              <span className="text-xs">{branch.manager.email}</span>
                            </span>
                          </p>
                        ) : (
                          <span className="text-slate-400">—</span>
                        )}
                      </td>
                      <td className="px-5 py-4">
                        <span className={`inline-flex rounded-full px-2.5 py-1 text-xs font-semibold ${branch.is_active ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-600'}`}>
                          {branch.is_active ? 'Active' : 'Inactive'}
                        </span>
                      </td>
                      <td className="px-5 py-4 text-slate-600">
                        <p>{branch.users_count ?? 0} users</p>
                        <p>{branch.catalog_items_count ?? 0} offerings</p>
                      </td>
                      <td className="px-5 py-4 text-right">
                        {canUpdate ? (
                          <BaseButton
                            variant="secondary"
                            size="sm"
                            leftIcon={Pencil}
                            onClick={() => setModalState({ open: true, branch })}
                          >
                            Edit
                          </BaseButton>
                        ) : null}
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
            <div className="flex flex-col gap-3 border-t border-slate-100 bg-slate-50/50 px-5 py-3 sm:flex-row sm:items-center sm:justify-between">
              <div className="flex items-center justify-between gap-2 sm:justify-start">
                <span className="text-sm font-medium text-slate-500">Rows per page:</span>
                <select
                  value={perPage}
                  onChange={(event) => setPerPage(Number(event.target.value))}
                  className="cursor-pointer rounded-lg border border-slate-200 bg-white px-2 py-1 text-sm font-medium text-slate-700 shadow-sm outline-none focus:border-brand-500"
                >
                  {[10, 25, 50, 100].map((n) => (
                    <option key={n} value={n}>{n}</option>
                  ))}
                </select>
              </div>
              <div className="flex items-center justify-between gap-4 sm:justify-end">
                <span className="text-sm font-medium text-slate-500">
                  {meta.total === 0
                    ? '0 of 0'
                    : `${((meta.current_page - 1) * meta.per_page) + 1}–${Math.min(meta.current_page * meta.per_page, meta.total)} of ${meta.total}`}
                </span>
                <div className="flex items-center gap-1">
                  <button
                    type="button"
                    disabled={page <= 1}
                    onClick={() => setPage((prev) => Math.max(prev - 1, 1))}
                    className="rounded-md p-1.5 text-slate-400 transition-colors hover:bg-slate-200 hover:text-slate-700 disabled:opacity-30 disabled:hover:bg-transparent"
                  >
                    <ChevronLeft className="h-4 w-4" />
                  </button>
                  <button
                    type="button"
                    disabled={page >= (meta.last_page || 1)}
                    onClick={() => setPage((prev) => Math.min(prev + 1, meta.last_page || 1))}
                    className="rounded-md p-1.5 text-slate-400 transition-colors hover:bg-slate-200 hover:text-slate-700 disabled:opacity-30 disabled:hover:bg-transparent"
                  >
                    <ChevronRight className="h-4 w-4" />
                  </button>
                </div>
              </div>
            </div>
          </>
        ) : (
          <div className="px-5 py-14 text-center text-slate-500">
            <Building2 className="mx-auto mb-3 h-10 w-10 text-slate-300" />
            <p className="font-semibold text-slate-700">No branches found</p>
            <p className="mt-1 text-sm">Create a branch on behalf of a salon to get started.</p>
          </div>
        )}
      </div>

      <BranchFormModal
        open={modalState.open}
        branch={modalState.branch}
        saloons={saloons}
        initialSalonId={salonFilter}
        onClose={() => setModalState({ open: false, branch: null })}
        onSaved={handleSaved}
        canCreate={canCreate}
        canUpdate={canUpdate}
      />
    </div>
  )
}
