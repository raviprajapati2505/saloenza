import React from 'react'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import PwaInstallPrompt from '../PwaInstallPrompt.jsx'
import { clearDeferredInstallPrompt, captureDeferredInstallPrompt } from '../../../pwa/deferredInstallPrompt.js'

vi.mock('../../../stores/platformBranding.js', () => ({
  getPlatformBranding: () => ({
    portal_name: 'Saloenza',
    primary_color: '#cc0f67',
    logo_url: '/branding/wrong-logo.png',
  }),
}))

describe('PwaInstallPrompt', () => {
  beforeEach(() => {
    localStorage.clear()
    clearDeferredInstallPrompt()
    vi.stubGlobal('matchMedia', vi.fn(() => ({ matches: false })))
    Object.defineProperty(window.navigator, 'standalone', {
      configurable: true,
      value: false,
    })
  })

  it('shows Install button when prompt was captured before mount', async () => {
    captureDeferredInstallPrompt({
      preventDefault: vi.fn(),
      prompt: vi.fn(),
      userChoice: Promise.resolve({ outcome: 'accepted' }),
    })

    render(<PwaInstallPrompt />)

    await waitFor(() => {
      expect(screen.getByRole('button', { name: /install/i })).toBeInTheDocument()
    })

    const icon = screen.getByAltText(/saloenza app icon/i)
    expect(icon).toHaveAttribute('src', '/icons/icon-512x512.png')
    expect(icon).toHaveClass('bg-black')
    expect(icon).not.toHaveAttribute('style')
  })

  it('shows Install button when beforeinstallprompt fires after mount', async () => {
    render(<PwaInstallPrompt />)

    window.dispatchEvent(
      new CustomEvent('beforeinstallprompt', {
        detail: {},
      }),
    )

    captureDeferredInstallPrompt({
      preventDefault: vi.fn(),
      prompt: vi.fn(),
      userChoice: Promise.resolve({ outcome: 'accepted' }),
    })

    await waitFor(() => {
      expect(screen.getByRole('button', { name: /install/i })).toBeInTheDocument()
    })
  })

  it('hides the banner when Not now is clicked', async () => {
    captureDeferredInstallPrompt({
      preventDefault: vi.fn(),
      prompt: vi.fn(),
      userChoice: Promise.resolve({ outcome: 'dismissed' }),
    })

    render(<PwaInstallPrompt />)

    await waitFor(() => {
      expect(screen.getByRole('button', { name: /not now/i })).toBeInTheDocument()
    })

    fireEvent.click(screen.getByRole('button', { name: /not now/i }))

    expect(screen.queryByText(/install saloenza/i)).not.toBeInTheDocument()
  })
})
