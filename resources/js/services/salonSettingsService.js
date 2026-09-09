import { api } from '../lib/api'

export async function fetchSalonSettings() {
  const response = await api.get('/v1/salon/settings')
  return response.data?.data || response.data
}

export async function updateSalonSettingsGroup(group, settings) {
  const response = await api.put(`/v1/salon/settings/${group}`, { settings })
  return response.data?.data || response.data
}

export async function uploadSalonLogo(file) {
  const formData = new FormData()
  formData.append('logo', file)
  const response = await api.post('/v1/salon/settings/branding/logo', formData, {
    headers: { 'Content-Type': 'multipart/form-data' },
  })
  return response.data?.data || response.data
}

export async function deleteSalonLogo() {
  const response = await api.delete('/v1/salon/settings/branding/logo')
  return response.data?.data || response.data
}

export async function fetchSalonBusinessProfile() {
  const response = await api.get('/v1/salon/business-profile')
  return response.data?.data || response.data
}

export async function updateSalonBusinessProfile(payload) {
  const response = await api.put('/v1/salon/business-profile', payload)
  return response.data?.data || response.data
}
