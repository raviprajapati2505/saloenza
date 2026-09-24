import React, { useCallback, useEffect, useState } from 'react'
import { Check, Plus, RefreshCw, X } from 'lucide-react'
import PageHeader from '../components/ui/PageHeader.jsx'
import BaseButton from '../components/ui/BaseButton.jsx'
import BaseInput from '../components/ui/BaseInput.jsx'
import BaseSelect from '../components/ui/BaseSelect.jsx'
import BaseBadge from '../components/ui/BaseBadge.jsx'
import { useAuthStore } from '../stores/auth'
import { TENANT_PERMISSIONS } from '../lib/tenantPermissions.js'
import { canMutate, subscriptionPageSubtitle } from '../lib/subscriptionModules.js'
import { fetchMasterList } from '../lib/apiHelpers'
import { pushToast } from '../stores/toast.js'
import {
  acceptWaitlistOffer,
  createWaitlistEntry,
  createWaitlistOffer,
  declineWaitlistOffer,
  deleteWaitlistEntry,
  fetchWaitlist,
} from '../services/waitlistService.js'

export default function WaitlistView() {
  const auth = useAuthStore()
  const canManage = canMutate(auth, TENANT_PERMISSIONS.WAITLIST_MANAGE)

  const [loading, setLoading] = useState(true)
  const [error, setError] = useState('')
  const [entries, setEntries] = useState([])
  const [customers, setCustomers] = useState([])
  const [services, setServices] = useState([])
  const [saving, setSaving] = useState(false)

  const [entryForm, setEntryForm] = useState({ customer_id: '', service_id: '', notes: '', earliest_at: '' })
  const [offerForm, setOfferForm] = useState({ entry_id: '', slot_starts_at: '', slot_ends_at: '' })

  const load = useCallback(async () => {
    setLoading(true)
    setError('')
    try {
      const [rows, customerRows, serviceRows] = await Promise.all([
        fetchWaitlist({ status: 'waiting' }),
        fetchMasterList('/v1/customers', 'customers').catch(() => []),
        fetchMasterList('/v1/services', 'services').catch(() => []),
      ])
      setEntries(rows)
      setCustomers(customerRows || [])
      setServices(serviceRows || [])
    } catch (err) {
      setError(err?.response?.data?.message || 'Unable to load waitlist.')
    } finally {
      setLoading(false)
    }
  }, [])

  useEffect(() => {
    void load()
  }, [load])

  const handleAdd = async () => {
    if (!entryForm.customer_id) return
    setSaving(true)
    try {
      await createWaitlistEntry({
        customer_id: Number(entryForm.customer_id),
        service_id: entryForm.service_id ? Number(entryForm.service_id) : undefined,
        notes: entryForm.notes || undefined,
        earliest_at: entryForm.earliest_at || undefined,
      })
      pushToast('Added to waitlist.', 'success')
      setEntryForm({ customer_id: '', service_id: '', notes: '', earliest_at: '' })
      await load()
    } catch (err) {
      pushToast(err?.response?.data?.message || 'Unable to add entry.', 'error')
    } finally {
      setSaving(false)
    }
  }

  const handleOffer = async () => {
    if (!offerForm.entry_id || !offerForm.slot_starts_at) return
    setSaving(true)
    try {
      await createWaitlistOffer(offerForm.entry_id, {
        slot_starts_at: offerForm.slot_starts_at,
        slot_ends_at: offerForm.slot_ends_at || undefined,
      })
      pushToast('Offer created.', 'success')
      setOfferForm({ entry_id: '', slot_starts_at: '', slot_ends_at: '' })
      await load()
    } catch (err) {
      pushToast(err?.response?.data?.message || 'Unable to create offer.', 'error')
    } finally {
      setSaving(false)
    }
  }

  const handleAccept = async (offerId) => {
    setSaving(true)
    try {
      await acceptWaitlistOffer(offerId)
      pushToast('Offer accepted.', 'success')
      await load()
    } catch (err) {
      pushToast(err?.response?.data?.message || 'Accept failed.', 'error')
    } finally {
      setSaving(false)
    }
  }

  const handleDecline = async (offerId) => {
    setSaving(true)
    try {
      await declineWaitlistOffer(offerId)
      pushToast('Offer declined.', 'success')
      await load()
    } catch (err) {
      pushToast(err?.response?.data?.message || 'Decline failed.', 'error')
    } finally {
      setSaving(false)
    }
  }

  const handleDelete = async (id) => {
    setSaving(true)
    try {
      await deleteWaitlistEntry(id)
      pushToast('Entry removed.', 'success')
      await load()
    } catch (err) {
      pushToast(err?.response?.data?.message || 'Unable to remove entry.', 'error')
    } finally {
      setSaving(false)
    }
  }

  return (
    <div className="space-y-6">
      <PageHeader
        title="Waitlist"
        subtitle={subscriptionPageSubtitle(auth, 'Queue waiting customers and send slot offers.')}
        actions={(
          <BaseButton variant="secondary" size="sm" leftIcon={RefreshCw} loading={loading} onClick={() => void load()}>
            Refresh
          </BaseButton>
        )}
      />

      {error ? <div className="rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-700">{error}</div> : null}

      {canManage ? (
        <>
          <div className="rounded-[18px] border border-slate-200 bg-white p-5 shadow-[0_8px_30px_rgb(0,0,0,0.04)]">
            <h3 className="text-base font-semibold text-slate-900">Add entry</h3>
            <div className="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
              <BaseSelect
                label="Customer"
                value={entryForm.customer_id}
                onChange={(e) => setEntryForm((f) => ({ ...f, customer_id: e.target.value }))}
                options={[{ value: '', label: 'Select customer' }, ...customers.map((c) => ({ value: String(c.id), label: c.name }))]}
              />
              <BaseSelect
                label="Service"
                value={entryForm.service_id}
                onChange={(e) => setEntryForm((f) => ({ ...f, service_id: e.target.value }))}
                options={[{ value: '', label: 'Any' }, ...services.map((s) => ({ value: String(s.id), label: s.name }))]}
              />
              <BaseInput label="Earliest" type="datetime-local" value={entryForm.earliest_at} onChange={(e) => setEntryForm((f) => ({ ...f, earliest_at: e.target.value }))} />
              <BaseInput label="Notes" value={entryForm.notes} onChange={(e) => setEntryForm((f) => ({ ...f, notes: e.target.value }))} />
              <div className="flex items-end">
                <BaseButton leftIcon={Plus} loading={saving} onClick={() => void handleAdd()}>Add</BaseButton>
              </div>
            </div>
          </div>

          <div className="rounded-[18px] border border-slate-200 bg-white p-5 shadow-[0_8px_30px_rgb(0,0,0,0.04)]">
            <h3 className="text-base font-semibold text-slate-900">Create offer</h3>
            <div className="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
              <BaseSelect
                label="Waitlist entry"
                value={offerForm.entry_id}
                onChange={(e) => setOfferForm((f) => ({ ...f, entry_id: e.target.value }))}
                options={[
                  { value: '', label: 'Select entry' },
                  ...entries.map((row) => ({
                    value: String(row.id),
                    label: row.customer?.name || `Entry #${row.id}`,
                  })),
                ]}
              />
              <BaseInput label="Slot starts" type="datetime-local" value={offerForm.slot_starts_at} onChange={(e) => setOfferForm((f) => ({ ...f, slot_starts_at: e.target.value }))} />
              <BaseInput label="Slot ends" type="datetime-local" value={offerForm.slot_ends_at} onChange={(e) => setOfferForm((f) => ({ ...f, slot_ends_at: e.target.value }))} />
              <div className="flex items-end">
                <BaseButton loading={saving} onClick={() => void handleOffer()}>Offer slot</BaseButton>
              </div>
            </div>
          </div>
        </>
      ) : null}

      <div className="space-y-4">
        {loading ? Array.from({ length: 2 }).map((_, i) => <div key={i} className="h-24 animate-pulse rounded-[18px] bg-slate-100" />) : null}
        {!loading && entries.length === 0 ? (
          <div className="rounded-[18px] border border-dashed border-slate-200 bg-white px-6 py-12 text-center text-sm text-slate-500">
            No waiting customers.
          </div>
        ) : null}
        {!loading
          ? entries.map((entry) => (
            <div key={entry.id} className="rounded-[18px] border border-slate-200 bg-white p-5 shadow-[0_8px_30px_rgb(0,0,0,0.04)]">
              <div className="flex flex-wrap items-start justify-between gap-3">
                <div>
                  <div className="flex flex-wrap items-center gap-2">
                    <h3 className="text-base font-semibold text-slate-900">{entry.customer?.name || `Customer #${entry.customer_id}`}</h3>
                    <BaseBadge size="sm" variant="info">{entry.status || 'waiting'}</BaseBadge>
                  </div>
                  <p className="mt-1 text-sm text-slate-500">
                    {entry.service?.name || 'Any service'}
                    {entry.earliest_at ? ` · from ${entry.earliest_at}` : ''}
                    {entry.notes ? ` · ${entry.notes}` : ''}
                  </p>
                </div>
                {canManage ? (
                  <BaseButton size="sm" variant="secondary" onClick={() => void handleDelete(entry.id)}>Remove</BaseButton>
                ) : null}
              </div>
              {(entry.offers || []).length > 0 ? (
                <ul className="mt-4 space-y-2 border-t border-slate-100 pt-4">
                  {entry.offers.map((offer) => (
                    <li key={offer.id} className="flex flex-wrap items-center justify-between gap-2 rounded-xl bg-slate-50 px-3 py-2 text-sm">
                      <span>
                        Offer {offer.slot_starts_at}
                        {offer.slot_ends_at ? ` → ${offer.slot_ends_at}` : ''} · {offer.status || 'pending'}
                      </span>
                      {canManage && (offer.status === 'pending' || !offer.status) ? (
                        <div className="flex gap-2">
                          <BaseButton size="sm" leftIcon={Check} loading={saving} onClick={() => void handleAccept(offer.id)}>Accept</BaseButton>
                          <BaseButton size="sm" variant="secondary" leftIcon={X} loading={saving} onClick={() => void handleDecline(offer.id)}>Decline</BaseButton>
                        </div>
                      ) : null}
                    </li>
                  ))}
                </ul>
              ) : null}
            </div>
          ))
          : null}
      </div>
    </div>
  )
}
