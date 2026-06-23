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

export function cancelEventApi(eventId) {
  return apiClient.patch(`/events/${eventId}/cancel`)
}