import React, { useCallback, useEffect, useState } from 'react'
import { Copy, Download, Link2, Plus, QrCode, RefreshCw } from 'lucide-react'
import PageHeader from '../components/ui/PageHeader.jsx'
import BaseButton from '../components/ui/BaseButton.jsx'
import BaseInput from '../components/ui/BaseInput.jsx'
import BaseSelect from '../components/ui/BaseSelect.jsx'
import BaseBadge from '../components/ui/BaseBadge.jsx'
import { useAuthStore } from '../stores/auth'
import { subscriptionPageSubtitle, canMutate } from '../lib/subscriptionModules.js'
import {
  createBookingLink,
  fetchBookingLinks,
  updateBookingLink,
} from '../services/bookingLinkService.js'
import { fetchBranches } from '../services/branchService.js'
import { pushToast } from '../stores/toast.js'

function bookingLinkUrl(link) {
  return link.url || `${window.location.origin}${link.path}`
}

function qrImageUrl(url) {
  return `https://api.qrserver.com/v1/create-qr-code/?size=180x180&data=${encodeURIComponent(url)}`
}

export default function BookingLinksView() {
  const auth = useAuthStore()
  const canManage = canMutate(auth, 'booking_links.manage')
  const [loading, setLoading] = useState(true)
  const [links, setLinks] = useState([])
  const [branches, setBranches] = useState([])
  const [label, setLabel] = useState('Customer booking link')
  const [branchId, setBranchId] = useState('')
  const [creating, setCreating] = useState(false)

  const load = useCallback(async () => {
    setLoading(true)
    try {
      const [fetchedLinks, branchPayload] = await Promise.all([
        fetchBookingLinks(),
        fetchBranches().catch(() => ({ branches: [] })),
      ])
      setLinks(fetchedLinks)
      setBranches(branchPayload?.branches ?? [])
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
    const url = bookingLinkUrl(link)
    try {
      await navigator.clipboard.writeText(url)
      pushToast('Booking link copied to clipboard.', 'success')
    } catch {
      pushToast(url, 'info')
    }
  }

  const downloadQr = (link) => {
    const url = bookingLinkUrl(link)
    const anchor = document.createElement('a')
    anchor.href = qrImageUrl(url)
    anchor.download = `booking-qr-${link.token || link.id}.png`
    anchor.target = '_blank'
    anchor.rel = 'noopener noreferrer'
    document.body.appendChild(anchor)
    anchor.click()
    document.body.removeChild(anchor)
  }

  const handleCreate = async () => {
    setCreating(true)
    try {
      const link = await createBookingLink({
        label: label.trim() || undefined,
        branch_id: branchId ? Number(branchId) : undefined,
      })
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
          'Generate a link or QR code customers can scan to book online.',
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
          <p className="mt-1 text-sm text-slate-500">
            Leave branch empty for a salon-wide link, or pick a branch for a branch-specific booking page.
          </p>
          <div className="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
            <BaseInput
              label="Link label"
              value={label}
              onChange={(event) => setLabel(event.target.value)}
              placeholder="Front desk booking link"
            />
            <BaseSelect
              label="Branch (optional)"
              value={branchId}
              onChange={(event) => setBranchId(event.target.value)}
              options={[
                { value: '', label: 'All branches (salon-wide)' },
                ...branches.map((branch) => ({
                  value: String(branch.id),
                  label: branch.name || branch.branch_name,
                })),
              ]}
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
          ? links.map((link) => {
              const url = bookingLinkUrl(link)
              return (
                <div key={link.id} className="rounded-[18px] border border-slate-200 bg-white p-5 shadow-[0_8px_30px_rgb(0,0,0,0.04)]">
                  <div className="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                    <div className="min-w-0 flex-1">
                      <div className="flex flex-wrap items-center gap-2">
                        <Link2 className="h-5 w-5 text-brand-500" />
                        <h3 className="text-base font-semibold text-slate-900">{link.label || 'Booking link'}</h3>
                        <BaseBadge variant={link.is_active ? 'success' : 'default'} size="sm">
                          {link.is_active ? 'Active' : 'Disabled'}
                        </BaseBadge>
                        <BaseBadge variant="default" size="sm">
                          {link.branch?.name ? `Branch: ${link.branch.name}` : 'Salon-wide'}
                        </BaseBadge>
                      </div>
                      <p className="mt-3 break-all rounded-xl bg-slate-50 px-3 py-2 font-mono text-xs text-slate-700">
                        {url}
                      </p>
                      <div className="mt-3 flex flex-wrap gap-2">
                        <BaseButton variant="secondary" size="sm" leftIcon={Copy} onClick={() => void copyLink(link)}>
                          Copy link
                        </BaseButton>
                        <BaseButton variant="secondary" size="sm" leftIcon={Download} onClick={() => downloadQr(link)}>
                          Download QR
                        </BaseButton>
                        {canManage ? (
                          <BaseButton variant="secondary" size="sm" onClick={() => void toggleActive(link)}>
                            {link.is_active ? 'Disable' : 'Enable'}
                          </BaseButton>
                        ) : null}
                      </div>
                    </div>
                    <div className="flex shrink-0 flex-col items-center gap-2 rounded-2xl border border-slate-100 bg-slate-50 p-3">
                      <img
                        src={qrImageUrl(url)}
                        alt={`QR code for ${link.label || 'booking link'}`}
                        className="h-[140px] w-[140px] rounded-lg bg-white p-1"
                      />
                      <p className="inline-flex items-center gap-1 text-xs font-medium text-slate-500">
                        <QrCode className="h-3.5 w-3.5" />
                        Scan to book
                      </p>
                    </div>
                  </div>
                </div>
              )
            })
          : null}
      </div>
    </div>
  )
}
