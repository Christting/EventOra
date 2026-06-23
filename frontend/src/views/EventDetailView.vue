<template>
  <main class="app-shell">
    <div v-if="loading" style="padding: 60px; text-align: center;">
      Loading event details...
    </div>

    <div v-else-if="!event" style="padding: 60px; text-align: center;">
      <h2>Event not found</h2>
      <router-link to="/" class="button button-secondary">Back to Events</router-link>
    </div>

    <section v-else class="detail-layout page-section" style="margin-top: 24px;">
      <article class="event-detail-copy">
        <div :class="['event-banner', event.coverClass]" style="padding: 40px; border-radius: 16px; color: white; margin-bottom: 24px;">
          <span :class="['badge', event.badgeClass]">{{ event.societyName }}</span>
          <h2 style="margin-top: 12px; color: white;">{{ event.title }}</h2>
        </div>

        <h3>About this event</h3>
        <p style="line-height: 1.6; font-size: 1.1rem; margin-bottom: 24px;">{{ event.description }}</p>

        <dl class="detail-list" style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; background: var(--bg-surface, #f8fafc); padding: 20px; border-radius: 12px; margin-bottom: 24px;">
          <div>
            <dt style="font-size: 0.85rem; color: #64748b;">Date & Time</dt>
            <dd style="font-weight: 600; margin: 4px 0 0 0;">{{ formattedDate }}</dd>
          </div>
          <div>
            <dt style="font-size: 0.85rem; color: #64748b;">Venue</dt>
            <dd style="font-weight: 600; margin: 4px 0 0 0;">{{ event.venue }}</dd>
          </div>
          <div>
            <dt style="font-size: 0.85rem; color: #64748b;">Category</dt>
            <dd style="font-weight: 600; margin: 4px 0 0 0; text-transform: capitalize;">{{ event.category }}</dd>
          </div>
          <div>
            <dt style="font-size: 0.85rem; color: #64748b;">Registration Closes</dt>
            <dd style="font-weight: 600; margin: 4px 0 0 0;">{{ formattedDeadline }}</dd>
          </div>
        </dl>

        <div style="display: flex; flex-wrap: wrap; gap: 12px;">
          <button class="button button-secondary" @click="downloadICS">📅 Download .ics</button>
          <button class="button button-secondary" @click="addToGoogleCalendar">➕ Google Calendar</button>
          <button class="button button-ghost" @click="toggleFavorite">
            {{ isFavorited ? '❤️ Favorited' : '🤍 Add to Favorites' }}
          </button>
          <button class="button button-ghost" @click="shareEvent">🔗 Share Link</button>
        </div>

        <div v-if="shareToast" style="color: #10b981; font-size: 0.85rem; margin-top: 8px; font-weight: 500;">
          ✓ Event link copied to clipboard!
        </div>
      </article>

      <aside class="registration-panel">
        <div class="registration-header">
          <div>
            <span class="badge badge-blue">{{ event.priceType === 'paid' ? 'Paid Ticket' : 'Free Entry' }}</span>
            <h3 style="margin: 8px 0 0 0;">Reserve your seat</h3>
          </div>
          <strong style="font-size: 1.5rem;">{{ event.priceType === 'paid' ? `RM ${event.price}` : 'Free' }}</strong>
        </div>

        <div class="capacity-labels">
          <span>{{ event.confirmedCount }} registered</span>
          <strong :style="{ color: seatsLeft === 0 ? '#ef4444' : 'inherit' }">
            {{ seatsLeft === 0 ? 'Fully Booked' : `${seatsLeft} seats left` }}
          </strong>
        </div>

        <div class="capacity-bar" style="margin-bottom: 24px;">
          <span :style="{ width: `${occupancyRate}%`, background: seatsLeft === 0 ? '#ef4444' : '#3b82f6' }"></span>
        </div>

        <button 
          class="button button-primary full-width" 
          style="justify-content: center;"
          :disabled="seatsLeft === 0 && !event.waitlistEnabled"
          @click="proceedFlow"
        >
          {{ buttonLabel }}
        </button>
      </aside>
    </section>
  </main>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRoute } from 'vue-router'

const route = useRoute()
const loading = ref(true)
const event = ref(null)
const favorites = ref([])
const shareToast = ref(false)

const favKey = 'eventora_favs_v2'

const backupAnnualTech = {
  id: 'event-annual-tech-2026',
  title: 'Annual Tech Symposium 2026',
  societyName: 'Computer Society UTM',
  category: 'academic',
  price: 5,
  priceType: 'paid',
  startAt: '2026-07-15T09:00:00',
  endAt: '2026-07-15T17:00:00',
  registrationDeadline: '2026-07-10T23:59:00',
  venue: 'Dewan Sultan Iskandar, UTM JB',
  capacity: 120,
  confirmedCount: 78,
  description: 'Annual Tech Symposium 2026 brings together students, organisers, and faculty members for a full-day technology event. The event includes tech talks, demo booths, student project showcases, and networking sessions.',
  coverClass: 'academic-cover',
  badgeClass: 'badge-blue',
  waitlistEnabled: true
}

onMounted(async () => {
  favorites.value = JSON.parse(localStorage.getItem(favKey) || '[]')
  try {
    const res = await fetch('/mock/events.json')
    const all = await res.json()
    
    let target = all.find(e => String(e.id) === String(route.params.id))
    
    if (!target && route.params.id === 'event-annual-tech-2026') {
      target = backupAnnualTech
    }
    
    event.value = target || null
  } catch(e) {
    console.error(e)
    if (route.params.id === 'event-annual-tech-2026') {
      event.value = backupAnnualTech
    }
  } finally {
    loading.value = false
  }
})

const seatsLeft = computed(() => event.value ? Math.max(event.value.capacity - event.value.confirmedCount, 0) : 0)
const occupancyRate = computed(() => event.value ? Math.min(Math.round((event.value.confirmedCount / event.value.capacity) * 100), 100) : 0)
const isFavorited = computed(() => event.value ? favorites.value.includes(event.value.id) : false)

const formattedDate = computed(() => event.value ? new Date(event.value.startAt).toLocaleString('en-MY', { dateStyle: 'medium', timeStyle: 'short' }) : '')
const formattedDeadline = computed(() => event.value ? new Date(event.value.registrationDeadline).toLocaleString('en-MY', { dateStyle: 'medium', timeStyle: 'short' }) : '')

const buttonLabel = computed(() => {
  if (seatsLeft.value > 0) return event.value.priceType === 'paid' ? 'Proceed to Mock Checkout' : 'Confirm Free Registration'
  return event.value.waitlistEnabled ? 'Join Waitlist' : 'Registration Closed'
})

function toggleFavorite() {
  if (!event.value) return
  if (isFavorited.value) favorites.value = favorites.value.filter(id => id !== event.value.id)
  else favorites.value.push(event.value.id)
  localStorage.setItem(favKey, JSON.stringify(favorites.value))
}

function shareEvent() {
  navigator.clipboard.writeText(window.location.href)
  shareToast.value = true
  setTimeout(() => shareToast.value = false, 3000)
}

function downloadICS() {
  if (!event.value) return
  const s = new Date(event.value.startAt).toISOString().replace(/-|:|\.\d+/g, '').slice(0, 15) + 'Z'
  const e = new Date(event.value.endAt).toISOString().replace(/-|:|\.\d+/g, '').slice(0, 15) + 'Z'
  
  const ics = `BEGIN:VCALENDAR\nVERSION:2.0\nPRODID:-//EventOra//VueRefactor\nBEGIN:VEVENT\nSUMMARY:${event.value.title}\nDESCRIPTION:${event.value.description}\nLOCATION:${event.value.venue}\nDTSTART:${s}\nDTEND:${e}\nEND:VEVENT\nEND:VCALENDAR`
  
  const blob = new Blob([ics], { type: 'text/calendar' })
  const url = URL.createObjectURL(blob)
  const a = document.createElement('a')
  a.href = url
  a.download = `${event.value.id}.ics`
  a.click()
}

function addToGoogleCalendar() {
  if (!event.value) return
  const s = new Date(event.value.startAt).toISOString().replace(/-|:|\.\d+/g, '').slice(0, 15) + 'Z'
  const e = new Date(event.value.endAt).toISOString().replace(/-|:|\.\d+/g, '').slice(0, 15) + 'Z'
  window.open(`https://calendar.google.com/calendar/render?action=TEMPLATE&text=${encodeURIComponent(event.value.title)}&dates=${s}/${e}&details=${encodeURIComponent(event.value.description)}&location=${encodeURIComponent(event.value.venue)}`, '_blank')
}

function proceedFlow() {
  if (seatsLeft.value === 0 && event.value.waitlistEnabled) {
    window.location.href = '/src/pages/waitlist-demo.html'
  } else if (event.value.priceType === 'paid') {
    window.location.href = `/src/pages/checkout-demo.html?event=${event.value.id}`
  } else {
    window.location.href = `/src/pages/tickets.html?status=free-confirmed`
  }
}
</script>