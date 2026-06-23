<template>
  <main class="create-shell">
    <section class="create-header">
      <router-link to="/organiser/dashboard">← Back to Dashboard</router-link>
      <h1>{{ editingEventId ? 'Edit Event' : 'Create New Event' }}</h1>
      <p>Fill in the details. Faculty admin will review before publishing.</p>
    </section>

    <section class="stepper">
      <div
        v-for="(s, idx) in steps"
        :key="s.key"
        :class="['step-item', { active: currentStep === idx, done: currentStep > idx }]"
      >
        <span class="step-number">{{ idx + 1 }}</span>
        <span>{{ s.label }}</span>
      </div>
    </section>

    <!-- Step 1: Basic Information -->
    <section v-if="currentStep === 0" class="create-card">
      <h2>Step 1 — Basic Information</h2>

      <label class="form-label">
        Event title *
        <input type="text" v-model="form.title" placeholder="e.g. Annual Tech Symposium 2026" />
      </label>

      <div class="input-row-2">
        <label class="form-label">
          Category *
          <select v-model="form.category">
            <option value="">Select category...</option>
            <option>Academic</option>
            <option>Sports</option>
            <option>Cultural</option>
            <option>Religious</option>
            <option>Workshop</option>
          </select>
        </label>
        <label class="form-label">
          Society *
          <select v-model="form.society">
            <option>Computer Society UTM</option>
            <option>IEEE UTM</option>
            <option>Sports Club UTM</option>
            <option>Cultural Club</option>
          </select>
        </label>
      </div>

      <div class="input-row-2">
        <label class="form-label">
          Start date &amp; time *
          <input type="datetime-local" v-model="form.startDateTime" />
        </label>
        <label class="form-label">
          End date &amp; time *
          <input type="datetime-local" v-model="form.endDateTime" />
        </label>
      </div>

      <label class="form-label">
        Venue *
        <input type="text" v-model="form.location" placeholder="e.g. Dewan Sultan Iskandar, UTM JB" />
      </label>

      <label class="form-label">
        Event description *
        <textarea
          v-model="form.description"
          placeholder="Describe your event — agenda, speakers, requirements..."
        ></textarea>
      </label>

      <label class="form-label">
        Banner image
        <input
          ref="bannerInput"
          type="file"
          accept="image/png,image/jpeg,image/jpg"
          class="hidden-file-input"
          @change="handleBannerUpload"
        />
        <div
          class="upload-box"
          :class="{ 'has-preview': form.bannerImage }"
          @click="$refs.bannerInput.click()"
        >
          <img v-if="form.bannerImage" :src="form.bannerImage" alt="Event banner preview" />
          <div v-else>
            Drag &amp; drop image here or <strong>browse</strong><br />
            <span>PNG, JPG up to 5MB · Recommended 1200x400px</span>
          </div>
        </div>
      </label>

      <p v-if="stepError" class="auth-error">{{ stepError }}</p>

      <div class="create-actions">
        <router-link class="button button-ghost" to="/organiser/dashboard">Cancel</router-link>
        <button class="button button-primary" @click="nextStep">Next: Ticketing →</button>
      </div>
    </section>

    <!-- Step 2: Ticketing -->
    <section v-if="currentStep === 1" class="create-card">
      <h2>Step 2 — Ticketing</h2>

      <div class="input-row-2">
        <label class="form-label">
          Capacity *
          <input type="number" v-model.number="form.capacity" placeholder="80" />
        </label>
        <label class="form-label">
          Registration deadline *
          <input type="datetime-local" v-model="form.deadline" />
        </label>
      </div>

      <label class="form-label">
        Ticket price
        <div class="input-row-2">
          <div class="ticket-option">
            <strong>
              <input type="radio" value="Free" v-model="form.feeType" />
              Free event
            </strong>
            <p>Students can register without mock payment.</p>
          </div>
          <div class="ticket-option">
            <strong>
              <input type="radio" value="Paid" v-model="form.feeType" />
              Paid event
            </strong>
            <p>Students complete mock payment before ticket confirmation.</p>
          </div>
        </div>
      </label>

      <div class="input-row-2">
        <label class="form-label">
          Fee amount (RM)
          <input
            type="number"
            min="0"
            step="0.01"
            v-model.number="form.feeAmount"
            :disabled="form.feeType === 'Free'"
            placeholder="0.00"
          />
        </label>
        <label class="form-label">
          Waitlist
          <select v-model="form.waitlist">
            <option value="enabled">Enable when event is full</option>
            <option value="disabled">Disable waitlist</option>
          </select>
        </label>
      </div>

      <p v-if="stepError" class="auth-error">{{ stepError }}</p>

      <div class="create-actions">
        <button class="button button-ghost" @click="prevStep">Back</button>
        <button class="button button-primary" @click="nextStep">Next: Details →</button>
      </div>
    </section>

    <!-- Step 3: Event Details -->
    <section v-if="currentStep === 2" class="create-card">
      <h2>Step 3 — Event Details</h2>

      <label class="form-label">
        Event poster *
        <input
          ref="posterInput"
          type="file"
          accept="image/png,image/jpeg,image/jpg"
          class="hidden-file-input"
          @change="handlePosterUpload"
        />
        <div
          class="upload-box"
          :class="{ 'has-preview': form.posterImage }"
          @click="$refs.posterInput.click()"
        >
          <img v-if="form.posterImage" :src="form.posterImage" alt="Event poster preview" />
          <div v-else>
            Drag &amp; drop poster here or <strong>browse</strong><br />
            <span>PNG, JPG up to 5MB · Recommended 1200x400px</span>
          </div>
        </div>
      </label>

      <div class="input-row-2">
        <label class="form-label">
          Contact person
          <input type="text" v-model="form.contactName" placeholder="e.g. Siti Noor" />
        </label>
        <label class="form-label">
          Contact email
          <input type="email" v-model="form.contactEmail" placeholder="society@utm.my" />
        </label>
      </div>

      <label class="form-label">
        Special instructions
        <textarea
          v-model="form.instructions"
          placeholder="Optional: dress code, materials to bring, prerequisite knowledge, or check-in notes."
        ></textarea>
      </label>

      <div class="create-actions">
        <button class="button button-ghost" @click="prevStep">Back</button>
        <button class="button button-primary" @click="nextStep">Next: Review →</button>
      </div>
    </section>

    <!-- Step 4: Review -->
    <section v-if="currentStep === 3" class="review-layout">
      <article class="create-card">
        <h2>Public Event Preview</h2>

        <div
          class="review-banner"
          :style="previewImage ? {
            backgroundImage: `linear-gradient(rgba(49, 46, 129, 0.35), rgba(49, 46, 129, 0.55)), url(${previewImage})`
          } : {}"
        >
          <div>
            <span class="badge badge-blue">{{ form.category || 'Academic' }}</span>
            <h3>{{ form.title || 'Untitled Event' }}</h3>
            <p>{{ form.society }} · Faculty approval required</p>
          </div>
        </div>

        <div class="review-grid">
          <div class="review-item"><span>Date &amp; Time</span><strong>{{ formattedDateRange }}</strong></div>
          <div class="review-item"><span>Venue</span><strong>{{ form.location || 'Not set' }}</strong></div>
          <div class="review-item"><span>Capacity</span><strong>{{ form.capacity || 0 }} attendees</strong></div>
          <div class="review-item"><span>Ticket</span><strong>{{ form.feeType === 'Paid' ? `RM ${form.feeAmount || 0}` : 'Free' }}</strong></div>
          <div class="review-item"><span>Deadline</span><strong>{{ formattedDeadline }}</strong></div>
          <div class="review-item"><span>Status</span><strong>{{ editingEventId ? 'Updated Draft' : 'Draft' }}</strong></div>
        </div>
      </article>

      <aside class="review-panel">
        <h2>Submission Checklist</h2>
        <div class="approval-note">
          This event will move from draft to pending approval after submission. Faculty Admin must approve it before it appears in the public event list.
        </div>
        <div class="detail-list">
          <div><dt>Basic information</dt><dd>Complete</dd></div>
          <div><dt>Ticketing</dt><dd>{{ form.feeType }} event configured</dd></div>
          <div><dt>Poster</dt><dd>Ready for review</dd></div>
          <div><dt>Approval status</dt><dd>Draft → Pending approval</dd></div>
        </div>
        <div class="create-actions">
          <button class="button button-ghost" @click="prevStep">Back</button>
          <div style="display:flex;gap:10px;">
            <button class="button button-secondary" @click="submitEvent('draft')">Save Draft</button>
            <button class="button button-primary" @click="submitEvent('submitted')">Submit for Approval</button>
          </div>
        </div>
      </aside>
    </section>
  </main>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { addApprovalEvent } from '@/stores/approvalEvents'
import { addNotification } from '@/stores/notifications'

const route = useRoute()
const router = useRouter()
const eventsStorageKey = 'eventora_society_events_v2'

const steps = [
  { key: 'basic',     label: 'Basic Info' },
  { key: 'ticketing', label: 'Ticketing'  },
  { key: 'details',   label: 'Details'    },
  { key: 'review',    label: 'Review'     },
]

const defaultEvents = [
  {
    id: 1,
    title: 'Build Your First AI App',
    category: 'Academic',
    society: 'Computer Society UTM',
    location: 'N28A Innovation Lab',
    description:
      'A practical evening workshop where students learn how to plan, prototype, and demo a simple AI-powered application.',
    bannerImage: '',
    posterImage: '',
    eventDate: '12 Jun 2026',
    startTime: '7:30 PM',
    endTime: '9:30 PM',
    registrationDeadline: '',
    feeType: 'Paid',
    feeAmount: 8,
    waitlist: 'enabled',
    contactName: '',
    contactEmail: '',
    instructions: '',
    status: 'published',
    registrations: 28,
    checkedIn: 18,
    avgRating: 4.5,
    capacity: 40,
  },
  {
    id: 2,
    title: 'Hackathon 2026',
    category: 'Academic',
    society: 'Computer Society UTM',
    location: 'FAB Lab',
    description:
      'A full-day hackathon for student teams to build software prototypes, receive mentor feedback, and present solutions.',
    bannerImage: '',
    posterImage: '',
    eventDate: '5 Jul 2026',
    startTime: '9:00 AM',
    endTime: '6:00 PM',
    registrationDeadline: '',
    feeType: 'Paid',
    feeAmount: 15,
    waitlist: 'enabled',
    contactName: '',
    contactEmail: '',
    instructions: '',
    status: 'pending_approval',
    registrations: 0,
    checkedIn: 0,
    avgRating: null,
    capacity: 60,
  },
  {
    id: 3,
    title: 'Futsal Tournament',
    category: 'Sports',
    society: 'Sports Club UTM',
    location: 'UTM Sports Hall',
    description:
      'A sports event for student teams to compete in an interfaculty futsal tournament at UTM Sports Hall.',
    bannerImage: '',
    posterImage: '',
    eventDate: '28 Jun 2026',
    startTime: '9:00 AM',
    endTime: '1:00 PM',
    registrationDeadline: '',
    feeType: 'Free',
    feeAmount: 0,
    waitlist: 'enabled',
    contactName: '',
    contactEmail: '',
    instructions: '',
    status: 'published',
    registrations: 40,
    checkedIn: 32,
    avgRating: 4.2,
    capacity: 40,
  },
]

const currentStep    = ref(0)
const stepError      = ref('')
const editingEventId = ref(null)

const form = reactive({
  title:         '',
  category:      '',
  society:       'Computer Society UTM',
  startDateTime: '',
  endDateTime:   '',
  location:      '',
  description:   '',
  bannerImage:   '',
  posterImage:   '',
  capacity:      null,
  deadline:      '',
  feeType:       'Free',
  feeAmount:     0,
  waitlist:      'enabled',
  contactName:   '',
  contactEmail:  '',
  instructions:  '',
})

// ── Computed ──────────────────────────────────────────────────────────────────

const formattedDateRange = computed(() => {
  if (!form.startDateTime) return 'Not set'
  const start = new Date(form.startDateTime)
  const end   = form.endDateTime ? new Date(form.endDateTime) : null
  const dateStr   = start.toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' })
  const startTime = start.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit' })
  const endTime   = end ? end.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit' }) : '--'
  return `${dateStr}, ${startTime} - ${endTime}`
})

const formattedDeadline = computed(() => {
  if (!form.deadline) return 'Not set'
  return new Date(form.deadline).toLocaleDateString('en-GB', {
    day: 'numeric', month: 'short', year: 'numeric',
    hour: 'numeric', minute: '2-digit',
  })
})

const previewImage = computed(() => form.posterImage || form.bannerImage)

// ── onMounted: load event for editing ────────────────────────────────────────

onMounted(() => {
  const editId = route.query.edit
  if (!editId) return

  const storedEvents = JSON.parse(localStorage.getItem(eventsStorageKey) || 'null')
  const events = storedEvents || defaultEvents
  const event  = events.find((ev) => String(ev.id) === String(editId))
  if (!event) return

  editingEventId.value = event.id

  form.title        = event.title        || ''
  form.category     = event.category     || ''
  form.society      = event.society      || 'Computer Society UTM'
  form.location     = event.location     || ''
  form.description  = event.description  || ''
  form.bannerImage  = event.bannerImage  || ''
  form.posterImage  = event.posterImage  || ''
  form.capacity     = event.capacity     || null
  form.deadline     = toDateTimeLocal(event.registrationDeadline)
  form.feeType      = event.feeType      || 'Free'
  form.feeAmount    = event.feeAmount    || 0
  form.waitlist     = event.waitlist     || 'enabled'
  form.contactName  = event.contactName  || ''
  form.contactEmail = event.contactEmail || ''
  form.instructions = event.instructions || ''

  form.startDateTime = combineDateAndTime(event.eventDate, event.startTime)
  form.endDateTime   = combineDateAndTime(event.eventDate, event.endTime)
})

// ── Image upload handlers ─────────────────────────────────────────────────────

function handleBannerUpload(event) {
  handleImageUpload(event, 'bannerImage', 'Banner image must be less than 5MB.')
}

function handlePosterUpload(event) {
  handleImageUpload(event, 'posterImage', 'Poster image must be less than 5MB.')
}

function handleImageUpload(event, targetField, errorMessage) {
  const file = event.target.files?.[0]
  if (!file) return

  if (file.size > 5 * 1024 * 1024) {
    stepError.value = errorMessage
    return
  }

  const reader = new FileReader()
  reader.onload = () => { form[targetField] = reader.result }
  reader.readAsDataURL(file)
}

// ── Step navigation ───────────────────────────────────────────────────────────

function nextStep() {
  stepError.value = ''

  if (currentStep.value === 0) {
    if (!form.title || !form.category || !form.startDateTime || !form.endDateTime || !form.location || !form.description) {
      stepError.value = 'Please fill in all required fields marked with *.'
      return
    }
  }

  if (currentStep.value === 1) {
    if (!form.capacity || form.capacity < 1 || !form.deadline) {
      stepError.value = 'Please provide a valid capacity and registration deadline.'
      return
    }
  }

  currentStep.value++
}

function prevStep() {
  stepError.value = ''
  currentStep.value--
}

// ── Submit ────────────────────────────────────────────────────────────────────

function submitEvent(action) {
  const storedEvents = JSON.parse(localStorage.getItem(eventsStorageKey) || 'null')
  const events       = storedEvents || [...defaultEvents]
  const existingEvent = events.find((ev) => String(ev.id) === String(editingEventId.value))

  const eventPayload = {
    id:                   editingEventId.value || Date.now(),
    title:                form.title,
    category:             form.category,
    society:              form.society,
    location:             form.location,
    description:          form.description,
    bannerImage:          form.bannerImage,
    posterImage:          form.posterImage,
    eventDate:            form.startDateTime
      ? new Date(form.startDateTime).toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' })
      : '',
    startTime:            form.startDateTime
      ? new Date(form.startDateTime).toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit' })
      : '',
    endTime:              form.endDateTime
      ? new Date(form.endDateTime).toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit' })
      : '',
    registrationDeadline: form.deadline,
    feeType:              form.feeType,
    feeAmount:            form.feeType === 'Paid' ? form.feeAmount : 0,
    waitlist:             form.waitlist,
    contactName:          form.contactName,
    contactEmail:         form.contactEmail,
    instructions:         form.instructions,
    status:               action === 'draft' ? 'draft' : 'pending_approval',
    registrations:        existingEvent?.registrations ?? 0,
    checkedIn:            existingEvent?.checkedIn     ?? 0,
    avgRating:            existingEvent?.avgRating     ?? null,
    capacity:             form.capacity,
  }

  if (editingEventId.value) {
    const updatedEvents = events.map((ev) =>
      String(ev.id) === String(editingEventId.value) ? eventPayload : ev
    )
    localStorage.setItem(eventsStorageKey, JSON.stringify(updatedEvents))
  } else {
    events.unshift(eventPayload)
    localStorage.setItem(eventsStorageKey, JSON.stringify(events))
  }

  if (action === 'submitted') {
    addEventToApprovalQueue(eventPayload)
    addSubmissionNotifications(eventPayload)
  }

  router.push({ path: '/organiser/dashboard', query: { eventSaved: action } })
}

function addSubmissionNotifications(event) {
  addNotification({
    audience:   'organiser',
    type:       'Approval',
    title:      'Event submitted for approval',
    message:    `${event.title} has been submitted and is waiting for Faculty Admin review.`,
    badgeClass: 'badge-yellow',
  })

  addNotification({
    audience:   'faculty_admin',
    type:       'Approval',
    title:      'New event pending approval',
    message:    `${event.title} submitted by ${event.society} is waiting for review.`,
    badgeClass: 'badge-yellow',
  })
}

function addEventToApprovalQueue(event) {
  addApprovalEvent({
    id:       event.id,
    society:  event.society,
    title:    event.title,
    date:     event.eventDate,
    category: event.category,
    capacity: event.capacity,
    details: {
      submittedBy:  event.contactName || 'Organiser',
      submittedAt:  'just now',
      displayDate:  `${event.eventDate}, ${event.startTime} - ${event.endTime}`,
      venue:        event.location || 'TBC',
      deadline:     formattedDeadline.value,
      price:        event.feeType === 'Paid' ? `RM ${Number(event.feeAmount || 0).toFixed(2)}` : 'Free',
      image:        event.posterImage || event.bannerImage || '',
      description:  event.description || 'Event description preview. Admin can open full details to review the complete submission.',
    },
  })
}

// ── Date helpers ──────────────────────────────────────────────────────────────

function combineDateAndTime(dateText, timeText) {
  if (!dateText || !timeText) return ''
  const parsed = new Date(`${dateText} ${timeText}`)
  return toDateTimeLocal(parsed)
}

function toDateTimeLocal(value) {
  if (!value) return ''
  const date = value instanceof Date ? value : new Date(value)
  if (Number.isNaN(date.getTime())) return ''
  const pad = (n) => String(n).padStart(2, '0')
  return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(date.getHours())}:${pad(date.getMinutes())}`
}
</script>

<style scoped>
.create-shell {
  width: min(1080px, calc(100% - 80px));
  margin: 0 auto;
  padding: 28px 0 96px;
  min-height: 100vh;
}

.create-header { margin-bottom: 24px; }
.create-header a { display: inline-flex; margin-bottom: 14px; color: var(--muted); text-decoration: none; font-size: 0.86rem; }
.create-header h1 { margin: 0 0 6px; font-size: 1.6rem; }
.create-header p  { margin: 0; color: var(--muted); font-size: 0.9rem; }

.stepper {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  align-items: start;
  margin: 28px 0 34px;
  position: relative;
}
.stepper::before {
  content: "";
  position: absolute;
  top: 13px;
  left: 8%; right: 8%;
  height: 1px;
  background: var(--border);
}
.step-item {
  position: relative;
  display: grid;
  justify-items: center;
  gap: 8px;
  color: var(--muted);
  font-size: 0.76rem;
  z-index: 1;
}
.step-number {
  width: 28px; height: 28px;
  border-radius: 999px;
  display: grid;
  place-items: center;
  background: #e5e7eb;
  color: var(--muted);
  font-weight: 700;
}
.step-item.done   .step-number { background: var(--success); color: #fff; }
.step-item.active              { color: var(--primary); font-weight: 700; }
.step-item.active .step-number { background: var(--primary); color: #fff; }

.create-card {
  border: 1px solid var(--border);
  border-radius: var(--radius-lg);
  background: var(--surface);
  box-shadow: var(--shadow);
  padding: 24px;
}
.create-card h2 { margin: 0 0 22px; font-size: 1rem; }

.form-label {
  display: grid;
  gap: 8px;
  margin-bottom: 18px;
  color: var(--muted);
  font-size: 0.76rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.06em;
}
.form-label input,
.form-label select,
.form-label textarea {
  width: 100%;
  min-height: 42px;
  border: 1px solid var(--border);
  border-radius: var(--radius-sm);
  padding: 0 12px;
  background: #fff;
  color: var(--text);
  font-size: 0.9rem;
  text-transform: none;
  letter-spacing: 0;
  font-weight: 500;
}
.form-label textarea { min-height: 100px; padding: 12px; resize: vertical; }

.hidden-file-input { display: none; }

.upload-box {
  display: grid;
  place-items: center;
  min-height: 96px;
  border: 1.5px dashed var(--border);
  border-radius: var(--radius-sm);
  background: #fff;
  color: var(--muted);
  text-align: center;
  font-size: 0.82rem;
  cursor: pointer;
  overflow: hidden;
}
.upload-box strong { color: var(--primary); }
.upload-box.has-preview { min-height: 180px; padding: 0; border-style: solid; }
.upload-box img { width: 100%; height: 100%; min-height: 180px; object-fit: cover; display: block; }

.create-actions { display: flex; justify-content: space-between; gap: 12px; margin-top: 20px; }

.ticket-option {
  padding: 16px;
  border: 1px solid var(--border);
  border-radius: var(--radius-md);
  background: var(--surface);
}
.ticket-option strong { display: block; margin-bottom: 4px; color: var(--text); }
.ticket-option p      { margin: 0; color: var(--muted); font-size: 0.84rem; }

.review-layout {
  display: grid;
  grid-template-columns: minmax(0, 1fr) 340px;
  gap: 18px;
  align-items: start;
}
.review-panel {
  border: 1px solid var(--border);
  border-radius: var(--radius-lg);
  background: var(--surface);
  box-shadow: var(--shadow);
  padding: 24px;
}
.review-banner {
  min-height: 180px;
  border-radius: var(--radius-md);
  background: linear-gradient(135deg, var(--primary), var(--primary-dark));
  background-size: cover;
  background-position: center;
  color: #fff;
  display: flex;
  align-items: end;
  padding: 20px;
  margin-bottom: 18px;
}
.review-banner h3 { color: #fff; margin: 8px 0 4px; }

.review-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 12px; }
.review-item { padding: 12px; border-radius: var(--radius-sm); background: var(--surface-soft); }
.review-item span   { display: block; color: var(--muted); font-size: 0.74rem; font-weight: 700; margin-bottom: 4px; text-transform: uppercase; letter-spacing: 0.05em; }
.review-item strong { display: block; color: var(--text); font-size: 0.9rem; }

.approval-note {
  padding: 14px;
  border: 1px solid #bfdbfe;
  border-radius: var(--radius-sm);
  background: #eff6ff;
  color: #1d4ed8;
  font-size: 0.86rem;
  margin-bottom: 16px;
}

@media (max-width: 760px) {
  .create-shell {
    width: min(100% - 32px, 1080px);
    padding: 18px 0 80px;
  }
  .stepper { grid-template-columns: repeat(2, 1fr); gap: 16px; }
  .stepper::before { display: none; }
  .review-layout { grid-template-columns: 1fr; }
  .create-actions { flex-wrap: wrap; }
}
</style>