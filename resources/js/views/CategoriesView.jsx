import React, { useCallback, useEffect, useState, useMemo } from 'react'
import { motion, AnimatePresence } from 'framer-motion'
import { Tags, Plus, Search, Edit2, Trash2, AlertTriangle, CheckCircle2, X } from 'lucide-react'
import PageHeader from '../components/ui/PageHeader.jsx'
import BaseButton from '../components/ui/BaseButton.jsx'
import BaseInput from '../components/ui/BaseInput.jsx'
import ListPagination from '../components/ui/ListPagination.jsx'
import { useAuthStore } from '../stores/auth'
import { subscriptionPageSubtitle } from '../lib/subscriptionModules.js'
import { apiDelete, apiPost, apiPut, fetchMasterList, parseItem } from '../lib/apiHelpers'

export default function CategoriesView() {
  const auth = useAuthStore()
  const canCreate = auth.can('categories.create')
  const canUpdate = auth.can('categories.update')
  const canDelete = auth.can('categories.delete')
  
  const [categories, setCategories] = useState([])
  const [isLoading, setIsLoading] = useState(true)
  const [search, setSearch] = useState('')
  const [page, setPage] = useState(1)
  const [itemsPerPage, setItemsPerPage] = useState(10)
  
  // Modal state
  const [isModalOpen, setIsModalOpen] = useState(false)
  const [editingCategory, setEditingCategory] = useState(null)
  const [form, setForm] = useState({ name: '', is_active: true })
  const [formErrors, setFormErrors] = useState({})
  const [isSaving, setIsSaving] = useState(false)
  const [successMessage, setSuccessMessage] = useState('')
  const [deleteModal, setDeleteModal] = useState({ isOpen: false, category: null })
  const [deleteError, setDeleteError] = useState('')
  const [isDeleting, setIsDeleting] = useState(false)

  const canSaveModal = editingCategory ? canUpdate : canCreate

  const fetchCategories = useCallback(async () => {
    setIsLoading(true)
    try {
      const rows = await fetchMasterList('/v1/categories', 'categories')
      setCategories(rows)
    } catch (error) {
      console.error('Failed to fetch categories:', error)
    } finally {
      setIsLoading(false)
    }
  }, [auth])

  useEffect(() => {
    void fetchCategories()
  }, [fetchCategories])

  const filteredCategories = useMemo(() => {
    return categories.filter(c => 
      c.name.toLowerCase().includes(search.toLowerCase())
    )
  }, [categories, search])

  useEffect(() => { setPage(1) }, [search, itemsPerPage])

  const paginatedCategories = useMemo(() => {
    const start = (page - 1) * itemsPerPage
    return filteredCategories.slice(start, start + itemsPerPage)
  }, [filteredCategories, page, itemsPerPage])

  const openCreateModal = () => {
    if (!canCreate) return
    setEditingCategory(null)
    setForm({ name: '', is_active: true })
    setFormErrors({})
    setIsModalOpen(true)
  }

  const openEditModal = (category) => {
    if (!canUpdate) return
    setEditingCategory(category)
    setForm({ name: category.name, is_active: category.is_active })
    setFormErrors({})
    setIsModalOpen(true)
  }

  const closeModal = () => {
    setIsModalOpen(false)
  }

  const saveCategory = async (e) => {
    e.preventDefault()
    if (editingCategory ? !canUpdate : !canCreate) return
    setFormErrors({})
    
    if (!form.name.trim()) {
      setFormErrors({ name: 'Category name is required.' })
      return
    }

    setIsSaving(true)
    try {
      if (editingCategory) {
        const response = await apiPut(`/v1/categories/${editingCategory.id}`, form)
        const updated = parseItem(response, 'category') || response.data.data || response.data
        setCategories(prev => prev.map(c => c.id === updated.id ? updated : c))
      } else {
        const response = await apiPost('/v1/categories', form)
        const created = parseItem(response, 'category') || response.data.data || response.data
        setCategories(prev => [created, ...prev])
      }
      setSuccessMessage(editingCategory ? 'Category updated successfully.' : 'Category created successfully.')
      setTimeout(() => setSuccessMessage(''), 3000)
      closeModal()
    } catch (error) {
      const serverErrors = error?.response?.data?.errors || {}
      if (Object.keys(serverErrors).length > 0) {
        const newErrors = {}
        for (const key in serverErrors) {
          newErrors[key] = serverErrors[key][0]
        }
        setFormErrors(newErrors)
      } else {
        setFormErrors({ _general: error?.response?.data?.message || 'Failed to save category.' })
      }
    } finally {
      setIsSaving(false)
    }
  }

  const confirmDelete = (category) => {
    if (!canDelete) return
    setDeleteModal({ isOpen: true, category })
  }

  const handleDelete = async () => {
    if (!canDelete || !deleteModal.category) return
    setIsDeleting(true)
    setDeleteError('')
    try {
      await apiDelete(`/v1/categories/${deleteModal.category.id}`)
      setCategories(prev => prev.filter(c => c.id !== deleteModal.category.id))
      setSuccessMessage('Category deleted successfully.')
      setTimeout(() => setSuccessMessage(''), 3000)
      setDeleteModal({ isOpen: false, category: null })
      setDeleteError('')
    } catch (error) {
      setDeleteError(error?.response?.data?.message || 'Failed to delete category.')
    } finally {
      setIsDeleting(false)
    }
  }

  return (
    <div className="space-y-6 pb-12">
      <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <PageHeader
          title="Categories"
          subtitle={subscriptionPageSubtitle(
            auth,
            canCreate || canUpdate || canDelete
              ? 'Manage categories used by services and products.'
              : 'Browse categories (read-only).',
          )}
        />
        {canCreate && (
        <BaseButton onClick={openCreateModal} size="sm" className="w-full sm:w-auto" leftIcon={Plus}>
          Add Category
        </BaseButton>
        )}
      </div>

      <div className="flex flex-col sm:flex-row gap-3">
        <div className="relative flex-1 max-w-md">
          <Search className="absolute left-3.5 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400" />
          <input
            type="text"
            value={search}
            onChange={e => setSearch(e.target.value)}
            placeholder="Search categories..."
            className="w-full pl-10 pr-4 py-2.5 text-sm border border-slate-200 rounded-xl bg-white shadow-sm focus:outline-none focus:ring-2 focus:ring-brand-500/20 focus:border-brand-400 transition"
          />
          {search && (
            <button onClick={() => setSearch('')} className="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600 cursor-pointer">
              <X className="w-3.5 h-3.5" />
            </button>
          )}
        </div>
      </div>

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
      </AnimatePresence>

      <div className="bg-white border border-slate-200/80 rounded-2xl shadow-sm overflow-hidden min-h-[400px]">
        {isLoading ? (
          <div className="flex flex-col items-center justify-center py-20 text-slate-400">
            <svg className="h-8 w-8 animate-spin text-brand-500 mb-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
              <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="3" />
              <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v3a5 5 0 00-5 5H4z" />
            </svg>
            <p>Loading categories...</p>
          </div>
        ) : (
          <div className="overflow-x-auto">
            <table className="w-full text-left">
              <thead>
                <tr className="border-b border-slate-100 bg-slate-50/50">
                  <th className="px-6 py-4 text-[11px] font-bold uppercase tracking-wider text-slate-400 whitespace-nowrap">
                    Category Name
                  </th>
                  <th className="px-6 py-4 text-[11px] font-bold uppercase tracking-wider text-slate-400 whitespace-nowrap w-40">
                    Status
                  </th>
                  <th className="px-6 py-4 text-[11px] font-bold uppercase tracking-wider text-slate-400 whitespace-nowrap w-24 text-right">
                    Actions
                  </th>
                </tr>
              </thead>
              <tbody>
                {paginatedCategories.length > 0 ? (
                  paginatedCategories.map((category, i) => (
                    <motion.tr
                      key={category.id}
                      initial={{ opacity: 0, y: 8 }}
                      animate={{ opacity: 1, y: 0 }}
                      transition={{ delay: i * 0.04 }}
                      whileHover={{ backgroundColor: 'rgba(248,250,252,0.8)' }}
                      className={`border-b border-slate-100 group ${canUpdate ? 'cursor-pointer' : ''}`}
                      onClick={() => canUpdate && openEditModal(category)}
                    >
                      <td className="py-4 px-6">
                        <div className="flex items-center gap-3">
                          <div className="w-9 h-9 rounded-xl bg-slate-100 border border-slate-200 flex items-center justify-center text-slate-400 group-hover:bg-brand-50 group-hover:text-brand-600 transition-colors">
                            <Tags className="w-4 h-4" />
                          </div>
                          <p className="text-sm font-semibold text-slate-900">{category.name}</p>
                        </div>
                      </td>
                      <td className="py-4 px-6">
                        {category.is_active ? (
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
                        {(canUpdate || canDelete) && (
                        <div className="flex items-center gap-2 justify-end">
                          {canUpdate && (
                          <button 
                            onClick={(e) => {
                              e.stopPropagation()
                              openEditModal(category)
                            }}
                            className="p-1.5 rounded-lg bg-slate-100 hover:bg-brand-50 hover:text-brand-600 text-slate-400 transition cursor-pointer"
                            title="Edit Category"
                          >
                            <Edit2 className="w-4 h-4" />
                          </button>
                          )}
                          {canDelete && (
                          <button 
                            onClick={(e) => {
                              e.stopPropagation()
                              confirmDelete(category)
                            }}
                            className="p-1.5 rounded-lg bg-slate-100 hover:bg-rose-50 hover:text-rose-600 text-slate-400 transition cursor-pointer"
                            title="Delete Category"
                          >
                            <Trash2 className="w-4 h-4" />
                          </button>
                          )}
                        </div>
                        )}
                      </td>
                    </motion.tr>
                  ))
                ) : (
                  <tr>
                    <td colSpan={3} className="py-16 text-center text-slate-400">
                      <Tags className="w-10 h-10 mx-auto mb-3 text-slate-300" />
                      <p className="text-lg font-semibold text-slate-600">No categories found</p>
                      <p className="text-sm mt-1">
                        {search ? 'Try adjusting your search criteria.' : 'Create your first category to get started.'}
                      </p>
                      {!search && canCreate && (
                        <BaseButton onClick={openCreateModal} className="mt-4" size="sm" leftIcon={Plus}>
                          Add Category
                        </BaseButton>
                      )}
                    </td>
                  </tr>
                )}
              </tbody>
            </table>
          </div>
        )}
        {!isLoading && (
          <ListPagination
            page={page}
            pageSize={itemsPerPage}
            total={filteredCategories.length}
            onPageChange={setPage}
            onPageSizeChange={setItemsPerPage}
            label="categories"
          />
        )}
      </div>

      {/* Modal */}
      <AnimatePresence>
        {isModalOpen && (editingCategory ? canUpdate : canCreate) && (
          <div className="fixed inset-0 z-50 flex items-center justify-center p-4">
            <motion.div 
              initial={{ opacity: 0 }} 
              animate={{ opacity: 1 }} 
              exit={{ opacity: 0 }}
              className="absolute inset-0 bg-slate-900/40 backdrop-blur-sm"
              onClick={closeModal}
            />
            <motion.div 
              initial={{ opacity: 0, scale: 0.95, y: 10 }}
              animate={{ opacity: 1, scale: 1, y: 0 }}
              exit={{ opacity: 0, scale: 0.95, y: 10 }}
              className="relative w-full max-w-md bg-white rounded-2xl shadow-2xl overflow-hidden"
            >
              <div className="px-6 py-5 border-b border-slate-100 flex items-center justify-between">
                <h3 className="text-lg font-bold text-slate-900">
                  {editingCategory ? 'Edit Category' : 'Create Category'}
                </h3>
                <button 
                  onClick={closeModal}
                  className="p-1.5 text-slate-400 hover:text-slate-600 hover:bg-slate-100 rounded-lg transition"
                >
                  <X className="w-5 h-5" />
                </button>
              </div>
              
              <form onSubmit={saveCategory} className="p-6 space-y-5">
                <fieldset disabled={!canSaveModal} className="space-y-5 disabled:opacity-60">
                {formErrors._general && (
                  <div className="p-3 text-sm text-rose-700 bg-rose-50 border border-rose-200 rounded-xl">
                    {formErrors._general}
                  </div>
                )}
                
                <BaseInput
                  label="Category Name"
                  placeholder="e.g. Hair Care"
                  modelValue={form.name}
                  onUpdateModelValue={(v) => setForm({ ...form, name: v })}
                  error={formErrors.name}
                  autoFocus
                />

                <div className="flex items-center justify-between p-4 bg-slate-50 border border-slate-200/60 rounded-xl">
                  <div>
                    <p className="text-sm font-semibold text-slate-900">Active Status</p>
                    <p className="text-[11px] text-slate-500 mt-0.5">Inactive categories are hidden from selections</p>
                  </div>
                  <button
                    type="button"
                    role="switch"
                    aria-checked={form.is_active}
                    disabled={!canSaveModal}
                    onClick={() => canSaveModal && setForm({ ...form, is_active: !form.is_active })}
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
                  <BaseButton 
                    type="button" 
                    variant="secondary" 
                    className="flex-1" 
                    onClick={closeModal}
                  >
                    Cancel
                  </BaseButton>
                  <BaseButton 
                    type="submit" 
                    className="flex-1"
                    disabled={isSaving || !canSaveModal}
                  >
                    {isSaving ? 'Saving...' : 'Save Category'}
                  </BaseButton>
                </div>
              </form>
            </motion.div>
          </div>
        )}
      </AnimatePresence>
      {/* Delete Confirmation Modal */}
      <AnimatePresence>
        {deleteModal.isOpen && canDelete && (
          <div className="fixed inset-0 z-50 flex items-center justify-center p-4">
            <motion.div
              initial={{ opacity: 0 }}
              animate={{ opacity: 1 }}
              exit={{ opacity: 0 }}
              className="absolute inset-0 bg-slate-900/40 backdrop-blur-sm"
              onClick={() => { setDeleteModal({ isOpen: false, category: null }); setDeleteError('') }}
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
              <h3 className="text-lg font-bold text-slate-900 mb-2">Delete Category</h3>
              <p className="text-sm text-slate-500 mb-4">
                Are you sure you want to delete <span className="font-semibold text-slate-700">"{deleteModal.category?.name}"</span>? This cannot be undone.
              </p>
              {deleteError && (
                <div className="flex items-center gap-2 text-sm text-rose-700 font-medium bg-rose-50 border border-rose-200 rounded-xl px-4 py-3 mb-4 text-left">
                  <AlertTriangle className="w-4 h-4 flex-shrink-0" />{deleteError}
                </div>
              )}
              <div className="flex gap-3">
                <BaseButton
                  type="button"
                  variant="secondary"
                  className="flex-1"
                  onClick={() => { setDeleteModal({ isOpen: false, category: null }); setDeleteError('') }}
                  disabled={isDeleting}
                >
                  Cancel
                </BaseButton>
                <BaseButton
                  type="button"
                  variant="danger"
                  className="flex-1"
                  onClick={handleDelete}
                  disabled={isDeleting}
                  loading={isDeleting}
                >
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
