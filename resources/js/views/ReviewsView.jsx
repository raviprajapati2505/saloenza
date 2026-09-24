import React, { useCallback, useEffect, useState } from 'react'
import { RefreshCw, Save, Send, Star } from 'lucide-react'
import PageHeader from '../components/ui/PageHeader.jsx'
import BaseButton from '../components/ui/BaseButton.jsx'
import BaseInput from '../components/ui/BaseInput.jsx'
import BaseBadge from '../components/ui/BaseBadge.jsx'
import { useAuthStore } from '../stores/auth'
import { TENANT_PERMISSIONS } from '../lib/tenantPermissions.js'
import { canMutate, subscriptionPageSubtitle } from '../lib/subscriptionModules.js'
import { pushToast } from '../stores/toast.js'
import {
  fetchReviewRequests,
  fetchReviewSchedule,
  fetchReviewSettings,
  fetchServiceRatings,
  sendReviewRequest,
  updateReviewSettings,
} from '../services/reviewService.js'

export default function ReviewsView() {
  const auth = useAuthStore()
  const canManage = canMutate(auth, TENANT_PERMISSIONS.REVIEWS_MANAGE)

  const [loading, setLoading] = useState(true)
  const [error, setError] = useState('')
  const [settings, setSettings] = useState({ google_review_url: '' })
  const [schedule, setSchedule] = useState([])
  const [requests, setRequests] = useState([])
  const [ratings, setRatings] = useState([])
  const [saving, setSaving] = useState(false)

  const load = useCallback(async () => {
    setLoading(true)
    setError('')
    try {
      const [settingsData, scheduleRows, requestRows, ratingRows] = await Promise.all([
        fetchReviewSettings(),
        fetchReviewSchedule(),
        fetchReviewRequests(),
        fetchServiceRatings(),
      ])
      setSettings({ google_review_url: settingsData?.google_review_url || '' })
      setSchedule(scheduleRows)
      setRequests(requestRows)
      setRatings(ratingRows)
    } catch (err) {
      setError(err?.response?.data?.message || 'Unable to load reviews.')
    } finally {
      setLoading(false)
    }
  }, [])

  useEffect(() => {
    void load()
  }, [load])

  const handleSaveSettings = async () => {
    setSaving(true)
    try {
      const next = await updateReviewSettings({ google_review_url: settings.google_review_url.trim() || null })
      setSettings({ google_review_url: next?.google_review_url || '' })
      pushToast('Review settings saved.', 'success')
    } catch (err) {
      pushToast(err?.response?.data?.message || 'Unable to save settings.', 'error')
    } finally {
      setSaving(false)
    }
  }

  const handleSend = async (appointmentId) => {
    setSaving(true)
    try {
      await sendReviewRequest({ appointment_id: appointmentId })
      pushToast('Review request sent.', 'success')
      await load()
    } catch (err) {
      pushToast(err?.response?.data?.message || 'Unable to send review request.', 'error')
    } finally {
      setSaving(false)
    }
  }

  return (
    <div className="space-y-6">
      <PageHeader
        title="Reviews"
        subtitle={subscriptionPageSubtitle(auth, 'Google review URL, schedule, send requests, and ratings.')}
        actions={(
          <BaseButton variant="secondary" size="sm" leftIcon={RefreshCw} loading={loading} onClick={() => void load()}>
            Refresh
          </BaseButton>
        )}
      />

      {error ? <div className="rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-700">{error}</div> : null}

      <div className="rounded-[18px] border border-slate-200 bg-white p-5 shadow-[0_8px_30px_rgb(0,0,0,0.04)]">
        <h3 className="text-base font-semibold text-slate-900">Settings</h3>
        <div className="mt-4 flex flex-wrap items-end gap-3">
          <div className="min-w-[260px] flex-1">
            <BaseInput
              label="Google review URL"
              value={settings.google_review_url}
              disabled={!canManage}
              onChange={(e) => setSettings({ google_review_url: e.target.value })}
              placeholder="https://g.page/r/..."
            />
          </div>
          {canManage ? (
            <BaseButton leftIcon={Save} loading={saving} onClick={() => void handleSaveSettings()}>Save</BaseButton>
          ) : null}
        </div>
      </div>

      <div className="overflow-hidden rounded-[18px] border border-slate-200 bg-white shadow-[0_8px_30px_rgb(0,0,0,0.04)]">
        <div className="border-b border-slate-100 px-5 py-4">
          <h3 className="text-base font-semibold text-slate-900">Schedule candidates</h3>
        </div>
        {loading ? <div className="p-5 text-sm text-slate-500">Loading…</div> : null}
        {!loading && schedule.length === 0 ? <p className="px-5 py-8 text-center text-sm text-slate-500">No candidates right now.</p> : null}
        {schedule.length > 0 ? (
          <ul className="divide-y divide-slate-100">
            {schedule.map((row) => (
              <li key={row.appointment_id || row.id} className="flex flex-wrap items-center justify-between gap-3 px-5 py-4">
                <div>
                  <p className="font-medium text-slate-900">{row.customer?.name || 'Customer'}</p>
                  <p className="text-xs text-slate-500">
                    {row.starts_at || '—'} · {row.staff?.name || '—'} · {row.branch?.name || '—'}
                  </p>
                </div>
                {canManage ? (
                  <BaseButton size="sm" leftIcon={Send} loading={saving} onClick={() => void handleSend(row.appointment_id || row.id)}>
                    Send
                  </BaseButton>
                ) : null}
              </li>
            ))}
          </ul>
        ) : null}
      </div>

      <div className="grid gap-4 lg:grid-cols-2">
        <div className="overflow-hidden rounded-[18px] border border-slate-200 bg-white shadow-[0_8px_30px_rgb(0,0,0,0.04)]">
          <div className="border-b border-slate-100 px-5 py-4">
            <h3 className="text-base font-semibold text-slate-900">Review requests</h3>
          </div>
          {requests.length === 0 ? <p className="px-5 py-8 text-center text-sm text-slate-500">No requests yet.</p> : null}
          {requests.length > 0 ? (
            <ul className="divide-y divide-slate-100">
              {requests.map((row) => (
                <li key={row.id} className="flex items-center justify-between gap-3 px-5 py-3 text-sm">
                  <div>
                    <p className="font-medium text-slate-900">{row.customer?.name || row.appointment_id}</p>
                    <p className="text-xs text-slate-500">{row.sent_at || row.created_at || '—'}</p>
                  </div>
                  <BaseBadge size="sm">{row.status || 'pending'}</BaseBadge>
                </li>
              ))}
            </ul>
          ) : null}
        </div>

        <div className="overflow-hidden rounded-[18px] border border-slate-200 bg-white shadow-[0_8px_30px_rgb(0,0,0,0.04)]">
          <div className="border-b border-slate-100 px-5 py-4">
            <h3 className="text-base font-semibold text-slate-900">Ratings</h3>
          </div>
          {ratings.length === 0 ? <p className="px-5 py-8 text-center text-sm text-slate-500">No ratings yet.</p> : null}
          {ratings.length > 0 ? (
            <ul className="divide-y divide-slate-100">
              {ratings.map((row) => (
                <li key={row.id} className="px-5 py-3 text-sm">
                  <div className="flex items-center gap-2">
                    <Star className="h-4 w-4 text-amber-500" />
                    <span className="font-semibold text-slate-900">{row.rating}/5</span>
                    <span className="text-slate-600">{row.customer?.name || row.service?.name || ''}</span>
                  </div>
                  {row.comment ? <p className="mt-1 text-xs text-slate-500">{row.comment}</p> : null}
                </li>
              ))}
            </ul>
          ) : null}
        </div>
      </div>
    </div>
  )
}
