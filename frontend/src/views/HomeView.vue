<template>
  <main class="app-shell">
    <section class="hero-section">
      <router-link
        v-if="authStore.isLoggedIn"
        to="/notifications"
        class="hero-notification-button"
        aria-label="Notifications"
        title="Notifications"
      >
        🔔
        <span
          v-if="unreadCount > 0"
          class="notification-dot"
        ></span>
      </router-link>

      <div>
        <p class="eyebrow">Discover events</p>
        <h1>What's happening at UTM?</h1>

        <p class="hero-copy">
          Browse society workshops, cultural nights, tournaments, and talks.
        </p>

        <div class="search-panel">
          <input
            v-model="keyword"
            type="text"
            placeholder="Search by title or society..."
          />
        </div>
      </div>

      <aside class="hero-card">
        <span>This month</span>
        <strong>{{ filteredEvents.length }} events live</strong>
      </aside>
    </section>

    <section class="stats-grid">
      <article class="stat-card">
        <span>Upcoming Events</span>
        <strong>{{ filteredEvents.length }}</strong>
      </article>

      <article class="stat-card">
        <span>Active Societies</span>
        <strong>12</strong>
      </article>

      <article class="stat-card">
        <span>This Week</span>
        <strong>{{ thisWeekCount }}</strong>
      </article>

      <article class="stat-card">
        <span>Free Events</span>
        <strong>{{ freeCount }}</strong>
      </article>
    </section>

    <section class="toolbar">
      <div>
        <p class="eyebrow">Featured calendar</p>
        <h2>Upcoming society activities</h2>
      </div>

      <div class="filter-bar">
        <select v-model="category">
          <option value="all">All</option>
          <option value="academic">Academic</option>
          <option value="sports">Sports</option>
          <option value="cultural">Cultural</option>
          <option value="religious">Religious</option>
        </select>

        <select v-model="price">
          <option value="all">All</option>
          <option value="free">Free</option>
          <option value="paid">Paid</option>
        </select>

        <select v-model="dateFilter">
          <option value="all">Any time</option>
          <option value="week">This week</option>
          <option value="month">This month</option>
        </select>

        <button
          class="button button-secondary"
          @click="clearFilters"
        >
          Clear filters
        </button>
      </div>
    </section>

    <section class="event-grid">
      <article
        v-for="event in filteredEvents"
        :key="event.id"
        class="event-card"
      >
        <div :class="['event-cover', event.coverClass]">
          <span :class="['badge', event.badgeClass]">
            {{ capitalize(event.category) }}
          </span>

          <span
            v-if="event.seatsLeft > 0"
            class="badge badge-gray"
          >
            {{ event.seatsLeft }} seats left
          </span>

          <span
            v-else
            class="badge badge-yellow"
          >
            Full
          </span>
        </div>

        <div class="event-card-body">
          <span class="event-date">
            {{ formatDate(event.date) }}
          </span>

          <h3>{{ event.title }}</h3>

          <p>
            {{ event.venue }}
            ·
            {{ event.society }}
          </p>

          <div class="card-meta">
            <span>
              {{ event.priceType === 'paid'
                ? 'Paid ticket'
                : 'Open registration' }}
            </span>

            <strong>
              {{
                event.priceType === 'free'
                  ? 'Free'
                  : `RM ${event.price}`
              }}
            </strong>
          </div>

          <button class="button button-primary">
            View Registration
          </button>
        </div>
      </article>

      <div
        v-if="filteredEvents.length === 0"
        style="padding:40px;text-align:center;"
      >
        No events found.
      </div>
    </section>
  </main>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useAuthStore } from '@/stores/auth'
import { loadNotifications } from '@/stores/notifications'

const authStore = useAuthStore()

const keyword = ref('')
const category = ref('all')
const price = ref('all')
const dateFilter = ref('all')

const societyEventsStorageKey = 'eventora_society_events_v2'
const notifications = ref([])

const unreadCount = computed(() =>
  notifications.value.filter(
    (notification) =>
      notification.audience === authStore.role &&
      notification.unread
  ).length
)

onMounted(async () => {
  notifications.value = await loadNotifications()
})

const basePublicEvents = [
  {
    id: 'event-annual-tech-2026',
    title: 'Annual Tech Symposium 2026',
    society: 'Computer Society UTM',
    category: 'academic',
    price: 5,
    priceType: 'paid',
    date: '2026-07-15T09:00:00',
    venue: 'Dewan Sultan Iskandar, UTM JB',
    seatsLeft: 42,
    coverClass: 'academic-cover',
    badgeClass: 'badge-blue',
  },
  {
    id: 1,
    title: 'Build Your First AI App',
    society: 'UTM Computing Society',
    category: 'academic',
    price: 8,
    priceType: 'paid',
    date: '2026-06-12T19:30:00',
    venue: 'N28A Innovation Lab',
    seatsLeft: 12,
    coverClass: 'academic-cover',
    badgeClass: 'badge-blue',
  },
  {
    id: 2,
    title: 'Campus Cultural Night',
    society: 'Campus Culture Club',
    category: 'cultural',
    price: 0,
    priceType: 'free',
    date: '2026-06-20T18:30:00',
    venue: 'Dewan Sultan Iskandar',
    seatsLeft: 54,
    coverClass: 'culture-cover',
    badgeClass: 'badge-purple',
  },
  {
    id: 3,
    title: 'Interfaculty Futsal Cup',
    society: 'UTM Sports Club',
    category: 'sports',
    price: 0,
    priceType: 'free',
    date: '2026-06-28T09:00:00',
    venue: 'UTM Sports Hall',
    seatsLeft: 0,
    coverClass: 'sports-cover',
    badgeClass: 'badge-green',
  },
]

const events = ref(loadPublicEvents())

function loadPublicEvents() {
  const savedEvents = JSON.parse(localStorage.getItem(societyEventsStorageKey) || 'null')

  if (!Array.isArray(savedEvents)) {
    return basePublicEvents
  }

  const savedIds = new Set(savedEvents.map((event) => String(event.id)))
  const savedPublishedEvents = savedEvents
    .filter((event) => event.status === 'published')
    .map(toPublicEvent)

  const untouchedBaseEvents = basePublicEvents.filter((event) => !savedIds.has(String(event.id)))

  return [...savedPublishedEvents, ...untouchedBaseEvents]
}

function toPublicEvent(event) {
  const category = (event.category || 'academic').toLowerCase()
  const registrations = event.registrations ?? event.confirmedCount ?? 0
  const capacity = event.capacity ?? 0

  return {
    id: event.id,
    title: event.title,
    society: event.society || event.societyName || 'UTM Society',
    category,
    price: event.feeAmount ?? event.price ?? 0,
    priceType:
      (event.feeType || event.priceType || 'free').toLowerCase() === 'paid' ? 'paid' : 'free',
    date: event.startAt || parsePublicDate(event.eventDate, event.startTime),
    venue: event.location || event.venue || 'Venue not set',
    seatsLeft: Math.max(capacity - registrations, 0),
    coverClass: event.coverClass || coverForCategory(category),
    badgeClass: event.badgeClass || badgeForCategory(category),
  }
}

function parsePublicDate(dateText, timeText) {
  if (!dateText) return new Date().toISOString()
  const date = new Date(`${dateText} ${timeText || ''}`)
  return Number.isNaN(date.getTime()) ? new Date().toISOString() : date.toISOString()
}

function coverForCategory(categoryName) {
  if (categoryName === 'sports') return 'sports-cover'
  if (categoryName === 'cultural') return 'culture-cover'
  return 'academic-cover'
}

function badgeForCategory(categoryName) {
  if (categoryName === 'sports') return 'badge-green'
  if (categoryName === 'cultural') return 'badge-purple'
  return 'badge-blue'
}

function isInDateRange(eventDate, filter) {
  const today = new Date()
  const eventDay = new Date(eventDate)

  if (filter === 'week') {
    const nextWeek = new Date()
    nextWeek.setDate(today.getDate() + 7)

    return eventDay >= today && eventDay <= nextWeek
  }

  if (filter === 'month') {
    const nextMonth = new Date()
    nextMonth.setMonth(today.getMonth() + 1)

    return eventDay >= today && eventDay <= nextMonth
  }

  return true
}

const filteredEvents = computed(() => {
  return events.value.filter((event) => {
    if (
      category.value !== 'all' &&
      event.category !== category.value
    )
      return false

    if (
      price.value !== 'all' &&
      event.priceType !== price.value
    )
      return false

    if (
      dateFilter.value !== 'all' &&
      !isInDateRange(event.date, dateFilter.value)
    )
      return false

    if (
      keyword.value &&
      !event.title.toLowerCase().includes(keyword.value.toLowerCase()) &&
      !event.society.toLowerCase().includes(keyword.value.toLowerCase())
    )
      return false

    return true
  })
})

const freeCount = computed(
  () =>
    filteredEvents.value.filter(
      (e) => e.priceType === 'free'
    ).length
)

const thisWeekCount = computed(
  () =>
    filteredEvents.value.filter((e) =>
      isInDateRange(e.date, 'week')
    ).length
)

function clearFilters() {
  keyword.value = ''
  category.value = 'all'
  price.value = 'all'
  dateFilter.value = 'all'
}

function formatDate(date) {
  return new Date(date).toLocaleString('en-MY')
}

function capitalize(text) {
  return text.charAt(0).toUpperCase() + text.slice(1)
}
</script>

<style scoped>
.hero-section {
  position: relative;
}

.hero-notification-button {
  position: absolute;
  top: 24px;
  right: 24px;
  width: 44px;
  height: 44px;
  border-radius: 999px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  background: #fff;
  color: var(--text);
  text-decoration: none;
  box-shadow: var(--shadow);
  border: 1px solid var(--border);
  font-size: 1.1rem;
  z-index: 3;
  transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.hero-notification-button:hover {
  transform: translateY(-1px);
  box-shadow: var(--shadow-lg);
}

.notification-dot {
  position: absolute;
  top: 9px;
  right: 9px;
  width: 9px;
  height: 9px;
  border-radius: 999px;
  background: var(--danger);
  border: 2px solid #fff;
}
</style>
