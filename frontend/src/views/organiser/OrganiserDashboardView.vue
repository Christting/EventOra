<template>
  <main class="app-shell">
    <div class="dashboard-layout">

      <aside class="sidebar-nav">
        <a
          v-for="tab in tabs"
          :key="tab.key"
          href="#"
          :class="{ active: currentTab === tab.key }"
          @click.prevent="currentTab = tab.key"
        >
          {{ tab.label }}
        </a>
      </aside>

      <div class="dashboard-main">

                <div class="od-stats-grid">
          <article class="od-stat-card">
            <span>Total Events</span>
            <strong>{{ totalEvents }}</strong>
            <p>{{ publishedCount }} published · {{ pendingCount }} pending</p>
          </article>
          <article class="od-stat-card">
            <span>Total Registrations</span>
            <strong>{{ totalRegistrations }}</strong>
            <p>Across all events</p>
          </article>
          <article class="od-stat-card">
            <span>Total Attendance</span>
            <strong>{{ totalCheckedIn }}</strong>
            <p>{{ attendanceRate }}% attendance rate</p>
          </article>
          <article class="od-stat-card">
            <span>Avg. Feedback Rating</span>
            <strong>{{ avgRatingDisplay }} <span v-if="avgRatingDisplay !== 'N/A'">★</span></strong>
            <p v-if="avgRatingDisplay === 'N/A'">No feedback data yet</p>
            <p v-else>From {{ feedbackData.length }} responses</p>
          </article>
        </div>

                <div v-if="currentTab === 'events'" class="page-section">
          <div class="section-heading">
            <h2>My Events</h2>
            <router-link to="/organiser/create-event" class="button button-primary">
              + Create Event
            </router-link>
          </div>

          <div class="admin-table-wrap">
            <table class="admin-table">
              <thead>
                <tr>
                  <th>Event Name</th>
                  <th>Date</th>
                  <th>Capacity</th>
                  <th>Registered</th>
                  <th>Status</th>
                  <th>Actions</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="ev in societyEvents" :key="ev.id">
                  <td>
                    <router-link
                      :to="`/organiser/event-detail/${ev.id}`"
                      style="font-weight:700;color:var(--text);text-decoration:none;"
                    >
                      {{ ev.title }}
                    </router-link>
                    <br />
                    <span
                      :class="['badge', ev.category === 'Sports' ? 'badge-yellow' : 'badge-blue']"
                      style="font-size:0.68rem;margin-top:6px;"
                    >
                      {{ ev.category || 'Academic' }}
                    </span>
                  </td>
                  <td>
                    {{ ev.eventDate || 'Not set' }}
                    <br />
                    <span style="color:var(--muted);font-size:0.78rem;">
                      {{ ev.startTime || '--' }} - {{ ev.endTime || '--' }}
                    </span>
                  </td>
                  <td>{{ ev.capacity }}</td>
                  <td>
                    {{ ev.registrations }}
                    <span style="color:var(--muted);font-size:0.78rem;">
                      ({{ ev.capacity ? Math.round((ev.registrations / ev.capacity) * 100) : 0 }}%)
                    </span>
                  </td>
                  <td>
                    <span :class="['badge', badgeForStatus(ev.status)]">{{ statusLabel(ev.status) }}</span>
                  </td>
                  <td>
                    <router-link :to="`/organiser/event-detail/${ev.id}`" class="button button-secondary">
                      Edit
                    </router-link>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

                <div v-if="currentTab === 'registrations'" class="page-section">
          <div class="section-heading">
            <h2>Registrations</h2>
            <button class="button button-primary" @click="exportCSV(registrationsList, 'registrations.csv')">
              Export CSV
            </button>
          </div>
          <div class="admin-table-wrap">
            <table class="admin-table">
              <thead><tr><th>Name</th><th>Email</th><th>Status</th><th>Ticket Code</th></tr></thead>
              <tbody>
                <tr v-for="r in registrationsList" :key="r.email">
                  <td>{{ r.name }}</td>
                  <td>{{ r.email }}</td>
                  <td>
                    <span :class="['badge', r.status === 'confirmed' ? 'badge-green' : 'badge-yellow']">
                      {{ r.status }}
                    </span>
                  </td>
                  <td><code>{{ r.ticketCode || '-' }}</code></td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

                <div v-if="currentTab === 'attendance'" class="page-section">
          <div class="section-heading">
            <div>
              <h2>Attendance Report</h2>
              <p style="color:var(--muted);margin:4px 0 0;">
                {{ attendanceList.length }} / {{ confirmedRegistrations }} confirmed attendees checked in
              </p>
            </div>
            <button class="button button-primary" @click="exportCSV(attendanceList, 'attendance.csv')">
              Export Attendance CSV
            </button>
          </div>
          <div class="capacity-bar" style="margin-bottom:1rem;">
            <span :style="{ width: attendanceTabRate + '%' }"></span>
          </div>
          <div class="admin-table-wrap">
            <table class="admin-table">
              <thead><tr><th>Attendee</th><th>Checked In At</th><th>Verified By</th></tr></thead>
              <tbody>
                <tr v-for="a in attendanceList" :key="a.attendee">
                  <td>{{ a.attendee }}</td>
                  <td>{{ a.checkedInAt }}</td>
                  <td>{{ a.verifiedBy }}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

                <div v-if="currentTab === 'feedback'" class="page-section">
          <div class="section-heading">
            <div>
              <h2>Feedback & Ratings</h2>
              <p style="color:var(--muted);margin:4px 0 0;">
                Average Rating: <strong>{{ avgRating }} / 5</strong> from {{ feedbackData.length }} reviews
              </p>
            </div>
            <button class="button button-primary" @click="exportCSV(feedbackData, 'feedback.csv')">
              Export Feedback CSV
            </button>
          </div>
          <div class="event-grid">
            <article v-for="(f, idx) in feedbackData" :key="idx" class="event-card">
              <div class="event-card-body">
                <strong>{{ '★'.repeat(f.rating) }}{{ '☆'.repeat(5 - f.rating) }}</strong>
                <p>{{ f.comment }}</p>
              </div>
            </article>
          </div>
        </div>

      </div>
    </div>

        <div
      v-if="toast.visible"
      class="registration-alert"
      style="display:block;position:fixed;right:24px;bottom:24px;z-index:1200;max-width:340px;box-shadow:var(--shadow-lg);"
    >
      <strong>{{ toast.title }}</strong>
      <p>{{ toast.message }}</p>
    </div>
  </main>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { getOrganiserDashboardApi } from '@/api/dashboard'

const route = useRoute()
const router = useRouter()

const eventsStorageKey = 'eventora_society_events_v2'

const defaultEvents = [
  {
    id: 1, title: 'Build Your First AI App', category: 'Academic', location: 'N28A Innovation Lab',
    eventDate: '12 Jun 2026', startTime: '7:30 PM', endTime: '9:30 PM',
    feeType: 'Paid', feeAmount: 8, status: 'published',
    registrations: 28, checkedIn: 18, avgRating: 4.5, capacity: 40,
  },
  {
    id: 2, title: 'Hackathon 2026', category: 'Academic', location: 'FAB Lab',
    eventDate: '5 Jul 2026', startTime: '9:00 AM', endTime: '6:00 PM',
    feeType: 'Paid', feeAmount: 15, status: 'pending_approval',
    registrations: 0, checkedIn: 0, avgRating: null, capacity: 60,
  },
  {
    id: 3, title: 'Futsal Tournament', category: 'Sports', location: 'UTM Sports Hall',
    eventDate: '28 Jun 2026', startTime: '9:00 AM', endTime: '1:00 PM',
    feeType: 'Free', feeAmount: 0, status: 'published',
    registrations: 40, checkedIn: 32, avgRating: 4.2, capacity: 40,
  },
]

const registrationsList = [
  { name: 'Aina Rahman', email: 'aina@utm.my', status: 'confirmed', ticketCode: 'EVT-9F4K-2Q8M-X7P1' },
  { name: 'Nurul Iman', email: 'nurul@utm.my', status: 'confirmed', ticketCode: 'EVT-3H7J-1L9N-P5R2' },
  { name: 'Kevin Tan', email: 'kevin@utm.my', status: 'waitlist', ticketCode: '' },
]

const attendanceList = [
  { attendee: 'Aina Rahman', checkedInAt: '7:18 PM, 12 Jun', verifiedBy: 'Mei Shuet' },
  { attendee: 'Nurul Iman', checkedInAt: '7:22 PM, 12 Jun', verifiedBy: 'Mei Shuet' },
]

const feedbackData = [
  { rating: 5, comment: 'Excellent workshop!' },
  { rating: 4, comment: 'Good but short' },
  { rating: 5, comment: 'Very inspiring' },
]

const tabs = [
  { key: 'events', label: 'Events' },
  { key: 'registrations', label: 'Registrations' },
  { key: 'attendance', label: 'Attendance' },
  { key: 'feedback', label: 'Feedback' },
]

const currentTab = ref('events')

const societyEvents = ref(
  JSON.parse(localStorage.getItem(eventsStorageKey) || 'null') || defaultEvents
)

function saveEvents() {
  localStorage.setItem(eventsStorageKey, JSON.stringify(societyEvents.value))
}

function badgeForStatus(status) {
  if (status === 'published') return 'badge-green'
  if (status === 'pending_approval') return 'badge-yellow'
  if (status === 'completed') return 'badge-purple'
  if (status === 'rejected' || status === 'cancelled') return 'badge-red'
  return 'badge-blue'
}

function statusLabel(status) {
  return status.replace('_', ' ')
}

// ===== Stats (now sourced from GET /api/dashboard/organiser, not
// societyEvents - those 3 mock events below only feed the Events tab
// table for now, see the "still mock" note in the tab section below) =====
const dashboardStats = ref(null)
const dashboardLoading = ref(true)
const dashboardError = ref('')

async function loadDashboardStats() {
  dashboardLoading.value = true
  dashboardError.value = ''

  try {
    const response = await getOrganiserDashboardApi()
    dashboardStats.value = response.data.data
  } catch (err) {
    dashboardError.value = err.response?.data?.error?.message || 'Failed to load dashboard stats.'
  } finally {
    dashboardLoading.value = false
  }
}

const totalEvents = computed(() => dashboardStats.value?.total_events ?? 0)
const publishedCount = computed(() => dashboardStats.value?.event_totals?.published ?? 0)
const pendingCount = computed(() => dashboardStats.value?.event_totals?.pending_approval ?? 0)
const totalRegistrations = computed(() => dashboardStats.value?.total_registrations ?? 0)
const totalCheckedIn = computed(() => dashboardStats.value?.attendance?.checked_in ?? 0)

// rate_percent can be null (no confirmed registrations to measure
// against yet) - safePercentage() on the backend returns null rather
// than 0 specifically so this distinction isn't lost.
const attendanceRate = computed(() => dashboardStats.value?.attendance?.rate_percent ?? 0)

// average_rating is always null until the feedback table exists
// (see DashboardController TODO). Showing "N/A" here instead of "0.0"
// avoids implying a real (bad) rating exists when there's simply no
// data yet.
const avgRatingDisplay = computed(() => {
  const rating = dashboardStats.value?.average_rating
  return rating === null || rating === undefined ? 'N/A' : rating.toFixed(1)
})

const confirmedRegistrations = computed(
  () => registrationsList.filter((r) => r.status === 'confirmed').length
)
const attendanceTabRate = computed(() =>
  confirmedRegistrations.value ? Math.round((attendanceList.length / confirmedRegistrations.value) * 100) : 0
)

// ===== CSV export =====
function escapeCsv(value) {
  const text = String(value ?? '')
  return `"${text.replaceAll('"', '""')}"`
}

function exportCSV(rows, filename) {
  if (!rows.length) return
  const headers = Object.keys(rows[0])
  const csv = [
    headers.map(escapeCsv).join(','),
    ...rows.map((row) => headers.map((header) => escapeCsv(row[header])).join(',')),
  ].join('\n')
  const blob = new Blob([csv], { type: 'text/csv;charset=utf-8' })
  const a = document.createElement('a')
  a.href = URL.createObjectURL(blob)
  a.download = filename
  a.click()
  URL.revokeObjectURL(a.href)
}

// ===== Toast from query params (after teammate's create-event flow redirects back) =====
const toast = reactive({ visible: false, title: '', message: '' })

function showCreateEventToast() {
  const eventSaved = route.query.eventSaved
  const eventAction = route.query.eventAction
  if (!eventSaved && !eventAction) return

  if (eventSaved === 'draft') {
    toast.title = 'Draft saved successfully'
    toast.message = 'The event is saved as a draft and can be edited before submission.'
    addCreatedEventToDashboard('draft')
  }
  if (eventSaved === 'submitted') {
    toast.title = 'Event submitted for approval'
    toast.message = 'Faculty Admin will review the event before it appears in the public list.'
    addCreatedEventToDashboard('pending_approval')
  }
  if (eventAction === 'submitted') {
    toast.title = 'Event submitted for approval'
    toast.message = 'The event moved from draft to pending approval for Faculty Admin review.'
  }
  if (eventAction === 'deleted') {
    toast.title = 'Draft deleted'
    toast.message = 'The draft event has been removed from the organiser workspace.'
  }

  toast.visible = true
  setTimeout(() => {
    toast.visible = false
    router.replace({ path: route.path })
  }, 3500)
}

function addCreatedEventToDashboard(status) {
  const mockId = 'created-event-annual-tech-symposium'
  const alreadyAdded = societyEvents.value.some((ev) => ev.mockId === mockId)
  if (alreadyAdded) return

  societyEvents.value.unshift({
    id: Date.now(),
    mockId,
    title: 'Annual Tech Symposium 2026',
    category: 'Academic',
    location: 'Dewan Sultan Iskandar, UTM JB',
    eventDate: '15 Jul 2026',
    startTime: '9:00 AM',
    endTime: '5:00 PM',
    feeType: 'Free',
    feeAmount: 0,
    status,
    registrations: 0,
    checkedIn: 0,
    avgRating: null,
    capacity: 120,
  })
  saveEvents()
}

onMounted(() => {
  showCreateEventToast()
  loadDashboardStats()
})
</script>