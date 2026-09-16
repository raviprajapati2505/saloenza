import React, { useEffect, useState } from 'react'
import { getPlatformBranding } from '../../stores/platformBranding.js'
import {
  clearDeferredInstallPrompt,
  getDeferredInstallPrompt,
  subscribeToInstallAvailable,
} from '../../pwa/deferredInstallPrompt.js'

const DISMISS_KEY = 'saloenza_pwa_install_dismissed_at'
const DISMISS_DAYS = 7
const DISMISS_MS = DISMISS_DAYS * 24 * 60 * 60 * 1000
const PWA_ICON_SRC = '/icons/icon-512x512.png'

function isStandalone() {
  return (
    window.matchMedia?.('(display-mode: standalone)')?.matches ||
    window.navigator.standalone === true
  )
}

function isIos() {
  const ua = navigator.userAgent || ''
  return (
    /iPad|iPhone|iPod/.test(ua) ||
    (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1)
  )
}

function isMobile() {
  return /Android|iPhone|iPad|iPod|Mobile/i.test(navigator.userAgent || '') || isIos()
}

function isDismissedRecently() {
  const raw = localStorage.getItem(DISMISS_KEY)
  if (!raw) return false
  const dismissedAt = Number(raw)
  if (!Number.isFinite(dismissedAt)) return false
  return Date.now() - dismissedAt < DISMISS_MS
}

export default function PwaInstallPrompt() {
  const [deferredPrompt, setDeferredPrompt] = useState(() => getDeferredInstallPrompt())
  const [visible, setVisible] = useState(false)
  const [iosHint, setIosHint] = useState(false)

  useEffect(() => {
    if (isStandalone()) return

    const showInstallBanner = (prompt) => {
      localStorage.removeItem(DISMISS_KEY)
      setDeferredPrompt(prompt)
      setIosHint(false)
      setVisible(true)
    }

    const existingPrompt = getDeferredInstallPrompt()
    if (existingPrompt && !isDismissedRecently()) {
      showInstallBanner(existingPrompt)
    }

    const unsubscribe = subscribeToInstallAvailable(showInstallBanner)

    const timer = window.setTimeout(() => {
      if (isStandalone() || isDismissedRecently()) return

      const prompt = getDeferredInstallPrompt()
      if (prompt) {
        showInstallBanner(prompt)
        return
      }

      if (isIos()) {
        setIosHint(true)
        setVisible(true)
      } else if (isMobile()) {
        setVisible(true)
      }
    }, 1500)

    return () => {
      window.clearTimeout(timer)
      unsubscribe()
    }
  }, [])

  if (!visible) return null

  const branding = getPlatformBranding()
  const portalName = branding?.portal_name || 'Saloenza'
  const primaryColor = branding?.primary_color || '#cc0f67'

  const dismiss = () => {
    localStorage.setItem(DISMISS_KEY, String(Date.now()))
    setVisible(false)
  }

  const install = async () => {
    const prompt = deferredPrompt ?? getDeferredInstallPrompt()
    if (!prompt) return

    prompt.prompt()
    const choice = await prompt.userChoice.catch(() => null)
    setDeferredPrompt(null)
    clearDeferredInstallPrompt()
    setVisible(false)

    if (choice?.outcome === 'accepted') {
      localStorage.removeItem(DISMISS_KEY)
    } else {
      localStorage.setItem(DISMISS_KEY, String(Date.now()))
    }
  }

  const canInstall = Boolean(deferredPrompt ?? getDeferredInstallPrompt())

  const helpText = canInstall
    ? `Add ${portalName} to your home screen for quick access like a mobile app.`
    : iosHint
      ? 'Tap Share, then Add to Home Screen to save the app icon.'
      : 'Open the browser menu and choose Install app / Add to Home screen.'

  return (
    <div className="fixed inset-x-3 bottom-20 z-[70] md:bottom-6 md:left-auto md:right-6 md:max-w-sm">
      <div className="rounded-2xl border border-white/10 bg-slate-950/95 p-4 text-white shadow-xl backdrop-blur">
        <div className="flex items-start gap-3">
          <img
            src={PWA_ICON_SRC}
            alt={`${portalName} app icon`}
            className="h-14 w-14 shrink-0 overflow-hidden rounded-2xl border border-white/15 bg-black object-cover"
            width={56}
            height={56}
          />
          <div className="min-w-0 flex-1">
            <p className="text-sm font-semibold">Install {portalName}</p>
            <p className="mt-1 text-xs leading-relaxed text-slate-300">{helpText}</p>
            <div className="mt-3 flex items-center gap-2">
              {canInstall ? (
                <button type="button" onClick={install} className="rounded-lg px-3 py-1.5 text-xs font-semibold text-white" style={{ backgroundColor: primaryColor }}>
                  Install
                </button>
              ) : null}
              <button type="button" onClick={dismiss} className="rounded-lg px-3 py-1.5 text-xs font-medium text-slate-300">
                Not now
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>
  )
}
