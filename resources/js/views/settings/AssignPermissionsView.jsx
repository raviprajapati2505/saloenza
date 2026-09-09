import React, { useCallback, useEffect, useMemo, useState } from 'react'
import { Link, useSearchParams } from 'react-router-dom'
import { motion, AnimatePresence } from 'framer-motion'
import {
  KeyRound,
  UsersRound,
  CheckCircle2,
  Save,
  Search,
  ChevronLeft,
  ChevronRight,
  AlertTriangle,
} from 'lucide-react'
import PageHeader from '../../components/ui/PageHeader.jsx'
import BaseButton from '../../components/ui/BaseButton.jsx'
import { useAuthStore } from '../../stores/auth'
import { subscriptionPageSubtitle } from '../../lib/subscriptionModules.js'
import { api } from '../../lib/api.js'

const ROLES_PER_PAGE = 5

function moduleLabel(module) {
  if (!module) return 'Other'
  return module.charAt(0).toUpperCase() + module.slice(1)
}

function setsEqual(a, b) {
  if (a.size !== b.size) return false
  for (const id of a) {
    if (!b.has(id)) return false
  }
  return true
}

export default function AssignPermissionsView() {
  const auth = useAuthStore()
  const canUpdateAssignments = auth.can('assign_permissions.update')
  const [searchParams] = useSearchParams()

  const [roles, setRoles] = useState([])
  const [allPermissions, setAllPermissions] = useState([])
  const [isLoading, setIsLoading] = useState(true)
  const [isPermsLoading, setIsPermsLoading] = useState(true)
  const [successMessage, setSuccessMessage] = useState('')
  const [saveError, setSaveError] = useState('')
  const [roleSearch, setRoleSearch] = useState('')
  const [permissionSearch, setPermissionSearch] = useState('')
  const [rolePage, setRolePage] = useState(0)
  const [selections, setSelections] = useState({})
  const [savingRoles, setSavingRoles] = useState(new Set())

  const fetchRoles = useCallback(async () => {
    try {
      const response = await api.get('/v1/roles', {
        params: { page: 1, per_page: 100 },
      })
      const raw = response.data?.data?.roles ?? response.data?.data ?? response.data
      const rolesData = Array.isArray(raw?.data) ? raw.data : Array.isArray(raw) ? raw : []
      setRoles(rolesData)

      const initialSelections = {}
      rolesData.forEach((role) => {
        initialSelections[role.id] = new Set((role.permissions || []).map((p) => p.id))
      })
      setSelections(initialSelections)
    } catch (error) {
      console.error('Failed to fetch roles:', error)
      setRoles([])
    } finally {
      setIsLoading(false)
    }
  }, [auth])

  const fetchPermissions = useCallback(async () => {
    try {
      const res = await api.get('/v1/permissions', {
        params: { page: 1, per_page: 100 },
      })
      const raw = res.data?.data?.permissions ?? res.data?.data ?? res.data
      const perms = Array.isArray(raw?.data) ? raw.data : Array.isArray(raw) ? raw : []
      setAllPermissions(perms)
    } catch (error) {
      console.error('Failed to load permissions', error)
      setAllPermissions([])
    } finally {
      setIsPermsLoading(false)
    }
  }, [auth])

  useEffect(() => {
    void fetchRoles()
    void fetchPermissions()
  }, [fetchRoles, fetchPermissions])

  const tenantPermissions = useMemo(() => {
    let list = allPermissions.filter((p) => auth.grantsAllPermissions || auth.isSystemAdmin || p.scope !== 'platform')
    if (!auth.grantsAllPermissions && !auth.isSystemAdmin) {
      const granted = new Set(auth.permissions || [])
      list = list.filter((p) => granted.has(p.code))
    }
    return list
  }, [allPermissions, auth.grantsAllPermissions, auth.isSystemAdmin, auth.permissions])

  const filteredPermissions = useMemo(() => {
    const q = permissionSearch.trim().toLowerCase()
    if (!q) return tenantPermissions
    return tenantPermissions.filter(
      (p) =>
        p.name.toLowerCase().includes(q) ||
        p.code.toLowerCase().includes(q) ||
        moduleLabel(p.module || 'other').toLowerCase().includes(q),
    )
  }, [tenantPermissions, permissionSearch])

  const permissionRows = useMemo(() => {
    const groups = {}
    filteredPermissions.forEach((perm) => {
      const key = perm.module || 'other'
      if (!groups[key]) groups[key] = []
      groups[key].push(perm)
    })
    const rows = []
    Object.entries(groups)
      .sort(([a], [b]) => a.localeCompare(b))
      .forEach(([module, perms]) => {
        rows.push({ type: 'module', key: `module-${module}`, module, count: perms.length })
        perms.forEach((perm) => rows.push({ type: 'permission', key: perm.id, perm }))
      })
    return rows
  }, [filteredPermissions])

  const roleCoverage = useCallback(
    (roleId) => {
      const count = selections[roleId]?.size ?? 0
      const total = tenantPermissions.length
      const pct = total > 0 ? Math.round((count / total) * 100) : 0
      return { count, total, pct }
    },
    [selections, tenantPermissions.length],
  )

  const filteredRoles = useMemo(() => {
    const q = roleSearch.trim().toLowerCase()
    if (!q) return roles
    return roles.filter(
      (r) => r.name.toLowerCase().includes(q) || (r.code || '').toLowerCase().includes(q),
    )
  }, [roles, roleSearch])

  const totalRolePages = Math.max(1, Math.ceil(filteredRoles.length / ROLES_PER_PAGE))

  const visibleRoles = useMemo(() => {
    const start = rolePage * ROLES_PER_PAGE
    return filteredRoles.slice(start, start + ROLES_PER_PAGE)
  }, [filteredRoles, rolePage])

  useEffect(() => {
    setRolePage(0)
  }, [roleSearch])

  useEffect(() => {
    if (rolePage > totalRolePages - 1) {
      setRolePage(Math.max(0, totalRolePages - 1))
    }
  }, [rolePage, totalRolePages])

  useEffect(() => {
    const roleId = searchParams.get('role')
    if (!roleId || filteredRoles.length === 0) return
    const idx = filteredRoles.findIndex((r) => r.id === Number(roleId))
    if (idx >= 0) setRolePage(Math.floor(idx / ROLES_PER_PAGE))
  }, [searchParams, filteredRoles])

  const isRoleEditable = (role) =>
    canUpdateAssignments && (auth.grantsAllPermissions || auth.isSystemAdmin || !role.is_system)

  const showSuccess = (msg) => {
    setSuccessMessage(msg)
    setSaveError('')
    setTimeout(() => setSuccessMessage(''), 3000)
  }

  const toggleCheckbox = (roleId, permId) => {
    if (!canUpdateAssignments) return
    const role = roles.find((r) => r.id === roleId)
    if (role && !isRoleEditable(role)) return
    setSelections((prev) => {
      const currentSet = prev[roleId] || new Set()
      const newSet = new Set(currentSet)
      if (newSet.has(permId)) newSet.delete(permId)
      else newSet.add(permId)
      return { ...prev, [roleId]: newSet }
    })
  }

  const selectAll = (roleId) => {
    if (!canUpdateAssignments) return
    const role = roles.find((r) => r.id === roleId)
    if (role && !isRoleEditable(role)) return
    setSelections((prev) => ({
      ...prev,
      [roleId]: new Set(tenantPermissions.map((p) => p.id)),
    }))
  }

  const clearAll = (roleId) => {
    if (!canUpdateAssignments) return
    const role = roles.find((r) => r.id === roleId)
    if (role && !isRoleEditable(role)) return
    setSelections((prev) => ({ ...prev, [roleId]: new Set() }))
  }

  const saveRolePermissions = async (roleId) => {
    if (!canUpdateAssignments) return
    const role = roles.find((r) => r.id === roleId)
    if (role && !isRoleEditable(role)) return

    const selectedIds = Array.from(selections[roleId] || new Set())
    setSavingRoles((prev) => new Set(prev).add(roleId))
    setSaveError('')
    try {
      await api.put(`/v1/roles/${roleId}/permissions`, {
        permission_ids: selectedIds,
      })

      showSuccess(`Permissions saved for ${role?.name ?? 'role'}.`)

      const updatedPermissionsObjects = allPermissions.filter((p) => selectedIds.includes(p.id))
      setRoles((prev) =>
        prev.map((r) => (r.id === roleId ? { ...r, permissions: updatedPermissionsObjects } : r)),
      )

      if (auth.role?.id === roleId) auth.fetchMe()
    } catch (error) {
      setSaveError(error?.response?.data?.message || 'Failed to update permissions.')
    } finally {
      setSavingRoles((prev) => {
        const next = new Set(prev)
        next.delete(roleId)
        return next
      })
    }
  }

  const highlightedRoleId = searchParams.get('role') ? Number(searchParams.get('role')) : null

  const roleIsDirty = useCallback(
    (roleId) => {
      const role = roles.find((r) => r.id === roleId)
      if (!role) return false
      const original = new Set((role.permissions || []).map((p) => p.id))
      return !setsEqual(original, selections[roleId] || new Set())
    },
    [roles, selections],
  )

  const editableVisibleRoles = useMemo(
    () => visibleRoles.filter((role) => isRoleEditable(role)),
    [visibleRoles, auth.grantsAllPermissions, auth.isSystemAdmin, canUpdateAssignments],
  )

  const anyVisibleDirty = editableVisibleRoles.some((role) => roleIsDirty(role.id))

  return (
    <div className="space-y-6 pb-32 md:pb-24">
      <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <PageHeader
          title="Assign Permissions to Roles"
          subtitle={subscriptionPageSubtitle(auth, 'Map capabilities to your configured roles.')}
        />
        <Link to="/roles" className="w-full sm:w-auto">
          <BaseButton variant="secondary" size="sm" className="w-full">
            Manage Roles
          </BaseButton>
        </Link>
      </div>

      {!canUpdateAssignments ? (
        <div className="rounded-xl border border-sky-200 bg-sky-50 px-4 py-3 text-sm text-sky-900">
          Permission assignments are view-only while your subscription is expired. Renew at Billing to update role capabilities.
        </div>
      ) : null}

      <AnimatePresence>
        {successMessage && (
          <motion.div
            initial={{ opacity: 0, y: -10 }}
            animate={{ opacity: 1, y: 0 }}
            exit={{ opacity: 0, y: -10 }}
            className="rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-emerald-800 shadow-sm"
          >
            <div className="flex items-center gap-2">
              <CheckCircle2 className="h-5 w-5 text-emerald-600" />
              <p className="text-sm font-semibold">{successMessage}</p>
            </div>
          </motion.div>
        )}
        {saveError && (
          <motion.div
            initial={{ opacity: 0, y: -10 }}
            animate={{ opacity: 1, y: 0 }}
            exit={{ opacity: 0, y: -10 }}
            className="rounded-xl border border-rose-200 bg-rose-50 p-4 text-rose-800 shadow-sm"
          >
            <div className="flex items-center gap-2">
              <AlertTriangle className="h-5 w-5 text-rose-600" />
              <p className="text-sm font-semibold">{saveError}</p>
            </div>
          </motion.div>
        )}
      </AnimatePresence>

      <div className="rounded-2xl border border-slate-200/80 bg-white shadow-sm">
        {isLoading || isPermsLoading ? (
          <div className="flex flex-col items-center justify-center py-20 text-slate-400">
            <svg className="mb-4 h-8 w-8 animate-spin text-brand-500" viewBox="0 0 24 24" fill="none">
              <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="3" />
              <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v3a5 5 0 00-5 5H4z" />
            </svg>
            <p>Loading matrix…</p>
          </div>
        ) : tenantPermissions.length === 0 ? (
          <div className="flex flex-col items-center justify-center py-20 text-slate-400">
            <KeyRound className="mx-auto mb-4 h-12 w-12 text-slate-300" />
            <p className="text-lg font-semibold text-slate-600">No permissions found</p>
          </div>
        ) : roles.length === 0 ? (
          <div className="flex flex-col items-center justify-center py-20 text-slate-400">
            <UsersRound className="mx-auto mb-4 h-12 w-12 text-slate-300" />
            <p className="text-lg font-semibold text-slate-600">No roles available</p>
            <p className="mt-1 max-w-sm text-center text-sm">Create a role first, then assign permissions here.</p>
          </div>
        ) : (
          <>
            {/* Toolbar — keeps matrix stable when many roles exist */}
            <div className="flex flex-col gap-3 border-b border-slate-100 px-4 py-3 sm:px-6">
              <div className="flex flex-col gap-3 sm:flex-row sm:items-center">
                <div className="relative max-w-xs flex-1">
                  <label htmlFor="role-search" className="sr-only">Search roles</label>
                  <Search className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                  <input
                    id="role-search"
                    type="search"
                    value={roleSearch}
                    onChange={(e) => setRoleSearch(e.target.value)}
                    placeholder="Search roles…"
                    className="w-full rounded-lg border border-slate-200 py-2 pl-9 pr-3 text-sm focus:border-brand-400 focus:outline-none focus:ring-2 focus:ring-brand-500/20"
                  />
                </div>
                <div className="relative max-w-xs flex-1">
                  <label htmlFor="permission-search" className="sr-only">Search permissions</label>
                  <Search className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                  <input
                    id="permission-search"
                    type="search"
                    value={permissionSearch}
                    onChange={(e) => setPermissionSearch(e.target.value)}
                    placeholder="Search permissions…"
                    className="w-full rounded-lg border border-slate-200 py-2 pl-9 pr-3 text-sm focus:border-brand-400 focus:outline-none focus:ring-2 focus:ring-brand-500/20"
                  />
                </div>
              </div>
              <div className="flex flex-wrap items-center gap-2 text-sm text-slate-500">
                <span>
                  {ROLES_PER_PAGE} roles per page · showing {visibleRoles.length} of {filteredRoles.length}
                </span>
                {permissionSearch.trim() && (
                  <span className="rounded-full bg-brand-50 px-2 py-0.5 text-xs font-medium text-brand-700">
                    {filteredPermissions.length} permission{filteredPermissions.length !== 1 ? 's' : ''} matched
                  </span>
                )}
                {totalRolePages > 1 && (
                  <div className="ml-auto flex items-center gap-1">
                    <button
                      type="button"
                      disabled={rolePage === 0}
                      onClick={() => setRolePage((p) => p - 1)}
                      className="rounded-lg p-1.5 text-slate-500 hover:bg-slate-100 disabled:opacity-40"
                      aria-label="Previous roles"
                    >
                      <ChevronLeft className="h-4 w-4" />
                    </button>
                    <span className="min-w-[4rem] text-center text-xs font-medium">
                      {rolePage + 1} / {totalRolePages}
                    </span>
                    <button
                      type="button"
                      disabled={rolePage >= totalRolePages - 1}
                      onClick={() => setRolePage((p) => p + 1)}
                      className="rounded-lg p-1.5 text-slate-500 hover:bg-slate-100 disabled:opacity-40"
                      aria-label="Next roles"
                    >
                      <ChevronRight className="h-4 w-4" />
                    </button>
                  </div>
                )}
              </div>
            </div>

            {/* Horizontal scroll for role columns; vertical scroll uses the page (no sticky header overlap) */}
            <div className="overflow-x-auto pb-2">
              <table className="w-full min-w-[560px] border-separate border-spacing-0 text-left">
                <thead className="bg-white">
                  <tr>
                    <th className="sticky left-0 z-20 min-w-[200px] border-b border-r border-slate-200 bg-white px-3 py-2.5 align-middle shadow-[2px_0_4px_-2px_rgba(0,0,0,0.06)]">
                      <div className="text-[10px] font-extrabold uppercase tracking-widest text-slate-400">
                        Permissions
                      </div>
                    </th>
                    {visibleRoles.map((role) => {
                      const editable = isRoleEditable(role)
                      const isHighlighted = role.id === highlightedRoleId
                      const allSelected =
                        selections[role.id]?.size === tenantPermissions.length && tenantPermissions.length > 0
                      const { count, total, pct } = roleCoverage(role.id)

                      return (
                        <th
                          key={role.id}
                          className={`min-w-[118px] border-b border-r border-slate-200 bg-white px-2 py-2.5 text-center align-top ${
                            isHighlighted ? 'bg-brand-50 ring-2 ring-inset ring-brand-300' : ''
                          }`}
                        >
                          <div className="flex flex-col items-center gap-0.5">
                            <span
                              title={role.name}
                              className={`block max-w-[108px] truncate text-sm font-bold leading-tight ${
                                !role.is_active ? 'text-rose-500' : 'text-slate-800'
                              }`}
                            >
                              {role.name}
                            </span>

                            <p className="text-[10px] font-semibold text-slate-500">
                              {count}/{total} · {pct}%
                            </p>
                            <div
                              className="h-1 w-full max-w-[96px] overflow-hidden rounded-full bg-slate-200"
                              role="progressbar"
                              aria-valuenow={pct}
                              aria-valuemin={0}
                              aria-valuemax={100}
                              aria-label={`${role.name} has ${pct}% of permissions`}
                            >
                              <div
                                className={`h-full rounded-full transition-all duration-300 ${
                                  pct >= 80 ? 'bg-brand-500' : pct >= 40 ? 'bg-brand-400' : 'bg-slate-400'
                                }`}
                                style={{ width: `${pct}%` }}
                              />
                            </div>

                            {role.is_system && (
                              <span className="rounded bg-amber-50 px-1 py-0.5 text-[8px] font-bold uppercase text-amber-700">
                                System
                              </span>
                            )}

                            {editable ? (
                              <label className="mt-1 flex cursor-pointer items-center justify-center gap-1.5">
                                <input
                                  type="checkbox"
                                  checked={allSelected}
                                  onChange={(e) => (e.target.checked ? selectAll(role.id) : clearAll(role.id))}
                                  className="h-3.5 w-3.5 cursor-pointer rounded border-slate-300 text-brand-500 focus:ring-brand-500"
                                />
                                <span className="text-[10px] font-bold uppercase tracking-wide text-slate-500">
                                  All
                                </span>
                              </label>
                            ) : (
                              <span className="mt-1 text-[9px] font-medium uppercase text-slate-400">View only</span>
                            )}
                          </div>
                        </th>
                      )
                    })}
                  </tr>
                </thead>
                <tbody>
                  {permissionRows.length === 0 ? (
                    <tr>
                      <td
                        colSpan={visibleRoles.length + 1}
                        className="px-4 py-12 text-center text-sm text-slate-400"
                      >
                        No permissions match &ldquo;{permissionSearch.trim()}&rdquo;
                      </td>
                    </tr>
                  ) : (
                    permissionRows.map((row) => {
                    if (row.type === 'module') {
                      return (
                        <tr key={row.key} className="bg-slate-50">
                          <td
                            colSpan={visibleRoles.length + 1}
                            className="border-b border-slate-200 px-3 py-1.5 text-[11px] font-bold uppercase tracking-wider text-slate-500"
                          >
                            {moduleLabel(row.module)}
                            {permissionSearch.trim() && (
                              <span className="ml-2 font-normal normal-case text-slate-400">
                                ({row.count} matched)
                              </span>
                            )}
                          </td>
                        </tr>
                      )
                    }

                    const perm = row.perm
                    return (
                      <tr key={row.key} className="group transition-colors hover:bg-slate-50/70">
                        <td className="sticky left-0 z-[1] border-b border-r border-slate-100 bg-white px-3 py-2 align-top shadow-[2px_0_4px_-2px_rgba(0,0,0,0.04)] group-hover:bg-slate-50">
                          <div className="flex flex-col gap-0.5">
                            <div className="text-sm font-semibold text-slate-800">{perm.name}</div>
                            <code className="text-[10px] text-slate-400">{perm.code}</code>
                          </div>
                        </td>
                        {visibleRoles.map((role) => {
                          const isSelected = selections[role.id]?.has(perm.id) || false
                          const editable = isRoleEditable(role)
                          const isHighlighted = role.id === highlightedRoleId

                          return (
                            <td
                              key={role.id}
                              className={`border-b border-r border-slate-100 px-2 py-2 text-center align-middle ${
                                isHighlighted ? 'bg-brand-50/30' : 'bg-slate-50/20'
                              } group-hover:bg-transparent`}
                            >
                              <input
                                type="checkbox"
                                checked={isSelected}
                                disabled={!editable}
                                onChange={() => toggleCheckbox(role.id, perm.id)}
                                aria-label={`${perm.name} for ${role.name}`}
                                className="h-4 w-4 cursor-pointer rounded border-slate-300 text-brand-600 focus:ring-brand-500 disabled:cursor-not-allowed disabled:opacity-50"
                              />
                            </td>
                          )
                        })}
                      </tr>
                    )
                  })
                  )}
                </tbody>
              </table>
            </div>

            {/* Spacer so last rows are not hidden behind the fixed save bar */}
            <div className="h-20 shrink-0 md:h-16" aria-hidden="true" />
          </>
        )}
      </div>

      {/* Fixed save bar — only place to save; avoids duplicate sticky header overlap */}
      {!isLoading && !isPermsLoading && canUpdateAssignments && editableVisibleRoles.length > 0 && (
        <div className="fixed inset-x-0 bottom-16 z-40 md:bottom-0">
          <div
            className="pointer-events-auto border-t border-slate-200 bg-white px-4 py-3 shadow-[0_-4px_24px_rgba(0,0,0,0.08)] md:ml-[72px] lg:ml-[260px]"
            role="toolbar"
            aria-label="Save role permissions"
          >
            <div className="mx-auto flex max-w-[1600px] flex-wrap items-center gap-2 sm:gap-3">
            <span className="mr-1 hidden text-xs font-semibold uppercase tracking-wide text-slate-400 sm:inline">
              Save
            </span>
            {editableVisibleRoles.map((role) => {
              const dirty = roleIsDirty(role.id)
              const saving = savingRoles.has(role.id)
              return (
                <button
                  key={role.id}
                  type="button"
                  onClick={() => saveRolePermissions(role.id)}
                  disabled={saving}
                  title={dirty ? `Save unsaved changes for ${role.name}` : `Save ${role.name}`}
                  className={`inline-flex items-center gap-1.5 rounded-lg px-3 py-2 text-xs font-bold transition sm:text-sm ${
                    dirty
                      ? 'bg-brand-600 text-white shadow-sm hover:bg-brand-500 ring-2 ring-amber-300'
                      : 'bg-slate-100 text-slate-700 hover:bg-slate-200'
                  } disabled:opacity-60`}
                >
                  <Save className="h-3.5 w-3.5 shrink-0" aria-hidden="true" />
                  <span className="max-w-[120px] truncate">{role.name}</span>
                  {dirty && <span className="text-[10px] opacity-90">*</span>}
                  {saving && <span className="text-[10px]">…</span>}
                </button>
              )
            })}
            {anyVisibleDirty && (
              <span className="ml-auto hidden text-xs text-amber-600 sm:inline">* unsaved changes</span>
            )}
            </div>
          </div>
        </div>
      )}
    </div>
  )
}
