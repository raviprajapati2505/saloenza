import React, { useState, useMemo, useCallback, useEffect } from 'react'
import { useSearchParams } from 'react-router-dom'
import { motion, AnimatePresence } from 'framer-motion'
import {
  Users, Search, Phone, Mail, Clock, Plus, Pencil, Trash2,
  TrendingUp, Award, ArrowUpRight, X, Store, Loader2,
  AlertTriangle, CheckCircle2, Tag, History,
} from 'lucide-react'
import PageHeader from '../components/ui/PageHeader.jsx'
import BaseButton from '../components/ui/BaseButton.jsx'
import BaseInput from '../components/ui/BaseInput.jsx'
import BaseModal from '../components/ui/BaseModal.jsx'
import { useAuthStore } from '../stores/auth'
import { subscriptionPageSubtitle } from '../lib/subscriptionModules.js'
import { useTenantFormatter } from '../hooks/useTenantFormatter.js'
import {
  fetchCustomers,
  fetchCustomer,
  createCustomer,
  updateCustomer,
  deleteCustomer,
  fetchCustomerTags,
  createCustomerTag,
} from '../services/customerService.js'
import { apiGet, parseList } from '../lib/apiHelpers'
import { customerContactSubtitle, customerEmailDisplay, customerPhoneDisplay } from '../lib/customerContact.js'
import { TENANT_PERMISSIONS } from '../lib/tenantPermissions.js'

const TAG_COLORS = [
  { id: 'brand', className: 'bg-brand-50 text-brand-700 border-brand-200' },
  { id: 'indigo', className: 'bg-indigo-50 text-indigo-700 border-indigo-200' },
  { id: 'rose', className: 'bg-rose-50 text-rose-700 border-rose-200' },
  { id: 'amber', className: 'bg-amber-50 text-amber-700 border-amber-200' },
  { id: 'emerald', className: 'bg-emerald-50 text-emerald-700 border-emerald-200' },
  { id: 'violet', className: 'bg-violet-50 text-violet-700 border-violet-200' },
  { id: 'slate', className: 'bg-slate-100 text-slate-600 border-slate-200' },
]

const SEGMENT_OPTIONS = [
  { value: '', label: 'All segments' },
  { value: 'new', label: 'New (30 days)' },
  { value: 'returning', label: 'Returning (2+ visits)' },
  { value: 'lapsed', label: 'Lapsed (90+ days)' },
]

function tagColorClass(color) {
  return TAG_COLORS.find((c) => c.id === color)?.className ?? TAG_COLORS.find((c) => c.id === 'slate').className
}

function getAvatarBg(name) {
  const colors = [
    'bg-brand-600 text-white', 'bg-indigo-600 text-white',
    'bg-rose-600 text-white', 'bg-amber-600 text-white',
    'bg-sky-600 text-white', 'bg-purple-600 text-white',
  ]
  let hash = 0
  for (let i = 0; i < (name?.length || 0); i++) {
    hash = name.charCodeAt(i) + ((hash << 5) - hash)
  }
  return colors[Math.abs(hash) % colors.length]
}

function getInitials(name) {
  return name?.split(' ').map(n => n[0]).join('').toUpperCase().slice(0, 2) || 'CU'
}

function formatDate(iso) {
  if (!iso) return '—'
  return new Date(iso).toLocaleDateString('en-IN', { day: 'numeric', month: 'short', year: 'numeric' })
}

function formatDateTime(iso) {
  if (!iso) return '—'
  return new Date(iso).toLocaleString('en-IN', {
    day: 'numeric', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit',
  })
}

function salonLabel(customer) {
  const names = (customer?.saloons || []).map((s) => s.name).filter(Boolean)
  if (names.length === 0) return null
  if (names.length === 1) return names[0]
  return `${names[0]} +${names.length - 1}`
}

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
      <button type="button" onClick={onDismiss} className="ml-2 opacity-50 hover:opacity-100 cursor-pointer">
        <X className="w-3.5 h-3.5" />
      </button>
    </div>
  )
}

function TagBadge({ tag }) {
  return (
    <span className={`inline-flex items-center gap-1 px-2 py-0.5 text-[10px] font-bold uppercase border rounded-full ${tagColorClass(tag.color)}`}>
      <Tag className="w-2.5 h-2.5" />
      {tag.name}
    </span>
  )
}

function CustomerFormModal({
  open,
  customer,
  saloons,
  tags,
  isSystemAdmin,
  defaultSaloonId,
  onClose,
  onSaved,
  onTagCreated,
}) {
  const isEdit = Boolean(customer?.id)
  const canEditContact = !isEdit || customer?.can_view_contact !== false
  const [name, setName] = useState('')
  const [email, setEmail] = useState('')
  const [phone, setPhone] = useState('')
  const [birthday, setBirthday] = useState('')
  const [anniversary, setAnniversary] = useState('')
  const [notes, setNotes] = useState('')
  const [isActive, setIsActive] = useState(true)
  const [saloonId, setSaloonId] = useState('')
  const [selectedTagIds, setSelectedTagIds] = useState([])
  const [newTagName, setNewTagName] = useState('')
  const [newTagColor, setNewTagColor] = useState('brand')
  const [creatingTag, setCreatingTag] = useState(false)
  const [saving, setSaving] = useState(false)
  const [errors, setErrors] = useState({})
  const [serverError, setServerError] = useState('')

  useEffect(() => {
    if (!open) return
    setName(customer?.name || '')
    setEmail(customer?.email || '')
    setPhone(customer?.phone || '')
    setBirthday(customer?.birthday || '')
    setAnniversary(customer?.anniversary || '')
    setNotes(customer?.notes || '')
    setIsActive(isEdit ? Boolean(customer.is_active) : true)
    setSaloonId(
      isEdit
        ? String(customer?.saloon_ids?.[0] ?? customer?.saloons?.[0]?.id ?? '')
        : String(defaultSaloonId || saloons[0]?.id || ''),
    )
    setSelectedTagIds((customer?.tags || []).map((t) => t.id))
    setNewTagName('')
    setNewTagColor('brand')
    setErrors({})
    setServerError('')
  }, [open, customer, isEdit, defaultSaloonId, saloons])

  const toggleTag = (tagId) => {
    setSelectedTagIds((prev) => (
      prev.includes(tagId) ? prev.filter((id) => id !== tagId) : [...prev, tagId]
    ))
  }

  const handleCreateTag = async () => {
    const trimmed = newTagName.trim()
    if (!trimmed) return
    setCreatingTag(true)
    setServerError('')
    try {
      const payload = { name: trimmed, color: newTagColor }
      if (isSystemAdmin) payload.saloon_id = Number(saloonId || defaultSaloonId)
      const tag = await createCustomerTag(payload)
      onTagCreated?.(tag)
      setSelectedTagIds((prev) => [...prev, tag.id])
      setNewTagName('')
    } catch (error) {
      setServerError(error?.response?.data?.message || 'Failed to create tag.')
    } finally {
      setCreatingTag(false)
    }
  }

  const handleSubmit = async (e) => {
    e.preventDefault()
    setSaving(true)
    setErrors({})
    setServerError('')

    const payload = {
      name: name.trim(),
      notes: notes.trim() || null,
      birthday: birthday || null,
      anniversary: anniversary || null,
      is_active: isActive,
      tag_ids: selectedTagIds,
    }
    if (canEditContact) {
      payload.email = email.trim() || null
      payload.phone = phone.trim() || null
    }
    if (isSystemAdmin && !isEdit) {
      payload.saloon_id = Number(saloonId)
    }

    try {
      const saved = isEdit
        ? await updateCustomer(customer.id, payload)
        : await createCustomer(payload)
      onSaved(saved)
      onClose()
    } catch (error) {
      const fieldErrors = error?.response?.data?.errors
      if (fieldErrors) {
        setErrors(Object.fromEntries(
          Object.entries(fieldErrors).map(([key, msgs]) => [key, Array.isArray(msgs) ? msgs[0] : msgs]),
        ))
      } else {
        setServerError(error?.response?.data?.message || 'Failed to save customer.')
      }
    } finally {
      setSaving(false)
    }
  }

  return (
    <BaseModal
      open={open}
      onClose={onClose}
      title={isEdit ? 'Edit Customer' : 'Add Customer'}
      size="lg"
      footer={(
        <>
          <BaseButton variant="secondary" onClick={onClose} disabled={saving}>Cancel</BaseButton>
          <BaseButton onClick={handleSubmit} disabled={saving} leftIcon={saving ? Loader2 : undefined}>
            {saving ? 'Saving…' : isEdit ? 'Save Changes' : 'Create Customer'}
          </BaseButton>
        </>
      )}
    >
      <form onSubmit={handleSubmit} className="space-y-4">
        {serverError && (
          <div className="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">{serverError}</div>
        )}

        {isSystemAdmin && !isEdit && (
          <div>
            <label className="block text-xs font-bold text-slate-500 uppercase mb-1.5">Salon</label>
            <select
              value={saloonId}
              onChange={(e) => setSaloonId(e.target.value)}
              className="w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm font-medium text-slate-700 focus:outline-none focus:ring-2 focus:ring-brand-500/20 focus:border-brand-400"
              required
            >
              <option value="">Select salon</option>
              {saloons.map((s) => (
                <option key={s.id} value={s.id}>{s.name}</option>
              ))}
            </select>
            {errors.saloon_id && <p className="mt-1 text-xs text-rose-600">{errors.saloon_id}</p>}
          </div>
        )}

        <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <BaseInput label="Name" required value={name} onChange={(e) => setName(e.target.value)} error={errors.name} />
          {canEditContact ? (
            <BaseInput label="Phone" value={phone} onChange={(e) => setPhone(e.target.value)} error={errors.phone} />
          ) : (
            <div className="rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 text-sm text-slate-500">
              Phone is hidden — only salon owners, branch managers, or staff who added this customer can view it.
            </div>
          )}
          {canEditContact ? (
            <BaseInput label="Email" type="email" value={email} onChange={(e) => setEmail(e.target.value)} error={errors.email} />
          ) : (
            <div className="rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 text-sm text-slate-500">
              Email is hidden for privacy.
            </div>
          )}
          <BaseInput label="Birthday" type="date" value={birthday} onChange={(e) => setBirthday(e.target.value)} error={errors.birthday} />
          <BaseInput label="Anniversary" type="date" value={anniversary} onChange={(e) => setAnniversary(e.target.value)} error={errors.anniversary} />
          <div className="flex items-end">
            <label className="flex items-center gap-2 cursor-pointer pb-2.5">
              <input
                type="checkbox"
                checked={isActive}
                onChange={(e) => setIsActive(e.target.checked)}
                className="rounded border-slate-300 text-brand-600 focus:ring-brand-500"
              />
              <span className="text-sm font-semibold text-slate-700">Active customer</span>
            </label>
          </div>
        </div>

        <div>
          <label className="block text-xs font-bold text-slate-500 uppercase mb-1.5">Notes</label>
          <textarea
            value={notes}
            onChange={(e) => setNotes(e.target.value)}
            rows={3}
            placeholder="Preferences, allergies, stylist notes…"
            className="w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-700 focus:outline-none focus:ring-2 focus:ring-brand-500/20 focus:border-brand-400 resize-none"
          />
          {errors.notes && <p className="mt-1 text-xs text-rose-600">{errors.notes}</p>}
        </div>

        <div>
          <p className="text-xs font-bold text-slate-500 uppercase mb-2">Tags / Segments</p>
          {tags.length > 0 ? (
            <div className="flex flex-wrap gap-2 mb-3">
              {tags.map((tag) => (
                <button
                  key={tag.id}
                  type="button"
                  onClick={() => toggleTag(tag.id)}
                  className={`inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-bold rounded-xl border transition cursor-pointer
                    ${selectedTagIds.includes(tag.id)
                      ? tagColorClass(tag.color)
                      : 'bg-white text-slate-500 border-slate-200 hover:border-slate-300'}`}
                >
                  <Tag className="w-3 h-3" />
                  {tag.name}
                </button>
              ))}
            </div>
          ) : (
            <p className="text-xs text-slate-400 mb-3">No tags yet — create one below.</p>
          )}
          <div className="flex flex-col sm:flex-row gap-2">
            <input
              type="text"
              value={newTagName}
              onChange={(e) => setNewTagName(e.target.value)}
              placeholder="New tag (e.g. VIP, Regular)"
              className="flex-1 rounded-xl border border-slate-200 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500/20 focus:border-brand-400"
            />
            <select
              value={newTagColor}
              onChange={(e) => setNewTagColor(e.target.value)}
              className="rounded-xl border border-slate-200 px-3 py-2 text-sm bg-white"
            >
              {TAG_COLORS.map((c) => (
                <option key={c.id} value={c.id}>{c.id}</option>
              ))}
            </select>
            <BaseButton
              type="button"
              variant="secondary"
              onClick={handleCreateTag}
              disabled={creatingTag || !newTagName.trim()}
              leftIcon={creatingTag ? Loader2 : Plus}
            >
              Add Tag
            </BaseButton>
          </div>
          {errors.tag_ids && <p className="mt-1 text-xs text-rose-600">{errors.tag_ids}</p>}
        </div>

        {!isEdit && (
          <p className="text-xs text-slate-400">
            Tip: customers are also created automatically when booking appointments.
          </p>
        )}
      </form>
    </BaseModal>
  )
}

function CustomerDetailModal({
  customerId,
  onClose,
  onEdit,
  onDeleted,
  canManage,
  showSalons,
  fmt,
}) {
  const [customer, setCustomer] = useState(null)
  const [loading, setLoading] = useState(true)
  const [confirmDelete, setConfirmDelete] = useState(false)
  const [deleting, setDeleting] = useState(false)
  const [error, setError] = useState('')

  useEffect(() => {
    let cancelled = false
    setLoading(true)
    setError('')
    fetchCustomer(customerId)
      .then((data) => { if (!cancelled) setCustomer(data) })
      .catch(() => { if (!cancelled) setError('Failed to load customer details.') })
      .finally(() => { if (!cancelled) setLoading(false) })
    return () => { cancelled = true }
  }, [customerId])

  const handleDelete = async () => {
    setDeleting(true)
    try {
      await deleteCustomer(customerId)
      onDeleted()
      onClose()
    } catch {
      setError('Failed to delete customer.')
      setConfirmDelete(false)
    } finally {
      setDeleting(false)
    }
  }

  if (loading) {
    return (
      <AnimatePresence>
        <motion.div initial={{ opacity: 0 }} animate={{ opacity: 1 }} exit={{ opacity: 0 }}
          className="fixed inset-0 z-50 bg-black/40 backdrop-blur-sm flex items-center justify-center p-4">
          <div className="bg-white rounded-2xl p-8 flex items-center gap-3 text-slate-500">
            <Loader2 className="w-5 h-5 animate-spin" />
            Loading customer…
          </div>
        </motion.div>
      </AnimatePresence>
    )
  }

  if (!customer) {
    return (
      <AnimatePresence>
        <motion.div initial={{ opacity: 0 }} animate={{ opacity: 1 }} exit={{ opacity: 0 }}
          className="fixed inset-0 z-50 bg-black/40 backdrop-blur-sm flex items-center justify-center p-4" onClick={onClose}>
          <div className="bg-white rounded-2xl p-6 text-center" onClick={(e) => e.stopPropagation()}>
            <p className="text-slate-600">{error || 'Customer not found.'}</p>
            <BaseButton variant="secondary" className="mt-4" onClick={onClose}>Close</BaseButton>
          </div>
        </motion.div>
      </AnimatePresence>
    )
  }

  return (
    <AnimatePresence>
      <motion.div initial={{ opacity: 0 }} animate={{ opacity: 1 }} exit={{ opacity: 0 }}
        className="fixed inset-0 z-50 bg-black/40 backdrop-blur-sm flex items-center justify-center p-4" onClick={onClose}>
        <motion.div initial={{ opacity: 0, scale: 0.96, y: 16 }} animate={{ opacity: 1, scale: 1, y: 0 }}
          exit={{ opacity: 0, scale: 0.96, y: 16 }}
          className="bg-white rounded-2xl shadow-2xl border border-slate-200 w-full max-w-2xl max-h-[90vh] overflow-hidden flex flex-col"
          onClick={e => e.stopPropagation()}>
          <div className="relative bg-gradient-to-br from-brand-600 to-brand-700 p-6 text-white shrink-0">
            <button type="button" onClick={onClose} className="absolute top-4 right-4 p-1.5 rounded-xl bg-white/10 hover:bg-white/20">
              <X className="w-4 h-4" />
            </button>
            <div className="flex items-center gap-4">
              <div className={`w-16 h-16 rounded-2xl flex items-center justify-center text-xl font-black border-2 border-white/20 ${getAvatarBg(customer.name)}`}>
                {getInitials(customer.name)}
              </div>
              <div className="min-w-0">
                <h2 className="text-lg font-black truncate">{customer.name}</h2>
                <p className="text-white/70 text-xs mt-1">Added {formatDate(customer.created_at)}</p>
                {(customer.tags?.length > 0) && (
                  <div className="flex flex-wrap gap-1.5 mt-2">
                    {customer.tags.map((tag) => (
                      <span key={tag.id} className="px-2 py-0.5 text-[10px] font-bold uppercase bg-white/15 rounded-full border border-white/20">
                        {tag.name}
                      </span>
                    ))}
                  </div>
                )}
              </div>
            </div>
          </div>

          <div className="p-6 space-y-4 overflow-y-auto flex-1">
            {error && (
              <div className="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">{error}</div>
            )}

            {customer.visit_stats && (
              <div className="grid grid-cols-3 gap-3">
                {[
                  { label: 'Visits', value: customer.visit_stats.total_visits ?? 0 },
                  { label: 'Total Spent', value: fmt.money(customer.visit_stats.total_spent ?? 0) },
                  { label: 'Last Visit', value: formatDate(customer.visit_stats.last_visit_at) },
                ].map((stat) => (
                  <div key={stat.label} className="p-3 bg-slate-50 border border-slate-100 rounded-xl text-center">
                    <p className="text-[10px] text-slate-400 font-bold uppercase">{stat.label}</p>
                    <p className="text-sm font-black text-slate-800 mt-1">{stat.value}</p>
                  </div>
                ))}
              </div>
            )}

            <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
              <div className="flex items-center gap-3 p-3 bg-slate-50 border border-slate-100 rounded-xl">
                <Mail className="w-4 h-4 text-slate-400" />
                <div><p className="text-[10px] text-slate-400 font-bold uppercase">Email</p><p className="text-xs font-semibold text-slate-700">{customerEmailDisplay(customer) || '—'}</p></div>
              </div>
              <div className="flex items-center gap-3 p-3 bg-slate-50 border border-slate-100 rounded-xl">
                <Phone className="w-4 h-4 text-slate-400" />
                <div><p className="text-[10px] text-slate-400 font-bold uppercase">Phone</p><p className="text-xs font-semibold text-slate-700">{customerPhoneDisplay(customer) || '—'}</p></div>
              </div>
              <div className="flex items-center gap-3 p-3 bg-slate-50 border border-slate-100 rounded-xl">
                <Clock className="w-4 h-4 text-slate-400" />
                <div><p className="text-[10px] text-slate-400 font-bold uppercase">Birthday</p><p className="text-xs font-semibold text-slate-700">{formatDate(customer.birthday)}</p></div>
              </div>
              <div className="flex items-center gap-3 p-3 bg-slate-50 border border-slate-100 rounded-xl">
                <Award className="w-4 h-4 text-slate-400" />
                <div><p className="text-[10px] text-slate-400 font-bold uppercase">Anniversary</p><p className="text-xs font-semibold text-slate-700">{formatDate(customer.anniversary)}</p></div>
              </div>
            </div>

            {showSalons && (customer.saloons?.length > 0) && (
              <div className="p-4 bg-slate-50 border border-slate-100 rounded-xl">
                <p className="text-[10px] text-slate-400 font-bold uppercase mb-2">Salons</p>
                <div className="flex flex-wrap gap-2">
                  {customer.saloons.map((salon) => (
                    <span key={salon.id} className="inline-flex items-center gap-1.5 px-2.5 py-1 text-xs font-semibold text-slate-700 bg-white border border-slate-200 rounded-lg">
                      <Store className="w-3 h-3 text-slate-400" />
                      {salon.name}
                    </span>
                  ))}
                </div>
              </div>
            )}

            {customer.notes && (
              <div className="p-4 bg-slate-50 border border-slate-100 rounded-xl">
                <p className="text-[10px] text-slate-400 font-bold uppercase mb-1">Notes</p>
                <p className="text-sm text-slate-700 whitespace-pre-wrap">{customer.notes}</p>
              </div>
            )}

            <div>
              <div className="flex items-center gap-2 mb-3">
                <History className="w-4 h-4 text-slate-400" />
                <p className="text-xs font-bold text-slate-500 uppercase">Visit History</p>
              </div>
              {(customer.recent_visits?.length > 0) ? (
                <div className="space-y-2 max-h-64 overflow-y-auto">
                  {customer.recent_visits.map((visit) => (
                    <div key={visit.id} className="flex items-start justify-between gap-3 p-3 bg-slate-50 border border-slate-100 rounded-xl">
                      <div className="min-w-0">
                        <p className="text-sm font-semibold text-slate-800 truncate">
                          {visit.service_name || 'Service'}
                        </p>
                        <p className="text-xs text-slate-500 mt-0.5">
                          {formatDateTime(visit.starts_at)}
                          {visit.branch?.name ? ` · ${visit.branch.name}` : ''}
                          {visit.staff?.name ? ` · ${visit.staff.name}` : ''}
                        </p>
                      </div>
                      <div className="text-right shrink-0">
                        <span className={`inline-block px-2 py-0.5 text-[10px] font-bold uppercase rounded-full border
                          ${visit.status === 'completed' ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : 'bg-slate-100 text-slate-600 border-slate-200'}`}>
                          {visit.status}
                        </span>
                        {visit.grand_total != null && (
                          <p className="text-xs font-bold text-slate-700 mt-1">{fmt.money(visit.grand_total)}</p>
                        )}
                      </div>
                    </div>
                  ))}
                </div>
              ) : (
                <p className="text-sm text-slate-400 italic py-4 text-center border border-dashed border-slate-200 rounded-xl">
                  No visits recorded yet.
                </p>
              )}
            </div>
          </div>

          <div className="p-4 border-t border-slate-100 bg-slate-50/80 flex flex-col sm:flex-row gap-2 shrink-0">
            {canManage && !confirmDelete && (
              <>
                <BaseButton variant="secondary" leftIcon={Pencil} onClick={() => onEdit(customer)} className="flex-1">
                  Edit
                </BaseButton>
                <BaseButton variant="ghost" leftIcon={Trash2} onClick={() => setConfirmDelete(true)}
                  className="flex-1 text-rose-600 bg-rose-50 hover:bg-rose-100">
                  Delete
                </BaseButton>
              </>
            )}
            {confirmDelete && (
              <div className="flex-1 flex flex-col sm:flex-row gap-2">
                <p className="text-sm text-rose-700 font-semibold flex items-center gap-2 flex-1">
                  <AlertTriangle className="w-4 h-4" />
                  Delete this customer permanently?
                </p>
                <BaseButton variant="ghost" onClick={() => setConfirmDelete(false)} disabled={deleting}>Cancel</BaseButton>
                <BaseButton variant="ghost" leftIcon={deleting ? Loader2 : Trash2} onClick={handleDelete} disabled={deleting}
                  className="text-rose-600 bg-rose-50 hover:bg-rose-100">
                  {deleting ? 'Deleting…' : 'Confirm Delete'}
                </BaseButton>
              </div>
            )}
            {!confirmDelete && (
              <BaseButton variant="secondary" onClick={onClose} className="sm:w-auto">Close</BaseButton>
            )}
          </div>
        </motion.div>
      </motion.div>
    </AnimatePresence>
  )
}

function CustomerCard({ customer, onClick, showSalons }) {
  const label = showSalons ? salonLabel(customer) : null

  return (
    <motion.div whileHover={{ y: -3 }} whileTap={{ scale: 0.99 }} onClick={() => onClick(customer)}
      className="relative bg-white border border-slate-200/80 rounded-2xl p-5 shadow-sm cursor-pointer group overflow-hidden hover:border-slate-300">
      <div className="flex items-start justify-between mb-4">
        <div className={`w-12 h-12 rounded-xl flex items-center justify-center text-sm font-black ${getAvatarBg(customer.name)}`}>{getInitials(customer.name)}</div>
        <span className={`px-2 py-0.5 text-[10px] font-bold uppercase border rounded-full ${customer.is_active ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : 'bg-slate-100 text-slate-500 border-slate-200'}`}>
          {customer.is_active ? 'Active' : 'Inactive'}
        </span>
      </div>
      <h3 className="font-bold text-slate-900 group-hover:text-brand-600 transition-colors">{customer.name}</h3>
      <p className="text-xs text-slate-400 font-medium mt-0.5 truncate">{customerContactSubtitle(customer)}</p>
      {customer.tags?.length > 0 && (
        <div className="flex flex-wrap gap-1 mt-2">
          {customer.tags.slice(0, 3).map((tag) => <TagBadge key={tag.id} tag={tag} />)}
          {customer.tags.length > 3 && (
            <span className="text-[10px] font-bold text-slate-400">+{customer.tags.length - 3}</span>
          )}
        </div>
      )}
      {label && (
        <p className="mt-2 inline-flex items-center gap-1 text-[11px] font-semibold text-slate-500">
          <Store className="w-3 h-3" />
          {label}
        </p>
      )}
      <div className="flex items-center justify-between mt-4 pt-3 border-t border-slate-100 text-xs font-bold text-slate-400 group-hover:text-brand-600">
        <span className="flex items-center gap-1">
          <Clock className="w-3 h-3" />
          {customer.appointments_count > 0
            ? `${customer.appointments_count} visit${customer.appointments_count === 1 ? '' : 's'}`
            : formatDate(customer.created_at)}
        </span>
        <ArrowUpRight className="w-3.5 h-3.5" />
      </div>
    </motion.div>
  )
}

export default function CustomersView() {
  const auth = useAuthStore()
  const fmt = useTenantFormatter()
  const [searchParams, setSearchParams] = useSearchParams()
  const isSystemAdmin = Boolean(auth.isSystemAdmin || auth.grantsAllPermissions)
  const canView = auth.can('customers.view') || isSystemAdmin
  const canManage = auth.can('customers.manage') || isSystemAdmin
  const canSearchAllContacts = auth.can(TENANT_PERMISSIONS.CUSTOMERS_CONTACTS_VIEW) || isSystemAdmin || Boolean(auth.grantsAllPermissions)

  const [customers, setCustomers] = useState([])
  const [tags, setTags] = useState([])
  const [saloons, setSaloons] = useState([])
  const [saloonFilter, setSaloonFilter] = useState('')
  const [tagFilter, setTagFilter] = useState('')
  const [segmentFilter, setSegmentFilter] = useState('')
  const [isLoading, setIsLoading] = useState(true)
  const [search, setSearch] = useState('')
  const [statusFilter, setStatusFilter] = useState('all')
  const [selectedCustomerId, setSelectedCustomerId] = useState(null)
  const [formOpen, setFormOpen] = useState(false)
  const [editingCustomer, setEditingCustomer] = useState(null)
  const [toast, setToast] = useState(null)

  const listParams = useMemo(() => {
    const params = { page: 1, per_page: 100 }
    if (search.trim()) params.search = search.trim()
    if (statusFilter === 'active') params.is_active = 1
    if (statusFilter === 'inactive') params.is_active = 0
    if (isSystemAdmin && saloonFilter) params.saloon_id = Number(saloonFilter)
    if (tagFilter) params.tag_id = Number(tagFilter)
    if (segmentFilter) params.segment = segmentFilter
    return params
  }, [isSystemAdmin, saloonFilter, search, statusFilter, tagFilter, segmentFilter])

  const fetchTags = useCallback(async () => {
    if (!canView) return
    try {
      const params = { page: 1, per_page: 100 }
      if (isSystemAdmin && saloonFilter) params.saloon_id = Number(saloonFilter)
      const data = await fetchCustomerTags(params)
      setTags(data)
    } catch {
      setTags([])
    }
  }, [canView, isSystemAdmin, saloonFilter])

  const loadCustomers = useCallback(async () => {
    if (!canView) {
      setCustomers([])
      setIsLoading(false)
      return
    }

    setIsLoading(true)
    try {
      const data = await fetchCustomers(listParams)
      setCustomers(data)
    } catch (error) {
      console.error('Failed to fetch customers:', error)
      setCustomers([])
    } finally {
      setIsLoading(false)
    }
  }, [canView, listParams])

  useEffect(() => {
    if (!isSystemAdmin) return
    let cancelled = false
    ;(async () => {
      try {
        const saloonsRes = await apiGet('/v1/saloons', { page: 1, per_page: 100 })
        if (!cancelled) setSaloons(parseList(saloonsRes, 'saloons'))
      } catch {
        if (!cancelled) setSaloons([])
      }
    })()
    return () => { cancelled = true }
  }, [isSystemAdmin])

  useEffect(() => {
    const timer = setTimeout(() => { void loadCustomers() }, search ? 300 : 0)
    return () => clearTimeout(timer)
  }, [loadCustomers, search])

  useEffect(() => {
    void fetchTags()
  }, [fetchTags])

  useEffect(() => {
    const create = searchParams.get('create')
    const customerId = searchParams.get('id')
    if (create === '1' && canManage) {
      setEditingCustomer(null)
      setFormOpen(true)
      const next = new URLSearchParams(searchParams)
      next.delete('create')
      setSearchParams(next, { replace: true })
    } else if (customerId && canView) {
      setSelectedCustomerId(Number(customerId))
      const next = new URLSearchParams(searchParams)
      next.delete('id')
      setSearchParams(next, { replace: true })
    }
  }, [searchParams, setSearchParams, canManage, canView])

  const handleSaved = (saved) => {
    setToast({ message: editingCustomer ? 'Customer updated successfully.' : 'Customer created successfully.', type: 'success' })
    setEditingCustomer(null)
    void loadCustomers()
    void fetchTags()
    if (selectedCustomerId === saved?.id) {
      setSelectedCustomerId(null)
      setTimeout(() => setSelectedCustomerId(saved.id), 0)
    }
  }

  const handleEditFromDetail = (customer) => {
    setSelectedCustomerId(null)
    setEditingCustomer(customer)
    setFormOpen(true)
  }

  const activeCount = customers.filter(c => c.is_active).length
  const inactiveCount = customers.length - activeCount

  return (
    <div className="space-y-6">
      <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <PageHeader
          title="Customers"
          subtitle={isSystemAdmin
            ? 'Manage customers across all salons.'
            : subscriptionPageSubtitle(auth, 'Manage customer profiles, tags, and visit history.')}
        />
        <div className="flex flex-col sm:flex-row gap-2 sm:items-center">
          {isSystemAdmin && (
            <select
              value={saloonFilter}
              onChange={(e) => { setSaloonFilter(e.target.value); setTagFilter('') }}
              className="w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm font-medium text-slate-700 shadow-sm focus:outline-none focus:ring-2 focus:ring-brand-500/20 focus:border-brand-400 sm:w-auto"
            >
              <option value="">All salons</option>
              {saloons.map((s) => (
                <option key={s.id} value={s.id}>{s.name}</option>
              ))}
            </select>
          )}
          {canManage && (
            <BaseButton leftIcon={Plus} onClick={() => { setEditingCustomer(null); setFormOpen(true) }}>
              Add Customer
            </BaseButton>
          )}
        </div>
      </div>

      <div className="grid grid-cols-2 lg:grid-cols-4 gap-4">
        {[
          { label: 'Total Customers', value: customers.length, icon: Users, color: 'indigo' },
          { label: 'Active', value: activeCount, icon: TrendingUp, color: 'teal' },
          { label: 'Inactive', value: inactiveCount, icon: Clock, color: 'amber' },
          { label: 'With Email', value: customers.filter(c => c.email || c.has_email).length, icon: Award, color: 'violet' },
        ].map(stat => {
          const Icon = stat.icon
          const colorMap = { indigo: 'bg-indigo-50 border-indigo-100 text-indigo-600', teal: 'bg-brand-50 border-brand-100 text-brand-600', amber: 'bg-amber-50 border-amber-100 text-amber-600', violet: 'bg-violet-50 border-violet-100 text-violet-600' }
          return (
            <div key={stat.label} className="bg-white border border-slate-200/80 rounded-2xl p-5 shadow-sm relative overflow-hidden">
              <div className={`absolute right-4 top-4 p-2 rounded-xl border ${colorMap[stat.color]}`}><Icon className="w-4 h-4" /></div>
              <p className="text-[10px] font-bold text-slate-400 uppercase tracking-wider">{stat.label}</p>
              <h3 className="text-2xl font-black text-slate-800 mt-2">{stat.value}</h3>
            </div>
          )
        })}
      </div>

      <div className="flex flex-col lg:flex-row gap-3">
        <div className="relative flex-1">
          <Search className="absolute left-3.5 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400" />
          <input
            type="text"
            value={search}
            onChange={e => setSearch(e.target.value)}
            placeholder={canSearchAllContacts
              ? 'Search by name, email or phone...'
              : 'Search by name (phone/email only for customers you added)...'}
            className="w-full pl-10 pr-4 py-2.5 text-sm border border-slate-200 rounded-xl bg-white shadow-sm focus:outline-none focus:ring-2 focus:ring-brand-500/20 focus:border-brand-400"
          />
        </div>
        <div className="flex flex-wrap gap-2">
          {['all', 'active', 'inactive'].map(f => (
            <button key={f} type="button" onClick={() => setStatusFilter(f)}
              className={`px-3 py-2 text-xs font-bold uppercase rounded-xl border transition cursor-pointer ${statusFilter === f ? 'bg-brand-600 text-white border-brand-600' : 'bg-white text-slate-500 border-slate-200'}`}>
              {f === 'all' ? 'All' : f}
            </button>
          ))}
          <select
            value={tagFilter}
            onChange={(e) => setTagFilter(e.target.value)}
            className="px-3 py-2 text-xs font-bold rounded-xl border border-slate-200 bg-white text-slate-600 focus:outline-none focus:ring-2 focus:ring-brand-500/20"
          >
            <option value="">All tags</option>
            {tags.map((tag) => (
              <option key={tag.id} value={tag.id}>{tag.name}</option>
            ))}
          </select>
          <select
            value={segmentFilter}
            onChange={(e) => setSegmentFilter(e.target.value)}
            className="px-3 py-2 text-xs font-bold rounded-xl border border-slate-200 bg-white text-slate-600 focus:outline-none focus:ring-2 focus:ring-brand-500/20"
          >
            {SEGMENT_OPTIONS.map((opt) => (
              <option key={opt.value} value={opt.value}>{opt.label}</option>
            ))}
          </select>
        </div>
      </div>

      {isLoading ? (
        <div className="flex justify-center py-20 text-slate-400">
          <Loader2 className="w-5 h-5 animate-spin mr-2" />
          Loading customers...
        </div>
      ) : customers.length > 0 ? (
        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-5">
          {customers.map(customer => (
            <CustomerCard
              key={customer.id}
              customer={customer}
              onClick={(c) => setSelectedCustomerId(c.id)}
              showSalons={isSystemAdmin}
            />
          ))}
        </div>
      ) : (
        <div className="flex flex-col items-center justify-center py-16 text-slate-400 border border-dashed border-slate-200 rounded-2xl bg-slate-50/50">
          <Users className="w-10 h-10 mb-3 text-slate-300" />
          <p className="font-bold text-slate-500">No customers found</p>
          <p className="text-sm mt-1">
            {canManage ? 'Add a customer or create one via appointments.' : 'Customers appear when appointments are booked.'}
          </p>
          {canManage && (
            <BaseButton className="mt-4" leftIcon={Plus} onClick={() => { setEditingCustomer(null); setFormOpen(true) }}>
              Add Customer
            </BaseButton>
          )}
        </div>
      )}

      {selectedCustomerId && (
        <CustomerDetailModal
          customerId={selectedCustomerId}
          onClose={() => setSelectedCustomerId(null)}
          onEdit={handleEditFromDetail}
          onDeleted={() => {
            setToast({ message: 'Customer deleted successfully.', type: 'success' })
            void loadCustomers()
          }}
          canManage={canManage}
          showSalons={isSystemAdmin}
          fmt={fmt}
        />
      )}

      <CustomerFormModal
        open={formOpen}
        customer={editingCustomer}
        saloons={saloons}
        tags={tags}
        isSystemAdmin={isSystemAdmin}
        defaultSaloonId={isSystemAdmin ? saloonFilter : auth.user?.saloon_id}
        onClose={() => { setFormOpen(false); setEditingCustomer(null) }}
        onSaved={handleSaved}
        onTagCreated={(tag) => setTags((prev) => [...prev, tag])}
      />

      {toast && (
        <Toast message={toast.message} type={toast.type} onDismiss={() => setToast(null)} />
      )}
    </div>
  )
}
