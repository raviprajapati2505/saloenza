/** Common dial codes for phone / WhatsApp inputs. */
export const COUNTRY_DIAL_CODES = [
  { code: 'IN', dial: '91', label: 'India', flag: '🇮🇳' },
  { code: 'QA', dial: '974', label: 'Qatar', flag: '🇶🇦' },
  { code: 'AE', dial: '971', label: 'UAE', flag: '🇦🇪' },
  { code: 'SA', dial: '966', label: 'Saudi Arabia', flag: '🇸🇦' },
  { code: 'US', dial: '1', label: 'United States', flag: '🇺🇸' },
  { code: 'GB', dial: '44', label: 'United Kingdom', flag: '🇬🇧' },
  { code: 'SG', dial: '65', label: 'Singapore', flag: '🇸🇬' },
  { code: 'AU', dial: '61', label: 'Australia', flag: '🇦🇺' },
  { code: 'CA', dial: '1', label: 'Canada', flag: '🇨🇦' },
  { code: 'PK', dial: '92', label: 'Pakistan', flag: '🇵🇰' },
  { code: 'BD', dial: '880', label: 'Bangladesh', flag: '🇧🇩' },
  { code: 'NP', dial: '977', label: 'Nepal', flag: '🇳🇵' },
  { code: 'LK', dial: '94', label: 'Sri Lanka', flag: '🇱🇰' },
  { code: 'MY', dial: '60', label: 'Malaysia', flag: '🇲🇾' },
  { code: 'PH', dial: '63', label: 'Philippines', flag: '🇵🇭' },
  { code: 'ID', dial: '62', label: 'Indonesia', flag: '🇮🇩' },
  { code: 'TH', dial: '66', label: 'Thailand', flag: '🇹🇭' },
  { code: 'DE', dial: '49', label: 'Germany', flag: '🇩🇪' },
  { code: 'FR', dial: '33', label: 'France', flag: '🇫🇷' },
  { code: 'OM', dial: '968', label: 'Oman', flag: '🇴🇲' },
  { code: 'KW', dial: '965', label: 'Kuwait', flag: '🇰🇼' },
  { code: 'BH', dial: '973', label: 'Bahrain', flag: '🇧🇭' },
]

export const DEFAULT_DIAL_CODE = '91'

/** Digits only (no leading zeros stripped from national part beyond spaces). */
export function digitsOnly(value) {
  return String(value || '').replace(/\D/g, '')
}

/**
 * Split a stored phone into dial code + national number.
 * Prefers longest matching dial code from the list.
 */
export function splitPhoneNumber(value, fallbackDial = DEFAULT_DIAL_CODE) {
  const raw = String(value || '').trim()
  if (!raw) {
    return { dial: fallbackDial, national: '' }
  }

  let digits = digitsOnly(raw)
  if (digits.startsWith('00')) {
    digits = digits.slice(2)
  }

  const sorted = [...COUNTRY_DIAL_CODES].sort((a, b) => b.dial.length - a.dial.length)
  for (const country of sorted) {
    if (digits.startsWith(country.dial) && digits.length > country.dial.length) {
      return {
        dial: country.dial,
        national: digits.slice(country.dial.length),
      }
    }
  }

  // Legacy 10-digit India numbers without country code
  if (digits.length === 10) {
    return { dial: fallbackDial, national: digits }
  }

  return { dial: fallbackDial, national: digits }
}

/** Combine dial + national into E.164-style +{dial}{national}. */
export function combinePhoneNumber(dial, national) {
  const dialDigits = digitsOnly(dial)
  const nationalDigits = digitsOnly(national)
  if (!nationalDigits) return ''
  if (!dialDigits) return nationalDigits
  return `+${dialDigits}${nationalDigits}`
}

export function isValidE164(value) {
  return /^\+[1-9]\d{6,14}$/.test(String(value || '').trim())
}

export function dialCodeOptions() {
  return COUNTRY_DIAL_CODES.map((country) => ({
    value: country.dial,
    label: `${country.flag} +${country.dial} ${country.label}`,
    shortLabel: `${country.flag} +${country.dial}`,
  }))
}
