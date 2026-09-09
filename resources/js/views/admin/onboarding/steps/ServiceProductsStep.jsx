import React, { useCallback, useEffect, useMemo, useState } from 'react'
import { Controller, useFieldArray, useFormContext } from 'react-hook-form'
import { Scissors, Trash2, Search, Loader2, Sparkles } from 'lucide-react'
import { useAuthStore } from '../../../../stores/auth'
import { fetchMasterList } from '../../../../lib/apiHelpers'
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
} from '../../../../lib/serviceProductHelpers'
import { currencySymbol, formatMoneyDefault } from '../../../../lib/tenantFormatting.js'

const money = formatMoneyDefault
const currencyLabel = () => currencySymbol(null)

const compactSelectClass =
  'w-full min-w-0 rounded-lg border border-slate-200 bg-white px-2 py-1.5 text-xs text-slate-800 focus:border-brand-400 focus:outline-none focus:ring-2 focus:ring-brand-500/20'
const compactInputClass =
  'w-full min-w-0 rounded-lg border border-slate-200 bg-white px-2 py-1.5 text-xs text-slate-800 focus:border-brand-400 focus:outline-none focus:ring-2 focus:ring-brand-500/20'

function createCustomRow(overrides = {}) {
  return {
    service_id: null,
    service: '',
    product_id: null,
    product: DEFAULT_PRODUCT_NAME,
    price: 0,
    duration: 30,
    active: true,
    is_custom: true,
    ...overrides,
  }
}

export default function ServiceProductsStep() {
  const auth = useAuthStore()
  const {
    control,
    register,
    getValues,
    setValue,
    watch,
    formState: { errors },
  } = useFormContext()

  const { fields, append, remove } = useFieldArray({
    control,
    name: 'service_products',
  })

  const [masterServices, setMasterServices] = useState([])
  const [masterProducts, setMasterProducts] = useState([])
  const [loadingMasters, setLoadingMasters] = useState(true)
  const [loadError, setLoadError] = useState('')
  const [search, setSearch] = useState('')
  const [showCustomForm, setShowCustomForm] = useState(false)
  const [customDraft, setCustomDraft] = useState({
    service: '',
    product: DEFAULT_PRODUCT_NAME,
    price: '',
    duration: '30',
  })

  const spErrors = errors.service_products || []
  const rows = watch('service_products') || []

  useEffect(() => {
    let active = true
    async function loadMasters() {
      setLoadingMasters(true)
      setLoadError('')
      try {
        const [services, products] = await Promise.all([
          fetchMasterList('/v1/services', 'services'),
          fetchMasterList('/v1/products', 'products'),
        ])
        if (!active) return
        setMasterServices(services)
        setMasterProducts(products)
      } catch (error) {
        if (!active) return
        setMasterServices([])
        setMasterProducts([])
        const message = error?.response?.data?.message
          || error?.message
          || 'Unable to load master services and products.'
        setLoadError(message)
      } finally {
        if (active) setLoadingMasters(false)
      }
    }
    if (auth.token) void loadMasters()
    return () => { active = false }
  }, [auth.token])

  const filteredServices = useMemo(() => {
    const q = search.trim().toLowerCase()
    const activeOnly = masterServices.filter((s) => s.is_active !== false)
    if (!q) return activeOnly
    return activeOnly.filter((s) => s.name?.toLowerCase().includes(q))
  }, [masterServices, search])

  const selectedByService = useMemo(() => selectedProductIdsByService(rows), [rows])

  const selectedServiceIds = useMemo(() => {
    const ids = new Set()
    for (const row of rows) {
      if (row?.service_id) ids.add(Number(row.service_id))
    }
    return ids
  }, [rows])

  const offeringCount = useMemo(
    () => rows.filter((r) => r?.service_id || r?.is_custom).length,
    [rows],
  )

  const replaceRows = useCallback((nextRows) => {
    setValue('service_products', nextRows, { shouldDirty: true, shouldTouch: true })
  }, [setValue])

  const toggleMasterService = useCallback((service) => {
    const current = getValues('service_products') || []
    const serviceId = Number(service.id)

    if (hasServiceOnlyOffering(current, serviceId)) {
      replaceRows(current.filter((row) => !(
        Number(row.service_id) === serviceId && (row.product_id == null || row.product_id === '')
      )))
      return
    }

    // Service-only offering uses master service price (product optional).
    replaceRows([...current, ...createOfferingsForService(service, masterProducts)])
  }, [getValues, masterProducts, replaceRows])

  const toggleServiceProduct = useCallback((service, product) => {
    const current = getValues('service_products') || []
    const index = current.findIndex((row) => sameOffering(row, service.id, product.id))

    if (index >= 0) {
      remove(index)
      return
    }

    append(createOfferingFromServiceProduct(service, product))
  }, [append, getValues, remove])

  const addCustomService = () => {
    const name = customDraft.service.trim()
    if (!name) return
    append(createCustomRow({
      service: name,
      product: customDraft.product.trim() || DEFAULT_PRODUCT_NAME,
      price: Number(customDraft.price) || 0,
      duration: Number(customDraft.duration) || 30,
    }))
    setCustomDraft({ service: '', product: DEFAULT_PRODUCT_NAME, price: '', duration: '30' })
    setShowCustomForm(false)
  }

  const applyMasterDefaults = (index, serviceId) => {
    const service = masterServices.find((s) => Number(s.id) === Number(serviceId))
    if (!service) return
    const next = createOfferingFromServiceProduct(service, null)
    setValue(`service_products.${index}.service_id`, next.service_id)
    setValue(`service_products.${index}.service`, next.service)
    setValue(`service_products.${index}.product_id`, null)
    setValue(`service_products.${index}.product`, '')
    setValue(`service_products.${index}.price`, next.price)
    setValue(`service_products.${index}.duration`, next.duration)
    setValue(`service_products.${index}.is_custom`, false)
  }

  const applyProductSelection = (index, serviceId, productId) => {
    const service = masterServices.find((s) => Number(s.id) === Number(serviceId))
    const options = productsForService(service, masterProducts)
    const product = options.find((p) => Number(p.id) === Number(productId))
    const id = productId ? Number(productId) : null

    if (id) {
      const current = getValues('service_products') || []
      const duplicate = current.some(
        (row, i) => i !== index && sameOffering(row, serviceId, id),
      )
      if (duplicate) return
    } else {
      const current = getValues('service_products') || []
      const duplicate = current.some(
        (row, i) => i !== index && sameOffering(row, serviceId, null),
      )
      if (duplicate) return
    }

    setValue(`service_products.${index}.product_id`, id)
    setValue(`service_products.${index}.product`, product?.name ?? '')
    if (service) {
      setValue(`service_products.${index}.price`, resolveProductPrice(service, id))
    }
  }

  return (
    <div className="space-y-5">
      <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
        <div className="flex items-start gap-3">
          <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-amber-50 text-amber-600">
            <Scissors className="h-5 w-5" />
          </div>
          <div>
            <h2 className="text-lg font-semibold text-slate-900">Salon Services</h2>
            <p className="text-sm text-slate-500">
              Select a service for its base price. Optionally add products — each gets its own price.
            </p>
          </div>
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
        <div className="rounded-xl border border-brand-100 bg-brand-50/40 p-3">
          <p className="mb-2 text-xs font-semibold text-brand-800">Add custom service (creates master entry on submit)</p>
          <div className="grid grid-cols-2 gap-2 sm:grid-cols-[1.4fr_1fr_72px_64px_auto] sm:items-end">
            <input
              type="text"
              placeholder="Service name"
              value={customDraft.service}
              onChange={(e) => setCustomDraft((d) => ({ ...d, service: e.target.value }))}
              className={compactInputClass}
            />
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

      <div className="rounded-xl border border-slate-200 bg-slate-50/60 p-3">
        <div className="mb-2 flex items-center justify-between gap-2">
          <p className="text-xs font-bold uppercase tracking-wider text-slate-500">
            Master services
            {!loadingMasters && (
              <span className="ml-1 font-medium normal-case tracking-normal text-slate-400">
                ({selectedServiceIds.size} services · {offeringCount} offerings)
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
              const serviceOnlySelected = hasServiceOnlyOffering(rows, serviceId)
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

      <div>
        <div className="mb-2 flex items-center justify-between">
          <p className="text-xs font-bold uppercase tracking-wider text-slate-500">
            Selected offerings ({fields.length})
          </p>
          {fields.length > 0 && (
            <button
              type="button"
              onClick={() => setValue('service_products', [])}
              className="text-[11px] font-medium text-slate-500 hover:text-rose-600"
            >
              Clear all
            </button>
          )}
        </div>

        {fields.length === 0 ? (
          <div className="rounded-xl border border-dashed border-slate-300 bg-white py-8 text-center">
            <Scissors className="mx-auto mb-2 h-7 w-7 text-slate-300" />
            <p className="text-xs text-slate-500">Optional — select services and products above, or add a custom one.</p>
          </div>
        ) : (
          <div className="overflow-x-auto rounded-xl border border-slate-200 bg-white">
            <div className="min-w-[640px]">
              <div className="grid grid-cols-[minmax(140px,1.6fr)_minmax(110px,1.2fr)_72px_64px_44px_32px] gap-2 border-b border-slate-100 bg-slate-50/80 px-2 py-2 text-[10px] font-bold uppercase tracking-wider text-slate-400">
                <span>Service</span>
                <span>Product</span>
                <span>Price {currencyLabel()}</span>
                <span>Min</span>
                <span className="text-center">On</span>
                <span />
              </div>
              <div className="divide-y divide-slate-100">
                {fields.map((field, index) => {
                  const row = rows[index] || {}
                  const rowErrors = spErrors[index] || {}
                  const isCustom = row.is_custom || !row.service_id
                  const service = masterServices.find((s) => Number(s.id) === Number(row.service_id))
                  const productOptions = isCustom
                    ? []
                    : productsForService(service, masterProducts)
                  const usedProductIds = new Set(
                    rows
                      .filter((r, i) => i !== index && Number(r.service_id) === Number(row.service_id))
                      .map((r) => Number(r.product_id))
                      .filter(Boolean),
                  )

                  return (
                    <div
                      key={field.id}
                      className="grid grid-cols-[minmax(140px,1.6fr)_minmax(110px,1.2fr)_72px_64px_44px_32px] items-center gap-2 px-2 py-2"
                    >
                      <div className="min-w-0">
                        {isCustom ? (
                          <>
                            <input
                              type="text"
                              placeholder="Service name"
                              className={compactInputClass}
                              {...register(`service_products.${index}.service`)}
                            />
                            <input type="hidden" {...register(`service_products.${index}.service_id`)} />
                          </>
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
                        {rowErrors.service?.message && (
                          <p className="mt-0.5 text-[10px] text-rose-500">{rowErrors.service.message}</p>
                        )}
                      </div>

                      <div className="min-w-0">
                        {isCustom ? (
                          <input
                            type="text"
                            placeholder="Product"
                            className={compactInputClass}
                            {...register(`service_products.${index}.product`)}
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
                        {!isCustom && (
                          <input type="hidden" {...register(`service_products.${index}.product`)} />
                        )}
                        {rowErrors.product?.message && (
                          <p className="mt-0.5 text-[10px] text-rose-500">{rowErrors.product.message}</p>
                        )}
                      </div>

                      <div className="min-w-0">
                        <input
                          type="number"
                          min="0"
                          className={compactInputClass}
                          {...register(`service_products.${index}.price`)}
                        />
                        {rowErrors.price?.message && (
                          <p className="mt-0.5 text-[10px] text-rose-500">{rowErrors.price.message}</p>
                        )}
                      </div>
                      <div className="min-w-0">
                        <input
                          type="number"
                          min="1"
                          className={compactInputClass}
                          {...register(`service_products.${index}.duration`)}
                        />
                        {rowErrors.duration?.message && (
                          <p className="mt-0.5 text-[10px] text-rose-500">{rowErrors.duration.message}</p>
                        )}
                      </div>

                      <div className="flex justify-center">
                        <Controller
                          name={`service_products.${index}.active`}
                          control={control}
                          render={({ field: f }) => (
                            <input
                              type="checkbox"
                              checked={Boolean(f.value)}
                              onChange={(e) => f.onChange(e.target.checked)}
                              className="h-4 w-4 rounded border-slate-300 text-brand-600 focus:ring-brand-500"
                            />
                          )}
                        />
                      </div>

                      <button
                        type="button"
                        onClick={() => remove(index)}
                        className="flex h-7 w-7 items-center justify-center rounded-lg text-slate-400 hover:bg-rose-50 hover:text-rose-600"
                        aria-label="Remove"
                      >
                        <Trash2 className="h-3.5 w-3.5" />
                      </button>

                      <input type="hidden" {...register(`service_products.${index}.is_custom`)} />
                      <input type="hidden" {...register(`service_products.${index}.service_id`)} />
                      <input type="hidden" {...register(`service_products.${index}.product_id`)} />
                      {!isCustom && <input type="hidden" {...register(`service_products.${index}.service`)} />}
                    </div>
                  )
                })}
              </div>
            </div>
          </div>
        )}
      </div>

      <p className="text-[11px] text-slate-400">
        Service-only offerings use the service price. Adding a product creates a separate offering with that product&apos;s price.
      </p>
    </div>
  )
}
