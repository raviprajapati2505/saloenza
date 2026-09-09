/**
 * Lightweight global toast bus for use outside React (e.g. axios interceptors).
 */

let nextId = 1
const listeners = new Set()

/** @typedef {{ id: number, message: string, type: 'success'|'error'|'warning', duration?: number }} ToastItem */

/** @type {{ message: string, at: number } | null} */
let lastToast = null

const DEDUPE_MS = 2500

/**
 * @param {(toast: ToastItem) => void} listener
 * @returns {() => void}
 */
export function subscribeToasts(listener) {
  listeners.add(listener)
  return () => listeners.delete(listener)
}

/**
 * @param {string} message
 * @param {'success'|'error'|'warning'} [type='error']
 * @param {{ duration?: number }} [options]
 */
export function pushToast(message, type = 'error', options = {}) {
  const trimmed = String(message || '').trim()
  if (!trimmed) return

  const now = Date.now()
  if (lastToast && lastToast.message === trimmed && now - lastToast.at < DEDUPE_MS) {
    return
  }
  lastToast = { message: trimmed, at: now }

  /** @type {ToastItem} */
  const toast = {
    id: nextId++,
    message: trimmed,
    type,
    duration: options.duration ?? 5000,
  }

  listeners.forEach((listener) => listener(toast))
}

export function clearToastDedupeCache() {
  lastToast = null
}
