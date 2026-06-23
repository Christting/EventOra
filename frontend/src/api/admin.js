// src/api/admin.js
import apiClient from './client'

export function getPendingEventsApi() {
  return apiClient.get('/admin/events/pending')
}

export function getAdminEventApi(eventId) {
  return apiClient.get(`/admin/events/${eventId}`)
}

export function approveEventApi(eventId) {
  return apiClient.post(`/admin/events/${eventId}/approve`)
}

export function rejectEventApi(eventId, reason) {
  return apiClient.post(`/admin/events/${eventId}/reject`, { reason })
}
