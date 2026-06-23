// src/api/events.js
import apiClient from './client'

// POST /api/events
// payload must match EventController::create()'s expected fields:
// society_id, title, venue, category, start_datetime, end_datetime,
// reg_deadline, capacity, fee_type, fee_amount.
export function createEventApi(payload) {
  return apiClient.post('/events', payload)
}

// GET /api/events/mine
export function getMyEventsApi() {
  return apiClient.get('/events/mine')
}

export function getOrganiserEventDetailApi(eventId) {
  return apiClient.get(`/events/${eventId}`)
}

export function updateEventApi(eventId, payload) {
  return apiClient.put(`/events/${eventId}`, payload)
}

export function submitEventForApprovalApi(eventId) {
  return apiClient.post(`/events/${eventId}/submit`)
}

export function deleteDraftEventApi(eventId) {
  return apiClient.delete(`/events/${eventId}`)
}

export function cancelEventSubmissionApi(eventId) {
  return apiClient.patch(`/events/${eventId}/cancel-submission`)
}

export function cancelEventApi(eventId) {
  return apiClient.patch(`/events/${eventId}/cancel`)
}
