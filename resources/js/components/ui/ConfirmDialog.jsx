import React from 'react'
import BaseModal from './BaseModal.jsx'
import BaseButton from './BaseButton.jsx'
import { AlertTriangle, AlertCircle } from 'lucide-react'

export default function ConfirmDialog({
  open,
  title,
  message,
  confirmLabel = 'Confirm',
  cancelLabel = 'Cancel',
  variant = 'danger',
  onConfirm,
  onCancel,
  loading = false
}) {
  return (
    <BaseModal open={open} onClose={!loading ? onCancel : () => {}} size="md">
      <div className="sm:flex sm:items-start pt-2">
        <div className={`mx-auto flex h-12 w-12 flex-shrink-0 items-center justify-center rounded-full sm:mx-0 sm:h-10 sm:w-10 ${variant === 'danger' ? 'bg-rose-100' : 'bg-amber-100'}`}>
          {variant === 'danger' ? (
            <AlertTriangle className="h-6 w-6 text-rose-600" aria-hidden="true" />
          ) : (
            <AlertCircle className="h-6 w-6 text-amber-600" aria-hidden="true" />
          )}
        </div>
        <div className="mt-3 text-center sm:ml-4 sm:mt-0 sm:text-left">
          <h3 className="text-lg font-semibold leading-6 text-slate-900">
            {title}
          </h3>
          <div className="mt-2">
            <p className="text-sm text-slate-500 leading-relaxed">
              {message}
            </p>
          </div>
        </div>
      </div>
      
      <div className="mt-6 sm:mt-5 sm:flex sm:flex-row-reverse gap-3 border-t border-slate-100 pt-5">
        <BaseButton
          variant={variant}
          onClick={onConfirm}
          loading={loading}
          className="w-full sm:w-auto"
        >
          {confirmLabel}
        </BaseButton>
        <BaseButton
          variant="secondary"
          onClick={onCancel}
          disabled={loading}
          className="mt-3 w-full sm:mt-0 sm:w-auto"
        >
          {cancelLabel}
        </BaseButton>
      </div>
    </BaseModal>
  )
}
