import { api } from '../lib/api'

export async function fetchPlatformSettings() {
  const response = await api.get('/v1/admin/platform-settings')
  return response.data?.data || response.data
}

export async function updatePlatformSettingsGroup(group, settings) {
  const response = await api.put(`/v1/admin/platform-settings/${group}`, { settings })
  return response.data?.data || response.data
}

export async function uploadPlatformLogo(file) {
  const formData = new FormData()
  formData.append('logo', file)
  const response = await api.post('/v1/admin/platform-settings/branding/logo', formData, {
    headers: { 'Content-Type': 'multipart/form-data' },
  })
  return response.data?.data || response.data
}

export async function deletePlatformLogo() {
  const response = await api.delete('/v1/admin/platform-settings/branding/logo')
  return response.data?.data || response.data
}
