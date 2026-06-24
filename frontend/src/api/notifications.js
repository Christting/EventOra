import apiClient from './client'

export function getNotificationsApi() {
  return apiClient.get('/notifications')
}

export function getUnreadNotificationCountApi() {
  return apiClient.get('/notifications/unread-count')
}

export function markNotificationAsReadApi(notificationId) {
  return apiClient.post(`/notifications/${notificationId}/read`)
}

export function markAllNotificationsAsReadApi() {
  return apiClient.post('/notifications/read-all')
}
