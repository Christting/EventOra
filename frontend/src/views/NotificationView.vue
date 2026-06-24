<template>
  <main class="app-shell">
    <section class="page-section notification-shell">
      <div class="section-heading">
        <div>
          <p class="eyebrow">In-app notification center</p>
          <h2>Notifications</h2>
        </div>

        <span class="badge badge-blue">{{ unreadCount }} unread</span>
      </div>

      <div class="notification-toolbar">
        <p>
          Showing notifications for your current user role:
          <strong>{{ roleLabel }}</strong>
        </p>

        <button class="button button-secondary" :disabled="updating" @click="markAllAsRead">
          {{ updating ? 'Updating...' : 'Mark all as read' }}
        </button>
      </div>

      <p v-if="loading" class="notification-state">Loading notifications...</p>
      <p v-else-if="loadError" class="auth-error">{{ loadError }}</p>

      <div v-else class="notification-list">
        <article
          v-if="visibleNotifications.length === 0"
          class="notification-item read"
        >
          <span class="unread-dot" aria-hidden="true"></span>

          <div class="notification-content">
            <h3>No notifications</h3>
            <p>You do not have any notifications for this role yet.</p>

            <div class="notification-meta">
              <span class="badge badge-gray">Empty</span>
            </div>
          </div>
        </article>

        <article
          v-for="notification in visibleNotifications"
          :key="notification.id"
          :class="['notification-item', notification.unread ? 'unread' : 'read']"
        >
          <span class="unread-dot" aria-hidden="true"></span>

          <div class="notification-content">
            <h3>{{ notification.title }}</h3>
            <p>{{ notification.message }}</p>

            <div class="notification-meta">
              <span :class="['badge', notification.badgeClass]">
                {{ notification.type }}
              </span>

              <span class="notification-time">
                {{ notification.time }}
              </span>

              <span :class="['badge', notification.unread ? 'badge-blue' : 'badge-gray']">
                {{ notification.unread ? 'Unread' : 'Read' }}
              </span>
            </div>
          </div>

          <div class="notification-actions">
            <button
              v-if="notification.unread"
              class="button button-secondary"
              :disabled="updating"
              @click="markAsRead(notification.id)"
            >
              Mark as read
            </button>

            <span v-else class="badge badge-gray">Done</span>
          </div>
        </article>
      </div>
    </section>
  </main>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useAuthStore } from '@/stores/auth'
import {
  loadNotifications,
  markAllNotificationsAsRead,
  markNotificationAsRead,
} from '@/stores/notifications'

const authStore = useAuthStore()

const notifications = ref([])
const loading = ref(true)
const loadError = ref('')
const updating = ref(false)

const currentRole = computed(() => authStore.role || 'attendee')

const roleLabel = computed(() => {
  if (currentRole.value === 'faculty_admin') return 'Faculty Admin'
  if (currentRole.value === 'organiser') return 'Organiser'
  return 'Attendee'
})

const visibleNotifications = computed(() =>
  notifications.value.filter((notification) => notification.audience === currentRole.value)
)

const unreadCount = computed(() =>
  visibleNotifications.value.filter((notification) => notification.unread).length
)

async function refreshNotifications() {
  loadError.value = ''

  try {
    notifications.value = await loadNotifications()
  } catch (error) {
    loadError.value = 'Failed to load notifications. Please try again later.'
  } finally {
    loading.value = false
  }
}

onMounted(refreshNotifications)

async function markAsRead(id) {
  updating.value = true
  loadError.value = ''

  try {
    await markNotificationAsRead(id)
    notifications.value = notifications.value.map((notification) =>
      notification.id === id ? { ...notification, unread: false } : notification
    )
  } catch (error) {
    loadError.value = 'Failed to mark notification as read.'
  } finally {
    updating.value = false
  }
}

async function markAllAsRead() {
  updating.value = true
  loadError.value = ''

  try {
    await markAllNotificationsAsRead()
    notifications.value = notifications.value.map((notification) => ({
      ...notification,
      unread: false,
    }))
  } catch (error) {
    loadError.value = 'Failed to mark notifications as read.'
  } finally {
    updating.value = false
  }
}
</script>

<style scoped>
.notification-shell {
  max-width: 980px;
  margin: 0 auto;
}

.notification-toolbar {
  display: flex;
  justify-content: space-between;
  gap: 12px;
  align-items: center;
  margin-bottom: 18px;
}

.notification-toolbar p,
.notification-state {
  margin: 0;
  color: var(--muted);
  font-size: 0.9rem;
}

.notification-list {
  display: grid;
  gap: 12px;
}

.notification-item {
  display: grid;
  grid-template-columns: 12px minmax(0, 1fr) auto;
  gap: 14px;
  align-items: flex-start;
  padding: 16px;
  border: 1px solid var(--border);
  border-radius: var(--radius-md);
  background: var(--surface);
  box-shadow: var(--shadow);
}

.notification-item.unread {
  border-color: #bfdbfe;
  background: #eff6ff;
}

.unread-dot {
  width: 10px;
  height: 10px;
  margin-top: 7px;
  border-radius: 999px;
  background: var(--primary);
}

.notification-item.read .unread-dot {
  background: transparent;
}

.notification-content h3 {
  margin: 0 0 6px;
  font-size: 0.98rem;
}

.notification-content p {
  margin: 0;
  color: var(--muted);
  line-height: 1.5;
  font-size: 0.9rem;
}

.notification-meta {
  display: flex;
  gap: 8px;
  align-items: center;
  flex-wrap: wrap;
  margin-top: 10px;
}

.notification-time {
  color: var(--muted);
  font-size: 0.82rem;
}

.notification-actions {
  display: flex;
  justify-content: flex-end;
}

@media (max-width: 720px) {
  .notification-toolbar {
    display: block;
  }

  .notification-toolbar .button {
    margin-top: 12px;
    width: 100%;
  }

  .notification-item {
    grid-template-columns: 12px minmax(0, 1fr);
  }

  .notification-actions {
    grid-column: 2;
    justify-content: flex-start;
  }
}
</style>
