import React, { useCallback, useEffect, useState } from 'react'
import { Copy, Link2, Plus, RefreshCw } from 'lucide-react'
import PageHeader from '../components/ui/PageHeader.jsx'
import BaseButton from '../components/ui/BaseButton.jsx'
import BaseInput from '../components/ui/BaseInput.jsx'
import BaseBadge from '../components/ui/BaseBadge.jsx'
import { useAuthStore } from '../stores/auth'
import { subscriptionPageSubtitle, canMutate } from '../lib/subscriptionModules.js'
import {
  createBookingLink,
  fetchBookingLinks,
  updateBookingLink,
} from '../services/bookingLinkService.js'
import { pushToast } from '../stores/toast.js'

export default function BookingLinksView() {
  const auth = useAuthStore()
  const canManage = canMutate(auth, 'booking_links.manage')
  const [loading, setLoading] = useState(true)
  const [links, setLinks] = useState([])
  const [label, setLabel] = useState('Customer booking link')
  const [creating, setCreating] = useState(false)

  const load = useCallback(async () => {
    setLoading(true)
    try {
      setLinks(await fetchBookingLinks())
    } catch (err) {
      pushToast(err?.response?.data?.message || 'Unable to load booking links.', 'error')
      setLinks([])
    } finally {
      setLoading(false)
    }
  }, [])

  useEffect(() => {
    void load()
  }, [load])

  const copyLink = async (link) => {
    const url = link.url || `${window.location.origin}${link.path}`
    try {
      await navigator.clipboard.writeText(url)
      pushToast('Booking link copied to clipboard.', 'success')
    } catch {
      pushToast(url, 'info')
    }
  }

  const handleCreate = async () => {
    setCreating(true)
    try {
      const link = await createBookingLink({ label: label.trim() || undefined })
      pushToast('Booking link created.', 'success')
      setLinks((current) => [link, ...current])
    } catch (err) {
      pushToast(err?.response?.data?.message || 'Unable to create booking link.', 'error')
    } finally {
      setCreating(false)
    }
  }

  const toggleActive = async (link) => {
    try {
      const updated = await updateBookingLink(link.id, { is_active: !link.is_active })
      setLinks((current) => current.map((row) => (row.id === link.id ? updated : row)))
      pushToast(updated.is_active ? 'Link activated.' : 'Link disabled.', 'success')
    } catch (err) {
      pushToast(err?.response?.data?.message || 'Unable to update booking link.', 'error')
    }
  }

  return (
    <div className="space-y-6">
      <PageHeader
        title="Customer booking links"
        subtitle={subscriptionPageSubtitle(
          auth,
          'Generate a link to send when customers call or message asking to book online.',
        )}
        actions={(
          <BaseButton variant="secondary" size="sm" leftIcon={RefreshCw} onClick={() => void load()} loading={loading}>
            Refresh
          </BaseButton>
        )}
      />

      {canManage ? (
        <div className="rounded-[18px] border border-slate-200 bg-white p-5 shadow-[0_8px_30px_rgb(0,0,0,0.04)]">
          <h3 className="text-base font-semibold text-slate-900">Create a new link</h3>
          <p className="mt-1 text-sm text-slate-500">Customers can book without logging in.</p>
          <div className="mt-4 flex flex-col gap-3 sm:flex-row">
            <BaseInput
              label="Link label"
              value={label}
              onChange={(event) => setLabel(event.target.value)}
              placeholder="Front desk booking link"
            />
            <div className="flex items-end">
              <BaseButton leftIcon={Plus} loading={creating} onClick={() => void handleCreate()}>
                Generate link
              </BaseButton>
            </div>
          </div>
        </div>
      ) : null}

      <div className="space-y-4">
        {loading ? (
          Array.from({ length: 2 }).map((_, index) => (
            <div key={index} className="h-28 animate-pulse rounded-[18px] bg-slate-100" />
          ))
        ) : null}

        {!loading && links.length === 0 ? (
          <div className="rounded-[18px] border border-dashed border-slate-200 bg-white px-6 py-12 text-center text-sm text-slate-500">
            No booking links yet. Create one to share with customers.
          </div>
        ) : null}

        {!loading
          ? links.map((link) => (
              <div key={link.id} className="rounded-[18px] border border-slate-200 bg-white p-5 shadow-[0_8px_30px_rgb(0,0,0,0.04)]">
                <div className="flex flex-wrap items-start justify-between gap-3">
                  <div>
                    <div className="flex flex-wrap items-center gap-2">
                      <Link2 className="h-5 w-5 text-brand-500" />
                      <h3 className="text-base font-semibold text-slate-900">{link.label || 'Booking link'}</h3>
                      <BaseBadge variant={link.is_active ? 'success' : 'default'} size="sm">
                        {link.is_active ? 'Active' : 'Disabled'}
                      </BaseBadge>
                    </div>
                    {link.branch?.name ? (
                      <p className="mt-1 text-sm text-slate-500">Branch: {link.branch.name}</p>
                    ) : null}
                    <p className="mt-3 break-all rounded-xl bg-slate-50 px-3 py-2 font-mono text-xs text-slate-700">
                      {link.url || `${window.location.origin}${link.path}`}
                    </p>
                  </div>
                  <div className="flex flex-wrap gap-2">
                    <BaseButton variant="secondary" size="sm" leftIcon={Copy} onClick={() => void copyLink(link)}>
                      Copy link
                    </BaseButton>
                    {canManage ? (
                      <BaseButton variant="secondary" size="sm" onClick={() => void toggleActive(link)}>
                        {link.is_active ? 'Disable' : 'Enable'}
                      </BaseButton>
                    ) : null}
                  </div>
                </div>
              </div>
            ))
          : null}
      </div>
    </div>
  )
}
