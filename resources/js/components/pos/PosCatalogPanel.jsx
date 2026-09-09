import React, { useMemo, useState } from 'react'
import { Package, Scissors, Search } from 'lucide-react'
import BaseBadge from '../ui/BaseBadge.jsx'
import { addRetailToCart, addServiceToCart } from '../../lib/posCart.js'

export default function PosCatalogPanel({
  catalog,
  cart,
  onCartChange,
  fmt,
  staff = [],
  defaultStaffId = '',
}) {
  const [tab, setTab] = useState('services')
  const [search, setSearch] = useState('')

  const services = catalog?.services ?? []
  const retail = catalog?.retail_products ?? []

  const filteredServices = useMemo(() => {
    const q = search.trim().toLowerCase()
    if (!q) return services
    return services.filter((s) => s.name?.toLowerCase().includes(q))
  }, [services, search])

  const filteredRetail = useMemo(() => {
    const q = search.trim().toLowerCase()
    if (!q) return retail
    return retail.filter((p) => p.name?.toLowerCase().includes(q) || p.category?.toLowerCase().includes(q))
  }, [retail, search])

  const staffName = (id) => staff.find((s) => String(s.id) === String(id))?.name ?? ''

  const handleAddService = (service) => {
    const staffId = defaultStaffId || ''
    onCartChange(addServiceToCart(cart, service, {
      staff_id: staffId || null,
      staff_name: staffId ? staffName(staffId) : null,
    }))
  }

  const handleAddRetail = (product) => {
    onCartChange(addRetailToCart(cart, product))
  }

  return (
    <div className="rounded-2xl border border-slate-200 bg-white overflow-hidden flex flex-col min-h-[420px]">
      <div className="p-4 border-b border-slate-100 space-y-3">
        <div className="flex gap-2">
          <button
            type="button"
            onClick={() => setTab('services')}
            className={`flex-1 inline-flex items-center justify-center gap-2 rounded-xl px-3 py-2.5 text-sm font-bold transition-colors ${tab === 'services' ? 'bg-brand-600 text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'}`}
          >
            <Scissors className="w-4 h-4" /> Services
          </button>
          <button
            type="button"
            onClick={() => setTab('retail')}
            className={`flex-1 inline-flex items-center justify-center gap-2 rounded-xl px-3 py-2.5 text-sm font-bold transition-colors ${tab === 'retail' ? 'bg-brand-600 text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'}`}
          >
            <Package className="w-4 h-4" /> Retail
          </button>
        </div>
        <div className="relative">
          <Search className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400" />
          <input
            type="search"
            value={search}
            onChange={(e) => setSearch(e.target.value)}
            placeholder={tab === 'services' ? 'Search services…' : 'Search products…'}
            className="w-full rounded-xl border border-slate-200 pl-9 pr-3 py-2.5 text-sm"
          />
        </div>
      </div>

      <div className="flex-1 overflow-y-auto p-3 space-y-2 scroll-touch">
        {tab === 'services' ? (
          filteredServices.length === 0 ? (
            <p className="text-sm text-slate-400 text-center py-10">No services available.</p>
          ) : filteredServices.map((service) => (
            <button
              key={service.service_id}
              type="button"
              onClick={() => handleAddService(service)}
              className="w-full text-left rounded-xl border border-slate-200 px-4 py-3 hover:border-brand-300 hover:bg-brand-50/40 transition-colors"
            >
              <div className="flex items-start justify-between gap-3">
                <div>
                  <p className="font-bold text-slate-900">{service.name}</p>
                  <p className="text-xs text-slate-400 mt-0.5">{service.duration_minutes} min</p>
                </div>
                <p className="font-black text-brand-700 shrink-0">{fmt.money(service.price)}</p>
              </div>
            </button>
          ))
        ) : (
          filteredRetail.length === 0 ? (
            <p className="text-sm text-slate-400 text-center py-10">
              No retail products yet. Add branch stock with a selling price to sell products here.
            </p>
          ) : filteredRetail.map((product) => (
            <button
              key={product.product_id}
              type="button"
              onClick={() => handleAddRetail(product)}
              disabled={product.stock_on_hand <= 0}
              className="w-full text-left rounded-xl border border-slate-200 px-4 py-3 hover:border-brand-300 hover:bg-brand-50/40 transition-colors disabled:opacity-50"
            >
              <div className="flex items-start justify-between gap-3">
                <div>
                  <p className="font-bold text-slate-900">{product.name}</p>
                  <div className="flex items-center gap-2 mt-1">
                    {product.category && (
                      <span className="text-[10px] font-bold uppercase text-slate-400">{product.category}</span>
                    )}
                    <BaseBadge size="sm" variant={product.stock_on_hand <= product.reorder_level ? 'warning' : 'info'}>
                      {product.stock_on_hand} in stock
                    </BaseBadge>
                  </div>
                </div>
                <p className="font-black text-brand-700 shrink-0">{fmt.money(product.price)}</p>
              </div>
            </button>
          ))
        )}
      </div>
    </div>
  )
}
