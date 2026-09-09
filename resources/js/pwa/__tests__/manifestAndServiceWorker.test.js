import { describe, expect, it } from 'vitest'
import { readFileSync } from 'node:fs'
import { resolve } from 'node:path'

const root = resolve(process.cwd())

describe('PWA manifest and service worker', () => {
  it('manifest is installable standalone PWA with required fields', () => {
    const manifest = JSON.parse(
      readFileSync(resolve(root, 'public/manifest.json'), 'utf8'),
    )

    expect(manifest.name).toBeTruthy()
    expect(manifest.short_name).toBeTruthy()
    expect(manifest.start_url).toMatch(/^\//)
    expect(manifest.scope).toBe('/')
    expect(manifest.display).toBe('standalone')
    expect(manifest.theme_color).toBeTruthy()
    expect(manifest.background_color).toBeTruthy()
    expect(manifest.icons?.length).toBeGreaterThanOrEqual(4)

    const purposes = new Set()
    for (const icon of manifest.icons) {
      expect(icon.src).toMatch(/^\/icons\//)
      expect(icon.sizes).toBeTruthy()
      expect(icon.type).toBe('image/png')
      expect(icon.purpose === 'any' || icon.purpose === 'maskable').toBe(true)
      purposes.add(icon.purpose)
    }

    expect(purposes.has('any')).toBe(true)
    expect(purposes.has('maskable')).toBe(true)
  })

  it('service worker excludes API routes from cache and supports offline navigation', () => {
    const sw = readFileSync(resolve(root, 'public/sw.js'), 'utf8')

    expect(sw).toContain("includes('/api/')")
    expect(sw).toContain('return;')
    expect(sw).toContain("event.request.mode === 'navigate'")
    expect(sw).toContain('caches.match')
    expect(sw).toContain('skipWaiting')
    expect(sw).toContain('clients.claim')
    expect(sw).toContain('/manifest.json')
  })

  it('app shell references manifest and mobile viewport meta for PWA', () => {
    const blade = readFileSync(resolve(root, 'resources/views/app.blade.php'), 'utf8')

    expect(blade).toContain('manifest.json')
    expect(blade).toContain('__pwaDeferredInstallPrompt')
    expect(blade).toContain('beforeinstallprompt')
    expect(blade).toContain('viewport')
    expect(blade).toContain('icon-512x512.png')
    expect(blade).toContain('icon-180x180.png')
    expect(blade).toMatch(/apple-mobile-web-app|mobile-web-app|theme-color/)
  })
})
