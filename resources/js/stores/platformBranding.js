import { API_BASE_URL } from '../lib/api.js'
import { applyTenantBrandingTheme } from '../lib/tenantBranding.js'

let branding = null
let loadPromise = null

function updateDocumentMeta(nextBranding) {
  if (!nextBranding) return

  const themeMeta = document.querySelector('meta[name="theme-color"]')
  if (themeMeta && nextBranding.primary_color) {
    themeMeta.setAttribute('content', nextBranding.primary_color)
  }

  if (nextBranding.portal_name) {
    document.title = nextBranding.portal_name
  }
}

export function getPlatformBranding() {
  return branding
}

export function setPlatformBranding(nextBranding) {
  branding = nextBranding || null
  if (branding) {
    applyTenantBrandingTheme(branding)
    updateDocumentMeta(branding)
  }
}

export function hydratePlatformBranding() {
  if (branding) {
    return Promise.resolve(branding)
  }

  if (!loadPromise) {
    loadPromise = fetch(`${API_BASE_URL}/v1/public/platform-branding`, {
      headers: { Accept: 'application/json' },
    })
      .then(async (response) => {
        if (!response.ok) {
          throw new Error('Unable to load platform branding.')
        }
        const payload = await response.json()
        setPlatformBranding(payload?.data || null)
        return branding
      })
      .catch(() => {
        loadPromise = null
        return null
      })
  }

  return loadPromise
}
