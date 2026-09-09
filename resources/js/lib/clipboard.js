/**
 * Copy text to the clipboard. Uses the Clipboard API when available,
 * then falls back to a hidden textarea (needed on non-HTTPS local hosts).
 *
 * @param {string} text
 * @returns {Promise<boolean>}
 */
export async function copyTextToClipboard(text) {
  const value = String(text || '')
  if (!value) return false

  if (typeof navigator !== 'undefined' && navigator.clipboard?.writeText && window.isSecureContext) {
    try {
      await navigator.clipboard.writeText(value)
      return true
    } catch {
      // Fall through to legacy path.
    }
  }

  try {
    const textarea = document.createElement('textarea')
    textarea.value = value
    textarea.setAttribute('readonly', '')
    textarea.style.position = 'fixed'
    textarea.style.top = '0'
    textarea.style.left = '0'
    textarea.style.width = '1px'
    textarea.style.height = '1px'
    textarea.style.padding = '0'
    textarea.style.border = 'none'
    textarea.style.outline = 'none'
    textarea.style.boxShadow = 'none'
    textarea.style.background = 'transparent'
    textarea.style.opacity = '0'
    document.body.appendChild(textarea)
    textarea.focus()
    textarea.select()
    textarea.setSelectionRange(0, value.length)
    const ok = document.execCommand('copy')
    document.body.removeChild(textarea)
    return ok
  } catch {
    return false
  }
}
