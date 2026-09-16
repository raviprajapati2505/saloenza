function clamp(value, min = 0, max = 255) {
  return Math.min(max, Math.max(min, value))
}

function hexToRgb(hex) {
  const normalized = String(hex || '').replace('#', '')
  if (normalized.length !== 6) return null
  return {
    r: parseInt(normalized.slice(0, 2), 16),
    g: parseInt(normalized.slice(2, 4), 16),
    b: parseInt(normalized.slice(4, 6), 16),
  }
}

function rgbToHex({ r, g, b }) {
  return `#${[r, g, b].map((part) => clamp(part).toString(16).padStart(2, '0')).join('')}`
}

function mixHex(baseHex, targetHex, amount) {
  const base = hexToRgb(baseHex)
  const target = hexToRgb(targetHex)
  if (!base || !target) return baseHex
  const ratio = clamp(amount, 0, 1)
  return rgbToHex({
    r: Math.round(base.r + (target.r - base.r) * ratio),
    g: Math.round(base.g + (target.g - base.g) * ratio),
    b: Math.round(base.b + (target.b - base.b) * ratio),
  })
}

export function applyTenantBrandingTheme(branding) {
  const root = document.documentElement
  const primary = branding?.primary_color || '#cc0f67'
  const secondary = branding?.secondary_color || '#8f0a48'

  const vars = {
    '--color-brand-50': mixHex(primary, '#ffffff', 0.92),
    '--color-brand-100': mixHex(primary, '#ffffff', 0.84),
    '--color-brand-200': mixHex(primary, '#ffffff', 0.68),
    '--color-brand-300': mixHex(primary, '#ffffff', 0.48),
    '--color-brand-400': mixHex(primary, '#ffffff', 0.24),
    '--color-brand-500': primary,
    '--color-brand-600': mixHex(primary, '#000000', 0.18),
    '--color-brand-700': secondary,
    '--color-brand-800': mixHex(secondary, '#000000', 0.22),
    '--color-brand-900': mixHex(secondary, '#000000', 0.38),
  }

  Object.entries(vars).forEach(([key, value]) => {
    root.style.setProperty(key, value)
  })

  const themeMeta = document.querySelector('meta[name="theme-color"]')
  if (themeMeta) {
    themeMeta.setAttribute('content', primary)
  }
}

export function resetTenantBrandingTheme() {
  ;[
    '--color-brand-50',
    '--color-brand-100',
    '--color-brand-200',
    '--color-brand-300',
    '--color-brand-400',
    '--color-brand-500',
    '--color-brand-600',
    '--color-brand-700',
    '--color-brand-800',
    '--color-brand-900',
  ].forEach((key) => {
    document.documentElement.style.removeProperty(key)
  })
}
