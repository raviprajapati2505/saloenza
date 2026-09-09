const INSTALL_AVAILABLE = 'pwa-install-available'

export function getDeferredInstallPrompt() {
  return window.__pwaDeferredInstallPrompt ?? null
}

export function captureDeferredInstallPrompt(event) {
  event.preventDefault()
  window.__pwaDeferredInstallPrompt = event
  window.dispatchEvent(new Event(INSTALL_AVAILABLE))
}

export function clearDeferredInstallPrompt() {
  window.__pwaDeferredInstallPrompt = null
}

export function subscribeToInstallAvailable(callback) {
  const onNative = (event) => callback(event)
  const onCaptured = () => {
    const prompt = getDeferredInstallPrompt()
    if (prompt) callback(prompt)
  }

  window.addEventListener('beforeinstallprompt', onNative)
  window.addEventListener(INSTALL_AVAILABLE, onCaptured)

  return () => {
    window.removeEventListener('beforeinstallprompt', onNative)
    window.removeEventListener(INSTALL_AVAILABLE, onCaptured)
  }
}
