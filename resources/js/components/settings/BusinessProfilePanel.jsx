import React, { useCallback, useEffect, useState } from 'react'
import { Building2, Loader2, Save } from 'lucide-react'
import BaseButton from '../ui/BaseButton.jsx'
import BaseInput from '../ui/BaseInput.jsx'
import { fetchSalonBusinessProfile, updateSalonBusinessProfile } from '../../services/salonSettingsService.js'
import { pushToast } from '../../stores/toast.js'

export default function BusinessProfilePanel({ canUpdate = true }) {
  const [loading, setLoading] = useState(true)
  const [saving, setSaving] = useState(false)
  const [error, setError] = useState('')
  const [errors, setErrors] = useState({})
  const [form, setForm] = useState({
    name: '',
    phone: '',
    whatsapp: '',
    gst_number: '',
    address: '',
    city: '',
    state: '',
  })

  const loadData = useCallback(async () => {
    setLoading(true)
    setError('')
    try {
      const payload = await fetchSalonBusinessProfile()
      setForm({
        name: payload.name || '',
        phone: payload.phone || '',
        whatsapp: payload.whatsapp || '',
        gst_number: payload.gst_number || '',
        address: payload.address || '',
        city: payload.city || '',
        state: payload.state || '',
      })
    } catch (err) {
      setError(err?.response?.data?.message || 'Unable to load business profile.')
    } finally {
      setLoading(false)
    }
  }, [])

  useEffect(() => {
    void loadData()
  }, [loadData])

  const updateField = (key, value) => {
    setForm((previous) => ({ ...previous, [key]: value }))
    setErrors((previous) => ({ ...previous, [key]: '' }))
  }

  const save = async (event) => {
    event.preventDefault()
    if (!canUpdate) return

    setSaving(true)
    setErrors({})
    try {
      await updateSalonBusinessProfile(form)
      pushToast('Business profile updated successfully.', 'success')
    } catch (err) {
      const serverErrors = err?.response?.data?.errors || {}
      const nextErrors = {}
      Object.entries(serverErrors).forEach(([field, messages]) => {
        nextErrors[field] = Array.isArray(messages) ? messages[0] : messages
      })
      setErrors(nextErrors)
      pushToast(err?.response?.data?.message || 'Unable to save business profile.', 'error')
    } finally {
      setSaving(false)
    }
  }

  if (loading) {
    return (
      <div className="flex items-center gap-2 rounded-xl border border-slate-200 bg-slate-50 px-4 py-8 text-sm text-slate-500">
        <Loader2 className="h-4 w-4 animate-spin" />
        Loading business profile…
      </div>
    )
  }

  return (
    <form onSubmit={save} className="space-y-6">
      <div className="flex items-start gap-3">
        <div className="rounded-xl border border-brand-100 bg-brand-50 p-2.5 text-brand-600">
          <Building2 className="h-5 w-5" />
        </div>
        <div>
          <h3 className="text-base font-bold text-slate-900">Business Profile</h3>
          <p className="mt-1 text-xs text-slate-500">
            Legal and contact details for your salon. Used on invoices, receipts, and customer communications.
          </p>
        </div>
      </div>

      {error ? (
        <div className="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-xs text-rose-700">{error}</div>
      ) : null}

      {!canUpdate ? (
        <div className="rounded-xl border border-sky-200 bg-sky-50 px-4 py-3 text-xs text-sky-900">
          Business profile is view-only for your role or subscription state. Contact your salon owner or renew at Billing to make changes.
        </div>
      ) : null}

      <div className="grid gap-4 md:grid-cols-2">
        <div className="md:col-span-2">
          <BaseInput
            label="Business / Salon Name"
            modelValue={form.name}
            onUpdateModelValue={(value) => updateField('name', value)}
            error={errors.name}
            required
            disabled={!canUpdate}
          />
        </div>
        <BaseInput
          label="Phone"
          modelValue={form.phone}
          onUpdateModelValue={(value) => updateField('phone', value)}
          error={errors.phone}
          disabled={!canUpdate}
        />
        <BaseInput
          label="WhatsApp"
          modelValue={form.whatsapp}
          onUpdateModelValue={(value) => updateField('whatsapp', value)}
          error={errors.whatsapp}
          disabled={!canUpdate}
        />
        <BaseInput
          label="GST Number"
          modelValue={form.gst_number}
          onUpdateModelValue={(value) => updateField('gst_number', value)}
          error={errors.gst_number}
          disabled={!canUpdate}
        />
        <div className="md:col-span-2">
          <BaseInput
            label="Address"
            modelValue={form.address}
            onUpdateModelValue={(value) => updateField('address', value)}
            error={errors.address}
            disabled={!canUpdate}
          />
        </div>
        <BaseInput
          label="City"
          modelValue={form.city}
          onUpdateModelValue={(value) => updateField('city', value)}
          error={errors.city}
          disabled={!canUpdate}
        />
        <BaseInput
          label="State"
          modelValue={form.state}
          onUpdateModelValue={(value) => updateField('state', value)}
          error={errors.state}
          disabled={!canUpdate}
        />
      </div>

      {canUpdate ? (
        <div className="flex justify-end border-t border-slate-100 pt-5">
          <BaseButton type="submit" loading={saving} leftIcon={saving ? Loader2 : Save}>
            Save Business Profile
          </BaseButton>
        </div>
      ) : null}
    </form>
  )
}
