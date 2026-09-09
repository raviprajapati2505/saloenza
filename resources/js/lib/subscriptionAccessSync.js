/** Allows API interceptors to push subscription access mode into the auth store. */
let syncHandler = null

export function registerSubscriptionAccessSync(handler) {
  syncHandler = handler
}

export function syncSubscriptionAccessFromApi(mode) {
  if (!mode || typeof mode !== 'string') return
  syncHandler?.(mode)
}
