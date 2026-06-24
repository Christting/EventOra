<template>
  <div id="app-shell">
    <header class="site-header">
      <router-link class="brand" to="/" aria-label="EventOra home">
        <span class="brand-mark">E</span>
        <span>EventOra</span>
      </router-link>
      <nav class="desktop-nav" aria-label="Main navigation">
        <router-link to="/">Events</router-link>
        <router-link v-if="dashboardLink" :to="dashboardLink">Dashboard</router-link>
        <router-link v-if="!authStore.isLoggedIn" to="/login">Login</router-link>
        <router-link v-if="authStore.isLoggedIn" class="nav-notification-link" to="/notifications">
          Notifications
          <span v-if="unreadCount > 0" class="nav-unread-badge">{{ unreadCount }}</span>
        </router-link>
        <router-link v-if="authStore.isLoggedIn" to="/profile">Profile</router-link>
        <a v-if="authStore.isLoggedIn" href="#" @click.prevent="handleLogout">Logout</a>
      </nav>
      <router-link
        v-if="!authStore.isLoggedIn"
        class="header-action"
        to="/register"
      >
        Create account
      </router-link>
    </header>

    <router-view />

    <nav class="mobile-nav" aria-label="Mobile navigation">
  <router-link to="/">Events</router-link>
  <router-link v-if="dashboardLink" :to="dashboardLink">Dashboard</router-link>
  <router-link v-if="!authStore.isLoggedIn" to="/login">Login</router-link>
  <router-link v-if="authStore.isLoggedIn" class="nav-notification-link" to="/notifications">
    Notifications
    <span v-if="unreadCount > 0" class="nav-unread-badge">{{ unreadCount }}</span>
  </router-link>
  <router-link v-if="authStore.isLoggedIn" to="/profile">Profile</router-link>
    </nav>
  </div>
</template>

<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { getUnreadNotificationCountApi } from '@/api/notifications'
import { useAuthStore } from '@/stores/auth'

const router = useRouter()
const route = useRoute()
const authStore = useAuthStore()
const unreadCount = ref(0)

const dashboardLink = computed(() => {
  if (authStore.role === 'faculty_admin') return '/admin'
  if (authStore.role === 'organiser') return '/organiser/dashboard'
  return null
})

function handleLogout() {
  authStore.logout()
  unreadCount.value = 0
  router.push('/login')
}

async function refreshUnreadCount() {
  if (!authStore.isLoggedIn) {
    unreadCount.value = 0
    return
  }

  try {
    const response = await getUnreadNotificationCountApi()
    unreadCount.value = response.data.data.unread_count
  } catch (error) {
    unreadCount.value = 0
  }
}

onMounted(refreshUnreadCount)

watch(
  () => [authStore.token, route.fullPath],
  refreshUnreadCount
)
</script>

<style scoped>
.nav-notification-link {
  position: relative;
  display: inline-flex;
  align-items: center;
  gap: 6px;
}

.nav-unread-badge {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-width: 18px;
  height: 18px;
  padding: 0 6px;
  border-radius: 999px;
  background: var(--danger);
  color: #ffffff;
  font-size: 0.7rem;
  font-weight: 800;
  line-height: 1;
}
</style>
