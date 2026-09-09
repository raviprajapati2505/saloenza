import { API_BASE_URL } from '../lib/api.js'

const FALLBACK_MODULES = [
  { key: 'appointments', label: 'Appointments' },
  { key: 'customers', label: 'Customers' },
  { key: 'staff', label: 'Staff Management' },
  { key: 'billing', label: 'Billing' },
  { key: 'analytics', label: 'Analytics & Dashboard' },
  { key: 'inventory', label: 'Inventory' },
  { key: 'catalog', label: 'Catalog & Services' },
  { key: 'roles', label: 'Roles & Permissions' },
  { key: 'settings', label: 'Settings' },
  { key: 'queue', label: 'Live Queue' },
]

const FALLBACK_NAV_MODULE_MAP = {
  '/analytics': 'analytics',
  '/reports': 'analytics',
  '/appointments': 'appointments',
  '/pos': 'appointments',
  '/queue': 'queue',
  '/staff/earnings': 'staff',
  '/booking-links': 'appointments',
  '/customers': 'customers',
  '/staff': 'staff',
  '/inventory': 'inventory',
  '/billing': 'billing',
  '/categories': 'catalog',
  '/catalog': 'catalog',
  '/roles': 'roles',
  '/assign-permissions': 'roles',
  '/settings': 'settings',
  '/branches': 'settings',
}

let catalog = null
let loadPromise = null

export function setModuleCatalog(payload) {
  if (payload?.modules?.length) {
    catalog = payload
  }
}

export function getSubscriptionModuleCatalog() {
  return catalog?.modules ?? FALLBACK_MODULES
}

export function getNavModuleMap() {
  return catalog?.nav_module_map ?? FALLBACK_NAV_MODULE_MAP
}

export function hydrateModuleCatalog() {
  if (catalog) {
    return Promise.resolve(catalog)
  }

  if (!loadPromise) {
    loadPromise = fetch(`${API_BASE_URL}/v1/public/subscription-modules`, {
      headers: { Accept: 'application/json' },
    })
      .then(async (response) => {
        if (!response.ok) {
          throw new Error('Unable to load subscription modules.')
        }
        const payload = await response.json()
        setModuleCatalog(payload?.data || null)
        return catalog
      })
      .catch(() => {
        loadPromise = null
        return null
      })
  }

  return loadPromise
}
