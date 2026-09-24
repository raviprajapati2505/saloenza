import React, { useCallback, useEffect, useState } from 'react'
import { Plus, RefreshCw, Send } from 'lucide-react'
import PageHeader from '../components/ui/PageHeader.jsx'
import BaseButton from '../components/ui/BaseButton.jsx'
import BaseInput from '../components/ui/BaseInput.jsx'
import BaseSelect from '../components/ui/BaseSelect.jsx'
import BaseBadge from '../components/ui/BaseBadge.jsx'
import { useAuthStore } from '../stores/auth'
import { TENANT_PERMISSIONS } from '../lib/tenantPermissions.js'
import { canMutate, subscriptionPageSubtitle } from '../lib/subscriptionModules.js'
import { pushToast } from '../stores/toast.js'
import {
  createMarketingCampaign,
  createMarketingSegment,
  fetchMarketingCampaigns,
  fetchMarketingSegments,
  sendMarketingCampaign,
} from '../services/marketingService.js'

export default function MarketingView() {
  const auth = useAuthStore()
  const canManage = canMutate(auth, TENANT_PERMISSIONS.MARKETING_MANAGE)
  const canSend = canMutate(auth, TENANT_PERMISSIONS.MARKETING_SEND)

  const [loading, setLoading] = useState(true)
  const [error, setError] = useState('')
  const [segments, setSegments] = useState([])
  const [campaigns, setCampaigns] = useState([])
  const [saving, setSaving] = useState(false)

  const [segmentForm, setSegmentForm] = useState({
    name: '',
    type: 'dynamic',
    rule_type: 'last_visit_days',
    tag: '',
    days: '30',
  })
  const [campaignForm, setCampaignForm] = useState({
    name: '',
    segment_id: '',
    channel: 'email',
    subject: '',
    body: '',
  })

  const load = useCallback(async () => {
    setLoading(true)
    setError('')
    try {
      const [segmentRows, campaignRows] = await Promise.all([
        fetchMarketingSegments(),
        fetchMarketingCampaigns(),
      ])
      setSegments(segmentRows)
      setCampaigns(campaignRows)
    } catch (err) {
      setError(err?.response?.data?.message || 'Unable to load marketing data.')
    } finally {
      setLoading(false)
    }
  }, [])

  useEffect(() => {
    void load()
  }, [load])

  const handleCreateSegment = async () => {
    if (!segmentForm.name.trim()) return
    setSaving(true)
    try {
      const rules = segmentForm.rule_type === 'tag'
        ? { tag: segmentForm.tag.trim() }
        : { last_visit_days: Number(segmentForm.days) }
      await createMarketingSegment({
        name: segmentForm.name.trim(),
        type: segmentForm.type,
        rules,
        is_active: true,
      })
      pushToast('Segment created.', 'success')
      setSegmentForm({ name: '', type: 'dynamic', rule_type: 'last_visit_days', tag: '', days: '30' })
      await load()
    } catch (err) {
      pushToast(err?.response?.data?.message || 'Unable to create segment.', 'error')
    } finally {
      setSaving(false)
    }
  }

  const handleCreateCampaign = async () => {
    if (!campaignForm.name.trim() || !campaignForm.segment_id || !campaignForm.body.trim()) return
    setSaving(true)
    try {
      await createMarketingCampaign({
        name: campaignForm.name.trim(),
        segment_id: Number(campaignForm.segment_id),
        channel: campaignForm.channel,
        subject: campaignForm.subject || undefined,
        body: campaignForm.body.trim(),
      })
      pushToast('Campaign created.', 'success')
      setCampaignForm({ name: '', segment_id: '', channel: 'email', subject: '', body: '' })
      await load()
    } catch (err) {
      pushToast(err?.response?.data?.message || 'Unable to create campaign.', 'error')
    } finally {
      setSaving(false)
    }
  }

  const handleSend = async (id) => {
    setSaving(true)
    try {
      await sendMarketingCampaign(id)
      pushToast('Campaign sent.', 'success')
      await load()
    } catch (err) {
      pushToast(err?.response?.data?.message || 'Unable to send campaign.', 'error')
    } finally {
      setSaving(false)
    }
  }

  return (
    <div className="space-y-6">
      <PageHeader
        title="Marketing"
        subtitle={subscriptionPageSubtitle(auth, 'Segments and outbound campaigns.')}
        actions={(
          <BaseButton variant="secondary" size="sm" leftIcon={RefreshCw} loading={loading} onClick={() => void load()}>
            Refresh
          </BaseButton>
        )}
      />

      {error ? <div className="rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-700">{error}</div> : null}

      {canManage ? (
        <div className="rounded-[18px] border border-slate-200 bg-white p-5 shadow-[0_8px_30px_rgb(0,0,0,0.04)]">
          <h3 className="text-base font-semibold text-slate-900">Create segment</h3>
          <div className="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
            <BaseInput label="Name" value={segmentForm.name} onChange={(e) => setSegmentForm((f) => ({ ...f, name: e.target.value }))} />
            <BaseSelect
              label="Rule type"
              value={segmentForm.rule_type}
              onChange={(e) => setSegmentForm((f) => ({ ...f, rule_type: e.target.value }))}
              options={[
                { value: 'last_visit_days', label: 'Last visit (days)' },
                { value: 'tag', label: 'Tag' },
              ]}
            />
            {segmentForm.rule_type === 'tag' ? (
              <BaseInput label="Tag" value={segmentForm.tag} onChange={(e) => setSegmentForm((f) => ({ ...f, tag: e.target.value }))} />
            ) : (
              <BaseInput label="Days since visit" type="number" value={segmentForm.days} onChange={(e) => setSegmentForm((f) => ({ ...f, days: e.target.value }))} />
            )}
            <BaseSelect
              label="Type"
              value={segmentForm.type}
              onChange={(e) => setSegmentForm((f) => ({ ...f, type: e.target.value }))}
              options={[
                { value: 'dynamic', label: 'Dynamic' },
                { value: 'static', label: 'Static' },
              ]}
            />
            <div className="flex items-end">
              <BaseButton leftIcon={Plus} loading={saving} onClick={() => void handleCreateSegment()}>Create segment</BaseButton>
            </div>
          </div>
        </div>
      ) : null}

      <div className="overflow-hidden rounded-[18px] border border-slate-200 bg-white shadow-[0_8px_30px_rgb(0,0,0,0.04)]">
        <div className="border-b border-slate-100 px-5 py-4">
          <h3 className="text-base font-semibold text-slate-900">Segments</h3>
        </div>
        {loading ? <div className="p-5 text-sm text-slate-500">Loading…</div> : null}
        {!loading && segments.length === 0 ? <p className="px-5 py-8 text-center text-sm text-slate-500">No segments yet.</p> : null}
        {!loading && segments.length > 0 ? (
          <ul className="divide-y divide-slate-100">
            {segments.map((segment) => (
              <li key={segment.id} className="flex items-center justify-between gap-3 px-5 py-3">
                <div>
                  <p className="font-medium text-slate-900">{segment.name}</p>
                  <p className="text-xs text-slate-500">{segment.type} · est. {segment.estimated_size ?? 0}</p>
                </div>
                <BaseBadge size="sm" variant={segment.is_active ? 'success' : 'default'}>
                  {segment.is_active ? 'Active' : 'Inactive'}
                </BaseBadge>
              </li>
            ))}
          </ul>
        ) : null}
      </div>

      {canManage ? (
        <div className="rounded-[18px] border border-slate-200 bg-white p-5 shadow-[0_8px_30px_rgb(0,0,0,0.04)]">
          <h3 className="text-base font-semibold text-slate-900">Create campaign</h3>
          <div className="mt-4 grid gap-3 sm:grid-cols-2">
            <BaseInput label="Name" value={campaignForm.name} onChange={(e) => setCampaignForm((f) => ({ ...f, name: e.target.value }))} />
            <BaseSelect
              label="Segment"
              value={campaignForm.segment_id}
              onChange={(e) => setCampaignForm((f) => ({ ...f, segment_id: e.target.value }))}
              options={[{ value: '', label: 'Select segment' }, ...segments.map((s) => ({ value: String(s.id), label: s.name }))]}
            />
            <BaseSelect
              label="Channel"
              value={campaignForm.channel}
              onChange={(e) => setCampaignForm((f) => ({ ...f, channel: e.target.value }))}
              options={[
                { value: 'email', label: 'Email' },
                { value: 'sms', label: 'SMS' },
              ]}
            />
            <BaseInput label="Subject" value={campaignForm.subject} onChange={(e) => setCampaignForm((f) => ({ ...f, subject: e.target.value }))} />
            <div className="sm:col-span-2">
              <BaseInput label="Body" value={campaignForm.body} onChange={(e) => setCampaignForm((f) => ({ ...f, body: e.target.value }))} />
            </div>
            <div>
              <BaseButton leftIcon={Plus} loading={saving} onClick={() => void handleCreateCampaign()}>Create campaign</BaseButton>
            </div>
          </div>
        </div>
      ) : null}

      <div className="overflow-hidden rounded-[18px] border border-slate-200 bg-white shadow-[0_8px_30px_rgb(0,0,0,0.04)]">
        <div className="border-b border-slate-100 px-5 py-4">
          <h3 className="text-base font-semibold text-slate-900">Campaigns</h3>
        </div>
        {!loading && campaigns.length === 0 ? <p className="px-5 py-8 text-center text-sm text-slate-500">No campaigns yet.</p> : null}
        {campaigns.length > 0 ? (
          <ul className="divide-y divide-slate-100">
            {campaigns.map((campaign) => (
              <li key={campaign.id} className="flex flex-wrap items-center justify-between gap-3 px-5 py-4">
                <div>
                  <p className="font-medium text-slate-900">{campaign.name}</p>
                  <p className="text-xs text-slate-500">
                    {campaign.channel} · {campaign.segment?.name || `segment #${campaign.segment_id}`}
                    {campaign.sent_at ? ` · sent ${campaign.sent_at}` : ''}
                  </p>
                </div>
                <div className="flex items-center gap-2">
                  <BaseBadge size="sm" variant={campaign.status === 'sent' ? 'success' : 'info'}>{campaign.status || 'draft'}</BaseBadge>
                  {canSend && campaign.status !== 'sent' ? (
                    <BaseButton size="sm" variant="secondary" leftIcon={Send} loading={saving} onClick={() => void handleSend(campaign.id)}>
                      Send
                    </BaseButton>
                  ) : null}
                </div>
              </li>
            ))}
          </ul>
        ) : null}
      </div>
    </div>
  )
}
