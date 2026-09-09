import { pushToast } from '../stores/toast.js'
import { isForbiddenError, messageFromApiError } from './apiErrors.js'
import { syncSubscriptionAccessFromApi } from './subscriptionAccessSync.js'

/**
 * Register global axios response interceptors.
 *
 * @param {import('axios').AxiosInstance} client
 */
export function registerApiInterceptors(client) {
  client.interceptors.response.use(
    (response) => response,
    (error) => {
      if (isForbiddenError(error)) {
        const accessMode = error?.response?.data?.subscription_access_mode
        if (accessMode) {
          syncSubscriptionAccessFromApi(accessMode)
        }
        pushToast(messageFromApiError(error), 'error')
      }

      return Promise.reject(error)
    },
  )
}
