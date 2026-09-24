import React, { useCallback, useEffect, useState } from 'react'
import { Plus, RefreshCw, ShoppingBag, Ticket } from 'lucide-react'
import PageHeader from '../components/ui/PageHeader.jsx'
import BaseButton from '../components/ui/BaseButton.jsx'
import BaseInput from '../components/ui/BaseInput.jsx'
import BaseSelect from '../components/ui/BaseSelect.jsx'
import BaseBadge from '../components/ui/BaseBadge.jsx'
import { useAuthStore } from '../stores/auth'
import { TENANT_PERMISSIONS } from '../lib/tenantPermissions.js'
import { canMutate, subscriptionPageSubtitle } from '../lib/subscriptionModules.js'
import { useTenantFormatter } from '../hooks/useTenantFormatter.js'
import { fetchMasterList } from '../lib/apiHelpers'
import { pushToast } from '../stores/toast.js'
import {
  createPackage,
  fetchCustomerPackages,
  fetchPackages,
  redeemCustomerPackage,
  sellPackage,
} from '../services/packageService.js'

export default function PackagesView() {
  const auth = useAuthStore()
  const fmt = useTenantFormatter()
  const canManage = canMutate(auth, TENANT_PERMISSIONS.PACKAGES_MANAGE)
  const canSell = canMutate(auth, TENANT_PERMISSIONS.PACKAGES_SELL)
  const canRedeem = canMutate(auth, TENANT_PERMISSIONS.PACKAGES_REDEEM)

  const [loading, setLoading] = useState(true)
  const [error, setError] = useState('')
  const [packages, setPackages] = useState([])
  const [customerPackages, setCustomerPackages] = useState([])
  const [services, setServices] = useState([])
  const [customers, setCustomers] = useState([])
  const [saving, setSaving] = useState(false)

  const [pkgForm, setPkgForm] = useState({
    name: '',
    price: '',
    valid_days: '90',
    service_id: '',
    quantity_total: '5',
  })
  const [sellForm, setSellForm] = useState({ package_id: '', customer_id: '', amount_paid: '' })
  const [redeemForm, setRedeemForm] = useState({ customer_package_id: '', service_id: '', quantity: '1' })

  const load = useCallback(async () => {
    setLoading(true)
    setError('')
    try {
      const [pkgRows, cpRows, serviceRows, customerRows] = await Promise.all([
        fetchPackages(),
        fetchCustomerPackages(),
        fetchMasterList('/v1/services', 'services').catch(() => []),
        fetchMasterList('/v1/customers', 'customers').catch(() => []),
      ])
      setPackages(pkgRows)
      setCustomerPackages(cpRows)
      setServices(serviceRows || [])
      setCustomers(customerRows || [])
    } catch (err) {
      setError(err?.response?.data?.message || 'Unable to load packages.')
    } finally {
      setLoading(false)
    }
  }, [])

  useEffect(() => {
    void load()
  }, [load])

  const handleCreate = async () => {
    if (!pkgForm.name.trim() || !pkgForm.service_id) return
    setSaving(true)
    try {
      await createPackage({
        name: pkgForm.name.trim(),
        price: Number(pkgForm.price || 0),
        valid_days: Number(pkgForm.valid_days || 90),
        is_active: true,
        items: [{
          service_id: Number(pkgForm.service_id),
          quantity_total: Number(pkgForm.quantity_total || 1),
        }],
      })
      pushToast('Package created.', 'success')
      setPkgForm({ name: '', price: '', valid_days: '90', service_id: '', quantity_total: '5' })
      await load()
    } catch (err) {
      pushToast(err?.response?.data?.message || 'Unable to create package.', 'error')
    } finally {
      setSaving(false)
    }
  }

  const handleSell = async () => {
    if (!sellForm.package_id || !sellForm.customer_id) return
    setSaving(true)
    try {
      await sellPackage(sellForm.package_id, {
        customer_id: Number(sellForm.customer_id),
        amount_paid: sellForm.amount_paid !== '' ? Number(sellForm.amount_paid) : undefined,
      })
      pushToast('Package sold.', 'success')
      setSellForm({ package_id: '', customer_id: '', amount_paid: '' })
      await load()
    } catch (err) {
      pushToast(err?.response?.data?.message || 'Unable to sell package.', 'error')
    } finally {
      setSaving(false)
    }
  }

  const handleRedeem = async () => {
    if (!redeemForm.customer_package_id || !redeemForm.service_id) return
    setSaving(true)
    try {
      await redeemCustomerPackage(redeemForm.customer_package_id, {
        service_id: Number(redeemForm.service_id),
        quantity: Number(redeemForm.quantity || 1),
      })
      pushToast('Session redeemed.', 'success')
      setRedeemForm({ customer_package_id: '', service_id: '', quantity: '1' })
      await load()
    } catch (err) {
      pushToast(err?.response?.data?.message || 'Unable to redeem.', 'error')
    } finally {
      setSaving(false)
    }
  }

  return (
    <div className="space-y-6">
      <PageHeader
        title="Packages"
        subtitle={subscriptionPageSubtitle(auth, 'Define service packages, sell them, and redeem sessions.')}
        actions={(
          <BaseButton variant="secondary" size="sm" leftIcon={RefreshCw} loading={loading} onClick={() => void load()}>
            Refresh
          </BaseButton>
        )}
      />

      {error ? <div className="rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-700">{error}</div> : null}

      {canManage ? (
        <div className="rounded-[18px] border border-slate-200 bg-white p-5 shadow-[0_8px_30px_rgb(0,0,0,0.04)]">
          <h3 className="text-base font-semibold text-slate-900">Define package</h3>
          <div className="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-6">
            <BaseInput label="Name" value={pkgForm.name} onChange={(e) => setPkgForm((f) => ({ ...f, name: e.target.value }))} />
            <BaseInput label="Price" type="number" value={pkgForm.price} onChange={(e) => setPkgForm((f) => ({ ...f, price: e.target.value }))} />
            <BaseInput label="Valid days" type="number" value={pkgForm.valid_days} onChange={(e) => setPkgForm((f) => ({ ...f, valid_days: e.target.value }))} />
            <BaseSelect
              label="Service item"
              value={pkgForm.service_id}
              onChange={(e) => setPkgForm((f) => ({ ...f, service_id: e.target.value }))}
              options={[{ value: '', label: 'Select service' }, ...services.map((s) => ({ value: String(s.id), label: s.name }))]}
            />
            <BaseInput label="Qty" type="number" value={pkgForm.quantity_total} onChange={(e) => setPkgForm((f) => ({ ...f, quantity_total: e.target.value }))} />
            <div className="flex items-end">
              <BaseButton leftIcon={Plus} loading={saving} onClick={() => void handleCreate()}>Create</BaseButton>
            </div>
          </div>
        </div>
      ) : null}

      <div className="overflow-hidden rounded-[18px] border border-slate-200 bg-white shadow-[0_8px_30px_rgb(0,0,0,0.04)]">
        <div className="border-b border-slate-100 px-5 py-4">
          <h3 className="text-base font-semibold text-slate-900">Package catalog</h3>
        </div>
        {!loading && packages.length === 0 ? <p className="px-5 py-8 text-center text-sm text-slate-500">No packages defined.</p> : null}
        {packages.length > 0 ? (
          <ul className="divide-y divide-slate-100">
            {packages.map((pkg) => (
              <li key={pkg.id} className="flex flex-wrap items-center justify-between gap-3 px-5 py-4">
                <div>
                  <p className="font-medium text-slate-900">{pkg.name}</p>
                  <p className="text-xs text-slate-500">
                    {(pkg.items || []).map((item) => `${item.service?.name || item.service_id}×${item.quantity_total}`).join(', ') || 'No items'}
                    {pkg.valid_days ? ` · ${pkg.valid_days} days` : ''}
                  </p>
                </div>
                <div className="flex items-center gap-2">
                  <span className="font-semibold text-slate-900">{fmt.money(pkg.price ?? 0)}</span>
                  <BaseBadge size="sm" variant={pkg.is_active ? 'success' : 'default'}>{pkg.is_active ? 'Active' : 'Off'}</BaseBadge>
                </div>
              </li>
            ))}
          </ul>
        ) : null}
      </div>

      {canSell ? (
        <div className="rounded-[18px] border border-slate-200 bg-white p-5 shadow-[0_8px_30px_rgb(0,0,0,0.04)]">
          <h3 className="text-base font-semibold text-slate-900">Sell to customer</h3>
          <div className="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <BaseSelect
              label="Package"
              value={sellForm.package_id}
              onChange={(e) => setSellForm((f) => ({ ...f, package_id: e.target.value }))}
              options={[{ value: '', label: 'Select package' }, ...packages.map((p) => ({ value: String(p.id), label: p.name }))]}
            />
            <BaseSelect
              label="Customer"
              value={sellForm.customer_id}
              onChange={(e) => setSellForm((f) => ({ ...f, customer_id: e.target.value }))}
              options={[{ value: '', label: 'Select customer' }, ...customers.map((c) => ({ value: String(c.id), label: c.name }))]}
            />
            <BaseInput label="Amount paid" type="number" value={sellForm.amount_paid} onChange={(e) => setSellForm((f) => ({ ...f, amount_paid: e.target.value }))} />
            <div className="flex items-end">
              <BaseButton leftIcon={ShoppingBag} loading={saving} onClick={() => void handleSell()}>Sell</BaseButton>
            </div>
          </div>
        </div>
      ) : null}

      <div className="overflow-hidden rounded-[18px] border border-slate-200 bg-white shadow-[0_8px_30px_rgb(0,0,0,0.04)]">
        <div className="border-b border-slate-100 px-5 py-4">
          <h3 className="text-base font-semibold text-slate-900">Customer packages</h3>
        </div>
        {customerPackages.length === 0 ? <p className="px-5 py-8 text-center text-sm text-slate-500">No sold packages yet.</p> : null}
        {customerPackages.length > 0 ? (
          <table className="min-w-full text-sm">
            <thead className="bg-slate-50 text-left text-xs uppercase tracking-wider text-slate-500">
              <tr>
                <th className="px-5 py-3">Customer</th>
                <th className="px-5 py-3">Package</th>
                <th className="px-5 py-3">Status</th>
                <th className="px-5 py-3">Remaining</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-slate-100">
              {customerPackages.map((row) => (
                <tr key={row.id}>
                  <td className="px-5 py-3 font-medium text-slate-900">{row.customer?.name || row.customer_id}</td>
                  <td className="px-5 py-3">{row.package?.name || row.package_id}</td>
                  <td className="px-5 py-3"><BaseBadge size="sm">{row.status || 'active'}</BaseBadge></td>
                  <td className="px-5 py-3">{row.quantity_remaining ?? row.sessions_remaining ?? '—'}</td>
                </tr>
              ))}
            </tbody>
          </table>
        ) : null}
      </div>

      {canRedeem ? (
        <div className="rounded-[18px] border border-slate-200 bg-white p-5 shadow-[0_8px_30px_rgb(0,0,0,0.04)]">
          <h3 className="text-base font-semibold text-slate-900">Redeem session</h3>
          <div className="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <BaseSelect
              label="Customer package"
              value={redeemForm.customer_package_id}
              onChange={(e) => setRedeemForm((f) => ({ ...f, customer_package_id: e.target.value }))}
              options={[
                { value: '', label: 'Select' },
                ...customerPackages.map((row) => ({
                  value: String(row.id),
                  label: `${row.customer?.name || row.customer_id} · ${row.package?.name || row.id}`,
                })),
              ]}
            />
            <BaseSelect
              label="Service"
              value={redeemForm.service_id}
              onChange={(e) => setRedeemForm((f) => ({ ...f, service_id: e.target.value }))}
              options={[{ value: '', label: 'Select service' }, ...services.map((s) => ({ value: String(s.id), label: s.name }))]}
            />
            <BaseInput label="Qty" type="number" value={redeemForm.quantity} onChange={(e) => setRedeemForm((f) => ({ ...f, quantity: e.target.value }))} />
            <div className="flex items-end">
              <BaseButton leftIcon={Ticket} loading={saving} onClick={() => void handleRedeem()}>Redeem</BaseButton>
            </div>
          </div>
        </div>
      ) : null}
    </div>
  )
}
