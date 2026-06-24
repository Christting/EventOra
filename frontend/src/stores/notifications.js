import {
  getNotificationsApi,
  markAllNotificationsAsReadApi,
  markNotificationAsReadApi,
} from '@/api/notifications'

const badgeByType = {
  event_approved: 'badge-green',
  event_rejected: 'badge-red',
  event_cancelled: 'badge-red',
  registration_success: 'badge-green',
  payment_success: 'badge-green',
  waitlist_confirmed: 'badge-blue',
  event_reminder: 'badge-purple',
  test: 'badge-blue',
}

const labelByType = {
  event_approved: 'Approval',
  event_rejected: 'Revision',
  event_cancelled: 'Cancellation',
  registration_success: 'Registration',
  payment_success: 'Payment',
  waitlist_confirmed: 'Waitlist',
  event_reminder: 'Reminder',
  test: 'Test',
}

function currentUserRole() {
  try {
    const user = JSON.parse(localStorage.getItem('eventora_user') || 'null')
    return user?.role || 'attendee'
  } catch (error) {
    return 'attendee'
  }
}

function formatNotificationTime(value) {
  if (!value) return ''

  const date = new Date(value.replace(' ', 'T'))
  if (Number.isNaN(date.getTime())) return value

  return date.toLocaleString([], {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
    hour: 'numeric',
    minute: '2-digit',
  })
}

function mapNotification(notification) {
  return {
    id: notification.id,
    audience: currentUserRole(),
    type: labelByType[notification.type] || notification.type,
    rawType: notification.type,
    title: notification.title,
    message: notification.message,
    relatedEventId: notification.related_event_id,
    time: formatNotificationTime(notification.created_at),
    badgeClass: badgeByType[notification.type] || 'badge-blue',
    unread: Number(notification.is_read) === 0,
  }
}

export async function loadNotifications() {
  const response = await getNotificationsApi()
  return response.data.data.map(mapNotification)
}

export async function markNotificationAsRead(id) {
  await markNotificationAsReadApi(id)
}

export async function markAllNotificationsAsRead() {
  await markAllNotificationsAsReadApi()
}

export function saveNotifications() {
  // Notifications now come from the backend. This function remains so older
  // views importing it do not need localStorage-specific branching.
}

export function addNotification() {
  // Backend notification creation should use NotificationService in Slim.
  return null
}
