import React, { useState, useEffect, useCallback, useMemo } from 'react'
import {
  Package, Plus, Pencil, Trash2, Loader2, Save,
  ShieldAlert, AlertTriangle, RefreshCw, Search,
  X, CheckCircle2, ToggleLeft, ToggleRight
} from 'lucide-react'
import { useAuthStore } from '../../stores/auth'
import { api } from '../../lib/api.js'
import { subscriptionPageSubtitle } from '../../lib/subscriptionModules.js'
import PageHeader from '../../components/ui/PageHeader.jsx'
import ListPagination from '../../components/ui/ListPagination.jsx'
import BaseButton from '../../components/ui/BaseButton.jsx'
import BaseInput from '../../components/ui/BaseInput.jsx'
import BaseModal from '../../components/ui/BaseModal.jsx'
import { fetchMasterList } from '../../lib/apiHelpers'

// ─── Axios helper ─────────────────────────────────────────────
function useApi() {
  return useCallback(
    (method, url, data = null, params = {}) =>
      api({ method, url: `/v1${url}`, data, params }),
    [],
  )
}

// ─── Parse Laravel list response (paginated or flat array) ───
function parseList(res, key) {
  const d = res.data?.data?.[key] ?? res.data?.[key]
  if (!d) return []
  // Paginated: { current_page, data: [...] }
  if (Array.isArray(d.data)) return d.data
  if (Array.isArray(d)) return d
  return []
}

// ─── Toast notification ───────────────────────────────────────
function Toast({ message, type = 'success', onDismiss }) {
  useEffect(() => {
    const t = setTimeout(onDismiss, 4000)
    return () => clearTimeout(t)
  }, [onDismiss])

  return (
    <div className={`fixed bottom-6 right-6 z-[100] flex items-center gap-3 px-5 py-3.5 rounded-2xl shadow-xl border text-sm font-semibold transition-all
      ${type === 'success'
        ? 'bg-emerald-50 border-emerald-200 text-emerald-800'
        : 'bg-rose-50 border-rose-200 text-rose-800'}`}
    >
      {type === 'success'
        ? <CheckCircle2 className="w-4 h-4 text-emerald-500 flex-shrink-0" />
        : <AlertTriangle className="w-4 h-4 text-rose-500 flex-shrink-0" />}
      <span>{message}</span>
      <button onClick={onDismiss} className="ml-2 text-current opacity-50 hover:opacity-100 cursor-pointer">
        <X className="w-3.5 h-3.5" />
      </button>
    </div>
  )
}

// ─── Product Form Modal ───────────────────────────────────────
function ProductFormModal({ product, open, onClose, onSaved }) {
  const api = useApi()
  const auth = useAuthStore()
  const isEdit = Boolean(product?.id)
  const canSave = auth.can(isEdit ? 'products.update' : 'products.create')

  const [name, setName]         = useState(product?.name || '')
  const [isActive, setIsActive] = useState(product?.is_active ?? true)
  const [errors, setErrors]     = useState({})
  const [saving, setSaving]     = useState(false)
  const [serverError, setServerError] = useState('')

  // Reset form when modal opens/changes product
  useEffect(() => {
    if (open) {
      setName(product?.name || '')
      setIsActive(product?.is_active ?? true)
      setErrors({})
      setServerError('')
    }
  }, [open, product?.id])

  const handleSubmit = async (e) => {
    e.preventDefault()
    if (!canSave) return
    setSaving(true)
    setErrors({})
    setServerError('')
    try {
      const payload = { name: name.trim(), is_active: Boolean(isActive) }
      const res = isEdit
        ? await api('put',  `/products/${product.id}`, payload)
        : await api('post', '/products',               payload)

      const saved = res.data?.data?.product ?? res.data?.product
      onSaved(saved, isEdit, isEdit ? 'Product updated successfully.' : 'Product created successfully.')
      onClose()
    } catch (err) {
      const status = err?.response?.status
      if (status === 422) {
        setErrors(err.response.data?.errors || {})
        setServerError(err.response.data?.message || '')
      } else {
        setServerError(err?.response?.data?.message || 'Something went wrong. Please try again.')
      }
    } finally {
      setSaving(false)
    }
  }

  return (
    <BaseModal
      open={open}
      onClose={onClose}
      title={isEdit ? 'Edit Product' : 'Add Product'}
      size="sm"
      footer={
        <>
          <BaseButton type="button" variant="secondary" size="sm" onClick={onClose}>
            Cancel
          </BaseButton>
          <BaseButton
            type="submit"
            form="product-form"
            size="sm"
            loading={saving}
            disabled={saving}
            leftIcon={saving ? Loader2 : Save}
          >
            {isEdit ? 'Update' : 'Create'}
          </BaseButton>
        </>
      }
    >
      <form id="product-form" onSubmit={handleSubmit} className="space-y-4">
        {serverError && (
          <div className="flex items-center gap-2 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800 font-medium">
            <ShieldAlert className="w-4 h-4 text-rose-500 flex-shrink-0" />
            <span>{serverError}</span>
          </div>
        )}

        <BaseInput
          label="Product Name"
          type="text"
          modelValue={name}
          onUpdateModelValue={setName}
          error={errors.name?.[0]}
          placeholder="e.g. Shampoo"
          required
          prefix={Package}
        />

        {/* Active toggle */}
        <div>
          <label className="block text-sm font-medium text-slate-700 mb-1.5">Status</label>
          <button
            type="button"
            onClick={() => setIsActive((v) => !v)}
            className={`flex items-center gap-3 w-full px-4 py-3 rounded-xl border transition-colors cursor-pointer ${
              isActive
                ? 'bg-emerald-50 border-emerald-200 text-emerald-800'
                : 'bg-slate-50 border-slate-200 text-slate-600'
            }`}
          >
            {isActive
              ? <ToggleRight className="w-5 h-5 text-emerald-500 flex-shrink-0" />
              : <ToggleLeft  className="w-5 h-5 text-slate-400  flex-shrink-0" />}
            <div className="text-left">
              <p className="text-sm font-semibold">{isActive ? 'Active' : 'Inactive'}</p>
              <p className="text-xs mt-0.5 opacity-60">
                {isActive ? 'Visible and available for use.' : 'Hidden from service assignments.'}
              </p>
            </div>
          </button>
          {errors.is_active && (
            <p className="mt-1.5 text-sm text-rose-500 font-medium">{errors.is_active[0]}</p>
          )}
        </div>
      </form>
    </BaseModal>
  )
}

// ─── Delete Confirm Modal ─────────────────────────────────────
function DeleteProductModal({ product, open, onClose, onDeleted }) {
  const api = useApi()
  const auth = useAuthStore()
  const [deleting, setDeleting] = useState(false)
  const [error, setError]       = useState('')

  useEffect(() => { if (open) setError('') }, [open])

  const handleDelete = async () => {
    if (!auth.can('products.delete') || !product) return
    setDeleting(true)
    setError('')
    try {
      await api('delete', `/products/${product.id}`)
      onDeleted(product.id, 'Product deleted successfully.')
      onClose()
    } catch (err) {
      setError(err?.response?.data?.message || 'Failed to delete. Please try again.')
    } finally {
      setDeleting(false)
    }
  }

  return (
    <BaseModal
      open={open}
      onClose={onClose}
      title="Delete Product"
      size="sm"
      footer={
        <>
          <BaseButton variant="secondary" size="sm" onClick={onClose}>Cancel</BaseButton>
          <BaseButton
            variant="danger"
            size="sm"
            loading={deleting}
            disabled={deleting}
            leftIcon={deleting ? Loader2 : Trash2}
            onClick={handleDelete}
          >
            Delete
          </BaseButton>
        </>
      }
    >
      <div className="space-y-3">
        <p className="text-sm text-slate-600">
          Are you sure you want to delete{' '}
          <span className="font-bold text-slate-900">"{product?.name}"</span>?
          This action cannot be undone.
        </p>
        {error && (
          <div className="flex items-center gap-2 text-sm text-rose-700 font-medium bg-rose-50 border border-rose-200 rounded-xl px-4 py-3">
            <AlertTriangle className="w-4 h-4 flex-shrink-0" />
            {error}
          </div>
        )}
      </div>
    </BaseModal>
  )
}

// ─── Product Row ──────────────────────────────────────────────
function ProductRow({ product, index, onEdit, onDelete, canUpdate, canDelete }) {
  return (
    <tr className="border-b border-slate-100 group hover:bg-slate-50/60 transition-colors">
      <td className="py-3.5 px-5 text-xs font-bold text-slate-400">#{product.id}</td>
      <td className="py-3.5 px-5">
        <div className="flex items-center gap-2.5">
          <div className="w-8 h-8 rounded-lg bg-brand-50 border border-brand-100 flex items-center justify-center text-brand-600">
            <Package className="w-3.5 h-3.5" />
          </div>
          <span className="text-sm font-semibold text-slate-800">{product.name}</span>
        </div>
      </td>
      <td className="py-3.5 px-5">
        <span className="text-xs font-semibold text-slate-700 bg-slate-100 px-2 py-0.5 rounded-md border border-slate-200">
          {product.category?.name || '—'}
        </span>
      </td>
      <td className="py-3.5 px-5">
        <span className={`inline-flex items-center gap-1.5 px-2.5 py-1 text-xs font-semibold rounded-full border ${
          product.is_active
            ? 'bg-emerald-50 border-emerald-200 text-emerald-700'
            : 'bg-slate-100 border-slate-200 text-slate-500'
        }`}>
          <span className={`w-1.5 h-1.5 rounded-full ${product.is_active ? 'bg-emerald-500' : 'bg-slate-400'}`} />
          {product.is_active ? 'Active' : 'Inactive'}
        </span>
      </td>
      <td className="py-3.5 px-5 text-sm text-slate-500">
        {new Date(product.created_at).toLocaleDateString('en-IN', {
          day: '2-digit', month: 'short', year: 'numeric',
        })}
      </td>
      <td className="py-3.5 px-5">
        {(canUpdate || canDelete) && (
        <div className="flex items-center gap-1.5 opacity-0 group-hover:opacity-100 transition-opacity justify-end">
          {canUpdate && (
          <button
            onClick={() => onEdit(product)}
            className="flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold rounded-lg bg-indigo-50 border border-indigo-100 text-indigo-600 hover:bg-indigo-100 transition cursor-pointer"
          >
            <Pencil className="w-3 h-3" /> Edit
          </button>
          )}
          {canDelete && (
          <button
            onClick={() => onDelete(product)}
            className="flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold rounded-lg bg-rose-50 border border-rose-100 text-rose-600 hover:bg-rose-100 transition cursor-pointer"
          >
            <Trash2 className="w-3 h-3" /> Delete
          </button>
          )}
        </div>
        )}
      </td>
    </tr>
  )
}

// ─── Main View ────────────────────────────────────────────────
export default function ProductsMasterView() {
  const auth = useAuthStore()
  const canCreate = auth.can('products.create')
  const canUpdate = auth.can('products.update')
  const canDelete = auth.can('products.delete')

  const [products,    setProducts]    = useState([])
  const [loading,     setLoading]     = useState(true)
  const [fetchError,  setFetchError]  = useState('')
  const [search,      setSearch]      = useState('')
  const [page,        setPage]        = useState(1)
  const [itemsPerPage, setItemsPerPage] = useState(10)
  const [toast,       setToast]       = useState(null) // { message, type }

  // Modal states
  const [formOpen,    setFormOpen]    = useState(false)
  const [editProduct, setEditProduct] = useState(null)
  const [deleteOpen,  setDeleteOpen]  = useState(false)
  const [delProduct,  setDelProduct]  = useState(null)

  const showToast = useCallback((message, type = 'success') => {
    setToast({ message, type })
  }, [])

  // ── Fetch ─────────────────────────────────────────────────
  const fetchProducts = useCallback(async () => {
    setLoading(true)
    setFetchError('')
    try {
      const rows = await fetchMasterList('/v1/products', 'products')
      setProducts(rows)
    } catch (err) {
      if (err?.response?.status === 401) {
        auth.clearAuthState()
        window.location.href = '/login'
        return
      }
      setFetchError(err?.response?.data?.message || 'Failed to load products.')
    } finally {
      setLoading(false)
    }
  }, [auth])

  useEffect(() => { fetchProducts() }, [fetchProducts])

  // ── Handlers ─────────────────────────────────────────────
  const openCreate = () => { if (!canCreate) return; setEditProduct(null); setFormOpen(true) }
  const openEdit   = (p)  => { if (!canUpdate) return; setEditProduct(p); setFormOpen(true) }
  const openDelete = (p)  => { if (!canDelete) return; setDelProduct(p); setDeleteOpen(true) }

  const handleSaved = (saved, isEdit, msg) => {
    setProducts((prev) =>
      isEdit ? prev.map((p) => (p.id === saved.id ? saved : p)) : [saved, ...prev]
    )
    showToast(msg)
  }

  const handleDeleted = (id, msg) => {
    setProducts((prev) => prev.filter((p) => p.id !== id))
    showToast(msg)
  }

  const filtered = useMemo(() => products.filter(
    (p) => !search
      || p.name.toLowerCase().includes(search.toLowerCase())
      || (p.category?.name || '').toLowerCase().includes(search.toLowerCase()),
  ), [products, search])

  useEffect(() => { setPage(1) }, [search, itemsPerPage])

  const paginated = useMemo(() => {
    const start = (page - 1) * itemsPerPage
    return filtered.slice(start, start + itemsPerPage)
  }, [filtered, page, itemsPerPage])

  const activeCount   = products.filter((p) => p.is_active).length
  const inactiveCount = products.length - activeCount

  return (
    <div className="space-y-6">
      {/* Toast */}
      {toast && (
        <Toast
          message={toast.message}
          type={toast.type}
          onDismiss={() => setToast(null)}
        />
      )}

      {/* Header */}
      <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <PageHeader
          title="Products Master"
          subtitle={subscriptionPageSubtitle(
            auth,
            canCreate || canUpdate || canDelete
              ? 'Manage your product catalogue. Products can be linked to services.'
              : 'Browse products (read-only).',
          )}
        />
        <div className="flex w-full flex-col gap-2 sm:w-auto sm:flex-row sm:self-center">
          <BaseButton variant="secondary" size="sm" className="w-full sm:w-auto" leftIcon={RefreshCw} onClick={fetchProducts} loading={loading}>
            Refresh
          </BaseButton>
          {canCreate && (
          <BaseButton size="sm" className="w-full sm:w-auto" leftIcon={Plus} onClick={openCreate}>
            Add Product
          </BaseButton>
          )}
        </div>
      </div>

      {/* Stats */}
      <div className="grid grid-cols-3 gap-4">
        {[
          { label: 'Total',    value: products.length, color: 'text-indigo-600' },
          { label: 'Active',   value: activeCount,     color: 'text-brand-600'   },
          { label: 'Inactive', value: inactiveCount,   color: 'text-slate-500'  },
        ].map(({ label, value, color }) => (
          <div key={label} className="bg-white border border-slate-200/80 rounded-2xl p-5 shadow-sm">
            <p className="text-xs font-bold uppercase tracking-wider text-slate-400">{label}</p>
            <p className={`text-2xl font-black mt-1 ${color}`}>{value}</p>
          </div>
        ))}
      </div>

      {/* Search */}
      <div className="relative max-w-sm">
        <Search className="absolute left-3.5 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400" />
        <input
          type="text"
          value={search}
          onChange={(e) => setSearch(e.target.value)}
          placeholder="Search products..."
          className="w-full pl-10 pr-4 py-2.5 text-sm border border-slate-200 rounded-xl bg-white shadow-sm focus:outline-none focus:ring-2 focus:ring-brand-500/20 focus:border-brand-400 transition"
        />
        {search && (
          <button onClick={() => setSearch('')} className="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600 cursor-pointer">
            <X className="w-3.5 h-3.5" />
          </button>
        )}
      </div>

      {/* Table */}
      <div className="bg-white border border-slate-200/80 rounded-2xl shadow-sm overflow-hidden">
        {loading ? (
          <div className="flex flex-col items-center justify-center py-20 gap-3 text-slate-400">
            <Loader2 className="w-7 h-7 animate-spin text-brand-500" />
            <p className="text-sm font-semibold">Loading products...</p>
          </div>
        ) : fetchError ? (
          <div className="flex flex-col items-center justify-center py-20 gap-3">
            <AlertTriangle className="w-7 h-7 text-rose-400" />
            <p className="text-sm font-bold text-rose-500">{fetchError}</p>
            <BaseButton size="sm" variant="secondary" leftIcon={RefreshCw} onClick={fetchProducts}>
              Try Again
            </BaseButton>
          </div>
        ) : (
          <div className="overflow-x-auto">
            <table className="w-full text-left">
              <thead>
                <tr className="border-b border-slate-100 bg-slate-50/50">
                  {['ID', 'Product Name', 'Category', 'Status', 'Created', ''].map((col) => (
                    <th key={col || 'actions'} className="px-5 py-3 text-xs font-bold uppercase tracking-wider text-slate-400">
                      {col}
                    </th>
                  ))}
                </tr>
              </thead>
              <tbody>
                {paginated.length > 0 ? (
                  paginated.map((product, i) => (
                    <ProductRow
                      key={product.id}
                      product={product}
                      index={i}
                      onEdit={openEdit}
                      onDelete={openDelete}
                      canUpdate={canUpdate}
                      canDelete={canDelete}
                    />
                  ))
                ) : (
                  <tr>
                    <td colSpan={6} className="py-16 text-center text-slate-400">
                      <Package className="w-8 h-8 mx-auto mb-2 text-slate-300" />
                      <p className="font-semibold">
                        {search ? 'No products match your search.' : 'No products yet.'}
                      </p>
                      {!search && canCreate && (
                        <button onClick={openCreate} className="text-sm text-brand-600 font-semibold mt-1 hover:underline cursor-pointer">
                          Add your first product →
                        </button>
                      )}
                    </td>
                  </tr>
                )}
              </tbody>
            </table>
          </div>
        )}

        {!loading && !fetchError && (
          <ListPagination
            page={page}
            pageSize={itemsPerPage}
            total={filtered.length}
            onPageChange={setPage}
            onPageSizeChange={setItemsPerPage}
            label="products"
          />
        )}
      </div>

      {/* Modals */}
      <ProductFormModal
        product={editProduct}
        open={formOpen && (editProduct ? canUpdate : canCreate)}
        onClose={() => setFormOpen(false)}
        onSaved={handleSaved}
      />

      <DeleteProductModal
        product={delProduct}
        open={deleteOpen && canDelete}
        onClose={() => setDeleteOpen(false)}
        onDeleted={handleDeleted}
      />
    </div>
  )
}
