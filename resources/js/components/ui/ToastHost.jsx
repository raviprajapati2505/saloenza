import React, { useCallback, useEffect, useState } from 'react'
import { AlertTriangle, CheckCircle2, X } from 'lucide-react'
import { subscribeToasts } from '../../stores/toast.js'

function ToastCard({ toast, onDismiss }) {
  useEffect(() => {
    const timer = setTimeout(onDismiss, toast.duration ?? 5000)
    return () => clearTimeout(timer)
  }, [onDismiss, toast.duration])

  const styles = {
    success: 'bg-emerald-50 border-emerald-200 text-emerald-800',
    warning: 'bg-amber-50 border-amber-200 text-amber-800',
    error: 'bg-rose-50 border-rose-200 text-rose-800',
  }

  const icons = {
    success: CheckCircle2,
    warning: AlertTriangle,
    error: AlertTriangle,
  }

  const Icon = icons[toast.type] ?? AlertTriangle

  return (
    <div
      role="alert"
      className={`flex max-w-md items-start gap-3 rounded-2xl border px-4 py-3.5 text-sm font-medium shadow-xl ${styles[toast.type] ?? styles.error}`}
    >
      <Icon className="mt-0.5 h-4 w-4 shrink-0 opacity-80" />
      <p className="flex-1 leading-relaxed">{toast.message}</p>
      <button
        type="button"
        onClick={onDismiss}
        className="shrink-0 opacity-50 transition-opacity hover:opacity-100"
        aria-label="Dismiss notification"
      >
        <X className="h-3.5 w-3.5" />
      </button>
    </div>
  )
}

export default function ToastHost() {
  const [toasts, setToasts] = useState([])

  useEffect(() => {
    return subscribeToasts((toast) => {
      setToasts((current) => [...current, toast].slice(-3))
    })
  }, [])

  const dismiss = useCallback((id) => {
    setToasts((current) => current.filter((toast) => toast.id !== id))
  }, [])

  if (toasts.length === 0) return null

  return (
    <div className="pointer-events-none fixed bottom-6 right-6 z-[200] flex w-[min(100vw-2rem,28rem)] flex-col gap-3">
      {toasts.map((toast) => (
        <div key={toast.id} className="pointer-events-auto">
          <ToastCard toast={toast} onDismiss={() => dismiss(toast.id)} />
        </div>
      ))}
    </div>
  )
}
