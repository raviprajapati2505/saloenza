import React, { useCallback, useEffect, useState, useMemo } from 'react'
import { motion, AnimatePresence } from 'framer-motion'
import {
  Layers, Scissors, Package, Plus, Search, Edit2, Trash2,
  AlertTriangle, CheckCircle2, X, Clock,
} from 'lucide-react'
import PageHeader from '../components/ui/PageHeader.jsx'
import BaseButton from '../components/ui/BaseButton.jsx'
import BaseInput from '../components/ui/BaseInput.jsx'
import ListPagination from '../components/ui/ListPagination.jsx'
import { useAuthStore } from '../stores/auth'
import { useTenantFormatter } from '../hooks/useTenantFormatter.js'
import { subscriptionPageSubtitle } from '../lib/subscriptionModules.js'
import { apiDelete, apiPost, apiPut, fetchMasterList, parseItem } from '../lib/apiHelpers'
import {
  productsForService,
  resolveProductPrice,
} from '../lib/serviceProductHelpers'

function fmtDuration(mins) {
  if (!mins && mins !== 0) return '—'
  const h = Math.floor(mins / 60), m = mins % 60
  if (!h) return `${m} min`
  if (!m) return `${h}h`
  return `${h}h ${m}m`
}

// ─── Shared Toggle Switch (exactly like CategoriesView) ────────
function ToggleSwitch({ checked, onChange, label, hint, disabled = false }) {
  return (
    <div className="flex items-center justify-between p-4 bg-slate-50 border border-slate-200/60 rounded-xl">
      <div>
        <p className="text-sm font-semibold text-slate-900">{label}</p>
        {hint && <p className="text-[11px] text-slate-500 mt-0.5">{hint}</p>}
      </div>
      <button
        type="button"
        role="switch"
        aria-checked={checked}
        disabled={disabled}
        onClick={() => !disabled && onChange(!checked)}
        className={`relative inline-flex h-6 w-11 flex-shrink-0 rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-brand-500 focus:ring-offset-2 ${
          disabled ? 'cursor-not-allowed opacity-50' : 'cursor-pointer'
        } ${checked ? 'bg-brand-500' : 'bg-slate-200'}`}
      >
        <span
          aria-hidden="true"
          className={`pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out ${
            checked ? 'translate-x-5' : 'translate-x-0'
          }`}
        />
      </button>
    </div>
  )
}

// ─── Shared Inline Modal (exactly like CategoriesView/RolesView) ─
function InlineModal({ isOpen, onClose, title, children, maxWidth = 'max-w-md' }) {
  return (
    <AnimatePresence>
      {isOpen && (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4">
          <motion.div
            initial={{ opacity: 0 }} animate={{ opacity: 1 }} exit={{ opacity: 0 }}
            className="absolute inset-0 bg-slate-900/40 backdrop-blur-sm"
            onClick={onClose}
          />
          <motion.div
            initial={{ opacity: 0, scale: 0.95, y: 10 }}
            animate={{ opacity: 1, scale: 1, y: 0 }}
            exit={{ opacity: 0, scale: 0.95, y: 10 }}
            className={`relative w-full ${maxWidth} bg-white rounded-2xl shadow-2xl overflow-hidden`}
          >
            <div className="px-6 py-5 border-b border-slate-100 flex items-center justify-between">
              <h3 className="text-lg font-bold text-slate-900">{title}</h3>
              <button
                onClick={onClose}
                className="p-1.5 text-slate-400 hover:text-slate-600 hover:bg-slate-100 rounded-lg transition"
              >
                <X className="w-5 h-5" />
              </button>
            </div>
            {children}
          </motion.div>
        </div>
      )}
    </AnimatePresence>
  )
}

// ══════════════════════════════════════════════════════════════
//  SERVICE MODAL
// ══════════════════════════════════════════════════════════════
function ServiceModal({ isOpen, onClose, editingService, allProducts, allCategories, onSaved }) {
  const auth = useAuthStore()
  const fmt = useTenantFormatter()
  const isEdit = Boolean(editingService?.id)
  const canSave = auth.can(isEdit ? 'services.update' : 'services.create')

  const [form, setForm] = useState({ name: '', category_id: '', default_price: '', duration_minutes: '', is_active: true })
  const [rows, setRows] = useState([])
  const [formErrors, setFormErrors] = useState({})
  const [isSaving, setIsSaving] = useState(false)

  useEffect(() => {
    if (isOpen) {
      setForm({
        name: editingService?.name || '',
        category_id: editingService?.category_id ?? editingService?.category?.id ?? '',
        default_price: editingService?.default_price ?? '',
        duration_minutes: editingService?.duration_minutes ?? '',
        is_active: isEdit ? Boolean(editingService.is_active) : true,
      })
      setRows(editingService?.products?.map(p => ({
        product_id: p.product_id,
        default_price: p.default_price,
      })) || [])
      setFormErrors({})
    }
  }, [isOpen, editingService, isEdit])

  const activeProducts = allProducts.filter(p => p.is_active)
  const usedIds = rows.map(r => r.product_id).filter(Boolean)

  const addRow = () => setRows(prev => [...prev, { product_id: '', default_price: '' }])
  const removeRow = i => setRows(prev => prev.filter((_, idx) => idx !== i))
  const updateRow = (i, field, value) =>
    setRows(prev => prev.map((r, idx) => idx === i ? { ...r, [field]: value } : r))

  const activeCategories = allCategories.filter(c => c.is_active !== false)

  const handleSubmit = async (e) => {
    e.preventDefault()
    if (!canSave) return
    setFormErrors({})

    if (!form.category_id) {
      setFormErrors({ category_id: 'Category is required.' })
      return
    }

    const ids = rows.map(r => r.product_id).filter(Boolean)
    if (ids.length !== new Set(ids).size) {
      setFormErrors({ _general: 'Each product can only be added once.' })
      return
    }

    setIsSaving(true)
    try {
      const payload = {
        name: form.name.trim(),
        category_id: Number(form.category_id),
        default_price: parseFloat(form.default_price),
        duration_minutes: parseInt(form.duration_minutes, 10),
        is_active: Boolean(form.is_active),
        products: rows.filter(r => r.product_id).map(r => ({
          product_id: Number(r.product_id),
          default_price: parseFloat(r.default_price) || 0,
        })),
      }
      const response = isEdit
        ? await apiPut(`/v1/services/${editingService.id}`, payload)
        : await apiPost('/v1/services', payload)
      const saved = parseItem(response, 'service') ?? response.data?.data?.service ?? response.data?.service
      onSaved(saved, isEdit, isEdit ? 'Service updated successfully.' : 'Service created successfully.')
      onClose()
    } catch (error) {
      const serverErrors = error?.response?.data?.errors || {}
      if (Object.keys(serverErrors).length > 0) {
        const newErrors = {}
        for (const key in serverErrors) newErrors[key] = serverErrors[key][0]
        setFormErrors(newErrors)
      } else {
        setFormErrors({ _general: error?.response?.data?.message || 'Failed to save service.' })
      }
    } finally {
      setIsSaving(false)
    }
  }

  if (!isOpen || !canSave) return null

  return (
    <InlineModal isOpen={isOpen} onClose={onClose} title={isEdit ? 'Edit Service' : 'Create Service'} maxWidth="max-w-lg">
      <form onSubmit={handleSubmit} className="p-6 space-y-5">
        <fieldset disabled={!canSave} className="space-y-5 disabled:opacity-80">
        {formErrors._general && (
          <div className="p-3 text-sm text-rose-700 bg-rose-50 border border-rose-200 rounded-xl">
            {formErrors._general}
          </div>
        )}

        <BaseInput
          label="Service Name"
          placeholder="e.g. Hair Wash"
          modelValue={form.name}
          onUpdateModelValue={v => setForm({ ...form, name: v })}
          error={formErrors.name}
          autoFocus
        />

        <div>
          <label className="block text-sm font-semibold text-slate-700 mb-1.5">Category</label>
          <select
            value={form.category_id}
            onChange={e => setForm({ ...form, category_id: e.target.value })}
            className="block w-full rounded-xl border border-slate-300 py-2.5 px-3 text-sm focus:border-brand-400 focus:outline-none focus:ring-2 focus:ring-brand-500/20"
          >
            <option value="">Select category...</option>
            {activeCategories.map(c => (
              <option key={c.id} value={c.id}>{c.name}</option>
            ))}
          </select>
          {formErrors.category_id && <p className="mt-1 text-xs text-rose-500 font-medium">{formErrors.category_id}</p>}
        </div>

        <div className="grid grid-cols-2 gap-4">
          <div>
            <label className="block text-sm font-semibold text-slate-700 mb-1.5">
              Default Price ({fmt.symbol()}) <span className="text-rose-500">*</span>
            </label>
            <div className="relative flex items-center border border-slate-300 rounded-xl overflow-hidden bg-white focus-within:ring-2 focus-within:ring-brand-500/20 focus-within:border-brand-400 transition-shadow">
              <span className="pl-3 text-slate-400 text-sm font-medium">{fmt.symbol()}</span>
              <input
                type="number" min="0" step="0.01"
                value={form.default_price}
                onChange={e => setForm({ ...form, default_price: e.target.value })}
                placeholder="0.00" required
                className="block w-full border-0 bg-transparent py-2.5 pl-2 pr-3 text-sm text-slate-900 placeholder:text-slate-400 focus:ring-0"
              />
            </div>
            {formErrors.default_price && <p className="mt-1 text-xs text-rose-500 font-medium">{formErrors.default_price}</p>}
          </div>
          <div>
            <label className="block text-sm font-semibold text-slate-700 mb-1.5">
              Duration (min) <span className="text-rose-500">*</span>
            </label>
            <div className="relative flex items-center border border-slate-300 rounded-xl overflow-hidden bg-white focus-within:ring-2 focus-within:ring-brand-500/20 focus-within:border-brand-400 transition-shadow">
              <Clock className="ml-3 w-4 h-4 text-slate-400 flex-shrink-0" />
              <input
                type="number" min="1" max="1440" step="1"
                value={form.duration_minutes}
                onChange={e => setForm({ ...form, duration_minutes: e.target.value })}
                placeholder="30" required
                className="block w-full border-0 bg-transparent py-2.5 pl-2 pr-3 text-sm text-slate-900 placeholder:text-slate-400 focus:ring-0"
              />
            </div>
            {formErrors.duration_minutes && <p className="mt-1 text-xs text-rose-500 font-medium">{formErrors.duration_minutes}</p>}
          </div>
        </div>

        <ToggleSwitch
          checked={form.is_active}
          onChange={v => setForm({ ...form, is_active: v })}
          label="Active Status"
          hint="Inactive services are hidden from bookings."
          disabled={!canSave}
        />

        {/* Linked Products */}
        <div>
          <div className="flex items-center justify-between mb-2">
            <div>
              <p className="text-sm font-semibold text-slate-700">Linked Products</p>
              <p className="text-[11px] text-slate-500 mt-0.5">Optional — no duplicates per service.</p>
            </div>
            <button
              type="button" onClick={addRow}
              disabled={activeProducts.length === 0}
              className="flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold rounded-lg bg-brand-50 border border-brand-100 text-brand-700 hover:bg-brand-100 transition cursor-pointer disabled:opacity-40"
            >
              <Plus className="w-3.5 h-3.5" /> Add Product
            </button>
          </div>
          {rows.length === 0 ? (
            <p className="text-xs text-slate-400 bg-slate-50 border border-dashed border-slate-200 rounded-xl p-3 text-center">
              No products linked yet.
            </p>
          ) : (
            <div className="space-y-2">
              {rows.map((row, i) => (
                <div key={i} className="flex items-center gap-2">
                  <select
                    value={row.product_id || ''}
                    onChange={e => updateRow(i, 'product_id', Number(e.target.value))}
                    className="flex-1 text-sm border border-slate-200 rounded-xl px-3 py-2.5 bg-white text-slate-700 focus:outline-none focus:ring-2 focus:ring-brand-500/20 focus:border-brand-400 transition cursor-pointer"
                  >
                    <option value="">— Select product —</option>
                    {activeProducts.filter(p => p.id === row.product_id || !usedIds.includes(p.id)).map(p => (
                      <option key={p.id} value={p.id}>{p.name}</option>
                    ))}
                  </select>
                  <div className="relative w-28">
                    <span className="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-sm">{fmt.symbol()}</span>
                    <input
                      type="number" min="0" step="0.01"
                      value={row.default_price}
                      onChange={e => updateRow(i, 'default_price', e.target.value)}
                      placeholder="0"
                      className="w-full pl-7 pr-3 py-2.5 text-sm border border-slate-200 rounded-xl bg-white text-slate-700 focus:outline-none focus:ring-2 focus:ring-brand-500/20 focus:border-brand-400 transition"
                    />
                  </div>
                  <button
                    type="button" onClick={() => removeRow(i)}
                    className="p-2 rounded-xl text-slate-400 hover:text-rose-500 hover:bg-rose-50 transition cursor-pointer flex-shrink-0"
                  >
                    <X className="w-4 h-4" />
                  </button>
                </div>
              ))}
            </div>
          )}
        </div>

        <div className="flex gap-3 pt-2">
          <BaseButton type="button" variant="secondary" className="flex-1" onClick={onClose}>
            Cancel
          </BaseButton>
          <BaseButton type="submit" className="flex-1" disabled={!canSave || isSaving}>
            {isSaving ? 'Saving...' : isEdit ? 'Update Service' : 'Save Service'}
          </BaseButton>
        </div>
        </fieldset>
      </form>
    </InlineModal>
  )
}

// ══════════════════════════════════════════════════════════════
//  PRODUCT MODAL
// ══════════════════════════════════════════════════════════════
function ProductModal({ isOpen, onClose, editingProduct, allCategories, onSaved }) {
  const auth = useAuthStore()
  const isEdit = Boolean(editingProduct?.id)
  const canSave = auth.can(isEdit ? 'products.update' : 'products.create')

  const [form, setForm] = useState({ name: '', category_id: '', is_active: true })
  const [formErrors, setFormErrors] = useState({})
  const [isSaving, setIsSaving] = useState(false)

  useEffect(() => {
    if (isOpen) {
      setForm({
        name: editingProduct?.name || '',
        category_id: editingProduct?.category_id ?? editingProduct?.category?.id ?? '',
        is_active: isEdit ? Boolean(editingProduct.is_active) : true,
      })
      setFormErrors({})
    }
  }, [isOpen, editingProduct, isEdit])

  const handleSubmit = async (e) => {
    e.preventDefault()
    if (!canSave) return
    setFormErrors({})
    if (!form.name.trim()) { setFormErrors({ name: 'Product name is required.' }); return }
    if (!form.category_id) { setFormErrors({ category_id: 'Category is required.' }); return }

    setIsSaving(true)
    try {
      const payload = { name: form.name.trim(), category_id: Number(form.category_id), is_active: Boolean(form.is_active) }
      const response = isEdit
        ? await apiPut(`/v1/products/${editingProduct.id}`, payload)
        : await apiPost('/v1/products', payload)
      const saved = parseItem(response, 'product') ?? response.data?.data?.product ?? response.data?.product
      onSaved(saved, isEdit, isEdit ? 'Product updated successfully.' : 'Product created successfully.')
      onClose()
    } catch (error) {
      const serverErrors = error?.response?.data?.errors || {}
      if (Object.keys(serverErrors).length > 0) {
        const newErrors = {}
        for (const key in serverErrors) newErrors[key] = serverErrors[key][0]
        setFormErrors(newErrors)
      } else {
        setFormErrors({ _general: error?.response?.data?.message || 'Failed to save product.' })
      }
    } finally {
      setIsSaving(false)
    }
  }

  if (!isOpen || !canSave) return null

  return (
    <InlineModal isOpen={isOpen} onClose={onClose} title={isEdit ? 'Edit Product' : 'Create Product'}>
      <form onSubmit={handleSubmit} className="p-6 space-y-5">
        <fieldset disabled={!canSave} className="space-y-5 disabled:opacity-80">
        {formErrors._general && (
          <div className="p-3 text-sm text-rose-700 bg-rose-50 border border-rose-200 rounded-xl">
            {formErrors._general}
          </div>
        )}
        <BaseInput
          label="Product Name"
          placeholder="e.g. Shampoo"
          modelValue={form.name}
          onUpdateModelValue={v => setForm({ ...form, name: v })}
          error={formErrors.name}
          autoFocus
        />

        <div>
          <label className="block text-sm font-semibold text-slate-700 mb-1.5">Category</label>
          <select
            value={form.category_id}
            onChange={e => setForm({ ...form, category_id: e.target.value })}
            className="block w-full rounded-xl border border-slate-300 py-2.5 px-3 text-sm focus:border-brand-400 focus:outline-none focus:ring-2 focus:ring-brand-500/20"
          >
            <option value="">Select category...</option>
            {(allCategories || []).filter(c => c.is_active !== false).map(c => (
              <option key={c.id} value={c.id}>{c.name}</option>
            ))}
          </select>
          {formErrors.category_id && <p className="mt-1 text-xs text-rose-500 font-medium">{formErrors.category_id}</p>}
        </div>

        <ToggleSwitch
          checked={form.is_active}
          onChange={v => setForm({ ...form, is_active: v })}
          label="Active Status"
          hint="Inactive products cannot be assigned to services."
          disabled={!canSave}
        />
        <div className="flex gap-3 pt-2">
          <BaseButton type="button" variant="secondary" className="flex-1" onClick={onClose}>
            Cancel
          </BaseButton>
          <BaseButton type="submit" className="flex-1" disabled={!canSave || isSaving}>
            {isSaving ? 'Saving...' : isEdit ? 'Update Product' : 'Save Product'}
          </BaseButton>
        </div>
        </fieldset>
      </form>
    </InlineModal>
  )
}

// ══════════════════════════════════════════════════════════════
//  SALON CATALOG MODAL (tenant offerings)
// ══════════════════════════════════════════════════════════════
function CatalogItemModal({ isOpen, onClose, editingItem, services, products, branches, selectedBranchId, canChooseBranch, onSaved }) {
  const auth = useAuthStore()
  const fmt = useTenantFormatter()
  const isEdit = Boolean(editingItem?.id)
  const canSave = isEdit
    ? auth.can('services.update') || auth.can('products.update')
    : auth.can('services.create') || auth.can('products.create')
  const [form, setForm] = useState({ branch_id: '', service_id: '', product_id: '', price: '', duration_minutes: '', is_active: true })
  const [formErrors, setFormErrors] = useState({})
  const [isSaving, setIsSaving] = useState(false)

  useEffect(() => {
    if (isOpen) {
      setForm({
        branch_id: editingItem?.branch_id ? String(editingItem.branch_id) : (selectedBranchId ? String(selectedBranchId) : ''),
        service_id: editingItem?.service_id ? String(editingItem.service_id) : '',
        product_id: editingItem?.product_id ? String(editingItem.product_id) : '',
        price: editingItem?.price ?? '',
        duration_minutes: editingItem?.duration_minutes ?? '',
        is_active: isEdit ? Boolean(editingItem.is_active) : true,
      })
      setFormErrors({})
    }
  }, [isOpen, editingItem, isEdit, selectedBranchId])

  const selectedService = useMemo(
    () => services.find((s) => Number(s.id) === Number(form.service_id)) ?? null,
    [services, form.service_id],
  )

  const productOptions = useMemo(
    () => productsForService(selectedService, products),
    [selectedService, products],
  )

  const handleServiceChange = (serviceId) => {
    const service = services.find((s) => Number(s.id) === Number(serviceId)) ?? null
    setForm((prev) => ({
      ...prev,
      service_id: serviceId,
      product_id: '',
      price: service ? String(resolveProductPrice(service, null)) : prev.price,
      duration_minutes: service?.duration_minutes != null
        ? String(service.duration_minutes)
        : prev.duration_minutes,
    }))
  }

  const handleProductChange = (productId) => {
    setForm((prev) => ({
      ...prev,
      product_id: productId,
      price: selectedService
        ? String(resolveProductPrice(selectedService, productId || null))
        : prev.price,
    }))
  }

  const handleSubmit = async (e) => {
    e.preventDefault()
    if (!canSave) return
    setFormErrors({})
    setIsSaving(true)
    try {
      const payload = {
        branch_id: form.branch_id ? Number(form.branch_id) : null,
        service_id: Number(form.service_id),
        product_id: form.product_id ? Number(form.product_id) : null,
        price: parseFloat(form.price),
        duration_minutes: parseInt(form.duration_minutes, 10),
        is_active: Boolean(form.is_active),
      }
      const response = isEdit
        ? await apiPut(`/v1/catalog/${editingItem.id}`, payload)
        : await apiPost('/v1/catalog', payload)
      const saved = parseItem(response, 'catalog')
      onSaved(saved, isEdit, isEdit ? 'Offering updated successfully.' : 'Offering created successfully.')
      onClose()
    } catch (error) {
      const serverErrors = error?.response?.data?.errors || {}
      if (Object.keys(serverErrors).length) {
        const mapped = {}
        for (const key in serverErrors) mapped[key] = serverErrors[key][0]
        setFormErrors(mapped)
      } else {
        setFormErrors({ _general: error?.response?.data?.message || 'Failed to save offering.' })
      }
    } finally {
      setIsSaving(false)
    }
  }

  if (!isOpen || !canSave) return null

  return (
    <InlineModal isOpen={isOpen} onClose={onClose} title={isEdit ? 'Edit Offering' : 'Add Salon Offering'} maxWidth="max-w-lg">
      <form onSubmit={handleSubmit} className="p-6 space-y-5">
        <fieldset disabled={!canSave} className="space-y-5 disabled:opacity-80">
        {formErrors._general && <div className="p-3 text-sm text-rose-700 bg-rose-50 border border-rose-200 rounded-xl">{formErrors._general}</div>}
        {canChooseBranch ? (
          <div>
            <label className="block text-sm font-semibold text-slate-700 mb-1.5">Branch</label>
            <select value={form.branch_id} onChange={e => setForm({ ...form, branch_id: e.target.value })}
              className="block w-full rounded-xl border border-slate-300 py-2.5 px-3 text-sm">
              <option value="">Select branch...</option>
              {branches.map(branch => <option key={branch.id} value={branch.id}>{branch.branch_name}</option>)}
            </select>
            {formErrors.branch_id && <p className="mt-1 text-xs text-rose-500">{formErrors.branch_id}</p>}
          </div>
        ) : null}
        <div>
          <label className="block text-sm font-semibold text-slate-700 mb-1.5">Service</label>
          <select value={form.service_id} onChange={e => handleServiceChange(e.target.value)}
            className="block w-full rounded-xl border border-slate-300 py-2.5 px-3 text-sm">
            <option value="">Select service...</option>
            {services.filter(s => s.is_active !== false).map(s => <option key={s.id} value={s.id}>{s.name}</option>)}
          </select>
          {formErrors.service_id && <p className="mt-1 text-xs text-rose-500">{formErrors.service_id}</p>}
        </div>
        <div>
          <label className="block text-sm font-semibold text-slate-700 mb-1.5">Product (optional)</label>
          <select value={form.product_id} onChange={e => handleProductChange(e.target.value)}
            className="block w-full rounded-xl border border-slate-300 py-2.5 px-3 text-sm"
            disabled={!form.service_id}
          >
            <option value="">Service only</option>
            {productOptions.map(p => (
              <option key={p.id} value={p.id}>
                {p.name} · {fmt.money(resolveProductPrice(selectedService, p.id))}
              </option>
            ))}
          </select>
          {formErrors.product_id && <p className="mt-1 text-xs text-rose-500">{formErrors.product_id}</p>}
        </div>
        <div className="grid grid-cols-2 gap-4">
          <BaseInput label={`Price (${fmt.symbol()})`} modelValue={form.price} onUpdateModelValue={v => setForm({ ...form, price: v })} error={formErrors.price} />
          <BaseInput label="Duration (mins)" modelValue={form.duration_minutes} onUpdateModelValue={v => setForm({ ...form, duration_minutes: v })} error={formErrors.duration_minutes} />
        </div>
        <ToggleSwitch checked={form.is_active} onChange={v => setForm({ ...form, is_active: v })} label="Active" hint="Inactive offerings are hidden from booking." disabled={!canSave} />
        <div className="flex gap-3 pt-2">
          <BaseButton type="button" variant="secondary" className="flex-1" onClick={onClose}>Cancel</BaseButton>
          <BaseButton type="submit" className="flex-1" disabled={!canSave || isSaving}>{isSaving ? 'Saving...' : 'Save Offering'}</BaseButton>
        </div>
        </fieldset>
      </form>
    </InlineModal>
  )
}

// ─── Shared Delete Confirm Modal ──────────────────────────────
function DeleteModal({ isOpen, onClose, title, name, onConfirm, isDeleting, deleteError }) {
  return (
    <AnimatePresence>
      {isOpen && (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4">
          <motion.div
            initial={{ opacity: 0 }} animate={{ opacity: 1 }} exit={{ opacity: 0 }}
            className="absolute inset-0 bg-slate-900/40 backdrop-blur-sm"
            onClick={onClose}
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
            <h3 className="text-lg font-bold text-slate-900 mb-2">{title}</h3>
            <p className="text-sm text-slate-500 mb-4">
              Are you sure you want to delete{' '}
              <span className="font-semibold text-slate-700">"{name}"</span>?{' '}
              This action cannot be undone.
            </p>
            {deleteError && (
              <div className="flex items-center gap-2 text-sm text-rose-700 font-medium bg-rose-50 border border-rose-200 rounded-xl px-4 py-3 mb-4 text-left">
                <AlertTriangle className="w-4 h-4 flex-shrink-0" />{deleteError}
              </div>
            )}
            <div className="flex gap-3">
              <BaseButton type="button" variant="secondary" className="flex-1" onClick={onClose} disabled={isDeleting}>
                Cancel
              </BaseButton>
              <BaseButton
                type="button"
                className="flex-1 !bg-rose-600 hover:!bg-rose-700 !shadow-[0_4px_15px_rgba(225,29,72,0.3)]"
                onClick={onConfirm}
                disabled={isDeleting}
              >
                {isDeleting ? 'Deleting...' : 'Delete'}
              </BaseButton>
            </div>
          </motion.div>
        </div>
      )}
    </AnimatePresence>
  )
}

// ══════════════════════════════════════════════════════════════
//  MAIN COMBINED VIEW
// ══════════════════════════════════════════════════════════════
export default function CatalogView() {
  const auth = useAuthStore()
  const fmt = useTenantFormatter()
  const isAdmin = auth.isSystemAdmin
  const isBranchScoped = Boolean(auth.isBranchScoped)
  const lockedBranchId = auth.user?.branch_id ? String(auth.user.branch_id) : ''
  const canCreateService = auth.can('services.create')
  const canUpdateService = auth.can('services.update')
  const canDeleteService = auth.can('services.delete')
  const canCreateProduct = auth.can('products.create')
  const canUpdateProduct = auth.can('products.update')
  const canDeleteProduct = auth.can('products.delete')
  const canCreateCatalog = canCreateService || canCreateProduct
  const canUpdateCatalog = canUpdateService || canUpdateProduct
  const canDeleteCatalog = canDeleteService || canDeleteProduct

  const [services,    setServices]    = useState([])
  const [products,    setProducts]    = useState([])
  const [categories,  setCategories]  = useState([])
  const [branches, setBranches] = useState([])
  const [catalogItems, setCatalogItems] = useState([])
  const [isLoading,   setIsLoading]   = useState(true)
  const [successMessage, setSuccessMessage] = useState('')
  const [activeTab,   setActiveTab]   = useState(isAdmin ? 'services' : 'catalog')
  const [branchFilter, setBranchFilter] = useState(lockedBranchId)

  useEffect(() => {
    if (isBranchScoped && lockedBranchId) {
      setBranchFilter(lockedBranchId)
    }
  }, [isBranchScoped, lockedBranchId])

  const [svcSearch,   setSvcSearch]   = useState('')
  const [prdSearch,   setPrdSearch]   = useState('')

  // Pagination
  const [svcPage,     setSvcPage]     = useState(1)
  const [prdPage,     setPrdPage]     = useState(1)
  const [catPage,     setCatPage]     = useState(1)
  const [itemsPerPage, setItemsPerPage] = useState(10)

  // Service modal state
  const [isSvcModalOpen, setIsSvcModalOpen] = useState(false)
  const [editingService,  setEditingService]  = useState(null)
  const [svcDeleteModal,  setSvcDeleteModal]  = useState({ isOpen: false, service: null })
  const [isSvcDeleting,   setIsSvcDeleting]   = useState(false)
  const [svcDeleteError,  setSvcDeleteError]  = useState('')

  // Product modal state
  const [isPrdModalOpen, setIsPrdModalOpen] = useState(false)
  const [editingProduct,  setEditingProduct]  = useState(null)
  const [prdDeleteModal,  setPrdDeleteModal]  = useState({ isOpen: false, product: null })
  const [isPrdDeleting,   setIsPrdDeleting]   = useState(false)
  const [prdDeleteError,  setPrdDeleteError]  = useState('')

  const [isCatModalOpen, setIsCatModalOpen] = useState(false)
  const [editingCatalog, setEditingCatalog] = useState(null)
  const [catDeleteModal, setCatDeleteModal] = useState({ isOpen: false, item: null })
  const [isCatDeleting, setIsCatDeleting] = useState(false)
  const [catDeleteError, setCatDeleteError] = useState('')
  const [catSearch, setCatSearch] = useState('')

  const showSuccess = (msg) => { setSuccessMessage(msg); setTimeout(() => setSuccessMessage(''), 3000) }

  // ── Fetch ─────────────────────────────────────────────────
  const fetchAll = useCallback(async () => {
    setIsLoading(true)
    try {
      if (isAdmin) {
        const [servicesRows, productsRows, categoriesRows] = await Promise.all([
          fetchMasterList('/v1/services', 'services'),
          fetchMasterList('/v1/products', 'products'),
          fetchMasterList('/v1/categories', 'categories', { is_active: 1 }),
        ])
        setServices(servicesRows)
        setProducts(productsRows)
        setCategories(categoriesRows)
        setCatalogItems([])
        setBranches([])
      } else {
        // Tenant / branch: inventory is salon offerings only.
        // Masters are loaded solely to pick new offerings in "Add Offering".
        const [catalogRows, branchRows, servicesRows, productsRows] = await Promise.all([
          fetchMasterList('/v1/catalog', 'catalog', {
            ...(branchFilter ? { branch_id: Number(branchFilter) } : {}),
          }),
          fetchMasterList('/v1/branches', 'branches'),
          fetchMasterList('/v1/services', 'services', { is_active: 1 }),
          fetchMasterList('/v1/products', 'products', { is_active: 1 }),
        ])
        setCatalogItems(catalogRows)
        setBranches(branchRows)
        if (!branchFilter && branchRows.length === 1) {
          setBranchFilter(String(branchRows[0].id))
        }
        // Keep full masters for Add Offering picker + category enrichment.
        setServices(servicesRows)
        setProducts(productsRows)
        setCategories([])
      }
    } catch (error) {
      console.error('Failed to fetch catalog:', error)
    } finally {
      setIsLoading(false)
    }
  }, [isAdmin, branchFilter])

  useEffect(() => { void fetchAll() }, [fetchAll])
  useEffect(() => { setSvcPage(1) }, [svcSearch])
  useEffect(() => { setPrdPage(1) }, [prdSearch])
  useEffect(() => { setCatPage(1) }, [catSearch])
  useEffect(() => {
    setSvcPage(1)
    setPrdPage(1)
    setCatPage(1)
  }, [itemsPerPage])

  /** Unique services / products already onboarded for this salon+branch (tenant list views). */
  const salonServices = useMemo(() => {
    const masterById = new Map(services.map((s) => [s.id, s]))
    const map = new Map()
    for (const row of catalogItems) {
      const svc = row.service
      if (!svc?.id) continue
      const master = masterById.get(svc.id)
      const existing = map.get(svc.id)
      const isServiceOnly = row.product_id == null
      // Prefer service-only offering price when present.
      if (!existing || (isServiceOnly && existing._fromProduct)) {
        map.set(svc.id, {
          ...svc,
          // Catalog payload may omit category — enrich from master list.
          category_id: svc.category_id ?? master?.category_id ?? null,
          category: svc.category ?? master?.category ?? null,
          products: master?.products ?? svc.products ?? [],
          default_price: row.price,
          duration_minutes: row.duration_minutes ?? svc.duration_minutes ?? master?.duration_minutes,
          is_active: row.is_active,
          _fromProduct: !isServiceOnly,
        })
      }
    }
    return Array.from(map.values()).map(({ _fromProduct, ...svc }) => svc)
  }, [catalogItems, services])

  const salonProducts = useMemo(() => {
    const masterById = new Map(products.map((p) => [p.id, p]))
    const map = new Map()
    for (const row of catalogItems) {
      const prd = row.product
      if (!prd?.id || map.has(prd.id)) continue
      const master = masterById.get(prd.id)
      map.set(prd.id, {
        ...prd,
        category_id: prd.category_id ?? master?.category_id ?? null,
        category: prd.category ?? master?.category ?? null,
        is_active: row.is_active,
      })
    }
    return Array.from(map.values())
  }, [catalogItems, products])

  const listServices = isAdmin ? services : salonServices
  const listProducts = isAdmin ? products : salonProducts

  // ── Service handlers (platform masters only) ──────────────
  const openCreateService = () => { if (!isAdmin || !canCreateService) return; setEditingService(null); setIsSvcModalOpen(true) }
  const openEditService   = s  => { if (!isAdmin || !canUpdateService) return; setEditingService(s); setIsSvcModalOpen(true) }

  const handleSvcSaved = (saved, isEdit, msg) => {
    setServices(prev => isEdit ? prev.map(s => s.id === saved.id ? saved : s) : [saved, ...prev])
    showSuccess(msg)
  }

  const handleSvcDelete = async () => {
    if (!isAdmin || !canDeleteService || !svcDeleteModal.service) return
    setIsSvcDeleting(true); setSvcDeleteError('')
    try {
      await apiDelete(`/v1/services/${svcDeleteModal.service.id}`)
      setServices(prev => prev.filter(s => s.id !== svcDeleteModal.service.id))
      showSuccess('Service deleted successfully.')
      setSvcDeleteModal({ isOpen: false, service: null })
    } catch (error) {
      setSvcDeleteError(error?.response?.data?.message || 'Failed to delete service.')
    } finally { setIsSvcDeleting(false) }
  }

  // ── Product handlers (platform masters only) ──────────────
  const openCreateProduct = () => { if (!isAdmin || !canCreateProduct) return; setEditingProduct(null); setIsPrdModalOpen(true) }
  const openEditProduct   = p  => { if (!isAdmin || !canUpdateProduct) return; setEditingProduct(p); setIsPrdModalOpen(true) }

  const handlePrdSaved = (saved, isEdit, msg) => {
    setProducts(prev => isEdit ? prev.map(p => p.id === saved.id ? saved : p) : [saved, ...prev])
    showSuccess(msg)
  }

  const handlePrdDelete = async () => {
    if (!isAdmin || !canDeleteProduct || !prdDeleteModal.product) return
    setIsPrdDeleting(true); setPrdDeleteError('')
    try {
      await apiDelete(`/v1/products/${prdDeleteModal.product.id}`)
      setProducts(prev => prev.filter(p => p.id !== prdDeleteModal.product.id))
      showSuccess('Product deleted successfully.')
      setPrdDeleteModal({ isOpen: false, product: null })
    } catch (error) {
      setPrdDeleteError(error?.response?.data?.message || 'Failed to delete product.')
    } finally { setIsPrdDeleting(false) }
  }

  const openCreateCatalog = () => { if (!canCreateCatalog) return; setEditingCatalog(null); setIsCatModalOpen(true) }
  const openEditCatalog = item => { if (!canUpdateCatalog) return; setEditingCatalog(item); setIsCatModalOpen(true) }

  const handleCatSaved = (saved, isEdit, msg) => {
    setCatalogItems(prev => isEdit ? prev.map(c => c.id === saved.id ? saved : c) : [saved, ...prev])
    showSuccess(msg)
  }

  const handleCatDelete = async () => {
    if (!canDeleteCatalog || !catDeleteModal.item) return
    setIsCatDeleting(true); setCatDeleteError('')
    try {
      await apiDelete(`/v1/catalog/${catDeleteModal.item.id}`)
      setCatalogItems(prev => prev.filter(c => c.id !== catDeleteModal.item.id))
      showSuccess('Offering deleted successfully.')
      setCatDeleteModal({ isOpen: false, item: null })
    } catch (error) {
      setCatDeleteError(error?.response?.data?.message || 'Failed to delete offering.')
    } finally { setIsCatDeleting(false) }
  }

  const filteredCat = useMemo(() =>
    catalogItems.filter(c =>
      (c.service?.name || '').toLowerCase().includes(catSearch.toLowerCase()) ||
      (c.product?.name || '').toLowerCase().includes(catSearch.toLowerCase())
    ), [catalogItems, catSearch])
  const paginatedCat = filteredCat.slice((catPage - 1) * itemsPerPage, catPage * itemsPerPage)

  // ── Filtered + Paginated ─────────────────────────────────
  const filteredSvc = useMemo(() =>
    listServices.filter(s =>
      (s.name || '').toLowerCase().includes(svcSearch.toLowerCase()) ||
      (s.category?.name || '').toLowerCase().includes(svcSearch.toLowerCase())
    ),
    [listServices, svcSearch])

  const filteredPrd = useMemo(() =>
    listProducts.filter(p =>
      (p.name || '').toLowerCase().includes(prdSearch.toLowerCase()) ||
      (p.category?.name || '').toLowerCase().includes(prdSearch.toLowerCase())
    ),
    [listProducts, prdSearch])

  const paginatedSvc  = filteredSvc.slice((svcPage - 1) * itemsPerPage, svcPage * itemsPerPage)
  const paginatedPrd  = filteredPrd.slice((prdPage - 1) * itemsPerPage, prdPage * itemsPerPage)

  return (
    <div className="space-y-6 pb-12">

      {/* ─── Header ─── */}
      <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <PageHeader
          title="Services & Products"
          subtitle={isAdmin
            ? 'Manage global masters and salon offerings.'
            : subscriptionPageSubtitle(auth, 'Services and products available for your salon and branch (from onboarding / offerings).')}
        />
        {activeTab === 'catalog' && canCreateCatalog ? (
          <BaseButton onClick={openCreateCatalog} size="sm" className="w-full sm:w-auto" leftIcon={Plus}>Add Offering</BaseButton>
        ) : activeTab === 'services' && isAdmin && canCreateService ? (
          <BaseButton onClick={openCreateService} size="sm" className="w-full sm:w-auto" leftIcon={Plus}>Add Service</BaseButton>
        ) : activeTab === 'products' && isAdmin && canCreateProduct ? (
          <BaseButton onClick={openCreateProduct} size="sm" className="w-full sm:w-auto" leftIcon={Plus}>Add Product</BaseButton>
        ) : null}
      </div>

      {/* ─── Success Toast ─── */}
      <AnimatePresence>
        {successMessage && (
          <motion.div
            initial={{ opacity: 0, y: -10 }} animate={{ opacity: 1, y: 0 }} exit={{ opacity: 0, y: -10 }}
            className="rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-emerald-800 shadow-sm"
          >
            <div className="flex items-center gap-2">
              <CheckCircle2 className="h-5 w-5 text-emerald-600" />
              <p className="text-sm font-semibold">{successMessage}</p>
            </div>
          </motion.div>
        )}
      </AnimatePresence>

      {/* ─── Tabs ─── */}
      <div className="flex items-center gap-1 bg-slate-100 p-1 rounded-xl w-fit flex-wrap">
        {[
          ...(!isAdmin ? [{ key: 'catalog', label: 'Salon Offerings', icon: Layers, count: catalogItems.length }] : []),
          { key: 'services', label: isAdmin ? 'Services' : 'Salon Services', icon: Scissors, count: listServices.length },
          { key: 'products', label: isAdmin ? 'Products' : 'Salon Products', icon: Package,  count: listProducts.length },
        ].map(({ key, label, icon: Icon, count }) => (
          <button
            key={key}
            onClick={() => setActiveTab(key)}
            className={`flex items-center gap-2 px-5 py-2 text-sm font-bold rounded-lg transition-all cursor-pointer ${
              activeTab === key
                ? 'bg-white text-brand-700 shadow-sm border border-slate-200'
                : 'text-slate-500 hover:text-slate-700'
            }`}
          >
            <Icon className="w-3.5 h-3.5" />
            {label}
            {!isLoading && (
              <span className="ml-0.5 text-xs opacity-60">({count})</span>
            )}
          </button>
        ))}
      </div>

      {/* ─── Table Container ─── */}
      <div className="bg-white border border-slate-200/80 rounded-2xl shadow-sm overflow-hidden min-h-[320px]">
        <div>

          {/* Search bar */}
          <div className="p-4 border-b border-slate-100">
            <div className="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
            <div className="relative max-w-md">
              <Search className="absolute left-3.5 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400" />
              <input
                type="text"
                value={activeTab === 'catalog' ? catSearch : activeTab === 'services' ? svcSearch : prdSearch}
                onChange={e => {
                  if (activeTab === 'catalog') setCatSearch(e.target.value)
                  else if (activeTab === 'services') setSvcSearch(e.target.value)
                  else setPrdSearch(e.target.value)
                }}
                placeholder={activeTab === 'catalog' ? 'Search offerings...' : activeTab === 'services' ? 'Search services...' : 'Search products...'}
                className="w-full pl-10 pr-4 py-2 text-sm border border-slate-200 rounded-xl bg-white shadow-sm focus:outline-none focus:ring-2 focus:ring-brand-500/20 focus:border-brand-400 transition"
              />
              {(activeTab === 'catalog' ? catSearch : activeTab === 'services' ? svcSearch : prdSearch) && (
                <button
                  onClick={() => activeTab === 'catalog' ? setCatSearch('') : activeTab === 'services' ? setSvcSearch('') : setPrdSearch('')}
                  className="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600 cursor-pointer"
                >
                  <X className="w-3.5 h-3.5" />
                </button>
              )}
            </div>
            {!isAdmin && !isBranchScoped && activeTab === 'catalog' && branches.length > 1 ? (
              <select
                value={branchFilter}
                onChange={e => setBranchFilter(e.target.value)}
                className="rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700"
              >
                <option value="">All branches</option>
                {branches.map(branch => (
                  <option key={branch.id} value={branch.id}>{branch.branch_name}</option>
                ))}
              </select>
            ) : null}
            </div>
          </div>

          {/* Table */}
          {isLoading ? (
            <div className="flex flex-col items-center justify-center py-20 text-slate-400">
              <svg className="h-8 w-8 animate-spin text-brand-500 mb-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="3" />
                <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v3a5 5 0 00-5 5H4z" />
              </svg>
              <p>Loading...</p>
            </div>
          ) : activeTab === 'catalog' ? (
            <div className="overflow-x-auto">
              <table className="w-full text-left">
                <thead>
                  <tr className="border-b border-slate-100 bg-slate-50/50">
                    <th className="px-6 py-4 text-[11px] font-bold uppercase tracking-wider text-slate-400">Branch</th>
                    <th className="px-6 py-4 text-[11px] font-bold uppercase tracking-wider text-slate-400">Service</th>
                    <th className="px-6 py-4 text-[11px] font-bold uppercase tracking-wider text-slate-400">Product</th>
                    <th className="px-6 py-4 text-[11px] font-bold uppercase tracking-wider text-slate-400">Price</th>
                    <th className="px-6 py-4 text-[11px] font-bold uppercase tracking-wider text-slate-400">Duration</th>
                    <th className="px-6 py-4 text-[11px] font-bold uppercase tracking-wider text-slate-400 w-32">Status</th>
                    {(canUpdateCatalog || canDeleteCatalog) && <th className="px-6 py-4 text-[11px] font-bold uppercase tracking-wider text-slate-400 w-24 text-right">Actions</th>}
                  </tr>
                </thead>
                <tbody>
                  {paginatedCat.length > 0 ? paginatedCat.map((item, i) => (
                    <motion.tr key={item.id} initial={{ opacity: 0, y: 8 }} animate={{ opacity: 1, y: 0 }} transition={{ delay: i * 0.03 }}
                      className={`border-b border-slate-100 group ${canUpdateCatalog ? 'cursor-pointer' : ''}`} onClick={() => canUpdateCatalog && openEditCatalog(item)}>
                      <td className="py-4 px-6 text-sm text-slate-700">{item.branch?.branch_name || 'Default'}</td>
                      <td className="py-4 px-6 text-sm font-semibold text-slate-900">{item.service?.name || '—'}</td>
                      <td className="py-4 px-6 text-sm text-slate-700">{item.product?.name || '—'}</td>
                      <td className="py-4 px-6 text-sm font-bold">{fmt.money(item.price)}</td>
                      <td className="py-4 px-6 text-sm">{fmtDuration(item.duration_minutes)}</td>
                      <td className="py-4 px-6">
                        {item.is_active ? (
                          <span className="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200/60"><CheckCircle2 className="w-3.5 h-3.5" /> Active</span>
                        ) : (
                          <span className="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold bg-slate-100 text-slate-600 border border-slate-200"><AlertTriangle className="w-3.5 h-3.5" /> Inactive</span>
                        )}
                      </td>
                      {(canUpdateCatalog || canDeleteCatalog) && (
                        <td className="py-4 px-6 text-right">
                          <div className="flex items-center gap-2 justify-end">
                            {canUpdateCatalog && <button onClick={e => { e.stopPropagation(); openEditCatalog(item) }} className="p-1.5 rounded-lg bg-slate-100 hover:bg-brand-50 hover:text-brand-600 text-slate-400"><Edit2 className="w-4 h-4" /></button>}
                            {canDeleteCatalog && <button onClick={e => { e.stopPropagation(); setCatDeleteModal({ isOpen: true, item }); setCatDeleteError('') }} className="p-1.5 rounded-lg bg-slate-100 hover:bg-rose-50 hover:text-rose-600 text-slate-400"><Trash2 className="w-4 h-4" /></button>}
                          </div>
                        </td>
                      )}
                    </motion.tr>
                  )) : (
                    <tr><td colSpan={canUpdateCatalog || canDeleteCatalog ? 7 : 6} className="py-16 text-center text-slate-400">
                      <Layers className="w-10 h-10 mx-auto mb-3 text-slate-300" />
                      <p className="text-lg font-semibold text-slate-600">No salon offerings yet</p>
                      {canCreateCatalog && !catSearch && <BaseButton onClick={openCreateCatalog} className="mt-4" size="sm" leftIcon={Plus}>Add Offering</BaseButton>}
                    </td></tr>
                  )}
                </tbody>
              </table>
            </div>
          ) : activeTab === 'services' ? (
            <div className="overflow-x-auto">
              <table className="w-full text-left">
                <thead>
                  <tr className="border-b border-slate-100 bg-slate-50/50">
                    <th className="px-6 py-4 text-[11px] font-bold uppercase tracking-wider text-slate-400">Service Name</th>
                    <th className="px-6 py-4 text-[11px] font-bold uppercase tracking-wider text-slate-400 whitespace-nowrap">Category</th>
                    <th className="px-6 py-4 text-[11px] font-bold uppercase tracking-wider text-slate-400 whitespace-nowrap">Price</th>
                    <th className="px-6 py-4 text-[11px] font-bold uppercase tracking-wider text-slate-400 whitespace-nowrap">Duration</th>
                    <th className="px-6 py-4 text-[11px] font-bold uppercase tracking-wider text-slate-400 whitespace-nowrap">Products</th>
                    <th className="px-6 py-4 text-[11px] font-bold uppercase tracking-wider text-slate-400 w-32">Status</th>
                    {(isAdmin && (canUpdateService || canDeleteService)) && <th className="px-6 py-4 text-[11px] font-bold uppercase tracking-wider text-slate-400 w-24 text-right">Actions</th>}
                  </tr>
                </thead>
                <tbody>
                  {paginatedSvc.length > 0 ? paginatedSvc.map((svc, i) => (
                    <motion.tr
                      key={svc.id}
                      initial={{ opacity: 0, y: 8 }} animate={{ opacity: 1, y: 0 }}
                      transition={{ delay: i * 0.03 }}
                      whileHover={{ backgroundColor: 'rgba(248,250,252,0.8)' }}
                      className={`border-b border-slate-100 group ${isAdmin && canUpdateService ? 'cursor-pointer' : ''}`}
                      onClick={() => isAdmin && canUpdateService && openEditService(svc)}
                    >
                      <td className="py-4 px-6">
                        <div className="flex items-center gap-3">
                          <div className="w-9 h-9 rounded-xl bg-slate-100 border border-slate-200 flex items-center justify-center text-slate-400 group-hover:bg-brand-50 group-hover:text-brand-600 transition-colors">
                            <Scissors className="w-4 h-4" />
                          </div>
                          <p className="text-sm font-semibold text-slate-900">{svc.name}</p>
                        </div>
                      </td>
                      <td className="py-4 px-6">
                        <span className="text-xs font-semibold text-slate-700 bg-slate-100 px-2 py-0.5 rounded-md border border-slate-200">
                          {svc.category?.name || '—'}
                        </span>
                      </td>
                      <td className="py-4 px-6">
                        <span className="text-sm font-bold text-slate-800">
                          {fmt.money(svc.default_price)}
                        </span>
                      </td>
                      <td className="py-4 px-6">
                        <span className="inline-flex items-center gap-1.5 text-sm text-slate-600 font-medium">
                          <Clock className="w-3.5 h-3.5 text-slate-400" />
                          {fmtDuration(svc.duration_minutes)}
                        </span>
                      </td>
                      <td className="py-4 px-6">
                        {svc.products?.length > 0 ? (
                          <span className="text-xs font-semibold text-slate-700 bg-slate-100 px-2 py-0.5 rounded-md border border-slate-200">
                            {svc.products.length} linked
                          </span>
                        ) : (
                          <span className="text-xs text-slate-400">{isAdmin ? 'None' : '—'}</span>
                        )}
                      </td>
                      <td className="py-4 px-6">
                        {svc.is_active ? (
                          <span className="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200/60">
                            <CheckCircle2 className="w-3.5 h-3.5" /> Active
                          </span>
                        ) : (
                          <span className="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold bg-slate-100 text-slate-600 border border-slate-200">
                            <AlertTriangle className="w-3.5 h-3.5" /> Inactive
                          </span>
                        )}
                      </td>
                      {(isAdmin && (canUpdateService || canDeleteService)) && <td className="py-4 px-6 text-right">
                        <div className="flex items-center gap-2 justify-end">
                          {canUpdateService && (
                          <button
                            onClick={e => { e.stopPropagation(); openEditService(svc) }}
                            className="p-1.5 rounded-lg bg-slate-100 hover:bg-brand-50 hover:text-brand-600 text-slate-400 transition cursor-pointer"
                            title="Edit Service"
                          >
                            <Edit2 className="w-4 h-4" />
                          </button>
                          )}
                          {canDeleteService && (
                          <button
                            onClick={e => { e.stopPropagation(); setSvcDeleteModal({ isOpen: true, service: svc }); setSvcDeleteError('') }}
                            className="p-1.5 rounded-lg bg-slate-100 hover:bg-rose-50 hover:text-rose-600 text-slate-400 transition cursor-pointer"
                            title="Delete Service"
                          >
                            <Trash2 className="w-4 h-4" />
                          </button>
                          )}
                        </div>
                      </td>}
                    </motion.tr>
                  )) : (
                    <tr>
                      <td colSpan={7} className="py-16 text-center text-slate-400">
                        <Scissors className="w-10 h-10 mx-auto mb-3 text-slate-300" />
                        <p className="text-lg font-semibold text-slate-600">No services found</p>
                        <p className="text-sm mt-1">
                          {svcSearch
                            ? 'Try adjusting your search.'
                            : isAdmin
                              ? 'Create your first service to get started.'
                              : 'No services onboarded for this salon yet. Add offerings or complete onboarding.'}
                        </p>
                        {!svcSearch && isAdmin && canCreateService && (
                          <BaseButton onClick={openCreateService} className="mt-4" size="sm" leftIcon={Plus}>Add Service</BaseButton>
                        )}
                      </td>
                    </tr>
                  )}
                </tbody>
              </table>
            </div>
          ) : (
            <div className="overflow-x-auto">
              <table className="w-full text-left">
                <thead>
                  <tr className="border-b border-slate-100 bg-slate-50/50">
                    <th className="px-6 py-4 text-[11px] font-bold uppercase tracking-wider text-slate-400">Product Name</th>
                    <th className="px-6 py-4 text-[11px] font-bold uppercase tracking-wider text-slate-400 whitespace-nowrap">Category</th>
                    <th className="px-6 py-4 text-[11px] font-bold uppercase tracking-wider text-slate-400 w-32">Status</th>
                    {(isAdmin && (canUpdateProduct || canDeleteProduct)) && <th className="px-6 py-4 text-[11px] font-bold uppercase tracking-wider text-slate-400 w-24 text-right">Actions</th>}
                  </tr>
                </thead>
                <tbody>
                  {paginatedPrd.length > 0 ? paginatedPrd.map((prd, i) => (
                    <motion.tr
                      key={prd.id}
                      initial={{ opacity: 0, y: 8 }} animate={{ opacity: 1, y: 0 }}
                      transition={{ delay: i * 0.03 }}
                      whileHover={{ backgroundColor: 'rgba(248,250,252,0.8)' }}
                      className={`border-b border-slate-100 group ${isAdmin && canUpdateProduct ? 'cursor-pointer' : ''}`}
                      onClick={() => isAdmin && canUpdateProduct && openEditProduct(prd)}
                    >
                      <td className="py-4 px-6">
                        <div className="flex items-center gap-3">
                          <div className="w-9 h-9 rounded-xl bg-slate-100 border border-slate-200 flex items-center justify-center text-slate-400 group-hover:bg-brand-50 group-hover:text-brand-600 transition-colors">
                            <Package className="w-4 h-4" />
                          </div>
                          <p className="text-sm font-semibold text-slate-900">{prd.name}</p>
                        </div>
                      </td>
                      <td className="py-4 px-6">
                        <span className="text-xs font-semibold text-slate-700 bg-slate-100 px-2 py-0.5 rounded-md border border-slate-200">
                          {prd.category?.name || '—'}
                        </span>
                      </td>
                      <td className="py-4 px-6">
                        {prd.is_active ? (
                          <span className="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200/60">
                            <CheckCircle2 className="w-3.5 h-3.5" /> Active
                          </span>
                        ) : (
                          <span className="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold bg-slate-100 text-slate-600 border border-slate-200">
                            <AlertTriangle className="w-3.5 h-3.5" /> Inactive
                          </span>
                        )}
                      </td>
                      {(isAdmin && (canUpdateProduct || canDeleteProduct)) && <td className="py-4 px-6 text-right">
                        <div className="flex items-center gap-2 justify-end">
                          {canUpdateProduct && (
                          <button
                            onClick={e => { e.stopPropagation(); openEditProduct(prd) }}
                            className="p-1.5 rounded-lg bg-slate-100 hover:bg-brand-50 hover:text-brand-600 text-slate-400 transition cursor-pointer"
                            title="Edit Product"
                          >
                            <Edit2 className="w-4 h-4" />
                          </button>
                          )}
                          {canDeleteProduct && (
                          <button
                            onClick={e => { e.stopPropagation(); setPrdDeleteModal({ isOpen: true, product: prd }); setPrdDeleteError('') }}
                            className="p-1.5 rounded-lg bg-slate-100 hover:bg-rose-50 hover:text-rose-600 text-slate-400 transition cursor-pointer"
                            title="Delete Product"
                          >
                            <Trash2 className="w-4 h-4" />
                          </button>
                          )}
                        </div>
                      </td>}
                    </motion.tr>
                  )) : (
                    <tr>
                      <td colSpan={4} className="py-16 text-center text-slate-400">
                        <Package className="w-10 h-10 mx-auto mb-3 text-slate-300" />
                        <p className="text-lg font-semibold text-slate-600">No products found</p>
                        <p className="text-sm mt-1">
                          {prdSearch
                            ? 'Try adjusting your search.'
                            : isAdmin
                              ? 'Create your first product to get started.'
                              : 'No products onboarded for this salon yet. Add offerings or complete onboarding.'}
                        </p>
                        {!prdSearch && isAdmin && canCreateProduct && (
                          <BaseButton onClick={openCreateProduct} className="mt-4" size="sm" leftIcon={Plus}>Add Product</BaseButton>
                        )}
                      </td>
                    </tr>
                  )}
                </tbody>
              </table>
            </div>
          )}

          {/* Pagination — always show count when the active tab has rows */}
          {!isLoading && (
            activeTab === 'catalog' ? (
              <ListPagination
                page={catPage}
                pageSize={itemsPerPage}
                total={filteredCat.length}
                onPageChange={setCatPage}
                onPageSizeChange={setItemsPerPage}
                label="offerings"
              />
            ) : activeTab === 'services' ? (
              <ListPagination
                page={svcPage}
                pageSize={itemsPerPage}
                total={filteredSvc.length}
                onPageChange={setSvcPage}
                onPageSizeChange={setItemsPerPage}
                label="services"
              />
            ) : (
              <ListPagination
                page={prdPage}
                pageSize={itemsPerPage}
                total={filteredPrd.length}
                onPageChange={setPrdPage}
                onPageSizeChange={setItemsPerPage}
                label="products"
              />
            )
          )}
        </div>
      </div>

      {/* ─── Modals ─── */}
      <ServiceModal
        isOpen={isSvcModalOpen && (editingService ? canUpdateService : canCreateService)}
        onClose={() => setIsSvcModalOpen(false)}
        editingService={editingService}
        allProducts={products}
        allCategories={categories}
        onSaved={handleSvcSaved}
      />

      <ProductModal
        isOpen={isPrdModalOpen && (editingProduct ? canUpdateProduct : canCreateProduct)}
        onClose={() => setIsPrdModalOpen(false)}
        editingProduct={editingProduct}
        allCategories={categories}
        onSaved={handlePrdSaved}
      />

      <CatalogItemModal
        isOpen={isCatModalOpen && (editingCatalog ? canUpdateCatalog : canCreateCatalog)}
        onClose={() => setIsCatModalOpen(false)}
        editingItem={editingCatalog}
        services={services}
        products={products}
        branches={branches}
        selectedBranchId={branchFilter}
        canChooseBranch={!isAdmin && !isBranchScoped}
        onSaved={handleCatSaved}
      />

      <DeleteModal
        isOpen={svcDeleteModal.isOpen && canDeleteService}
        onClose={() => { setSvcDeleteModal({ isOpen: false, service: null }); setSvcDeleteError('') }}
        title="Delete Service"
        name={svcDeleteModal.service?.name}
        onConfirm={handleSvcDelete}
        isDeleting={isSvcDeleting}
        deleteError={svcDeleteError}
      />

      <DeleteModal
        isOpen={prdDeleteModal.isOpen && canDeleteProduct}
        onClose={() => { setPrdDeleteModal({ isOpen: false, product: null }); setPrdDeleteError('') }}
        title="Delete Product"
        name={prdDeleteModal.product?.name}
        onConfirm={handlePrdDelete}
        isDeleting={isPrdDeleting}
        deleteError={prdDeleteError}
      />

      <DeleteModal
        isOpen={catDeleteModal.isOpen && canDeleteCatalog}
        onClose={() => { setCatDeleteModal({ isOpen: false, item: null }); setCatDeleteError('') }}
        title="Delete Offering"
        name={`${catDeleteModal.item?.service?.name || ''}${catDeleteModal.item?.product?.name ? ` / ${catDeleteModal.item.product.name}` : ' (service only)'}`}
        onConfirm={handleCatDelete}
        isDeleting={isCatDeleting}
        deleteError={catDeleteError}
      />
    </div>
  )
}
