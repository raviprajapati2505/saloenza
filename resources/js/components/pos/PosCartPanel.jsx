import React from 'react'
import { Minus, Plus, Trash2 } from 'lucide-react'
import BaseButton from '../ui/BaseButton.jsx'
import BaseSelect from '../ui/BaseSelect.jsx'
import { PAYMENT_METHODS } from '../../lib/appointmentPayments.js'
import {
  cartGrandTotal,
  cartLineKey,
  cartSubtotal,
  lineLabel,
  removeCartLine,
  updateCartQuantity,
} from '../../lib/posCart.js'

export default function PosCartPanel({
  cart,
  onCartChange,
  fmt,
  customerName,
  onCustomerNameChange,
  customerPhone,
  onCustomerPhoneChange,
  discount,
  onDiscountChange,
  collectPayment,
  onCollectPaymentChange,
  paymentMethod,
  onPaymentMethodChange,
  amountPaid,
  onAmountPaidChange,
  onCheckout,
  checkingOut = false,
  canCheckout = true,
  staff = [],
  lockStaff = false,
  defaultStaffId = '',
}) {
  const subtotal = cartSubtotal(cart)
  const total = cartGrandTotal(cart, discount)

  const setStaffOnLine = (key, staffId) => {
    const member = staff.find((s) => String(s.id) === String(staffId))
    onCartChange(cart.map((row) => {
      if (cartLineKey(row) !== key || row.kind !== 'service') return row
      return {
        ...row,
        staff_id: staffId ? Number(staffId) : null,
        staff_name: member?.name ?? null,
      }
    }))
  }

  return (
    <div className="rounded-2xl border border-slate-200 bg-white flex flex-col min-h-[420px]">
      <div className="p-4 border-b border-slate-100">
        <h3 className="text-sm font-black uppercase tracking-wider text-slate-700">Cart</h3>
        <p className="text-xs text-slate-400 mt-0.5">{cart.length} line{cart.length === 1 ? '' : 's'}</p>
      </div>

      <div className="flex-1 overflow-y-auto p-3 space-y-2 scroll-touch">
        {cart.length === 0 ? (
          <p className="text-sm text-slate-400 text-center py-10">Add services or retail products.</p>
        ) : cart.map((line) => {
          const key = cartLineKey(line)
          const lineTotal = (Number(line.price) || 0) * (Number(line.quantity) || 1)
          return (
            <div key={key} className="rounded-xl border border-slate-200 p-3 space-y-2">
              <div className="flex items-start justify-between gap-2">
                <div className="min-w-0">
                  <p className="font-semibold text-slate-900 text-sm">{lineLabel(line)}</p>
                  {line.kind === 'service' && staff.length > 0 && (
                    <div className="mt-2">
                      <BaseSelect
                        label="Staff"
                        value={line.staff_id ? String(line.staff_id) : (defaultStaffId ? String(defaultStaffId) : '')}
                        onChange={(e) => setStaffOnLine(key, e.target.value)}
                        disabled={lockStaff}
                        options={[
                          { value: '', label: 'Any staff' },
                          ...staff.map((s) => ({ value: String(s.id), label: s.name })),
                        ]}
                      />
                    </div>
                  )}
                </div>
                <button
                  type="button"
                  onClick={() => onCartChange(removeCartLine(cart, key))}
                  className="p-1.5 rounded-lg text-rose-500 hover:bg-rose-50"
                  aria-label="Remove"
                >
                  <Trash2 className="w-4 h-4" />
                </button>
              </div>
              <div className="flex items-center justify-between gap-3">
                <div className="inline-flex items-center rounded-lg border border-slate-200">
                  <button
                    type="button"
                    className="p-2 text-slate-500 hover:bg-slate-50"
                    onClick={() => onCartChange(updateCartQuantity(cart, key, line.quantity - 1))}
                  >
                    <Minus className="w-3.5 h-3.5" />
                  </button>
                  <span className="px-2 text-sm font-bold tabular-nums">{line.quantity}</span>
                  <button
                    type="button"
                    className="p-2 text-slate-500 hover:bg-slate-50"
                    onClick={() => onCartChange(updateCartQuantity(cart, key, line.quantity + 1))}
                  >
                    <Plus className="w-3.5 h-3.5" />
                  </button>
                </div>
                <p className="font-bold text-slate-800 tabular-nums">{fmt.money(lineTotal)}</p>
              </div>
            </div>
          )
        })}
      </div>

      <div className="p-4 border-t border-slate-100 space-y-3 bg-slate-50/60">
        <div className="grid grid-cols-2 gap-2">
          <input
            type="text"
            value={customerName}
            onChange={(e) => onCustomerNameChange(e.target.value)}
            placeholder="Customer name"
            className="rounded-xl border border-slate-200 px-3 py-2 text-sm"
          />
          <input
            type="tel"
            value={customerPhone}
            onChange={(e) => onCustomerPhoneChange(e.target.value)}
            placeholder="Phone (optional)"
            className="rounded-xl border border-slate-200 px-3 py-2 text-sm"
          />
        </div>
        <div className="flex items-center justify-between text-sm">
          <span className="text-slate-500">Subtotal</span>
          <span className="font-semibold">{fmt.money(subtotal)}</span>
        </div>
        <div className="flex items-center gap-2">
          <span className="text-sm text-slate-500 shrink-0">Discount</span>
          <input
            type="number"
            min="0"
            step="0.01"
            value={discount}
            onChange={(e) => onDiscountChange(e.target.value)}
            className="flex-1 rounded-xl border border-slate-200 px-3 py-2 text-sm text-right"
          />
        </div>
        <div className="flex items-center justify-between">
          <span className="font-bold text-slate-800">Total</span>
          <span className="text-xl font-black text-brand-700">{fmt.money(total)}</span>
        </div>
        <label className="flex items-center gap-2 text-sm font-medium text-slate-700">
          <input
            type="checkbox"
            checked={collectPayment}
            onChange={(e) => onCollectPaymentChange(e.target.checked)}
            className="rounded border-slate-300 text-brand-600"
          />
          Collect payment now
        </label>
        {collectPayment && (
          <div className="grid grid-cols-2 gap-2">
            <BaseSelect
              label="Method"
              value={paymentMethod}
              onChange={(e) => onPaymentMethodChange(e.target.value)}
              options={PAYMENT_METHODS.map((m) => ({ value: m.value, label: m.label }))}
            />
            <div>
              <label className="block text-sm font-medium text-slate-700 mb-1.5">Amount</label>
              <input
                type="number"
                min="0.01"
                step="0.01"
                value={amountPaid}
                onChange={(e) => onAmountPaidChange(e.target.value)}
                className="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm"
              />
            </div>
          </div>
        )}
        <BaseButton
          className="w-full"
          onClick={onCheckout}
          disabled={!canCheckout || cart.length === 0 || checkingOut}
          loading={checkingOut}
        >
          Complete checkout
        </BaseButton>
      </div>
    </div>
  )
}
