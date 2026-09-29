import axios from 'axios'
import { registerApiInterceptors } from './apiInterceptors.js'

function resolveApiBaseUrl() {
  const configured = import.meta.env.VITE_API_BASE_URL || 'http://localhost:8000/api'
  if (typeof window === 'undefined') {
    return configured
  }

  const host = window.location.hostname
  const isLocal = host === 'localhost' || host === '127.0.0.1' || host === '[::1]' || host === '::1'
  if (isLocal) {
    return configured
  }

  return `${window.location.origin}/api`
}

const API_BASE_URL = resolveApiBaseUrl()

export const api = axios.create({
  baseURL: API_BASE_URL,
  headers: {
    Accept: 'application/json',
    'Content-Type': 'application/json',
  },
})

registerApiInterceptors(api)

export function setAuthToken(token) {
  if (token) {
    api.defaults.headers.common.Authorization = `Bearer ${token}`
  } else {
    delete api.defaults.headers.common.Authorization
  }
}

export { API_BASE_URL }
