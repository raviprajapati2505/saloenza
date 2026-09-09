import React, { useCallback, useEffect, useState, useMemo } from 'react'
import { Link } from 'react-router-dom'
import { motion, AnimatePresence } from 'framer-motion'
import { ShieldCheck, Plus, Search, Edit2, Trash2, AlertTriangle, CheckCircle2, X, ChevronLeft, ChevronRight, Copy, KeyRound } from 'lucide-react'
import PageHeader from '../../components/ui/PageHeader.jsx'
import BaseButton from '../../components/ui/BaseButton.jsx'
import BaseInput from '../../components/ui/BaseInput.jsx'
import { useAuthStore } from '../../stores/auth'
import { subscriptionPageSubtitle } from '../../lib/subscriptionModules.js'
import { api } from '../../lib/api.js'

export default function RolesView() {
  const auth = useAuthStore()
  const canViewAssignments = auth.can('assign_permissions.view')
  
  const [roles, setRoles] = useState([])
  const [isLoading, setIsLoading] = useState(true)
  const [search, setSearch] = useState('')
  const [successMessage, setSuccessMessage] = useState('')
  const [actionError, setActionError] = useState('')
  const [currentPage, setCurrentPage] = useState(1)
  const [itemsPerPage, setItemsPerPage] = useState(10)
  
  // Role Modal state
  const [isModalOpen, setIsModalOpen] = useState(false)
  const [editingRole, setEditingRole] = useState(null)
  const [form, setForm] = useState({ name: '', is_active: true })
  const [formErrors, setFormErrors] = useState({})
  const [isSaving, setIsSaving] = useState(false)

  const canSaveRole = editingRole ? auth.can('roles.update') : auth.can('roles.create')

  // Delete Modal state
  const [deleteModal, setDeleteModal] = useState({ isOpen: false, role: null })
  const [deleteError, setDeleteError] = useState('')
  const [isDeleting, setIsDeleting] = useState(false)
  const [cloningRoleId, setCloningRoleId] = useState(null)


  const fetchRoles = useCallback(async () => {
    setIsLoading(true)
    try {
      const params = {}
      // Removed saloon_id filter so we can see roles with saloon_id = null
      
      const response = await api.get('/v1/roles', {
        params: { page: 1, per_page: 100 },
      })
      const data = response.data.data?.roles || response.data.data || response.data
      setRoles(Array.isArray(data) ? data : [])
    } catch (error) {
      console.error('Failed to fetch roles:', error)
      setRoles([])
    } finally {
      setIsLoading(false)
    }
  }, [auth])

  useEffect(() => {
    void fetchRoles()
  }, [fetchRoles])

  const filteredRoles = useMemo(() => {
    const q = search.toLowerCase()
    return roles.filter(r =>
      r.name.toLowerCase().includes(q) || (r.code || '').toLowerCase().includes(q),
    )
  }, [roles, search])

  useEffect(() => {
    setCurrentPage(1)
  }, [search])

  const totalPages = Math.ceil(filteredRoles.length / itemsPerPage)
  const startIndex = (currentPage - 1) * itemsPerPage
  const paginatedRoles = filteredRoles.slice(startIndex, startIndex + itemsPerPage)

  const showSuccess = (msg) => {
    setSuccessMessage(msg)
    setTimeout(() => setSuccessMessage(''), 3000)
  }

  // --- ROLE CRUD ---

  const openCreateModal = () => {
    if (!auth.can('roles.create')) return
    setEditingRole(null)
    setForm({ name: '', is_active: true })
    setFormErrors({})
    setIsModalOpen(true)
  }

  const openEditModal = (role) => {
    if (!auth.can('roles.update')) return
    setEditingRole(role)
    setForm({ name: role.name, is_active: role.is_active })
    setFormErrors({})
    setIsModalOpen(true)
  }

  const saveRole = async (e) => {
    e.preventDefault()
    if (editingRole ? !auth.can('roles.update') : !auth.can('roles.create')) return
    setFormErrors({})
    
    if (!form.name.trim()) {
      setFormErrors({ name: 'Role name is required.' })
      return
    }

    setIsSaving(true)
    try {
      const payload = { ...form }
      if (auth.isSystemAdmin) {
        payload.saloon_id = editingRole?.saloon_id ?? null
      } else if (auth.user?.saloon_id) {
        payload.saloon_id = auth.user.saloon_id
      }

      if (auth.isBranchScoped && auth.user?.branch_id) {
        payload.scope = 'branch'
        payload.branch_id = auth.user.branch_id
      }

      if (editingRole) {
        const response = await api.put(`/v1/roles/${editingRole.id}`, payload)
        const updated = response.data.data?.role || response.data.data || response.data
        setRoles(prev => prev.map(r => r.id === updated.id ? updated : r))
        showSuccess('Role updated successfully.')
      } else {
        const response = await api.post('/v1/roles', payload)
        const created = response.data.data?.role || response.data.data || response.data
        setRoles(prev => [created, ...prev])
        showSuccess('Role created successfully.')
      }
      setIsModalOpen(false)
    } catch (error) {
      const serverErrors = error?.response?.data?.errors || {}
      if (Object.keys(serverErrors).length > 0) {
        const newErrors = {}
        for (const key in serverErrors) {
          newErrors[key] = serverErrors[key][0]
        }
        setFormErrors(newErrors)
      } else {
        setFormErrors({ _general: error?.response?.data?.message || 'Failed to save role.' })
      }
    } finally {
      setIsSaving(false)
    }
  }

  const handleClone = async (role) => {
    if (!auth.can('roles.create')) return
    setActionError('')
    setCloningRoleId(role.id)
    try {
      const payload = {}
      if (!auth.isSystemAdmin && auth.user?.saloon_id) {
        payload.saloon_id = auth.user.saloon_id
      }
      const response = await api.post(`/v1/roles/${role.id}/clone`, payload)
      const created = response.data.data?.role || response.data.data || response.data
      setRoles((prev) => [created, ...prev])
      showSuccess(`Cloned "${role.name}" as "${created.name}".`)
    } catch (error) {
      setActionError(error?.response?.data?.message || 'Failed to clone role.')
      setTimeout(() => setActionError(''), 5000)
    } finally {
      setCloningRoleId(null)
    }
  }

  const handleDelete = async () => {
    if (!auth.can('roles.delete') || !deleteModal.role) return
    setIsDeleting(true)
    setDeleteError('')
    try {
      await api.delete(`/v1/roles/${deleteModal.role.id}`)
      setRoles(prev => prev.filter(r => r.id !== deleteModal.role.id))
      showSuccess('Role deleted successfully.')
      setDeleteModal({ isOpen: false, role: null })
    } catch (error) {
      setDeleteError(error?.response?.data?.message || 'Failed to delete role.')
    } finally {
      setIsDeleting(false)
    }
  }

  return (
    <div className="space-y-6 pb-12 h-full flex flex-col">
      <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <PageHeader
          title="Manage Roles"
          subtitle={auth.isSystemAdmin
            ? 'Manage all platform roles globally.'
            : subscriptionPageSubtitle(auth, 'Manage access control roles.')}
        />
        <div className="flex w-full flex-col gap-2 sm:w-auto sm:flex-row sm:flex-wrap sm:items-center">
        {auth.can('roles.create') && (
          <BaseButton onClick={openCreateModal} size="sm" className="w-full sm:w-auto" leftIcon={Plus}>
            Add Role
          </BaseButton>
        )}
        {canViewAssignments && (
          <Link to="/assign-permissions" className="w-full sm:w-auto">
            <BaseButton variant="secondary" size="sm" className="w-full" leftIcon={KeyRound}>
              Assign Permissions
            </BaseButton>
          </Link>
        )}
        </div>
      </div>

      <AnimatePresence>
        {actionError && (
          <motion.div
            initial={{ opacity: 0, y: -10 }}
            animate={{ opacity: 1, y: 0 }}
            exit={{ opacity: 0, y: -10 }}
            className="rounded-xl border border-rose-200 bg-rose-50 p-4 text-rose-800 shadow-sm"
          >
            <div className="flex items-center gap-2">
              <AlertTriangle className="h-5 w-5 text-rose-600" />
              <p className="text-sm font-semibold">{actionError}</p>
            </div>
          </motion.div>
        )}
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
      </AnimatePresence>

      <div className="bg-white border border-slate-200/80 rounded-2xl shadow-sm overflow-hidden flex-1 flex flex-col min-h-[400px]">
        <div className="flex-1 flex flex-col">
          <div className="p-4 border-b border-slate-100 flex flex-col sm:flex-row gap-3">
            <div className="relative flex-1 max-w-md">
              <Search className="absolute left-3.5 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400" />
              <input
                type="text"
                value={search}
                onChange={e => setSearch(e.target.value)}
                placeholder="Search roles..."
                className="w-full pl-10 pr-4 py-2 text-sm border border-slate-200 rounded-xl bg-white shadow-sm focus:outline-none focus:ring-2 focus:ring-brand-500/20 focus:border-brand-400 transition"
              />
              {search && (
                <button onClick={() => setSearch('')} className="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600 cursor-pointer">
                  <X className="w-3.5 h-3.5" />
                </button>
              )}
            </div>
          </div>

          {isLoading ? (
            <div className="flex flex-col items-center justify-center py-20 text-slate-400">
              <svg className="h-8 w-8 animate-spin text-brand-500 mb-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="3" />
                <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v3a5 5 0 00-5 5H4z" />
              </svg>
              <p>Loading roles...</p>
            </div>
          ) : (
            <div className="overflow-x-auto flex-1">
              <table className="w-full text-left">
                <thead>
                  <tr className="border-b border-slate-100 bg-slate-50/50">
                    <th className="px-6 py-4 text-[11px] font-bold uppercase tracking-wider text-slate-400 whitespace-nowrap">
                      Role
                    </th>
                    <th className="px-6 py-4 text-[11px] font-bold uppercase tracking-wider text-slate-400 whitespace-nowrap">
                      Key
                    </th>
                    <th className="px-6 py-4 text-[11px] font-bold uppercase tracking-wider text-slate-400 whitespace-nowrap">
                      Permissions Count
                    </th>
                    <th className="px-6 py-4 text-[11px] font-bold uppercase tracking-wider text-slate-400 whitespace-nowrap w-32">
                      Status
                    </th>
                    <th className="px-6 py-4 text-[11px] font-bold uppercase tracking-wider text-slate-400 whitespace-nowrap w-24 text-right">
                      Actions
                    </th>
                  </tr>
                </thead>
                <tbody>
                  {paginatedRoles.length > 0 ? (
                    paginatedRoles.map((role, i) => (
                      <motion.tr
                        key={role.id}
                        initial={{ opacity: 0, y: 8 }}
                        animate={{ opacity: 1, y: 0 }}
                        transition={{ delay: i * 0.04 }}
                        whileHover={{ backgroundColor: 'rgba(248,250,252,0.8)' }}
                        className="border-b border-slate-100 group"
                      >
                        <td className="py-4 px-6">
                          <div className="flex items-center gap-3">
                            <div className="w-9 h-9 rounded-xl bg-slate-100 border border-slate-200 flex items-center justify-center text-slate-400 group-hover:bg-brand-50 group-hover:text-brand-600 transition-colors">
                              <ShieldCheck className="w-4 h-4" />
                            </div>
                            <div>
                              <p className="text-sm font-semibold text-slate-900">{role.name}</p>
                              <div className="mt-0.5 flex flex-wrap items-center gap-1.5">
                                {role.is_system && (
                                  <span className="rounded bg-amber-50 px-1.5 py-0.5 text-[10px] font-bold uppercase tracking-wide text-amber-700 ring-1 ring-amber-200">
                                    System
                                  </span>
                                )}
                                {role.scope && (
                                  <span className="text-[10px] uppercase tracking-wide text-slate-400">{role.scope}</span>
                                )}
                              </div>
                              {auth.isSystemAdmin && (
                                <p className="text-[10px] text-slate-400 uppercase tracking-wide mt-0.5">
                                  {role.saloon_id ? `Tenant: ${role.saloon_id}` : 'Global Role'}
                                </p>
                              )}
                            </div>
                          </div>
                        </td>
                        <td className="py-4 px-6">
                          <code className="rounded bg-slate-100 px-2 py-1 text-[11px] text-slate-600">{role.code || '—'}</code>
                        </td>
                        <td className="py-4 px-6">
                          <div className="flex items-center gap-1.5 flex-wrap">
                            {role.permissions?.length > 0 ? (
                              canViewAssignments ? (
                                <Link
                                  to={`/assign-permissions?role=${role.id}`}
                                  className="text-xs font-semibold text-brand-700 bg-brand-50 px-2 py-0.5 rounded-md border border-brand-200/60 hover:bg-brand-100 transition"
                                  title="Manage permissions for this role"
                                >
                                  {role.permissions.length} Assigned
                                </Link>
                              ) : (
                                <span className="text-xs font-semibold text-slate-700 bg-slate-100 px-2 py-0.5 rounded-md border border-slate-200">
                                  {role.permissions.length} Assigned
                                </span>
                              )
                            ) : canViewAssignments ? (
                              <Link
                                to={`/assign-permissions?role=${role.id}`}
                                className="text-xs font-medium text-brand-600 hover:text-brand-700 hover:underline"
                              >
                                Assign permissions
                              </Link>
                            ) : (
                              <span className="text-xs text-slate-400">No permissions</span>
                            )}
                          </div>
                        </td>
                        <td className="py-4 px-6">
                          {role.is_active ? (
                            <span className="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200/60">
                              <CheckCircle2 className="w-3.5 h-3.5" /> Active
                            </span>
                          ) : (
                            <span className="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold bg-slate-100 text-slate-600 border border-slate-200">
                              <AlertTriangle className="w-3.5 h-3.5" /> Inactive
                            </span>
                          )}
                        </td>
                        <td className="py-4 px-6 text-right">
                          <div className="flex items-center gap-2 justify-end">
                            {canViewAssignments && (
                              <Link
                                to={`/assign-permissions?role=${role.id}`}
                                className="p-1.5 rounded-lg bg-slate-100 hover:bg-amber-50 hover:text-amber-600 text-slate-400 transition"
                                title="Manage permissions"
                              >
                                <KeyRound className="w-4 h-4" />
                              </Link>
                            )}
                            {auth.can('roles.create') && (
                              <button
                                type="button"
                                onClick={() => handleClone(role)}
                                disabled={cloningRoleId === role.id}
                                className="p-1.5 rounded-lg bg-slate-100 hover:bg-indigo-50 hover:text-indigo-600 text-slate-400 transition cursor-pointer disabled:opacity-50"
                                title="Clone role"
                              >
                                <Copy className="w-4 h-4" />
                              </button>
                            )}
                            {auth.can('roles.update') && (!role.is_system || auth.isSystemAdmin) && (
                              <button 
                                onClick={() => openEditModal(role)}
                                className="p-1.5 rounded-lg bg-slate-100 hover:bg-brand-50 hover:text-brand-600 text-slate-400 transition cursor-pointer"
                                title="Edit Role"
                              >
                                <Edit2 className="w-4 h-4" />
                              </button>
                            )}
                            {auth.can('roles.delete') && !role.is_system && (
                              <button 
                                onClick={() => setDeleteModal({ isOpen: true, role })}
                                className="p-1.5 rounded-lg bg-slate-100 hover:bg-rose-50 hover:text-rose-600 text-slate-400 transition cursor-pointer"
                                title="Delete Role"
                              >
                                <Trash2 className="w-4 h-4" />
                              </button>
                            )}
                          </div>
                        </td>
                      </motion.tr>
                    ))
                  ) : (
                    <tr>
                      <td colSpan={5} className="py-16 text-center text-slate-400">
                        <ShieldCheck className="w-10 h-10 mx-auto mb-3 text-slate-300" />
                        <p className="text-lg font-semibold text-slate-600">No roles found</p>
                        <p className="text-sm mt-1">
                          {search ? 'Try adjusting your search criteria.' : 'Create your first role to manage access.'}
                        </p>
                        {!search && auth.can('roles.create') && (
                          <BaseButton onClick={openCreateModal} className="mt-4" size="sm" leftIcon={Plus}>
                            Add Role
                          </BaseButton>
                        )}
                      </td>
                    </tr>
                  )}
                </tbody>
              </table>
            </div>
          )}
          
          {/* Pagination Controls */}
          {!isLoading && filteredRoles.length > 0 && (
            <div className="flex flex-col gap-3 border-t border-slate-100 bg-slate-50/50 p-4 sm:flex-row sm:items-center sm:justify-between">
              <div className="flex items-center justify-between gap-2 sm:justify-start">
                <span className="text-sm text-slate-500 font-medium">Rows per page:</span>
                <select 
                  value={itemsPerPage} 
                  onChange={e => {
                    setItemsPerPage(Number(e.target.value))
                    setCurrentPage(1)
                  }}
                  className="text-sm border border-slate-200 rounded-lg bg-white px-2 py-1 outline-none focus:border-brand-500 font-medium text-slate-700 cursor-pointer shadow-sm"
                >
                  {[5, 10, 25, 50, 100].map(n => <option key={n} value={n}>{n}</option>)}
                </select>
              </div>
              <div className="flex items-center justify-between gap-4 sm:justify-end">
                <span className="text-sm text-slate-500 font-medium">
                  {startIndex + 1}-{Math.min(startIndex + itemsPerPage, filteredRoles.length)} of {filteredRoles.length}
                </span>
                <div className="flex items-center gap-1">
                  <button 
                    disabled={currentPage === 1}
                    onClick={() => setCurrentPage(prev => Math.max(prev - 1, 1))}
                    className="p-1.5 rounded-md text-slate-400 hover:text-slate-700 hover:bg-slate-200 disabled:opacity-30 disabled:hover:bg-transparent disabled:hover:text-slate-400 transition-colors"
                  >
                    <ChevronLeft className="w-4 h-4" />
                  </button>
                  <button 
                    disabled={currentPage === totalPages || totalPages === 0}
                    onClick={() => setCurrentPage(prev => Math.min(prev + 1, totalPages))}
                    className="p-1.5 rounded-md text-slate-400 hover:text-slate-700 hover:bg-slate-200 disabled:opacity-30 disabled:hover:bg-transparent disabled:hover:text-slate-400 transition-colors"
                  >
                    <ChevronRight className="w-4 h-4" />
                  </button>
                </div>
              </div>
            </div>
          )}
        </div>
      </div>

      {/* Role Create/Edit Modal */}
      <AnimatePresence>
        {isModalOpen && (editingRole ? auth.can('roles.update') : auth.can('roles.create')) && (
          <div className="fixed inset-0 z-50 flex items-center justify-center p-4">
            <motion.div 
              initial={{ opacity: 0 }} animate={{ opacity: 1 }} exit={{ opacity: 0 }}
              className="absolute inset-0 bg-slate-900/40 backdrop-blur-sm"
              onClick={() => setIsModalOpen(false)}
            />
            <motion.div 
              initial={{ opacity: 0, scale: 0.95, y: 10 }}
              animate={{ opacity: 1, scale: 1, y: 0 }}
              exit={{ opacity: 0, scale: 0.95, y: 10 }}
              className="relative w-full max-w-md bg-white rounded-2xl shadow-2xl overflow-hidden"
            >
              <div className="px-6 py-5 border-b border-slate-100 flex items-center justify-between">
                <h3 className="text-lg font-bold text-slate-900">
                  {editingRole ? 'Edit Role' : 'Create Role'}
                </h3>
                <button 
                  onClick={() => setIsModalOpen(false)}
                  className="p-1.5 text-slate-400 hover:text-slate-600 hover:bg-slate-100 rounded-lg transition"
                >
                  <X className="w-5 h-5" />
                </button>
              </div>
              
              <form onSubmit={saveRole} className="p-6 space-y-5">
                <fieldset disabled={!canSaveRole} className="space-y-5 disabled:opacity-60">
                {formErrors._general && (
                  <div className="p-3 text-sm text-rose-700 bg-rose-50 border border-rose-200 rounded-xl">
                    {formErrors._general}
                  </div>
                )}
                
                {editingRole?.code && (
                  <div className="rounded-xl border border-slate-200 bg-slate-50 px-3 py-2">
                    <p className="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Role key</p>
                    <code className="mt-1 block text-xs text-slate-700">{editingRole.code}</code>
                    {editingRole.is_system && (
                      <p className="mt-1 text-[11px] text-slate-500">System role keys cannot be changed.</p>
                    )}
                  </div>
                )}
                <BaseInput
                  label="Display Name"
                  placeholder="e.g. Manager"
                  modelValue={form.name}
                  onUpdateModelValue={(v) => setForm({ ...form, name: v })}
                  error={formErrors.name}
                  autoFocus
                />

                <div className="flex items-center justify-between p-4 bg-slate-50 border border-slate-200/60 rounded-xl">
                  <div>
                    <p className="text-sm font-semibold text-slate-900">Active Status</p>
                    <p className="text-[11px] text-slate-500 mt-0.5">Inactive roles cannot be assigned.</p>
                  </div>
                  <button
                    type="button"
                    role="switch"
                    aria-checked={form.is_active}
                    disabled={!canSaveRole}
                    onClick={() => canSaveRole && setForm({ ...form, is_active: !form.is_active })}
                    className={`relative inline-flex h-6 w-11 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-brand-500 focus:ring-offset-2 disabled:cursor-not-allowed ${
                      form.is_active ? 'bg-brand-500' : 'bg-slate-200'
                    }`}
                  >
                    <span
                      aria-hidden="true"
                      className={`pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out ${
                        form.is_active ? 'translate-x-5' : 'translate-x-0'
                      }`}
                    />
                  </button>
                </div>
                </fieldset>

                <div className="flex gap-3 pt-2">
                  <BaseButton type="button" variant="secondary" className="flex-1" onClick={() => setIsModalOpen(false)}>
                    Cancel
                  </BaseButton>
                  <BaseButton type="submit" className="flex-1" disabled={isSaving || !canSaveRole}>
                    {isSaving ? 'Saving...' : 'Save Role'}
                  </BaseButton>
                </div>
              </form>
            </motion.div>
          </div>
        )}
      </AnimatePresence>

      {/* Delete Confirmation Modal */}
      <AnimatePresence>
        {deleteModal.isOpen && auth.can('roles.delete') && (
          <div className="fixed inset-0 z-50 flex items-center justify-center p-4">
            <motion.div 
              initial={{ opacity: 0 }} animate={{ opacity: 1 }} exit={{ opacity: 0 }}
              className="absolute inset-0 bg-slate-900/40 backdrop-blur-sm"
              onClick={() => { setDeleteModal({ isOpen: false, role: null }); setDeleteError('') }}
            />
            <motion.div 
              initial={{ opacity: 0, scale: 0.95, y: 10 }}
              animate={{ opacity: 1, scale: 1, y: 0 }}
              exit={{ opacity: 0, scale: 0.95, y: 10 }}
              className="relative w-full max-w-sm bg-white rounded-2xl shadow-2xl overflow-hidden p-6 text-center"
            >
              <div className="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-rose-100 mb-4">
                <AlertTriangle className="h-6 w-6 text-rose-600" />
              </div>
              <h3 className="text-lg font-bold text-slate-900 mb-2">Delete Role</h3>
              <p className="text-sm text-slate-500 mb-4">
                Are you sure you want to delete the role <span className="font-semibold text-slate-700">"{deleteModal.role?.name}"</span>? Any staff assigned to this role may lose access.
              </p>
              {deleteError && (
                <div className="flex items-center gap-2 text-sm text-rose-700 font-medium bg-rose-50 border border-rose-200 rounded-xl px-4 py-3 mb-4 text-left">
                  <AlertTriangle className="w-4 h-4 flex-shrink-0" />
                  {deleteError}
                </div>
              )}
              <div className="flex gap-3">
                <BaseButton type="button" variant="secondary" className="flex-1" onClick={() => { setDeleteModal({ isOpen: false, role: null }); setDeleteError('') }} disabled={isDeleting}>
                  Cancel
                </BaseButton>
                <BaseButton type="button" variant="danger" className="flex-1" onClick={handleDelete} disabled={isDeleting} loading={isDeleting}>
                  Delete
                </BaseButton>
              </div>
            </motion.div>
          </div>
        )}
      </AnimatePresence>
    </div>
  )
}
