import React, { forwardRef, useCallback, useEffect, useImperativeHandle, useMemo, useState } from 'react'
import { Loader2, Scissors, Search, Sparkles, Trash2 } from 'lucide-react'
import { fetchMasterList } from '../../../lib/apiHelpers'
import {
  DEFAULT_PRODUCT_NAME,
  createOfferingFromServiceProduct,
  createOfferingsForService,
  hasServiceOnlyOffering,
  linkedProductsOf,
  productsForService,
  resolveProductPrice,
  sameOffering,
  selectedProductIdsByService,
} from '../../../lib/serviceProductHelpers'
import { currencySymbol, formatMoneyDefault } from '../../../lib/tenantFormatting.js'

const money = formatMoneyDefault
const currencyLabel = () => currencySymbol(null)
const compactSelectClass =
  'w-full min-w-0 rounded-lg border border-slate-300 bg-white px-2 py-1.5 text-xs text-slate-900 focus:border-brand-400 focus:outline-none focus:ring-2 focus:ring-brand-500/20'
const compactInputClass =
  'w-full min-w-0 rounded-lg border border-slate-300 bg-white px-2 py-1.5 text-xs text-slate-900 focus:border-brand-400 focus:outline-none focus:ring-2 focus:ring-brand-500/20'

function toOwnerRow(offering, defaultCategoryId = null) {
  return {
    service_id: offering.service_id ?? null,
    name: offering.name ?? offering.service ?? '',
    category_id: offering.category_id ?? defaultCategoryId,
    product_id: offering.product_id ?? null,
    product: offering.product ?? DEFAULT_PRODUCT_NAME,
    price: Number(offering.price) || 0,
    duration: Number(offering.duration) || 30,
    is_custom: Boolean(offering.is_custom ?? !offering.service_id),
  }
}

function createCustomRow(overrides = {}) {
  return {
    service_id: null,
    name: '',
    category_id: null,
    product_id: null,
    product: DEFAULT_PRODUCT_NAME,
    price: 0,
    duration: 30,
    is_custom: true,
    ...overrides,
  }
}

function normalizeStoredServices(services) {
  if (!Array.isArray(services) || services.length === 0) return []
  return services.map((row) => ({
    service_id: row.service_id ?? null,
    name: row.name ?? '',
    category_id: row.category_id ?? null,
    product_id: row.product_id ?? null,
    product: row.product ?? row.category ?? DEFAULT_PRODUCT_NAME,
    price: Number(row.price) || 0,
    duration: Number(row.duration) || 30,
    is_custom: Boolean(row.is_custom ?? !row.service_id),
  }))
}

const StepService = forwardRef(function StepService(
  { modelValue = { services: [], skipped: false }, onUpdateModelValue },
  ref,
) {
  const [services, setServices] = useState(() => normalizeStoredServices(modelValue.services))
  const [skipped, setSkipped] = useState(Boolean(modelValue.skipped))
  const [error, setError] = useState('')

  const [masterServices, setMasterServices] = useState([])
  const [masterProducts, setMasterProducts] = useState([])
  const [masterCategories, setMasterCategories] = useState([])
  const [loadingMasters, setLoadingMasters] = useState(true)
  const [loadError, setLoadError] = useState('')
  const [search, setSearch] = useState('')
  const [showCustomForm, setShowCustomForm] = useState(false)
  const [customDraft, setCustomDraft] = useState({
    name: '',
    category_id: '',
    product: DEFAULT_PRODUCT_NAME,
    price: '',
    duration: '30',
  })

  const defaultCategory = useMemo(
    () => masterCategories.find((c) => c.is_active !== false) ?? null,
    [masterCategories],
  )

  useEffect(() => {
    let active = true
    async function loadMasters() {
      setLoadingMasters(true)
      setLoadError('')
      try {
        const [servicesList, products, categories] = await Promise.all([
          fetchMasterList('/v1/services', 'services'),
          fetchMasterList('/v1/products', 'products'),
          fetchMasterList('/v1/categories', 'categories'),
        ])
        if (!active) return
        setMasterServices(servicesList)
        setMasterProducts(products)
        setMasterCategories(categories)
      } catch (err) {
        if (!active) return
        setMasterServices([])
        setMasterProducts([])
        setMasterCategories([])
        const message = err?.response?.data?.message
          || err?.message
          || 'Unable to load master services and products.'
        setLoadError(message)
      } finally {
        if (active) setLoadingMasters(false)
      }
    }
    void loadMasters()
    return () => { active = false }
  }, [])

  const filteredServices = useMemo(() => {
    const q = search.trim().toLowerCase()
    const activeOnly = masterServices.filter((s) => s.is_active !== false)
    if (!q) return activeOnly
    return activeOnly.filter((s) => s.name?.toLowerCase().includes(q))
  }, [masterServices, search])

  const selectedByService = useMemo(() => selectedProductIdsByService(services), [services])

  const selectedServiceIds = useMemo(() => {
    const ids = new Set()
    for (const row of services) {
      if (row.service_id) ids.add(Number(row.service_id))
    }
    return ids
  }, [services])

  const getData = useCallback(() => ({
    skipped,
    services: structuredClone(services),
  }), [skipped, services])

  const validate = useCallback(() => {
    if (skipped) {
      setError('')
      return true
    }
    if (services.length === 0) {
      setError('Select at least one service/product from the grid, add a custom one, or choose "Skip for now".')
      return false
    }
    const hasInvalid = services.some(
      (row) => !row.name?.trim() || !row.category_id || !row.duration || row.duration < 1 || row.price < 0,
    )
    const nextError = hasInvalid
      ? 'Complete service, category, price, and duration for each row, or choose "Skip for now".'
      : ''
    setError(nextError)
    return !hasInvalid
  }, [skipped, services])

  useImperativeHandle(ref, () => ({
    validate,
    getData,
  }), [validate, getData])

  useEffect(() => {
    onUpdateModelValue?.(getData())
  }, [services, skipped, getData, onUpdateModelValue])

  const toggleMasterService = (service) => {
    setSkipped(false)
    setError('')
    setServices((previous) => {
      const serviceId = Number(service.id)
      if (hasServiceOnlyOffering(previous, serviceId)) {
        return previous.filter((row) => !(
          Number(row.service_id) === serviceId && (row.product_id == null || row.product_id === '')
        ))
      }
      const offerings = createOfferingsForService(service, masterProducts).map((row) =>
        toOwnerRow(row, service.category_id ?? service.category?.id ?? defaultCategory?.id ?? null),
      )
      return [...previous, ...offerings]
    })
  }

  const toggleServiceProduct = (service, product) => {
    setSkipped(false)
    setError('')
    setServices((previous) => {
      const index = previous.findIndex((row) => sameOffering(row, service.id, product.id))
      if (index >= 0) {
        return previous.filter((_, i) => i !== index)
      }
      return [
        ...previous,
        toOwnerRow(
          createOfferingFromServiceProduct(service, product),
          service.category_id ?? service.category?.id ?? defaultCategory?.id ?? null,
        ),
      ]
    })
  }

  const updateRow = (index, patch) => {
    setServices((previous) => previous.map((row, i) => (i === index ? { ...row, ...patch } : row)))
  }

  const applyMasterDefaults = (index, serviceId) => {
    const service = masterServices.find((s) => Number(s.id) === Number(serviceId))
    if (!service) return
    updateRow(index, toOwnerRow(
      createOfferingFromServiceProduct(service, null),
      service.category_id ?? service.category?.id ?? defaultCategory?.id ?? null,
    ))
  }

  const applyProductSelection = (index, serviceId, productId) => {
    const service = masterServices.find((s) => Number(s.id) === Number(serviceId))
    const options = productsForService(service, masterProducts)
    const product = options.find((p) => Number(p.id) === Number(productId))
    const id = productId ? Number(productId) : null

    if (id && services.some((row, i) => i !== index && sameOffering(row, serviceId, id))) {
      return
    }
    if (!id && services.some((row, i) => i !== index && sameOffering(row, serviceId, null))) {
      return
    }

    updateRow(index, {
      product_id: id,
      product: product?.name ?? '',
      ...(service ? { price: resolveProductPrice(service, id) } : {}),
    })
  }

  const addCustomService = () => {
    const name = customDraft.name.trim()
    if (!name) return
    setSkipped(false)
    setError('')
    setServices((previous) => [
      ...previous,
      createCustomRow({
        name,
        category_id: customDraft.category_id ? Number(customDraft.category_id) : defaultCategory?.id ?? null,
        product: customDraft.product.trim() || DEFAULT_PRODUCT_NAME,
        price: Number(customDraft.price) || 0,
        duration: Number(customDraft.duration) || 30,
      }),
    ])
    setCustomDraft({ name: '', category_id: defaultCategory?.id ?? '', product: DEFAULT_PRODUCT_NAME, price: '', duration: '30' })
    setShowCustomForm(false)
  }

  const skipForNow = () => {
    setSkipped(true)
    setError('')
    setServices([])
  }

  return (
    <div>
      <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
        <div>
          <h2 className="text-2xl font-semibold text-slate-900">Salon Services</h2>
          <p className="mt-1 text-sm text-slate-500">
            Select a service for its base price. Optionally add products — each gets its own price.
          </p>
        </div>
        <button
          type="button"
          onClick={() => setShowCustomForm((v) => !v)}
          className="inline-flex shrink-0 items-center gap-1.5 self-start rounded-lg border border-brand-200 bg-brand-50 px-3 py-1.5 text-xs font-semibold text-brand-700 hover:bg-brand-100"
        >
          <Sparkles className="h-3.5 w-3.5" />
          Custom service
        </button>
      </div>

      {showCustomForm && (
        <div className="mt-4 rounded-xl border border-brand-100 bg-brand-50/40 p-3">
          <p className="mb-2 text-xs font-semibold text-brand-800">Add custom service (creates master entry on submit)</p>
          <div className="grid grid-cols-2 gap-2 sm:grid-cols-[1.2fr_1fr_1fr_72px_64px_auto] sm:items-end">
            <input
              type="text"
              placeholder="Service name"
              value={customDraft.name}
              onChange={(e) => setCustomDraft((d) => ({ ...d, name: e.target.value }))}
              className={compactInputClass}
            />
            <select
              value={customDraft.category_id}
              onChange={(e) => setCustomDraft((d) => ({ ...d, category_id: e.target.value }))}
              className={compactSelectClass}
            >
              <option value="">Category</option>
              {masterCategories.filter((c) => c.is_active !== false).map((c) => (
                <option key={c.id} value={c.id}>{c.name}</option>
              ))}
            </select>
            <input
              type="text"
              placeholder="Product"
              value={customDraft.product}
              onChange={(e) => setCustomDraft((d) => ({ ...d, product: e.target.value }))}
              className={compactInputClass}
            />
            <input
              type="number"
              min="0"
              placeholder={currencyLabel()}
              value={customDraft.price}
              onChange={(e) => setCustomDraft((d) => ({ ...d, price: e.target.value }))}
              className={compactInputClass}
            />
            <input
              type="number"
              min="1"
              placeholder="min"
              value={customDraft.duration}
              onChange={(e) => setCustomDraft((d) => ({ ...d, duration: e.target.value }))}
              className={compactInputClass}
            />
            <button
              type="button"
              onClick={addCustomService}
              className="rounded-lg bg-brand-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-brand-500"
            >
              Add
            </button>
          </div>
        </div>
      )}

      <div className="mt-4 rounded-xl border border-slate-200 bg-slate-50/60 p-3">
        <div className="mb-2 flex items-center justify-between gap-2">
          <p className="text-xs font-bold uppercase tracking-wider text-slate-500">
            Master services
            {!loadingMasters && (
              <span className="ml-1 font-medium normal-case tracking-normal text-slate-400">
                ({selectedServiceIds.size} services · {services.length} offerings)
              </span>
            )}
          </p>
          {loadingMasters && <Loader2 className="h-3.5 w-3.5 animate-spin text-brand-600" />}
        </div>
        <div className="relative mb-3">
          <Search className="pointer-events-none absolute left-2.5 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-slate-400" />
          <input
            type="text"
            value={search}
            onChange={(e) => setSearch(e.target.value)}
            placeholder="Filter services..."
            className="w-full rounded-lg border border-slate-200 bg-white py-1.5 pl-8 pr-3 text-xs focus:border-brand-400 focus:outline-none focus:ring-2 focus:ring-brand-500/20"
          />
        </div>
        {!loadingMasters && loadError && (
          <p className="mb-2 rounded-lg border border-rose-200 bg-rose-50 px-2 py-1.5 text-xs text-rose-700">
            {loadError}
          </p>
        )}
        {!loadingMasters && !loadError && filteredServices.length === 0 ? (
          <p className="py-4 text-center text-xs text-slate-500">
            No master services yet. Use <span className="font-semibold">Custom service</span> to add one during onboarding.
          </p>
        ) : (
          <div className="grid max-h-[28rem] grid-cols-1 gap-2 overflow-y-auto lg:grid-cols-2">
            {filteredServices.map((service) => {
              const serviceId = Number(service.id)
              const linked = linkedProductsOf(service)
              const selectedProducts = selectedByService.get(serviceId) ?? new Set()
              const serviceOnlySelected = hasServiceOnlyOffering(services, serviceId)
              const serviceSelected = selectedServiceIds.has(serviceId)

              return (
                <div
                  key={service.id}
                  className={`rounded-xl border p-3 transition ${
                    serviceSelected
                      ? 'border-brand-500 bg-brand-50/80 ring-1 ring-brand-500/30'
                      : 'border-slate-200 bg-white hover:border-brand-300'
                  }`}
                >
                  <label className="flex cursor-pointer items-start gap-2.5">
                    <input
                      type="checkbox"
                      checked={serviceOnlySelected}
                      onChange={() => toggleMasterService(service)}
                      className="mt-0.5 h-4 w-4 shrink-0 rounded border-slate-300 text-brand-600 focus:ring-brand-500"
                    />
                    <span className="min-w-0 flex-1">
                      <span className="block truncate text-xs font-semibold text-slate-800">{service.name}</span>
                      <span className="mt-0.5 flex flex-wrap items-center gap-x-2 text-[11px] text-slate-500">
                        <span>{service.duration_minutes}m</span>
                        <span>{money(service.default_price || 0)}</span>
                        {linked.length > 0 && (
                          <span>{selectedProducts.size}/{linked.length} products</span>
                        )}
                      </span>
                    </span>
                  </label>

                  {linked.length > 0 ? (
                    <div className="mt-2 space-y-1.5 border-t border-slate-200/80 pt-2 pl-6">
                      {linked.map((product) => {
                        const checked = selectedProducts.has(Number(product.id))
                        const price = resolveProductPrice(service, product.id)
                        return (
                          <label
                            key={product.id}
                            className={`flex cursor-pointer items-center justify-between gap-2 rounded-lg border px-2 py-1.5 text-[11px] transition ${
                              checked
                                ? 'border-brand-400 bg-white text-brand-900'
                                : 'border-transparent bg-white/60 text-slate-600 hover:border-slate-200'
                            }`}
                          >
                            <span className="flex min-w-0 items-center gap-2">
                              <input
                                type="checkbox"
                                checked={checked}
                                onChange={() => toggleServiceProduct(service, product)}
                                className="h-3.5 w-3.5 rounded border-slate-300 text-brand-600 focus:ring-brand-500"
                              />
                              <span className="truncate font-medium">{product.name}</span>
                            </span>
                            <span className="shrink-0 tabular-nums text-slate-500">
                              {money(price)}
                            </span>
                          </label>
                        )
                      })}
                    </div>
                  ) : null}
                </div>
              )
            })}
          </div>
        )}
      </div>

      <div className="mt-4">
        <div className="mb-2 flex items-center justify-between">
          <p className="text-xs font-bold uppercase tracking-wider text-slate-500">
            Selected offerings ({services.length})
          </p>
          {services.length > 0 && (
            <button
              type="button"
              onClick={() => setServices([])}
              className="text-[11px] font-medium text-slate-500 hover:text-rose-600"
            >
              Clear all
            </button>
          )}
        </div>

        {services.length === 0 ? (
          <div className="rounded-xl border border-dashed border-slate-300 bg-white py-8 text-center">
            <Scissors className="mx-auto mb-2 h-7 w-7 text-slate-300" />
            <p className="text-xs text-slate-500">Optional — select services and products above, or add a custom one.</p>
          </div>
        ) : (
          <div className="overflow-x-auto rounded-xl border border-slate-200 bg-white">
            <div className="min-w-[720px]">
              <div className="grid grid-cols-[minmax(120px,1.4fr)_minmax(90px,1fr)_minmax(110px,1.2fr)_72px_64px_32px] gap-2 border-b border-slate-100 bg-slate-50/80 px-2 py-2 text-[10px] font-bold uppercase tracking-wider text-slate-400">
                <span>Service</span>
                <span>Category</span>
                <span>Product</span>
                <span>Price {currencyLabel()}</span>
                <span>Min</span>
                <span />
              </div>
              <div className="divide-y divide-slate-100">
                {services.map((row, index) => {
                  const isCustom = row.is_custom || !row.service_id
                  const service = masterServices.find((s) => Number(s.id) === Number(row.service_id))
                  const productOptions = isCustom
                    ? []
                    : productsForService(service, masterProducts)
                  const usedProductIds = new Set(
                    services
                      .filter((r, i) => i !== index && Number(r.service_id) === Number(row.service_id))
                      .map((r) => Number(r.product_id))
                      .filter(Boolean),
                  )
                  return (
                    <div
                      key={`${row.service_id ?? 'custom'}-${row.product_id ?? 'p'}-${index}`}
                      className="grid grid-cols-[minmax(120px,1.4fr)_minmax(90px,1fr)_minmax(110px,1.2fr)_72px_64px_32px] items-center gap-2 px-2 py-2"
                    >
                      <div className="min-w-0">
                        {isCustom ? (
                          <input
                            type="text"
                            placeholder="Service name"
                            value={row.name}
                            onChange={(e) => updateRow(index, { name: e.target.value })}
                            className={compactInputClass}
                          />
                        ) : (
                          <select
                            className={compactSelectClass}
                            value={row.service_id ?? ''}
                            onChange={(e) => applyMasterDefaults(index, e.target.value)}
                          >
                            {masterServices.map((s) => (
                              <option key={s.id} value={s.id}>{s.name}</option>
                            ))}
                          </select>
                        )}
                      </div>

                      <div className="min-w-0">
                        <select
                          className={compactSelectClass}
                          value={row.category_id ?? ''}
                          onChange={(e) => updateRow(index, { category_id: e.target.value ? Number(e.target.value) : null })}
                        >
                          <option value="">Category</option>
                          {masterCategories.filter((c) => c.is_active !== false).map((c) => (
                            <option key={c.id} value={c.id}>{c.name}</option>
                          ))}
                        </select>
                      </div>

                      <div className="min-w-0">
                        {isCustom ? (
                          <input
                            type="text"
                            placeholder="Product"
                            value={row.product}
                            onChange={(e) => updateRow(index, { product: e.target.value })}
                            className={compactInputClass}
                          />
                        ) : (
                          <select
                            className={compactSelectClass}
                            value={row.product_id ?? ''}
                            onChange={(e) => applyProductSelection(index, row.service_id, e.target.value)}
                          >
                            <option value="">Service only</option>
                            {productOptions.map((p) => (
                              <option
                                key={p.id}
                                value={p.id}
                                disabled={usedProductIds.has(Number(p.id)) && Number(p.id) !== Number(row.product_id)}
                              >
                                {p.name} · {money(resolveProductPrice(service, p.id))}
                              </option>
                            ))}
                          </select>
                        )}
                      </div>

                      <input
                        type="number"
                        min="0"
                        value={row.price}
                        onChange={(e) => updateRow(index, { price: Number(e.target.value) })}
                        className={compactInputClass}
                      />
                      <input
                        type="number"
                        min="1"
                        value={row.duration}
                        onChange={(e) => updateRow(index, { duration: Number(e.target.value) })}
                        className={compactInputClass}
                      />

                      <button
                        type="button"
                        onClick={() => setServices((previous) => previous.filter((_, i) => i !== index))}
                        className="flex h-7 w-7 items-center justify-center rounded-lg text-slate-400 hover:bg-rose-50 hover:text-rose-600"
                        aria-label="Remove"
                      >
                        <Trash2 className="h-3.5 w-3.5" />
                      </button>
                    </div>
                  )
                })}
              </div>
            </div>
          </div>
        )}
      </div>

      <div className="mt-4 flex items-center justify-end">
        <button
          type="button"
          className="text-sm text-slate-500 hover:text-slate-700"
          onClick={skipForNow}
        >
          Skip for now
        </button>
      </div>

      {error ? <p className="mt-2 text-xs text-rose-600">{error}</p> : null}

      <p className="mt-3 text-[11px] text-slate-400">
        Service-only offerings use the service price. Adding a product creates a separate offering with that product&apos;s price.
      </p>
    </div>
  )
})

export default StepService
