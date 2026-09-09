import React, { useState, useEffect, useCallback, useMemo } from 'react'
import {
  Scissors, Plus, Pencil, Trash2, Loader2, Save,
  ShieldAlert, AlertTriangle, RefreshCw, Search,
  X, CheckCircle2, Clock, Package
} from 'lucide-react'
import { useAuthStore } from '../../stores/auth'
import { api } from '../../lib/api.js'
import PageHeader from '../../components/ui/PageHeader.jsx'
import ListPagination from '../../components/ui/ListPagination.jsx'
import { subscriptionPageSubtitle } from '../../lib/subscriptionModules.js'
import { useTenantFormatter } from '../../hooks/useTenantFormatter.js'
import BaseButton from '../../components/ui/BaseButton.jsx'
import BaseInput from '../../components/ui/BaseInput.jsx'
import BaseModal from '../../components/ui/BaseModal.jsx'
import { fetchMasterList } from '../../lib/apiHelpers'

// ─── API helper ─────────────────────────────────────────────
function useApi() {
  return useCallback(
    (method, url, data = null, params = {}) =>
      api({ method, url: `/v1${url}`, data, params }),
    [],
  )
}

// ─── Parse Laravel list response (paginated or flat) ─────────
function parseList(res, key) {
  const d = res.data?.data?.[key] ?? res.data?.[key]
  if (!d) return []
  if (Array.isArray(d.data)) return d.data // paginated
  if (Array.isArray(d)) return d
  return []
}

// ─── Duration display ─────────────────────────────────────────
function formatDuration(mins) {
  if (!mins) return '—'
  const h = Math.floor(mins / 60)
  const m = mins % 60
  if (h === 0) return `${m}m`
  if (m === 0) return `${h}h`
  return `${h}h ${m}m`
}

// ─── Toast ────────────────────────────────────────────────────
function Toast({ message, type = 'success', onDismiss }) {
  useEffect(() => {
    const t = setTimeout(onDismiss, 4000)
    return () => clearTimeout(t)
  }, [onDismiss])

  return (
    <div className={`fixed bottom-6 right-6 z-[100] flex items-center gap-3 px-5 py-3.5 rounded-2xl shadow-xl border text-sm font-semibold
      ${type === 'success'
        ? 'bg-emerald-50 border-emerald-200 text-emerald-800'
        : 'bg-rose-50 border-rose-200 text-rose-800'}`}
    >
      {type === 'success'
        ? <CheckCircle2 className="w-4 h-4 text-emerald-500 flex-shrink-0" />
        : <AlertTriangle className="w-4 h-4 text-rose-500 flex-shrink-0" />}
      <span>{message}</span>
      <button onClick={onDismiss} className="ml-2 opacity-50 hover:opacity-100 cursor-pointer">
        <X className="w-3.5 h-3.5" />
      </button>
    </div>
  )
}

// ─── Product row inside service form ─────────────────────────
function ProductLine({ row, index, activeProducts, usedIds, onChange, onRemove }) {
  const fmt = useTenantFormatter()
  const options = activeProducts.filter(
    (p) => p.id === row.product_id || !usedIds.includes(p.id)
  )
  return (
    <div className="flex items-center gap-2">
      <select
        value={row.product_id || ''}
        onChange={(e) => onChange(index, 'product_id', Number(e.target.value))}
        className="flex-1 text-sm border border-slate-200 rounded-xl px-3 py-2.5 bg-white text-slate-700 focus:outline-none focus:ring-2 focus:ring-brand-500/20 focus:border-brand-400 transition cursor-pointer"
      >
        <option value="">— Select product —</option>
        {options.map((p) => (
          <option key={p.id} value={p.id}>{p.name}</option>
        ))}
      </select>
      <div className="relative w-32">
        <span className="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-sm">{fmt.symbol()}</span>
        <input
          type="number"
          min="0"
          step="0.01"
          value={row.default_price}
          onChange={(e) => onChange(index, 'default_price', e.target.value)}
          placeholder="Price"
          className="w-full pl-7 pr-3 py-2.5 text-sm border border-slate-200 rounded-xl bg-white text-slate-700 focus:outline-none focus:ring-2 focus:ring-brand-500/20 focus:border-brand-400 transition"
        />
      </div>
      <button
        type="button"
        onClick={() => onRemove(index)}
        className="p-2 rounded-xl text-slate-400 hover:text-rose-500 hover:bg-rose-50 transition cursor-pointer flex-shrink-0"
      >
        <X className="w-4 h-4" />
      </button>
    </div>
  )
}

// ─── Service Form Modal ───────────────────────────────────────
function ServiceFormModal({ service, allProducts, open, onClose, onSaved }) {
  const api    = useApi()
  const auth   = useAuthStore()
  const fmt    = useTenantFormatter()
  const isEdit = Boolean(service?.id)
  const canSave = auth.can(isEdit ? 'services.update' : 'services.create')

  const [name,     setName]     = useState('')
  const [price,    setPrice]    = useState('')
  const [duration, setDuration] = useState('')
  const [isActive, setIsActive] = useState(true)
  const [rows,     setRows]     = useState([])
  const [errors,   setErrors]   = useState({})
  const [saving,   setSaving]   = useState(false)
  const [serverError, setServerError] = useState('')

  useEffect(() => {
    if (open) {
      setName(service?.name || '')
      setPrice(service?.default_price ?? '')
      setDuration(service?.duration_minutes ?? '')
      setIsActive(isEdit ? Boolean(service.is_active) : true)
      setRows(
        service?.products?.map((p) => ({
          product_id:    p.product_id,
          default_price: p.default_price,
        })) || []
      )
      setErrors({})
      setServerError('')
    }
  }, [open, service, isEdit])

  const activeProducts = allProducts.filter((p) => p.is_active)
  const usedIds        = rows.map((r) => r.product_id).filter(Boolean)

  const addRow    = () => setRows((prev) => [...prev, { product_id: '', default_price: '' }])
  const removeRow = (i) => setRows((prev) => prev.filter((_, idx) => idx !== i))
  const updateRow = (i, field, value) =>
    setRows((prev) => prev.map((r, idx) => (idx === i ? { ...r, [field]: value } : r)))

  const handleSubmit = async (e) => {
    e.preventDefault()
    if (!canSave) return
    // Duplicate check
    const ids = rows.map((r) => r.product_id).filter(Boolean)
    if (ids.length !== new Set(ids).size) {
      setServerError('Each product can only be added once per service.')
      return
    }

    setSaving(true)
    setErrors({})
    setServerError('')

    const payload = {
      name:             name.trim(),
      default_price:    parseFloat(price),
      duration_minutes: parseInt(duration, 10),
      is_active:        Boolean(isActive),
      products: rows
        .filter((r) => r.product_id)
        .map((r) => ({
          product_id:    Number(r.product_id),
          default_price: parseFloat(r.default_price) || 0,
        })),
    }

    try {
      const res = isEdit
        ? await api('put',  `/services/${service.id}`, payload)
        : await api('post', '/services',              payload)

      const saved = res.data?.data?.service ?? res.data?.service
      onSaved(saved, isEdit, isEdit ? 'Service updated successfully.' : 'Service created successfully.')
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
      title={isEdit ? 'Edit Service' : 'Add Service'}
      size="md"
      footer={
        <>
          <BaseButton type="button" variant="secondary" size="sm" onClick={onClose}>Cancel</BaseButton>
          <BaseButton
            type="submit"
            form="service-form"
            size="sm"
            loading={saving}
            disabled={saving}
            leftIcon={saving ? Loader2 : Save}
          >
            {isEdit ? 'Update Service' : 'Create Service'}
          </BaseButton>
        </>
      }
    >
      <form id="service-form" onSubmit={handleSubmit} className="space-y-5">
        {serverError && (
          <div className="flex items-center gap-2 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800 font-medium">
            <ShieldAlert className="w-4 h-4 text-rose-500 flex-shrink-0" />
            <span>{serverError}</span>
          </div>
        )}

        {/* Name */}
        <BaseInput
          label="Service Name"
          type="text"
          modelValue={name}
          onUpdateModelValue={setName}
          error={errors.name?.[0]}
          placeholder="e.g. Hair Wash"
          required
          prefix={Scissors}
        />

        {/* Price + Duration */}
        <div className="grid grid-cols-2 gap-4">
          <div>
            <label className="block text-sm font-medium text-slate-700 mb-1.5">
              Default Price ({fmt.symbol()}) <span className="text-rose-500">*</span>
            </label>
            <div className="relative flex items-center rounded-xl overflow-hidden shadow-sm ring-1 ring-inset ring-slate-300 focus-within:ring-2 focus-within:ring-brand-500 transition-shadow bg-white">
              <span className="pl-3 text-slate-400 text-sm font-medium">{fmt.symbol()}</span>
              <input
                type="number"
                min="0"
                step="0.01"
                value={price}
                onChange={(e) => setPrice(e.target.value)}
                placeholder="0.00"
                required
                className="block w-full border-0 bg-transparent py-2.5 pl-2 pr-3 text-slate-900 placeholder:text-slate-400 focus:ring-0 sm:text-sm"
              />
            </div>
            {errors.default_price && (
              <p className="mt-1.5 text-sm text-rose-500 font-medium">{errors.default_price[0]}</p>
            )}
          </div>

          <div>
            <label className="block text-sm font-medium text-slate-700 mb-1.5">
              Duration (min) <span className="text-rose-500">*</span>
            </label>
            <div className="relative flex items-center rounded-xl overflow-hidden shadow-sm ring-1 ring-inset ring-slate-300 focus-within:ring-2 focus-within:ring-brand-500 transition-shadow bg-white">
              <div className="pl-3 text-slate-400">
                <Clock className="w-4 h-4" />
              </div>
              <input
                type="number"
                min="1"
                max="1440"
                step="1"
                value={duration}
                onChange={(e) => setDuration(e.target.value)}
                placeholder="30"
                required
                className="block w-full border-0 bg-transparent py-2.5 pl-2 pr-3 text-slate-900 placeholder:text-slate-400 focus:ring-0 sm:text-sm"
              />
            </div>
            {errors.duration_minutes && (
              <p className="mt-1.5 text-sm text-rose-500 font-medium">{errors.duration_minutes[0]}</p>
            )}
          </div>
        </div>

        <div className="flex items-center justify-between p-4 bg-slate-50 border border-slate-200/60 rounded-xl">
          <div>
            <p className="text-sm font-semibold text-slate-900">Active Status</p>
            <p className="text-[11px] text-slate-500 mt-0.5">Inactive services are hidden from bookings.</p>
          </div>
          <button
            type="button"
            role="switch"
            aria-checked={isActive}
            onClick={() => setIsActive((prev) => !prev)}
            className={`relative inline-flex h-6 w-11 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-brand-500 focus:ring-offset-2 ${
              isActive ? 'bg-brand-500' : 'bg-slate-200'
            }`}
          >
            <span
              aria-hidden="true"
              className={`pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out ${
                isActive ? 'translate-x-5' : 'translate-x-0'
              }`}
            />
          </button>
        </div>

        {/* Products */}
        <div>
          <div className="flex items-center justify-between mb-2">
            <div>
              <p className="text-sm font-medium text-slate-700">Linked Products</p>
              <p className="text-xs text-slate-400 mt-0.5">Optional. No duplicate products per service.</p>
            </div>
            <button
              type="button"
              onClick={addRow}
              disabled={activeProducts.length === 0}
              className="flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold rounded-lg bg-brand-50 border border-brand-100 text-brand-700 hover:bg-brand-100 transition cursor-pointer disabled:opacity-40 disabled:cursor-not-allowed"
            >
              <Plus className="w-3.5 h-3.5" /> Add
            </button>
          </div>

          {activeProducts.length === 0 ? (
            <p className="text-xs text-slate-400 bg-slate-50 border border-dashed border-slate-200 rounded-xl p-3 text-center">
              No active products. Go to <a href="/settings/products" className="text-brand-600 font-semibold hover:underline">Products</a> to create some first.
            </p>
          ) : rows.length === 0 ? (
            <p className="text-xs text-slate-400 bg-slate-50 border border-dashed border-slate-200 rounded-xl p-3 text-center">
              No products linked. Click "Add" to attach a product.
            </p>
          ) : (
            <div className="space-y-2">
              <div className="flex gap-2 text-xs font-bold uppercase tracking-wider text-slate-400">
                <span className="flex-1">Product</span>
                <span className="w-32">Price ({fmt.symbol()})</span>
                <span className="w-8"></span>
              </div>
              {rows.map((row, i) => (
                <ProductLine
                  key={i}
                  row={row}
                  index={i}
                  activeProducts={activeProducts}
                  usedIds={usedIds}
                  onChange={updateRow}
                  onRemove={removeRow}
                />
              ))}
              {errors.products && (
                <p className="text-sm text-rose-500 font-medium">{errors.products[0]}</p>
              )}
            </div>
          )}
        </div>
      </form>
    </BaseModal>
  )
}

// ─── Delete Confirm Modal ─────────────────────────────────────
function DeleteServiceModal({ service, open, onClose, onDeleted }) {
  const api  = useApi()
  const auth = useAuthStore()
  const [deleting, setDeleting] = useState(false)
  const [error,    setError]    = useState('')

  useEffect(() => { if (open) setError('') }, [open])

  const handleDelete = async () => {
    if (!auth.can('services.delete') || !service) return
    setDeleting(true)
    setError('')
    try {
      await api('delete', `/services/${service.id}`)
      onDeleted(service.id, 'Service deleted successfully.')
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
      title="Delete Service"
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
          <span className="font-bold text-slate-900">"{service?.name}"</span>?
          This cannot be undone.
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

// ─── Service Card ─────────────────────────────────────────────
function ServiceCard({ service, onEdit, onDelete, canUpdate, canDelete }) {
  const fmt = useTenantFormatter()
  const productCount = service.products?.length || 0
  return (
    <div className="bg-white border border-slate-200/80 rounded-2xl p-5 shadow-sm group hover:border-brand-300 hover:shadow-md transition-all relative overflow-hidden">
      <div className="absolute -right-8 -top-8 w-20 h-20 bg-brand-500/5 rounded-full blur-xl group-hover:bg-brand-500/10 transition duration-500" />

      <div className="flex items-start justify-between gap-3 mb-4">
        <div className="flex items-center gap-2.5 min-w-0">
          <div className="p-2 bg-brand-50 border border-brand-100 rounded-xl text-brand-600 flex-shrink-0">
            <Scissors className="w-3.5 h-3.5" />
          </div>
          <div className="min-w-0">
            <h3 className="text-sm font-bold text-slate-900 truncate group-hover:text-brand-700 transition-colors">
              {service.name}
            </h3>
            <p className="mt-0.5 text-[10px] font-semibold text-slate-500 truncate">
              {service.category?.name || 'Uncategorized'}
            </p>
            <span className={`inline-flex mt-1 items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-semibold border ${
              service.is_active
                ? 'bg-emerald-50 text-emerald-700 border-emerald-200'
                : 'bg-slate-100 text-slate-600 border-slate-200'
            }`}>
              {service.is_active ? 'Active' : 'Inactive'}
            </span>
          </div>
        </div>
        {(canUpdate || canDelete) && (
        <div className="flex gap-1 flex-shrink-0 opacity-0 group-hover:opacity-100 transition-opacity">
          {canUpdate && (
          <button
            onClick={() => onEdit(service)}
            className="p-1.5 rounded-lg bg-indigo-50 border border-indigo-100 text-indigo-600 hover:bg-indigo-100 transition cursor-pointer"
          >
            <Pencil className="w-3.5 h-3.5" />
          </button>
          )}
          {canDelete && (
          <button
            onClick={() => onDelete(service)}
            className="p-1.5 rounded-lg bg-rose-50 border border-rose-100 text-rose-600 hover:bg-rose-100 transition cursor-pointer"
          >
            <Trash2 className="w-3.5 h-3.5" />
          </button>
          )}
        </div>
        )}
      </div>

      <div className="grid grid-cols-3 gap-2 text-center">
        <div className="bg-slate-50 border border-slate-100 rounded-xl p-2.5">
          <p className="text-[9px] font-bold uppercase tracking-wider text-slate-400">Price</p>
          <p className="text-xs font-black text-brand-700 mt-0.5">
            {fmt.money(service.default_price)}
          </p>
        </div>
        <div className="bg-slate-50 border border-slate-100 rounded-xl p-2.5">
          <p className="text-[9px] font-bold uppercase tracking-wider text-slate-400">Duration</p>
          <p className="text-xs font-black text-slate-800 mt-0.5">{formatDuration(service.duration_minutes)}</p>
        </div>
        <div className="bg-slate-50 border border-slate-100 rounded-xl p-2.5">
          <p className="text-[9px] font-bold uppercase tracking-wider text-slate-400">Products</p>
          <p className={`text-xs font-black mt-0.5 ${productCount > 0 ? 'text-indigo-600' : 'text-slate-400'}`}>
            {productCount}
          </p>
        </div>
      </div>

      {productCount > 0 && (
        <div className="mt-3 flex flex-wrap gap-1">
          {service.products.slice(0, 3).map((p) => (
            <span key={p.id} className="px-2 py-0.5 bg-indigo-50 border border-indigo-100 text-indigo-700 text-[9px] font-bold rounded-full">
              {p.product?.name || `Product #${p.product_id}`}
            </span>
          ))}
          {productCount > 3 && (
            <span className="px-2 py-0.5 bg-slate-100 text-slate-500 text-[9px] font-bold rounded-full">
              +{productCount - 3} more
            </span>
          )}
        </div>
      )}
    </div>
  )
}

// ─── Main View ────────────────────────────────────────────────
export default function ServicesMasterView() {
  const auth = useAuthStore()
  const canCreate = auth.can('services.create')
  const canUpdate = auth.can('services.update')
  const canDelete = auth.can('services.delete')

  const [services,    setServices]    = useState([])
  const [allProducts, setAllProducts] = useState([])
  const [loading,     setLoading]     = useState(true)
  const [fetchError,  setFetchError]  = useState('')
  const [search,      setSearch]      = useState('')
  const [page,        setPage]        = useState(1)
  const [itemsPerPage, setItemsPerPage] = useState(12)
  const [toast,       setToast]       = useState(null)

  const [formOpen,     setFormOpen]    = useState(false)
  const [editService,  setEditService] = useState(null)
  const [deleteOpen,   setDeleteOpen]  = useState(false)
  const [delService,   setDelService]  = useState(null)

  const showToast = useCallback((message, type = 'success') => setToast({ message, type }), [])

  // ── Fetch services + products (all pages) ──────────────────
  const fetchData = useCallback(async () => {
    setLoading(true)
    setFetchError('')
    try {
      const [svcRows, prdRows] = await Promise.all([
        fetchMasterList('/v1/services', 'services'),
        fetchMasterList('/v1/products', 'products'),
      ])
      setServices(svcRows)
      setAllProducts(prdRows)
    } catch (err) {
      if (err?.response?.status === 401) {
        auth.clearAuthState()
        window.location.href = '/login'
        return
      }
      setFetchError(err?.response?.data?.message || 'Failed to load data.')
    } finally {
      setLoading(false)
    }
  }, [auth])

  useEffect(() => { fetchData() }, [fetchData])

  const openCreate = () => { if (!canCreate) return; setEditService(null); setFormOpen(true) }
  const openEdit   = (s) => { if (!canUpdate) return; setEditService(s); setFormOpen(true) }
  const openDelete = (s) => { if (!canDelete) return; setDelService(s); setDeleteOpen(true) }

  const handleSaved = (saved, isEdit, msg) => {
    setServices((prev) =>
      isEdit ? prev.map((s) => (s.id === saved.id ? saved : s)) : [saved, ...prev]
    )
    showToast(msg)
  }

  const handleDeleted = (id, msg) => {
    setServices((prev) => prev.filter((s) => s.id !== id))
    showToast(msg)
  }

  const filtered = useMemo(() => services.filter(
    (s) => !search
      || s.name.toLowerCase().includes(search.toLowerCase())
      || (s.category?.name || '').toLowerCase().includes(search.toLowerCase()),
  ), [services, search])

  useEffect(() => { setPage(1) }, [search, itemsPerPage])

  const paginated = useMemo(() => {
    const start = (page - 1) * itemsPerPage
    return filtered.slice(start, start + itemsPerPage)
  }, [filtered, page, itemsPerPage])

  return (
    <div className="space-y-6">
      {toast && <Toast message={toast.message} type={toast.type} onDismiss={() => setToast(null)} />}

      {/* Header */}
      <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <PageHeader
          title="Services Master"
          subtitle={subscriptionPageSubtitle(
            auth,
            canCreate || canUpdate || canDelete
              ? 'Manage salon services, pricing, duration, and linked products.'
              : 'Browse services (read-only).',
          )}
        />
        <div className="flex w-full flex-col gap-2 sm:w-auto sm:flex-row sm:self-center">
          <BaseButton variant="secondary" size="sm" className="w-full sm:w-auto" leftIcon={RefreshCw} onClick={fetchData} loading={loading}>
            Refresh
          </BaseButton>
          {canCreate && (
          <BaseButton size="sm" className="w-full sm:w-auto" leftIcon={Plus} onClick={openCreate}>
            Add Service
          </BaseButton>
          )}
        </div>
      </div>

      {/* Stats */}
      <div className="grid grid-cols-3 gap-4">
        {[
          { label: 'Total Services', value: services.length, color: 'text-indigo-600' },
          { label: 'Avg Price',
            value: services.length
              ? fmt.money(Math.round(services.reduce((s, x) => s + Number(x.default_price), 0) / services.length))
              : '—',
            color: 'text-brand-600' },
          { label: 'Avg Duration',
            value: services.length
              ? formatDuration(Math.round(services.reduce((s, x) => s + x.duration_minutes, 0) / services.length))
              : '—',
            color: 'text-amber-600' },
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
          placeholder="Search services..."
          className="w-full pl-10 pr-4 py-2.5 text-sm border border-slate-200 rounded-xl bg-white shadow-sm focus:outline-none focus:ring-2 focus:ring-brand-500/20 focus:border-brand-400 transition"
        />
        {search && (
          <button onClick={() => setSearch('')} className="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600 cursor-pointer">
            <X className="w-3.5 h-3.5" />
          </button>
        )}
      </div>

      {/* Content */}
      {loading ? (
        <div className="flex flex-col items-center justify-center py-24 gap-3 text-slate-400">
          <Loader2 className="w-7 h-7 animate-spin text-brand-500" />
          <p className="text-sm font-semibold">Loading services...</p>
        </div>
      ) : fetchError ? (
        <div className="flex flex-col items-center justify-center py-20 gap-3">
          <AlertTriangle className="w-7 h-7 text-rose-400" />
          <p className="text-sm font-bold text-rose-500">{fetchError}</p>
          <BaseButton size="sm" variant="secondary" leftIcon={RefreshCw} onClick={fetchData}>
            Try Again
          </BaseButton>
        </div>
      ) : filtered.length > 0 ? (
        <>
          <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
            {paginated.map((service) => (
              <ServiceCard
                key={service.id}
                service={service}
                onEdit={openEdit}
                onDelete={openDelete}
                canUpdate={canUpdate}
                canDelete={canDelete}
              />
            ))}
          </div>
          <div className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <ListPagination
              page={page}
              pageSize={itemsPerPage}
              total={filtered.length}
              onPageChange={setPage}
              onPageSizeChange={setItemsPerPage}
              label="services"
              pageSizeOptions={[8, 12, 24, 48]}
            />
          </div>
        </>
      ) : (
        <div className="flex flex-col items-center justify-center py-20 border border-dashed border-slate-200 rounded-2xl bg-slate-50/50 text-slate-400">
          <Scissors className="w-10 h-10 mb-3 text-slate-300" />
          <p className="font-semibold text-slate-500">
            {search ? 'No services match your search.' : 'No services yet.'}
          </p>
          {!search && canCreate && (
            <button onClick={openCreate} className="text-sm text-brand-600 font-semibold mt-2 hover:underline cursor-pointer">
              Add your first service →
            </button>
          )}
        </div>
      )}

      {/* Modals */}
      <ServiceFormModal
        service={editService}
        allProducts={allProducts}
        open={formOpen && (editService ? canUpdate : canCreate)}
        onClose={() => setFormOpen(false)}
        onSaved={handleSaved}
      />

      <DeleteServiceModal
        service={delService}
        open={deleteOpen && canDelete}
        onClose={() => setDeleteOpen(false)}
        onDeleted={handleDeleted}
      />
    </div>
  )
}
