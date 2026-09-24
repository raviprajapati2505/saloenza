import React, { useCallback, useEffect, useState } from 'react'
import { Plus, RefreshCw, Search, SlidersHorizontal, Ticket } from 'lucide-react'
import PageHeader from '../components/ui/PageHeader.jsx'
import BaseButton from '../components/ui/BaseButton.jsx'
import BaseInput from '../components/ui/BaseInput.jsx'
import BaseBadge from '../components/ui/BaseBadge.jsx'
import { useAuthStore } from '../stores/auth'
import { TENANT_PERMISSIONS } from '../lib/tenantPermissions.js'
import { canMutate, subscriptionPageSubtitle } from '../lib/subscriptionModules.js'
import { useTenantFormatter } from '../hooks/useTenantFormatter.js'
import { pushToast } from '../stores/toast.js'
import {
  adjustGiftCard,
  fetchGiftCards,
  issueGiftCard,
  lookupGiftCard,
  redeemGiftCard,
} from '../services/giftCardService.js'

export default function GiftCardsView() {
  const auth = useAuthStore()
  const fmt = useTenantFormatter()
  const canManage = canMutate(auth, TENANT_PERMISSIONS.GIFTCARDS_MANAGE)
  const canSell = canMutate(auth, TENANT_PERMISSIONS.GIFTCARDS_SELL)
  const canRedeem = canMutate(auth, TENANT_PERMISSIONS.GIFTCARDS_REDEEM)
  const canAdjust = canMutate(auth, TENANT_PERMISSIONS.GIFTCARDS_ADJUST)

  const [loading, setLoading] = useState(true)
  const [error, setError] = useState('')
  const [cards, setCards] = useState([])
  const [lookup, setLookup] = useState(null)
  const [saving, setSaving] = useState(false)

  const [issueForm, setIssueForm] = useState({ initial_balance: '', code: '', recipient_name: '' })
  const [lookupCode, setLookupCode] = useState('')
  const [redeemAmount, setRedeemAmount] = useState('')
  const [adjustForm, setAdjustForm] = useState({ amount: '', reason: '' })

  const load = useCallback(async () => {
    setLoading(true)
    setError('')
    try {
      setCards(await fetchGiftCards())
    } catch (err) {
      setError(err?.response?.data?.message || 'Unable to load gift cards.')
    } finally {
      setLoading(false)
    }
  }, [])

  useEffect(() => {
    void load()
  }, [load])

  const handleIssue = async () => {
    if (!issueForm.initial_balance) return
    setSaving(true)
    try {
      await issueGiftCard({
        initial_balance: Number(issueForm.initial_balance),
        code: issueForm.code.trim() || undefined,
        recipient_name: issueForm.recipient_name.trim() || undefined,
      })
      pushToast('Gift card issued.', 'success')
      setIssueForm({ initial_balance: '', code: '', recipient_name: '' })
      await load()
    } catch (err) {
      pushToast(err?.response?.data?.message || 'Unable to issue gift card.', 'error')
    } finally {
      setSaving(false)
    }
  }

  const handleLookup = async () => {
    if (!lookupCode.trim()) return
    setSaving(true)
    try {
      const card = await lookupGiftCard(lookupCode.trim())
      setLookup(card)
      if (!card) pushToast('No gift card found for that code.', 'info')
    } catch (err) {
      setLookup(null)
      pushToast(err?.response?.data?.message || 'Lookup failed.', 'error')
    } finally {
      setSaving(false)
    }
  }

  const handleRedeem = async () => {
    if (!lookup?.id || !redeemAmount) return
    setSaving(true)
    try {
      const card = await redeemGiftCard(lookup.id, { amount: Number(redeemAmount) })
      setLookup(card)
      pushToast('Redeemed.', 'success')
      setRedeemAmount('')
      await load()
    } catch (err) {
      pushToast(err?.response?.data?.message || 'Redeem failed.', 'error')
    } finally {
      setSaving(false)
    }
  }

  const handleAdjust = async () => {
    if (!lookup?.id || !adjustForm.amount || !adjustForm.reason.trim()) return
    setSaving(true)
    try {
      const card = await adjustGiftCard(lookup.id, {
        amount: Number(adjustForm.amount),
        reason: adjustForm.reason.trim(),
      })
      setLookup(card)
      pushToast('Balance adjusted.', 'success')
      setAdjustForm({ amount: '', reason: '' })
      await load()
    } catch (err) {
      pushToast(err?.response?.data?.message || 'Adjust failed.', 'error')
    } finally {
      setSaving(false)
    }
  }

  return (
    <div className="space-y-6">
      <PageHeader
        title="Gift cards"
        subtitle={subscriptionPageSubtitle(auth, 'Issue, look up, redeem, and adjust gift cards.')}
        actions={(
          <BaseButton variant="secondary" size="sm" leftIcon={RefreshCw} loading={loading} onClick={() => void load()}>
            Refresh
          </BaseButton>
        )}
      />

      {error ? <div className="rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-700">{error}</div> : null}

      {(canSell || canManage) ? (
        <div className="rounded-[18px] border border-slate-200 bg-white p-5 shadow-[0_8px_30px_rgb(0,0,0,0.04)]">
          <h3 className="text-base font-semibold text-slate-900">Issue gift card</h3>
          <div className="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <BaseInput label="Initial balance" type="number" value={issueForm.initial_balance} onChange={(e) => setIssueForm((f) => ({ ...f, initial_balance: e.target.value }))} />
            <BaseInput label="Code (optional)" value={issueForm.code} onChange={(e) => setIssueForm((f) => ({ ...f, code: e.target.value }))} />
            <BaseInput label="Recipient name" value={issueForm.recipient_name} onChange={(e) => setIssueForm((f) => ({ ...f, recipient_name: e.target.value }))} />
            <div className="flex items-end">
              <BaseButton leftIcon={Plus} loading={saving} onClick={() => void handleIssue()}>Issue</BaseButton>
            </div>
          </div>
        </div>
      ) : null}

      <div className="rounded-[18px] border border-slate-200 bg-white p-5 shadow-[0_8px_30px_rgb(0,0,0,0.04)]">
        <h3 className="text-base font-semibold text-slate-900">Lookup by code</h3>
        <div className="mt-4 flex flex-wrap items-end gap-3">
          <div className="min-w-[200px] flex-1">
            <BaseInput label="Code" value={lookupCode} onChange={(e) => setLookupCode(e.target.value)} />
          </div>
          <BaseButton leftIcon={Search} loading={saving} onClick={() => void handleLookup()}>Lookup</BaseButton>
        </div>
        {lookup ? (
          <div className="mt-4 rounded-xl border border-slate-100 bg-slate-50 p-4">
            <div className="flex flex-wrap items-center justify-between gap-2">
              <div>
                <p className="font-mono text-sm font-semibold text-slate-900">{lookup.code}</p>
                <p className="text-xs text-slate-500">{lookup.recipient_name || 'No recipient'} · {lookup.status}</p>
              </div>
              <p className="text-lg font-bold text-brand-700">{fmt.money(lookup.balance ?? lookup.current_balance ?? 0)}</p>
            </div>
            <div className="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
              {canRedeem ? (
                <>
                  <BaseInput label="Redeem amount" type="number" value={redeemAmount} onChange={(e) => setRedeemAmount(e.target.value)} />
                  <div className="flex items-end">
                    <BaseButton leftIcon={Ticket} loading={saving} onClick={() => void handleRedeem()}>Redeem</BaseButton>
                  </div>
                </>
              ) : null}
              {canAdjust ? (
                <>
                  <BaseInput label="Adjust amount (+/-)" type="number" value={adjustForm.amount} onChange={(e) => setAdjustForm((f) => ({ ...f, amount: e.target.value }))} />
                  <BaseInput label="Reason" value={adjustForm.reason} onChange={(e) => setAdjustForm((f) => ({ ...f, reason: e.target.value }))} />
                  <div className="flex items-end lg:col-span-2">
                    <BaseButton variant="secondary" leftIcon={SlidersHorizontal} loading={saving} onClick={() => void handleAdjust()}>
                      Adjust
                    </BaseButton>
                  </div>
                </>
              ) : null}
            </div>
          </div>
        ) : null}
      </div>

      <div className="overflow-hidden rounded-[18px] border border-slate-200 bg-white shadow-[0_8px_30px_rgb(0,0,0,0.04)]">
        <div className="border-b border-slate-100 px-5 py-4">
          <h3 className="text-base font-semibold text-slate-900">Recent gift cards</h3>
        </div>
        {loading ? <div className="p-5 text-sm text-slate-500">Loading…</div> : null}
        {!loading && cards.length === 0 ? <p className="px-5 py-8 text-center text-sm text-slate-500">No gift cards yet.</p> : null}
        {cards.length > 0 ? (
          <table className="min-w-full text-sm">
            <thead className="bg-slate-50 text-left text-xs uppercase tracking-wider text-slate-500">
              <tr>
                <th className="px-5 py-3">Code</th>
                <th className="px-5 py-3">Status</th>
                <th className="px-5 py-3 text-right">Balance</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-slate-100">
              {cards.map((card) => (
                <tr key={card.id}>
                  <td className="px-5 py-3 font-mono text-slate-900">{card.code}</td>
                  <td className="px-5 py-3"><BaseBadge size="sm">{card.status}</BaseBadge></td>
                  <td className="px-5 py-3 text-right font-medium">{fmt.money(card.balance ?? card.current_balance ?? 0)}</td>
                </tr>
              ))}
            </tbody>
          </table>
        ) : null}
      </div>
    </div>
  )
}
