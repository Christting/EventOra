<template>
  <main class="create-shell">
    <section class="create-header">
      <router-link to="/organiser/dashboard">← Back to Dashboard</router-link>
      <h1>{{ isEditMode ? 'Edit Event' : 'Create New Event' }}</h1>
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
            <option value="academic">Academic</option>
            <option value="sports">Sports</option>
            <option value="cultural">Cultural</option>
            <option value="religious">Religious</option>
          </select>
        </label>
        <label class="form-label">
          Society *
          <select v-model="form.societyId" :disabled="loadingSocieties">
            <option value="" disabled>{{ loadingSocieties ? 'Loading...' : 'Select society...' }}</option>
            <option v-for="s in societyOptions" :key="s.id" :value="s.id">{{ s.name }}</option>
          </select>
          <span v-if="societyLoadError" class="auth-error">{{ societyLoadError }}</span>
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
              <input type="radio" value="free" v-model="form.feeType" />
              Free event
            </strong>
            <p>Students can register without mock payment.</p>
          </div>
          <div class="ticket-option">
            <strong>
              <input type="radio" value="paid" v-model="form.feeType" />
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
            :disabled="form.feeType === 'free'"
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
            <span class="badge badge-blue">{{ categoryLabel || 'Academic' }}</span>
            <h3>{{ form.title || 'Untitled Event' }}</h3>
            <p>{{ societyName }} · Faculty approval required</p>
          </div>
        </div>

        <div class="review-grid">
          <div class="review-item"><span>Date &amp; Time</span><strong>{{ formattedDateRange }}</strong></div>
          <div class="review-item"><span>Venue</span><strong>{{ form.location || 'Not set' }}</strong></div>
          <div class="review-item"><span>Capacity</span><strong>{{ form.capacity || 0 }} attendees</strong></div>
          <div class="review-item"><span>Ticket</span><strong>{{ form.feeType === 'paid' ? `RM ${form.feeAmount || 0}` : 'Free' }}</strong></div>
          <div class="review-item"><span>Deadline</span><strong>{{ formattedDeadline }}</strong></div>
          <div class="review-item"><span>Status</span><strong>Pending Approval (on submit)</strong></div>
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
            <button
              class="button button-secondary"
              disabled
              title="Draft saving isn't supported by the backend yet (current scaffold only supports submitting directly to pending_approval)"
            >Save Draft</button>
            <button class="button button-primary" :disabled="isSubmitting" @click="submitEvent">
              {{ isSubmitting ? 'Submitting…' : (isEditMode ? 'Update & Submit' : 'Submit for Approval') }}
            </button>
          </div>
        </div>
        <p v-if="submitError" class="auth-error">{{ submitError }}</p>
      </aside>
    </section>
  </main>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { createEventApi, getOrganiserEventDetailApi, updateEventApi } from '@/api/events'
import { getMySocietiesApi } from '@/api/societies'

const route = useRoute()
const router = useRouter()

const steps = [
  { key: 'basic',     label: 'Basic Info' },
  { key: 'ticketing', label: 'Ticketing'  },
  { key: 'details',   label: 'Details'    },
  { key: 'review',    label: 'Review'     },
]

const currentStep    = ref(0)
const stepError      = ref('')
const submitError    = ref('')
const isSubmitting   = ref(false)
const isEditMode     = computed(() => Boolean(route.query.edit))

const societyOptions   = ref([])
const loadingSocieties = ref(true)
const societyLoadError = ref('')

const form = reactive({
  title:         '',
  category:      '',
  societyId:     '',
  startDateTime: '',
  endDateTime:   '',
  location:      '',
  description:   '',
  bannerImage:   '',
  posterImage:   '',
  capacity:      null,
  deadline:      '',
  feeType:       'free',
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

const categoryLabels = {
  academic: 'Academic',
  sports: 'Sports',
  cultural: 'Cultural',
  religious: 'Religious',
}
const categoryLabel = computed(() => categoryLabels[form.category] || '')

const societyName = computed(() => {
  const match = societyOptions.value.find((s) => String(s.id) === String(form.societyId))
  return match ? match.name : 'Society'
})

// ── onMounted: load societies this organiser belongs to ─────────────────────
// NOTE: editing an existing event (route.query.edit) isn't supported here -
// the backend scaffold (EventController) only exposes POST /api/events and
// GET /api/events/mine, there's no GET-by-id or PUT/PATCH yet. Once Christ's
// real Event CRUD lands, edit support should be wired up against that.

onMounted(async () => {
  try {
    const response = await getMySocietiesApi()
    societyOptions.value = response.data.data
    if (societyOptions.value.length === 1) {
      form.societyId = societyOptions.value[0].id
    }
    if (societyOptions.value.length === 0) {
      societyLoadError.value = "You're not a member of any society yet, so you can't create an event. Contact your faculty admin to be added to a society."
    }
  } catch (err) {
    societyLoadError.value = err.response?.data?.error?.message || 'Failed to load your societies. Please try again later.'
  } finally {
    loadingSocieties.value = false
  }

  if (route.query.edit) {
    await loadEventForEdit(route.query.edit)
  }
})

async function loadEventForEdit(eventId) {
  try {
    const response = await getOrganiserEventDetailApi(eventId)
    const event = response.data.data

    form.title = event.title || ''
    form.category = event.category || ''
    form.societyId = societyOptions.value.find((society) => society.name === event.society_name)?.id || form.societyId
    form.startDateTime = toDateTimeLocal(event.startAt)
    form.endDateTime = toDateTimeLocal(event.endAt)
    form.location = event.venue || event.location || ''
    form.description = event.description || ''
    form.capacity = event.capacity || null
    form.deadline = toDateTimeLocal(event.registrationDeadline)
    form.feeType = event.feeType || 'free'
    form.feeAmount = event.feeAmount || 0
    form.waitlist = event.waitlistEnabled === false ? 'disabled' : 'enabled'
  } catch (err) {
    submitError.value = err.response?.data?.error?.message || 'Failed to load event for editing.'
  }
}

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
    if (!form.title || !form.category || !form.societyId || !form.startDateTime || !form.endDateTime || !form.location || !form.description) {
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
// Posts straight to POST /api/events. Note this backend scaffold only
// accepts a subset of the form's fields (see EventController.php) -
// description, poster/banner images, contact info, and special
// instructions are NOT persisted yet. They'll keep working in the UI but
// silently won't be saved until Christ's real Event CRUD replaces this
// scaffold and adds columns/handling for them.

function datetimeLocalToMysql(value) {
  // '2026-07-10T14:00' -> '2026-07-10 14:00:00'
  if (!value) return ''
  return `${value.replace('T', ' ')}:00`
}

async function submitEvent() {
  submitError.value = ''
  isSubmitting.value = true

  try {
    const payload = {
      society_id:      form.societyId,
      title:           form.title,
      description:     form.description,
      venue:           form.location,
      category:        form.category,
      start_datetime:  datetimeLocalToMysql(form.startDateTime),
      end_datetime:    datetimeLocalToMysql(form.endDateTime),
      reg_deadline:    datetimeLocalToMysql(form.deadline),
      capacity:        form.capacity,
      fee_type:        form.feeType,
      fee_amount:       form.feeType === 'paid' ? form.feeAmount : 0,
      waitlist_enabled: form.waitlist === 'enabled',
      contact_person:  form.contactName,
      contact_email:   form.contactEmail,
      special_instructions: form.instructions,
    }

    const response = isEditMode.value
      ? await updateEventApi(route.query.edit, payload)
      : await createEventApi(payload)

    router.push({
      path: '/organiser/dashboard',
      query: { eventSaved: 'submitted', eventId: response.data.data.id || route.query.edit },
    })
  } catch (err) {
    const apiError = err.response?.data?.error
    if (apiError?.fields && Object.keys(apiError.fields).length) {
      submitError.value = Object.values(apiError.fields).join(' ')
    } else {
      submitError.value = apiError?.message || 'Failed to submit event. Please try again.'
    }
  } finally {
    isSubmitting.value = false
  }
}

// ── Date helpers ──────────────────────────────────────────────────────────────

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
