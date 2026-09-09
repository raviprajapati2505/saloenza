import React, { useCallback, useEffect, useState } from 'react'
import {
  AlertTriangle, Package, Plus, RefreshCw, Search, ShoppingCart, TrendingDown, BarChart2, Layers,
} from 'lucide-react'
import PageHeader from '../components/ui/PageHeader.jsx'
import BaseButton from '../components/ui/BaseButton.jsx'
import BaseBadge from '../components/ui/BaseBadge.jsx'
import BaseModal from '../components/ui/BaseModal.jsx'
import BaseSelect from '../components/ui/BaseSelect.jsx'
import SubscriptionAccessFallback from '../components/subscription/SubscriptionAccessFallback.jsx'
import ProductSalesPanel from '../components/inventory/ProductSalesPanel.jsx'
import { useAuthStore } from '../stores/auth'
import { useTenantFormatter } from '../hooks/useTenantFormatter.js'
import { authHasModule, canMutate, subscriptionPageSubtitle } from '../lib/subscriptionModules.js'
import { isForbiddenError } from '../lib/apiErrors.js'
import { fetchBranches } from '../services/branchService.js'
import {
  adjustInventoryStock,
  createPurchaseOrder,
  createSupplier,
  fetchInventoryStock,
  fetchInventorySummary,
  fetchProducts,
  fetchProductSalesReport,
  fetchPurchaseOrders,
  fetchSuppliers,
  receivePurchaseOrder,
  saveInventoryStock,
} from '../services/inventoryService.js'

const STATUS_VARIANT = { out: 'danger', low: 'warning', ok: 'info', high: 'success' }
const STATUS_LABEL = { out: 'Out of Stock', low: 'Low Stock', ok: 'In Stock', high: 'Well Stocked' }

const TABS = [
  { key: 'stock', label: 'Stock' },
  { key: 'orders', label: 'Purchase Orders' },
  { key: 'sales', label: 'Product Sales' },
]

function isoDaysAgo(days) {
  const date = new Date()
  date.setDate(date.getDate() - days)
  return date.toISOString().slice(0, 10)
}

export default function InventoryView() {
  const auth = useAuthStore()
  const fmt = useTenantFormatter()
  const canView = auth.can('inventory.view')
  const canManage = auth.can('inventory.manage') && canMutate(auth)
  const hasModule = authHasModule(auth, 'inventory')

  const [branches, setBranches] = useState([])
  const [branchId, setBranchId] = useState('')
  const [search, setSearch] = useState('')
  const [filter, setFilter] = useState('all')
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState('')
  const [summary, setSummary] = useState(null)
  const [stock, setStock] = useState([])
  const [orders, setOrders] = useState([])
  const [suppliers, setSuppliers] = useState([])
  const [products, setProducts] = useState([])
  const [tab, setTab] = useState('stock')
  const [adjustTarget, setAdjustTarget] = useState(null)
  const [adjustQty, setAdjustQty] = useState('')
  const [adjustNotes, setAdjustNotes] = useState('')
  const [poOpen, setPoOpen] = useState(false)
  const [poSupplier, setPoSupplier] = useState('')
  const [poLines, setPoLines] = useState([{ product_id: '', quantity_ordered: '1', unit_cost: '' }])
  const [supplierOpen, setSupplierOpen] = useState(false)
  const [supplierName, setSupplierName] = useState('')
  const [addStockOpen, setAddStockOpen] = useState(false)
  const [newStock, setNewStock] = useState({ product_id: '', quantity_on_hand: '0', reorder_level: '5', cost_price: '', selling_price: '' })
  const [saving, setSaving] = useState(false)
  const [salesRange, setSalesRange] = useState({ from: isoDaysAgo(29), to: isoDaysAgo(0) })
  const [salesReport, setSalesReport] = useState(null)
  const [salesLoading, setSalesLoading] = useState(false)

  const load = useCallback(async () => {
    if (!canView || !hasModule) {
      setLoading(false)
      return
    }
    if (!branchId) {
      setLoading(false)
      return
    }
    setLoading(true)
    setError('')
    try {
      const params = { branch_id: Number(branchId), per_page: 100 }
      if (search.trim()) params.search = search.trim()
      if (filter === 'low') params.low_stock = 1
      if (filter === 'out') params.out_of_stock = 1

      const [summaryData, stockData, orderData, supplierData] = await Promise.all([
        fetchInventorySummary({ branch_id: Number(branchId) }),
        fetchInventoryStock(params),
        fetchPurchaseOrders({ branch_id: Number(branchId) }),
        fetchSuppliers({ is_active: 1 }),
      ])
      setSummary(summaryData)
      setStock(stockData.stock)
      setOrders(orderData)
      setSuppliers(supplierData)
    } catch (err) {
      const message = err?.response?.data?.message
        || err?.response?.data?.errors?.page?.[0]
        || err?.response?.data?.errors?.per_page?.[0]
        || 'Unable to load inventory.'
      if (isForbiddenError(err)) setError(err?.response?.data?.message || 'Inventory access denied.')
      else setError(message)
    } finally {
      setLoading(false)
    }
  }, [branchId, canView, filter, hasModule, search])

  useEffect(() => {
    if (auth.user?.branch_id) {
      setBranchId(String(auth.user.branch_id))
      return
    }

    if (auth.can('branches.view')) {
      fetchBranches()
        .then((r) => {
          const list = r.branches || []
          setBranches(list)
          setBranchId((current) => current || (list[0] ? String(list[0].id) : ''))
        })
        .catch(() => {})
    }
  }, [auth])

  useEffect(() => {
    if (addStockOpen && products.length === 0 && auth.can('products.view')) {
      fetchProducts({ per_page: 100, is_active: 1 }).then(setProducts).catch(() => {})
    }
  }, [addStockOpen, auth, products.length])

  useEffect(() => {
    void load()
  }, [load])

  useEffect(() => {
    if (tab !== 'sales' || !branchId) return
    let cancelled = false
    setSalesLoading(true)
    fetchProductSalesReport({ branch_id: Number(branchId), from: salesRange.from, to: salesRange.to })
      .then((data) => { if (!cancelled) setSalesReport(data) })
      .catch(() => { if (!cancelled) setSalesReport(null) })
      .finally(() => { if (!cancelled) setSalesLoading(false) })
    return () => { cancelled = true }
  }, [tab, branchId, salesRange.from, salesRange.to])

  const filteredStock = stock

  const handleAdjust = async () => {
    if (!adjustTarget) return
    setSaving(true)
    try {
      await adjustInventoryStock(adjustTarget.id, {
        quantity_change: Number(adjustQty),
        notes: adjustNotes || null,
      })
      setAdjustTarget(null)
      setAdjustQty('')
      setAdjustNotes('')
      await load()
    } catch (err) {
      setError(err?.response?.data?.message || 'Adjustment failed.')
    } finally {
      setSaving(false)
    }
  }

  const handleCreatePo = async () => {
    setSaving(true)
    try {
      const items = poLines
        .filter((line) => line.product_id && Number(line.quantity_ordered) > 0)
        .map((line) => ({
          product_id: Number(line.product_id),
          quantity_ordered: Number(line.quantity_ordered),
          unit_cost: line.unit_cost !== '' ? Number(line.unit_cost) : 0,
        }))
      await createPurchaseOrder({
        branch_id: Number(branchId),
        supplier_id: poSupplier ? Number(poSupplier) : null,
        place_order: true,
        receive_now: true,
        items,
      })
      setPoOpen(false)
      setPoLines([{ product_id: '', quantity_ordered: '1', unit_cost: '' }])
      await load()
    } catch (err) {
      setError(err?.response?.data?.message || 'Purchase order failed.')
    } finally {
      setSaving(false)
    }
  }

  const handleAddStock = async () => {
    setSaving(true)
    try {
      await saveInventoryStock({
        branch_id: Number(branchId),
        product_id: Number(newStock.product_id),
        quantity_on_hand: Number(newStock.quantity_on_hand),
        reorder_level: Number(newStock.reorder_level || 5),
        cost_price: newStock.cost_price !== '' ? Number(newStock.cost_price) : null,
        selling_price: newStock.selling_price !== '' ? Number(newStock.selling_price) : null,
      })
      setAddStockOpen(false)
      await load()
    } catch (err) {
      setError(err?.response?.data?.message || 'Unable to save stock.')
    } finally {
      setSaving(false)
    }
  }

  const handleCreateSupplier = async () => {
    if (!supplierName.trim()) return
    setSaving(true)
    try {
      await createSupplier({ name: supplierName.trim(), is_active: true })
      setSupplierOpen(false)
      setSupplierName('')
      await load()
    } catch (err) {
      setError(err?.response?.data?.message || 'Unable to create supplier.')
    } finally {
      setSaving(false)
    }
  }

  if (!hasModule) {
    return (
      <SubscriptionAccessFallback mode="error" message="Inventory is not included in your current plan." showRenew={auth.can('settings.view')} />
    )
  }

  if (error && !stock.length && !loading) {
    return <SubscriptionAccessFallback mode="error" message={error} showRenew={auth.can('settings.view')} />
  }

  if (!branchId && !loading) {
    return (
      <div className="rounded-2xl border border-dashed border-slate-200 bg-slate-50 py-16 px-6 text-center text-slate-500">
        Select a branch to view inventory.
      </div>
    )
  }

  return (
    <div className="space-y-6">
      <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <PageHeader
          title="Inventory"
          subtitle={subscriptionPageSubtitle(auth, 'Track stock, suppliers, and purchase orders by branch.')}
        />
        <div className="flex flex-wrap items-end gap-2">
          {!auth.user?.branch_id && auth.can('branches.view') && (
            <BaseSelect
              label="Branch"
              value={branchId}
              onChange={(e) => setBranchId(e.target.value)}
              options={[
                { value: '', label: 'Select branch' },
                ...branches.map((branch) => ({
                  value: String(branch.id),
                  label: branch.branch_name || branch.name || `Branch #${branch.id}`,
                })),
              ]}
              className="min-w-[160px]"
            />
          )}
          <button type="button" onClick={() => load()} className="p-3 rounded-xl border border-slate-200 bg-white text-slate-500 hover:text-brand-600">
            <RefreshCw className={`w-5 h-5 ${loading ? 'animate-spin' : ''}`} />
          </button>
          {canManage && (
            <>
              <BaseButton variant="secondary" size="sm" leftIcon={Plus} onClick={() => setSupplierOpen(true)}>Supplier</BaseButton>
              <BaseButton variant="secondary" size="sm" leftIcon={ShoppingCart} onClick={() => setPoOpen(true)}>Restock Order</BaseButton>
              <BaseButton size="sm" leftIcon={Plus} onClick={() => setAddStockOpen(true)}>Add Stock</BaseButton>
            </>
          )}
        </div>
      </div>

      {error && <div className="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">{error}</div>}

      <div className="grid grid-cols-2 lg:grid-cols-4 gap-4">
        {[
          { label: 'Total SKUs', value: summary?.total_skus ?? 0, icon: Layers, color: 'indigo' },
          { label: 'Inventory Value', value: fmt.money(summary?.inventory_value ?? 0), icon: BarChart2, color: 'teal' },
          { label: 'Low Stock', value: summary?.low_stock_count ?? 0, icon: TrendingDown, color: 'amber' },
          { label: 'Out of Stock', value: summary?.out_of_stock_count ?? 0, icon: AlertTriangle, color: 'rose' },
        ].map((stat) => {
          const Icon = stat.icon
          return (
            <div key={stat.label} className="bg-white border border-slate-200/80 rounded-2xl p-5 shadow-sm">
              <div className="flex items-center justify-between">
                <p className="text-[10px] font-bold uppercase tracking-wider text-slate-400">{stat.label}</p>
                <Icon className="w-4 h-4 text-brand-600" />
              </div>
              <h3 className="text-2xl font-black text-slate-800 mt-2">{stat.value}</h3>
            </div>
          )
        })}
      </div>

      <div className="flex gap-2 flex-wrap">
        {TABS.map(({ key, label }) => (
          <button
            key={key}
            type="button"
            onClick={() => setTab(key)}
            className={`px-4 py-2 rounded-xl text-sm font-bold ${tab === key ? 'bg-brand-600 text-white' : 'bg-slate-100 text-slate-600'}`}
          >
            {label}
          </button>
        ))}
      </div>

      {tab === 'sales' ? (
        <ProductSalesPanel
          fmt={fmt}
          loading={salesLoading}
          range={salesRange}
          onRangeChange={setSalesRange}
          report={salesReport}
        />
      ) : tab === 'stock' ? (
        <>
          <div className="flex flex-col sm:flex-row gap-3">
            <div className="relative flex-1">
              <Search className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400" />
              <input
                type="search"
                value={search}
                onChange={(e) => setSearch(e.target.value)}
                placeholder="Search product, SKU, brand…"
                className="w-full pl-10 pr-4 py-2.5 text-sm border border-slate-200 rounded-xl bg-white"
              />
            </div>
            <div className="flex gap-2 flex-wrap">
              {['all', 'low', 'out'].map((key) => (
                <button key={key} type="button" onClick={() => setFilter(key)} className={`px-3 py-2 text-xs font-bold rounded-xl border ${filter === key ? 'bg-brand-600 text-white border-brand-600' : 'bg-white text-slate-500 border-slate-200'}`}>
                  {key === 'all' ? 'All' : key === 'low' ? 'Low stock' : 'Out of stock'}
                </button>
              ))}
            </div>
          </div>

          <div className="bg-white border border-slate-200/80 rounded-2xl shadow-sm overflow-hidden">
            <div className="overflow-x-auto">
              <table className="w-full text-left">
                <thead>
                  <tr className="border-b border-slate-100 bg-slate-50/50">
                    {['Product', 'Category', 'Stock', 'Status', 'Cost / Sell', 'Supplier', ''].map((col) => (
                      <th key={col} className="px-4 py-3 text-[10px] font-bold uppercase tracking-wider text-slate-400">{col}</th>
                    ))}
                  </tr>
                </thead>
                <tbody>
                  {loading ? (
                    <tr><td colSpan={7} className="py-12 text-center text-slate-400">Loading inventory…</td></tr>
                  ) : filteredStock.length === 0 ? (
                    <tr><td colSpan={7} className="py-12 text-center text-slate-400">No stock records for this branch.</td></tr>
                  ) : filteredStock.map((row) => (
                    <tr key={row.id} className="border-b border-slate-100">
                      <td className="py-4 px-4">
                        <p className="text-sm font-bold text-slate-900">{row.product?.name}</p>
                        <p className="text-[10px] text-slate-400">{row.product?.sku || '—'} · {row.product?.brand || '—'}</p>
                      </td>
                      <td className="py-4 px-4 text-xs text-slate-600">{row.product?.category?.name || '—'}</td>
                      <td className="py-4 px-4 text-sm font-bold">{row.quantity_on_hand} {row.product?.unit || 'units'}</td>
                      <td className="py-4 px-4"><BaseBadge variant={STATUS_VARIANT[row.stock_status] || 'default'}>{STATUS_LABEL[row.stock_status] || row.stock_status}</BaseBadge></td>
                      <td className="py-4 px-4 text-xs font-semibold">
                        <p>{row.cost_price != null ? fmt.money(row.cost_price) : '—'} / {row.selling_price != null ? fmt.money(row.selling_price) : '—'}</p>
                        {row.selling_price == null ? (
                          <p className="mt-1 text-[10px] font-semibold text-amber-600">No sell price — hidden from POS</p>
                        ) : row.cost_price == null ? (
                          <p className="mt-1 text-[10px] font-semibold text-amber-600">No cost price — margin unknown</p>
                        ) : null}
                      </td>
                      <td className="py-4 px-4 text-xs text-slate-500">{row.supplier?.name || '—'}</td>
                      <td className="py-4 px-4 text-right">
                        {canManage && (
                          <BaseButton size="sm" variant="secondary" onClick={() => { setAdjustTarget(row); setAdjustQty('1') }}>Adjust</BaseButton>
                        )}
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          </div>
        </>
      ) : (
        <div className="space-y-3">
          {orders.length === 0 ? (
            <div className="rounded-2xl border border-dashed border-slate-200 py-12 text-center text-slate-400">No purchase orders yet.</div>
          ) : orders.map((order) => (
            <div key={order.id} className="rounded-2xl border border-slate-200 bg-white p-4 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
              <div>
                <p className="font-bold text-slate-900">{order.po_number}</p>
                <p className="text-xs text-slate-500">{order.supplier?.name || 'No supplier'} · {order.items?.length || 0} items</p>
              </div>
              <div className="flex items-center gap-2">
                <BaseBadge>{order.status}</BaseBadge>
                {canManage && order.status === 'ordered' && (
                  <BaseButton size="sm" onClick={async () => { await receivePurchaseOrder(order.id); await load() }}>Receive</BaseButton>
                )}
              </div>
            </div>
          ))}
        </div>
      )}

      <BaseModal open={Boolean(adjustTarget)} onClose={() => setAdjustTarget(null)} title="Adjust stock" footer={(
        <>
          <BaseButton variant="secondary" onClick={() => setAdjustTarget(null)}>Cancel</BaseButton>
          <BaseButton onClick={handleAdjust} loading={saving}>Save</BaseButton>
        </>
      )}>
        <p className="text-sm text-slate-600 mb-3">{adjustTarget?.product?.name} — current {adjustTarget?.quantity_on_hand}</p>
        <input type="number" value={adjustQty} onChange={(e) => setAdjustQty(e.target.value)} placeholder="+/- quantity" className="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm mb-3" />
        <input type="text" value={adjustNotes} onChange={(e) => setAdjustNotes(e.target.value)} placeholder="Notes (optional)" className="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm" />
      </BaseModal>

      <BaseModal open={poOpen} onClose={() => setPoOpen(false)} title="Create restock order" size="lg" footer={(
        <>
          <BaseButton variant="secondary" onClick={() => setPoOpen(false)}>Cancel</BaseButton>
          <BaseButton onClick={handleCreatePo} loading={saving}>Place & receive</BaseButton>
        </>
      )}>
        <BaseSelect label="Supplier" value={poSupplier} onChange={(e) => setPoSupplier(e.target.value)} options={[{ value: '', label: 'Optional' }, ...suppliers.map((s) => ({ value: String(s.id), label: s.name }))]} />
        <div className="mt-4 space-y-3">
          {poLines.map((line, index) => (
            <div key={index} className="grid grid-cols-3 gap-2">
              <BaseSelect label="Product" value={line.product_id} onChange={(e) => setPoLines(poLines.map((row, i) => i === index ? { ...row, product_id: e.target.value } : row))} options={[{ value: '', label: 'Select' }, ...stock.map((s) => ({ value: String(s.product_id), label: s.product?.name }))]} />
              <input type="number" min="1" value={line.quantity_ordered} onChange={(e) => setPoLines(poLines.map((row, i) => i === index ? { ...row, quantity_ordered: e.target.value } : row))} placeholder="Qty" className="rounded-xl border border-slate-200 px-3 py-2 text-sm" />
              <input type="number" min="0" step="0.01" value={line.unit_cost} onChange={(e) => setPoLines(poLines.map((row, i) => i === index ? { ...row, unit_cost: e.target.value } : row))} placeholder="Unit cost" className="rounded-xl border border-slate-200 px-3 py-2 text-sm" />
            </div>
          ))}
        </div>
      </BaseModal>

      <BaseModal open={addStockOpen} onClose={() => setAddStockOpen(false)} title="Add stock record" footer={(
        <>
          <BaseButton variant="secondary" onClick={() => setAddStockOpen(false)}>Cancel</BaseButton>
          <BaseButton onClick={handleAddStock} loading={saving}>Save</BaseButton>
        </>
      )}>
        <BaseSelect label="Product" value={newStock.product_id} onChange={(e) => setNewStock({ ...newStock, product_id: e.target.value })} options={[{ value: '', label: 'Select product' }, ...products.map((p) => ({ value: String(p.id), label: p.name }))]} />
        <div className="grid grid-cols-2 gap-3 mt-3">
          <input type="number" min="0" value={newStock.quantity_on_hand} onChange={(e) => setNewStock({ ...newStock, quantity_on_hand: e.target.value })} placeholder="Quantity" className="rounded-xl border border-slate-200 px-3 py-2 text-sm" />
          <input type="number" min="0" value={newStock.reorder_level} onChange={(e) => setNewStock({ ...newStock, reorder_level: e.target.value })} placeholder="Reorder level" className="rounded-xl border border-slate-200 px-3 py-2 text-sm" />
          <input type="number" min="0" step="0.01" value={newStock.cost_price} onChange={(e) => setNewStock({ ...newStock, cost_price: e.target.value })} placeholder="Cost price" className="rounded-xl border border-slate-200 px-3 py-2 text-sm" />
          <input type="number" min="0" step="0.01" value={newStock.selling_price} onChange={(e) => setNewStock({ ...newStock, selling_price: e.target.value })} placeholder="Selling price" className="rounded-xl border border-slate-200 px-3 py-2 text-sm" />
        </div>
        <p className="mt-2 text-[11px] text-slate-400">
          A selling price is what makes this product sellable at the counter. Cost price drives margin in the profit report.
        </p>
      </BaseModal>

      <BaseModal open={supplierOpen} onClose={() => setSupplierOpen(false)} title="Add supplier" footer={(
        <>
          <BaseButton variant="secondary" onClick={() => setSupplierOpen(false)}>Cancel</BaseButton>
          <BaseButton onClick={handleCreateSupplier} loading={saving}>Save</BaseButton>
        </>
      )}>
        <input type="text" value={supplierName} onChange={(e) => setSupplierName(e.target.value)} placeholder="Supplier name" className="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm" />
      </BaseModal>
    </div>
  )
}
