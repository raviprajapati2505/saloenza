/**
 * Build a user-facing message from API forbidden responses.
 *
 * @param {Record<string, unknown> | undefined} data
 * @returns {string}
 */
export function formatForbiddenMessage(data) {
  const base = typeof data?.message === 'string' && data.message.trim()
    ? data.message.trim()
    : 'You do not have permission to perform this action.'

  const tenantPermissions = Array.isArray(data?.required_permissions)
    ? data.required_permissions.filter((code) => typeof code === 'string' && code.trim())
    : []

  if (tenantPermissions.length > 0) {
    const label = tenantPermissions.length === 1 ? 'Required permission' : 'Required one of'
    return `${base} ${label}: ${tenantPermissions.join(', ')}.`
  }

  if (typeof data?.required_permission === 'string' && data.required_permission.trim()) {
    return `${base} Required permission: ${data.required_permission.trim()}.`
  }

  return base.endsWith('.') ? base : `${base}.`
}

/**
 * @param {import('axios').AxiosError} error
 * @returns {boolean}
 */
export function isForbiddenError(error) {
  return error?.response?.status === 403
}

/**
 * @param {import('axios').AxiosError} error
 * @returns {string}
 */
export function messageFromApiError(error) {
  const data = error?.response?.data
  if (isForbiddenError(error)) {
    return formatForbiddenMessage(data)
  }

  if (typeof data?.message === 'string' && data.message.trim()) {
    return data.message.trim()
  }

  return 'Something went wrong. Please try again.'
}
