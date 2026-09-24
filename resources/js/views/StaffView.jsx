import React, { useState, useCallback, useEffect, useMemo } from 'react'
import { Link } from 'react-router-dom'
import { motion } from 'framer-motion'
import { Users, Award, ShieldAlert, CheckCircle2, Plus, X, LayoutGrid, List } from 'lucide-react'
import PageHeader from '../components/ui/PageHeader.jsx'
import BaseButton from '../components/ui/BaseButton.jsx'
import BaseInput from '../components/ui/BaseInput.jsx'
import BaseBadge from '../components/ui/BaseBadge.jsx'
import PhoneWithCountryInput from '../components/inputs/PhoneWithCountryInput.jsx'
import StaffCard from '../components/staff/StaffCard.jsx'
// Skills Matrix is UI-only (no skills table/API) — hidden for now.
// import SkillsMatrix from '../components/staff/SkillsMatrix.jsx'
import StaffProfileView from './staff/StaffProfileView.jsx'
import { useAuthStore } from '../stores/auth'
import { TENANT_PERMISSIONS } from '../lib/tenantPermissions'
import { authHasModule, subscriptionPageSubtitle } from '../lib/subscriptionModules.js'
import { apiGet, apiPost, apiPut, parseItem, parseList } from '../lib/apiHelpers'
import { currencySymbol } from '../lib/tenantFormatting.js'

const STAFF_LAYOUT_KEY = 'staff.layoutMode'

function mapStaffForCard(member) {
  return {
    id: member.id,
    name: member.name,
    email: member.email,
    phone: member.phone,
    role: member.role?.name || 'Staff',
    roleId: member.role_id ?? member.role?.id ?? null,
    saloon: member.saloon?.name || null,
    branch: member.branch?.branch_name || null,
    joinDate: member.joined_at
      ? new Date(member.joined_at).toLocaleDateString('en-IN', { month: 'short', year: 'numeric' })
      : member.created_at
        ? new Date(member.created_at).toLocaleDateString('en-IN', { month: 'short', year: 'numeric' })
        : 'Recently',
    status: member.is_active ? 'available' : 'day-off',
    is_active: Boolean(member.is_active),
    photo: member.photo_url || member.photo,
    raw: member,
  }
}

function groupStaffByRole(staff) {
  const groups = new Map()

  for (const member of staff) {
    const roleName = member.role || 'Staff'
    const key = member.roleId != null ? `id:${member.roleId}` : `name:${roleName}`
    if (!groups.has(key)) {
      groups.set(key, { role: roleName, members: [] })
    }
    groups.get(key).members.push(member)
  }

  return Array.from(groups.values())
    .map((group) => ({
      ...group,
      members: [...group.members].sort((a, b) => String(a.name || '').localeCompare(String(b.name || ''))),
    }))
    .sort((a, b) => String(a.role).localeCompare(String(b.role)))
}

function getInitials(name) {
  return name
    ? name
        .split(' ')
        .map((n) => n[0])
        .join('')
        .toUpperCase()
        .slice(0, 2)
    : 'ST'
}

function StaffListRow({ staff, onClick, showSaloon = false, showBranch = false }) {
  const isActive = staff.status === 'available' || staff.is_active

  return (
    <tr
      onClick={() => onClick?.(staff.id)}
      className="cursor-pointer border-b border-slate-100 last:border-b-0 hover:bg-slate-50/80 transition-colors"
    >
      <td className="px-4 py-3">
        <div className="flex items-center gap-3 min-w-0">
          {staff.photo ? (
            <img src={staff.photo} alt={staff.name} className="h-9 w-9 rounded-lg object-cover border border-slate-100" />
          ) : (
            <div className="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-brand-600 text-xs font-bold text-brand-50">
              {getInitials(staff.name)}
            </div>
          )}
          <div className="min-w-0">
            <p className="truncate text-sm font-semibold text-slate-900">{staff.name}</p>
            <p className="truncate text-xs text-slate-400">Joined {staff.joinDate || 'recently'}</p>
          </div>
        </div>
      </td>
      <td className="px-4 py-3 text-sm text-slate-600">
        <p className="truncate">{staff.email || '—'}</p>
        <p className="truncate text-xs text-slate-400">{staff.phone || ''}</p>
      </td>
      {showSaloon ? (
        <td className="px-4 py-3 text-sm text-slate-600">{staff.saloon || '—'}</td>
      ) : null}
      {showBranch ? (
        <td className="px-4 py-3 text-sm text-slate-600">{staff.branch || '—'}</td>
      ) : null}
      <td className="px-4 py-3">
        <BaseBadge variant={isActive ? 'success' : 'default'} size="sm">
          {isActive ? 'Active' : 'Inactive'}
        </BaseBadge>
      </td>
    </tr>
  )
}

function StaffRoleGroups({
  groups,
  layoutMode,
  onSelect,
  showSaloon,
  showBranch,
}) {
  if (groups.length === 0) return null

  return (
    <div className="space-y-8">
      {groups.map((group) => (
        <section key={group.role} className="space-y-3">
          <div className="flex items-center justify-between gap-3">
            <div className="flex items-center gap-2 min-w-0">
              <h3 className="text-sm font-bold text-slate-800 tracking-tight">{group.role}</h3>
              <span className="inline-flex items-center rounded-full bg-slate-100 px-2 py-0.5 text-[11px] font-semibold text-slate-500">
                {group.members.length}
              </span>
            </div>
          </div>

          {layoutMode === 'cards' ? (
            <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
              {group.members.map((staff) => (
                <StaffCard
                  key={staff.id}
                  staff={staff}
                  onClick={onSelect}
                  showSaloon={showSaloon}
                  showBranch={showBranch}
                />
              ))}
            </div>
          ) : (
            <div className="overflow-hidden rounded-2xl border border-slate-200/80 bg-white shadow-sm">
              <div className="overflow-x-auto">
                <table className="min-w-full text-left">
                  <thead className="bg-slate-50/80 border-b border-slate-100">
                    <tr className="text-[11px] font-bold uppercase tracking-wider text-slate-400">
                      <th className="px-4 py-3">Staff</th>
                      <th className="px-4 py-3">Contact</th>
                      {showSaloon ? <th className="px-4 py-3">Salon</th> : null}
                      {showBranch ? <th className="px-4 py-3">Branch</th> : null}
                      <th className="px-4 py-3">Status</th>
                    </tr>
                  </thead>
                  <tbody>
                    {group.members.map((staff) => (
                      <StaffListRow
                        key={staff.id}
                        staff={staff}
                        onClick={onSelect}
                        showSaloon={showSaloon}
                        showBranch={showBranch}
                      />
                    ))}
                  </tbody>
                </table>
              </div>
            </div>
          )}
        </section>
      ))}
    </div>
  )
}

function StaffFormModal({ open, staff, roles, saloons, branches, isSystemAdmin, isBranchScoped, lockedBranchId, onClose, onSaved, canManage, showRolesUpgradeHint = false }) {
  const auth = useAuthStore()
  const salarySymbol = currencySymbol(auth)
  const [form, setForm] = useState({
    firstname: '',
    lastname: '',
    email: '',
    phone: '',
    whatsapp: '',
    password: '',
    password_confirmation: '',
    role_id: '',
    saloon_id: '',
    branch_id: '',
    notes: '',
    commission_rate: '',
    per_month_salary: '',
    is_active: true,
  })
  const [errors, setErrors] = useState({})
  const [saving, setSaving] = useState(false)
  const isEdit = Boolean(staff?.id)
  const filteredRoles = isBranchScoped
    ? roles.filter((role) => role.scope === 'branch')
    : roles

  useEffect(() => {
    if (open) {
      setForm({
        firstname: staff?.firstname || staff?.name?.split(' ')[0] || '',
        lastname: staff?.lastname || staff?.name?.split(' ').slice(1).join(' ') || '',
        email: staff?.email || '',
        phone: staff?.phone || '',
        whatsapp: staff?.whatsapp || '',
        password: '',
        password_confirmation: '',
        role_id: staff?.role_id ? String(staff.role_id) : (filteredRoles[0]?.id ? String(filteredRoles[0].id) : ''),
        saloon_id: staff?.saloon_id ? String(staff.saloon_id) : (saloons[0]?.id ? String(saloons[0].id) : ''),
        branch_id: isBranchScoped && lockedBranchId
          ? String(lockedBranchId)
          : (staff?.branch_id ? String(staff.branch_id) : ''),
        notes: staff?.notes || '',
        commission_rate: staff?.commission_rate != null ? String(staff.commission_rate) : '',
        per_month_salary: staff?.per_month_salary != null ? String(staff.per_month_salary) : '',
        is_active: staff?.is_active ?? true,
      })
      setErrors({})
    }
  }, [open, staff, filteredRoles, saloons, isBranchScoped, lockedBranchId])

  if (!open || !canManage) return null

  const filteredBranches = isBranchScoped && lockedBranchId
    ? branches.filter((b) => String(b.id) === String(lockedBranchId))
    : (form.saloon_id
      ? branches.filter((b) => String(b.saloon_id) === String(form.saloon_id))
      : branches)

  const handleSubmit = async (e) => {
    e.preventDefault()
    if (!canManage) return
    setErrors({})
    setSaving(true)
    try {
      const payload = {
        firstname: form.firstname.trim(),
        lastname: form.lastname.trim(),
        email: form.email.trim(),
        phone: form.phone.trim(),
        whatsapp: form.whatsapp.trim() || null,
        role_id: Number(form.role_id),
        is_active: Boolean(form.is_active),
        notes: form.notes.trim() || null,
        branch_id: form.branch_id ? Number(form.branch_id) : null,
      }
      if (form.commission_rate !== '') {
        payload.commission_rate = Number(form.commission_rate)
      }
      if (form.per_month_salary !== '') {
        payload.per_month_salary = Number(form.per_month_salary)
      }
      if (isSystemAdmin) {
        payload.saloon_id = Number(form.saloon_id)
      }
      if (!isEdit || form.password) {
        payload.password = form.password
        payload.password_confirmation = form.password_confirmation
      }
      const response = isEdit
        ? await apiPut(`/v1/staff/${staff.id}`, payload)
        : await apiPost('/v1/staff', payload)
      onSaved(parseItem(response, 'staff'))
      onClose()
    } catch (error) {
      const serverErrors = error?.response?.data?.errors || {}
      if (Object.keys(serverErrors).length) {
        const mapped = {}
        for (const key in serverErrors) mapped[key] = serverErrors[key][0]
        setErrors(mapped)
      } else {
        setErrors({ _general: error?.response?.data?.message || 'Failed to save staff member.' })
      }
    } finally {
      setSaving(false)
    }
  }

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center p-4">
      <div className="absolute inset-0 bg-slate-900/40 backdrop-blur-sm" onClick={onClose} />
      <motion.div initial={{ opacity: 0, scale: 0.95 }} animate={{ opacity: 1, scale: 1 }}
        className="relative w-full max-w-lg bg-white rounded-2xl shadow-2xl overflow-hidden max-h-[90vh] overflow-y-auto">
        <div className="px-6 py-5 border-b border-slate-100 flex items-center justify-between sticky top-0 bg-white">
          <h3 className="text-lg font-bold">{isEdit ? 'Edit Staff' : 'Add Staff Member'}</h3>
          <button type="button" onClick={onClose}><X className="w-5 h-5 text-slate-400" /></button>
        </div>
        <form onSubmit={handleSubmit} className="p-6 space-y-4">
          <fieldset disabled={!canManage} className="space-y-4 disabled:opacity-80">
          {errors._general && <div className="p-3 text-sm text-rose-700 bg-rose-50 border border-rose-200 rounded-xl">{errors._general}</div>}
          <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <BaseInput label="First Name" modelValue={form.firstname} onUpdateModelValue={v => setForm({ ...form, firstname: v })} error={errors.firstname} placeholder="Enter first name" />
            <BaseInput label="Last Name" modelValue={form.lastname} onUpdateModelValue={v => setForm({ ...form, lastname: v })} error={errors.lastname} placeholder="Enter last name" />
          </div>
          <BaseInput label="Email" modelValue={form.email} onUpdateModelValue={v => setForm({ ...form, email: v })} error={errors.email} placeholder="name@example.com" />
          <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <PhoneWithCountryInput
              label="Phone"
              required
              value={form.phone}
              onChange={(value) => setForm({ ...form, phone: value })}
              error={errors.phone}
            />
            <PhoneWithCountryInput
              label="WhatsApp"
              optional
              value={form.whatsapp}
              onChange={(value) => setForm({ ...form, whatsapp: value })}
              error={errors.whatsapp}
              placeholder="WhatsApp number"
            />
          </div>
          {isSystemAdmin && (
            <div>
              <label className="block text-sm font-semibold text-slate-700 mb-1.5">Salon</label>
              <select
                value={form.saloon_id}
                onChange={e => setForm({ ...form, saloon_id: e.target.value, branch_id: '' })}
                className="block w-full rounded-xl border border-slate-300 py-2.5 px-3 text-sm"
              >
                <option value="">Select salon...</option>
                {saloons.map(s => (
                  <option key={s.id} value={s.id}>{s.name}</option>
                ))}
              </select>
              {errors.saloon_id && <p className="mt-1 text-xs text-rose-500">{errors.saloon_id}</p>}
            </div>
          )}
          {filteredBranches.length > 0 && (
            <div>
              <label className="block text-sm font-semibold text-slate-700 mb-1.5">Branch</label>
              <select
                value={form.branch_id}
                onChange={e => setForm({ ...form, branch_id: e.target.value })}
                className="block w-full rounded-xl border border-slate-300 py-2.5 px-3 text-sm"
                disabled={isBranchScoped}
              >
                {!isBranchScoped ? <option value="">No branch</option> : null}
                {filteredBranches.map(b => (
                  <option key={b.id} value={b.id}>{b.branch_name}</option>
                ))}
              </select>
              {errors.branch_id && <p className="mt-1 text-xs text-rose-500">{errors.branch_id}</p>}
            </div>
          )}
          <div>
            <label className="block text-sm font-semibold text-slate-700 mb-1.5">Role</label>
            <select value={form.role_id} onChange={e => setForm({ ...form, role_id: e.target.value })}
              className="block w-full rounded-xl border border-slate-300 py-2.5 px-3 text-sm">
              <option value="">Select role...</option>
              {filteredRoles.map(r => (
                <option key={r.id} value={r.id}>
                  {r.name}{r.code ? ` (${r.code})` : ''}
                </option>
              ))}
            </select>
            {errors.role_id && <p className="mt-1 text-xs text-rose-500">{errors.role_id}</p>}
            {showRolesUpgradeHint ? (
              <p className="mt-1.5 text-xs text-slate-500">
                Assign from existing roles.{' '}
                <Link to="/billing" className="font-semibold text-brand-600 hover:text-brand-700 underline underline-offset-2">
                  Upgrade your plan
                </Link>{' '}
                to create and manage custom roles.
              </p>
            ) : null}
          </div>
          <BaseInput label="Notes" modelValue={form.notes} onUpdateModelValue={v => setForm({ ...form, notes: v })} error={errors.notes} placeholder="Optional notes" />
          <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <BaseInput
              label="Commission rate (%)"
              type="number"
              modelValue={form.commission_rate}
              onUpdateModelValue={v => setForm({ ...form, commission_rate: v })}
              error={errors.commission_rate}
              placeholder="e.g. 20"
            />
            <BaseInput
              label={`Monthly salary (${salarySymbol})`}
              type="number"
              modelValue={form.per_month_salary}
              onUpdateModelValue={v => setForm({ ...form, per_month_salary: v })}
              error={errors.per_month_salary}
              placeholder="Optional fixed salary"
            />
          </div>
          {(!isEdit || form.password) && (
            <>
              <BaseInput label="Password" type="password" modelValue={form.password} onUpdateModelValue={v => setForm({ ...form, password: v })} error={errors.password} placeholder="Create a password" />
              <BaseInput label="Confirm Password" type="password" modelValue={form.password_confirmation} onUpdateModelValue={v => setForm({ ...form, password_confirmation: v })} error={errors.password_confirmation} placeholder="Re-enter password" />
            </>
          )}
          <label className="flex items-center gap-2 text-sm font-medium text-slate-700">
            <input type="checkbox" checked={form.is_active} onChange={e => setForm({ ...form, is_active: e.target.checked })} className="rounded border-slate-300 text-brand-600" />
            Active staff member
          </label>
          <div className="flex gap-3 pt-2">
            <BaseButton type="button" variant="secondary" className="flex-1" onClick={onClose}>Cancel</BaseButton>
            <BaseButton type="submit" className="flex-1" disabled={saving || !canManage}>{saving ? 'Saving...' : 'Save'}</BaseButton>
          </div>
          </fieldset>
        </form>
      </motion.div>
    </div>
  )
}

export default function StaffView() {
  const auth = useAuthStore()
  const isSystemAdmin = Boolean(auth.isSystemAdmin || auth.grantsAllPermissions)
  const isBranchScoped = Boolean(auth.isBranchScoped)
  const lockedBranchId = auth.user?.branch_id ? String(auth.user.branch_id) : ''
  const canCreate = auth.can(TENANT_PERMISSIONS.STAFF_CREATE)
  const canUpdate = auth.can(TENANT_PERMISSIONS.STAFF_UPDATE)
  const canManage = canCreate || canUpdate
  const hasRolesModule = authHasModule(auth, 'roles')

  const [staffList, setStaffList] = useState([])
  const [roles, setRoles] = useState([])
  const [saloons, setSaloons] = useState([])
  const [branches, setBranches] = useState([])
  const [saloonFilter, setSaloonFilter] = useState('')
  const [branchFilter, setBranchFilter] = useState(lockedBranchId)
  const [isLoading, setIsLoading] = useState(true)
  const [selectedStaffId, setSelectedStaffId] = useState(null)
  const [formModal, setFormModal] = useState({ open: false, staff: null })
  const [layoutMode, setLayoutMode] = useState(() => {
    try {
      const saved = localStorage.getItem(STAFF_LAYOUT_KEY)
      return saved === 'list' ? 'list' : 'cards'
    } catch {
      return 'cards'
    }
  })

  useEffect(() => {
    if (isBranchScoped && lockedBranchId) {
      setBranchFilter(lockedBranchId)
    }
  }, [isBranchScoped, lockedBranchId])

  useEffect(() => {
    try {
      localStorage.setItem(STAFF_LAYOUT_KEY, layoutMode)
    } catch {
      // ignore storage failures
    }
  }, [layoutMode])

  const fetchStaff = useCallback(async () => {
    setIsLoading(true)
    try {
      const staffParams = { page: 1, per_page: 100 }
      if (isSystemAdmin && saloonFilter) {
        staffParams.saloon_id = Number(saloonFilter)
      }
      if (branchFilter) {
        staffParams.branch_id = Number(branchFilter)
      }

      try {
        const staffRes = await apiGet('/v1/staff', staffParams)
        setStaffList(parseList(staffRes, 'staff'))
      } catch (error) {
        console.error('Failed to fetch staff:', error)
        setStaffList([])
      }

      if (canManage) {
        try {
          const roleParams = {}
          if (isSystemAdmin && saloonFilter) {
            roleParams.saloon_id = Number(saloonFilter)
          }
          const rolesRes = await apiGet('/v1/staff/assignable-roles', roleParams)
          setRoles(parseList(rolesRes, 'roles'))
        } catch (error) {
          console.error('Failed to fetch assignable roles:', error)
          setRoles([])
        }
      } else {
        setRoles([])
      }

      try {
        const branchesRes = await apiGet('/v1/branches', { page: 1, per_page: 100 })
        setBranches(parseList(branchesRes, 'branches'))
      } catch {
        setBranches([])
      }

      if (isSystemAdmin) {
        try {
          const saloonsRes = await apiGet('/v1/saloons', { page: 1, per_page: 100 })
          setSaloons(parseList(saloonsRes, 'saloons'))
        } catch {
          setSaloons([])
        }
      }
    } finally {
      setIsLoading(false)
    }
  }, [branchFilter, canManage, isSystemAdmin, saloonFilter])

  useEffect(() => { void fetchStaff() }, [fetchStaff])

  const cardStaff = useMemo(() => staffList.map(mapStaffForCard), [staffList])
  const staffGroups = useMemo(() => groupStaffByRole(cardStaff), [cardStaff])
  const selectedStaff = staffList.find(s => s.id === selectedStaffId)
  const staffLimit = auth.subscriptionLimits?.max_staff
  const staffUsed = auth.subscriptionLimits?.staff_used ?? cardStaff.length
  const atStaffLimit = staffLimit != null && staffUsed >= staffLimit
  const roleCountInRoster = staffGroups.length
  const roleCount = roleCountInRoster || roles.length

  const handleSaved = (saved) => {
    setStaffList(prev => {
      const exists = prev.some(s => s.id === saved.id)
      return exists ? prev.map(s => s.id === saved.id ? saved : s) : [saved, ...prev]
    })
  }

  if (selectedStaffId && selectedStaff) {
    return (
      <>
        <StaffProfileView
          staffId={selectedStaffId}
          staffName={selectedStaff.name}
          staffMember={selectedStaff}
          onBack={() => setSelectedStaffId(null)}
          onEdit={canUpdate ? () => setFormModal({ open: true, staff: selectedStaff }) : undefined}
        />
        <StaffFormModal
          open={formModal.open}
          staff={formModal.staff}
          roles={roles}
          saloons={saloons}
          branches={branches}
          isSystemAdmin={isSystemAdmin}
          isBranchScoped={isBranchScoped}
          lockedBranchId={lockedBranchId}
          onClose={() => setFormModal({ open: false, staff: null })}
          onSaved={handleSaved}
          canManage={canManage}
          showRolesUpgradeHint={canManage && !hasRolesModule}
        />
      </>
    )
  }

  const totalStaff = cardStaff.length
  const availableToday = cardStaff.filter(s => s.status === 'available').length
  const onLeaveToday = cardStaff.filter(s => s.status === 'day-off').length
  const shouldShowBranchFilter = !isSystemAdmin && !isBranchScoped && branches.length > 1

  return (
    <div className="space-y-6">
      <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <PageHeader
          title="Staff Management"
          subtitle={isSystemAdmin
            ? 'Manage staff across all salons.'
            : subscriptionPageSubtitle(auth, 'Manage your salon workforce and roles.')}
        />
        <div className="flex w-full flex-col gap-2 sm:w-auto sm:flex-row sm:items-center sm:flex-wrap">
          {isSystemAdmin && (
            <select
              value={saloonFilter}
              onChange={(e) => setSaloonFilter(e.target.value)}
              className="w-full rounded-xl border border-slate-300 py-2 px-3 text-sm sm:w-auto"
            >
              <option value="">All salons</option>
              {saloons.map((s) => (
                <option key={s.id} value={s.id}>{s.name}</option>
              ))}
            </select>
          )}
          {shouldShowBranchFilter && (
            <select
              value={branchFilter}
              onChange={(e) => setBranchFilter(e.target.value)}
              className="w-full rounded-xl border border-slate-300 py-2 px-3 text-sm sm:w-auto"
            >
              <option value="">All branches</option>
              {branches.map((branch) => (
                <option key={branch.id} value={branch.id}>{branch.branch_name}</option>
              ))}
            </select>
          )}
          {canCreate && !atStaffLimit && <BaseButton leftIcon={Plus} size="sm" className="w-full sm:w-auto" onClick={() => setFormModal({ open: true, staff: null })}>Add Staff</BaseButton>}
          <div className="inline-flex w-full items-center justify-center rounded-xl border border-slate-200 bg-white p-1 shadow-sm sm:w-auto" role="group" aria-label="Staff layout">
            <button
              type="button"
              onClick={() => setLayoutMode('cards')}
              className={`inline-flex items-center gap-1.5 rounded-lg px-2.5 py-1.5 text-xs font-semibold transition ${
                layoutMode === 'cards'
                  ? 'bg-brand-600 text-white shadow-sm'
                  : 'text-slate-500 hover:text-slate-800 hover:bg-slate-50'
              }`}
              aria-pressed={layoutMode === 'cards'}
              title="Card view"
            >
              <LayoutGrid className="h-3.5 w-3.5" />
              Cards
            </button>
            <button
              type="button"
              onClick={() => setLayoutMode('list')}
              className={`inline-flex items-center gap-1.5 rounded-lg px-2.5 py-1.5 text-xs font-semibold transition ${
                layoutMode === 'list'
                  ? 'bg-brand-600 text-white shadow-sm'
                  : 'text-slate-500 hover:text-slate-800 hover:bg-slate-50'
              }`}
              aria-pressed={layoutMode === 'list'}
              title="List view"
            >
              <List className="h-3.5 w-3.5" />
              List
            </button>
          </div>
        </div>
      </div>

      {atStaffLimit ? (
        <div className="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
          Staff limit reached ({staffUsed}/{staffLimit}).{' '}
          <Link to="/billing" className="font-semibold underline underline-offset-2">Upgrade your plan</Link> to add more staff.
        </div>
      ) : null}

      <div className="grid grid-cols-2 lg:grid-cols-4 gap-6">
        <div className="bg-white border border-slate-200/80 rounded-2xl p-5 shadow-sm relative overflow-hidden">
          <div className="absolute right-4 top-4 p-2 bg-indigo-50 border border-indigo-100 text-indigo-600 rounded-xl"><Users className="w-5 h-5" /></div>
          <p className="text-xs font-bold text-slate-400 uppercase tracking-wider">Total Staff</p>
          <h3 className="text-2xl font-black text-slate-800 mt-2">{totalStaff}</h3>
        </div>
        <div className="bg-white border border-slate-200/80 rounded-2xl p-5 shadow-sm relative overflow-hidden">
          <div className="absolute right-4 top-4 p-2 bg-emerald-50 border border-emerald-100 text-emerald-600 rounded-xl"><CheckCircle2 className="w-5 h-5" /></div>
          <p className="text-xs font-bold text-slate-400 uppercase tracking-wider">Active</p>
          <h3 className="text-2xl font-black text-emerald-600 mt-2">{availableToday}</h3>
        </div>
        <div className="bg-white border border-slate-200/80 rounded-2xl p-5 shadow-sm relative overflow-hidden">
          <div className="absolute right-4 top-4 p-2 bg-rose-50 border border-rose-100 text-rose-600 rounded-xl"><ShieldAlert className="w-5 h-5" /></div>
          <p className="text-xs font-bold text-slate-400 uppercase tracking-wider">Inactive</p>
          <h3 className="text-2xl font-black text-slate-800 mt-2">{onLeaveToday}</h3>
        </div>
        <div className="bg-white border border-slate-200/80 rounded-2xl p-5 shadow-sm relative overflow-hidden">
          <div className="absolute right-4 top-4 p-2 bg-slate-100 border border-slate-200 text-slate-500 rounded-xl"><Award className="w-5 h-5" /></div>
          <p className="text-xs font-bold text-slate-400 uppercase tracking-wider">Roles</p>
          <h3 className="text-2xl font-black text-slate-500 mt-2">{roleCount}</h3>
        </div>
      </div>

      {isLoading ? (
        <div className="flex justify-center py-20 text-slate-400">Loading staff...</div>
      ) : cardStaff.length > 0 ? (
        <StaffRoleGroups
          groups={staffGroups}
          layoutMode={layoutMode}
          onSelect={setSelectedStaffId}
          showSaloon={isSystemAdmin}
          showBranch={shouldShowBranchFilter}
        />
      ) : (
        <div className="text-center py-16 text-slate-400 border border-dashed border-slate-200 rounded-2xl">
          <Users className="w-10 h-10 mx-auto mb-3 text-slate-300" />
          <p className="font-bold text-slate-500">No staff members yet</p>
          {canCreate && <BaseButton className="mt-4" size="sm" leftIcon={Plus} onClick={() => setFormModal({ open: true, staff: null })}>Add Staff</BaseButton>}
        </div>
      )}

      <StaffFormModal
        open={formModal.open}
        staff={formModal.staff}
        roles={roles}
        saloons={saloons}
        branches={branches}
        isSystemAdmin={isSystemAdmin}
        isBranchScoped={isBranchScoped}
        lockedBranchId={lockedBranchId}
        onClose={() => setFormModal({ open: false, staff: null })}
        onSaved={handleSaved}
        canManage={canManage}
        showRolesUpgradeHint={canManage && !hasRolesModule}
      />
    </div>
  )
}
