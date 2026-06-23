<template>
  <main class="app-shell">
    <p v-if="loading" style="color:var(--muted);padding:20px 0;">
      Loading event details...
    </p>

    <p v-else-if="!event" class="auth-error">
      Event not found.
    </p>

    <template v-else>
      <router-link class="back-link" to="/admin/approval-queue">
        ← Back to Approval Queue
      </router-link>

      <section class="approval-header">
        <div>
          <span v-if="event.status === 'published'" class="badge badge-green">Approved</span>
          <span v-else-if="event.status === 'rejected'" class="badge badge-red">Rejected</span>
          <span v-else class="badge badge-yellow">Pending approval</span>

          <h1>{{ event.title }}</h1>
          <p>
            {{ event.society_name }} · Submitted by {{ eventDetail.submittedBy }},
            {{ eventDetail.submittedAt }}
          </p>
        </div>
      </section>

      <section class="approval-layout">
        <article class="approval-card">
          <div
            class="poster-preview"
            :style="eventDetail.image ? {
              backgroundImage: `linear-gradient(rgba(49, 46, 129, 0.35), rgba(49, 46, 129, 0.55)), url(${eventDetail.image})`
            } : {}"
          >
            <div>
              <span class="badge badge-blue">{{ event.category }}</span>
              <h2>{{ event.title }}</h2>
              <p>{{ eventDetail.description }}</p>
            </div>
          </div>

          <div class="info-grid">
            <div class="info-item">
              <span>Date & Time</span>
              <strong>{{ eventDetail.displayDate }}</strong>
            </div>
            <div class="info-item">
              <span>Venue</span>
              <strong>{{ eventDetail.venue }}</strong>
            </div>
            <div class="info-item">
              <span>Registration Deadline</span>
              <strong>{{ eventDetail.deadline }}</strong>
            </div>
            <div class="info-item">
              <span>Capacity</span>
              <strong>{{ event.capacity }} attendees</strong>
            </div>
            <div class="info-item">
              <span>Ticket</span>
              <strong>{{ eventDetail.price }}</strong>
            </div>
            <div class="info-item">
              <span>Submitted By</span>
              <strong>{{ event.society_name }}</strong>
            </div>
          </div>

          <h2 class="section-title">Event Description</h2>
          <p class="detail-text">{{ eventDetail.description }}</p>
        </article>

        <aside class="decision-card">
          <h2>Approval Decision</h2>

          <div v-if="event.status === 'pending_approval'" class="review-note">
            <strong>Faculty Admin review required</strong>
            <p>
              Approving this event will allow it to appear in the public event list.
              Rejecting it requires a reason for the organiser.
            </p>
          </div>

          <div v-else-if="event.status === 'published'" class="approved-note">
            <strong>Approved</strong>
            <p>This event has been approved and can appear in the public event list.</p>
          </div>

          <div v-else class="rejected-note">
            <strong>Rejected</strong>
            <p>{{ event.reason || 'No reason provided.' }}</p>
          </div>

          <div v-if="event.status === 'pending_approval'" class="decision-actions">
            <button class="button button-success full-width" @click="approveEvent">
              Approve Event
            </button>
            <button class="button button-danger full-width" @click="showModal = true">
              Reject Event
            </button>
          </div>
        </aside>
      </section>
    </template>

    <div
      v-if="showModal"
      class="modal-overlay"
      role="dialog"
      aria-modal="true"
      @click.self="closeModal"
    >
      <div class="modal-box">
        <div class="modal-header">
          <div>
            <p class="eyebrow">Reject event</p>
            <h3>{{ event.title }}</h3>
            <span style="color:var(--muted);font-size:0.82rem;">
              {{ event.society_name }}
            </span>
          </div>
          <span class="badge badge-red">Reason required</span>
        </div>

        <label class="input-label" style="margin-top:20px;">
          Rejection reason <span style="color:var(--danger);">*</span>
          <textarea
            v-model="rejectReason"
            rows="4"
            placeholder="e.g. Venue booking confirmation is missing."
            style="padding:10px;border:1px solid var(--border);border-radius:var(--radius-sm);font-size:0.88rem;resize:vertical;width:100%;box-sizing:border-box;margin-top:6px;"
          ></textarea>
        </label>

        <p v-if="modalError" class="auth-error">{{ modalError }}</p>

        <div class="rejection-notice">
          <strong>The organiser will be notified</strong>
          <p>Your reason will be saved and shown to the organiser before they resubmit.</p>
        </div>

        <div class="modal-actions" style="margin-top:20px;">
          <button class="button button-ghost" @click="closeModal">Cancel</button>
          <button class="button button-danger" @click="rejectEvent">Confirm Rejection</button>
        </div>
      </div>
    </div>
  </main>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { approveEventApi, getAdminEventApi, rejectEventApi } from '@/api/admin'

const route = useRoute()
const router = useRouter()

const showModal = ref(false)
const rejectReason = ref('')
const modalError = ref('')
const event = ref(null)
const loading = ref(true)
const loadError = ref('')

onMounted(loadEvent)

async function loadEvent() {
  loading.value = true
  loadError.value = ''

  try {
    const response = await getAdminEventApi(route.params.id)
    event.value = response.data.data
  } catch (err) {
    loadError.value = err.response?.data?.error?.message || 'Event not found.'
  } finally {
    loading.value = false
  }
}

const eventDetail = computed(() => {
  if (!event.value) return {}
  return {
    description: event.value.description || 'No description provided.',
    venue: event.value.venue || 'Not set',
    deadline: formatDate(event.value.reg_deadline),
    displayDate: `${formatDate(event.value.start_datetime)} - ${formatTime(event.value.end_datetime)}`,
    price: event.value.fee_type === 'paid' ? `RM ${Number(event.value.fee_amount || 0).toFixed(2)}` : 'Free',
    submittedBy: event.value.created_by_name || event.value.society_name,
    submittedAt: formatDate(event.value.created_at),
    image: '',
  }
})

async function approveEvent() {
  try {
    await approveEventApi(event.value.id)
    router.push('/admin/approval-queue')
  } catch (err) {
    modalError.value = err.response?.data?.error?.message || 'Failed to approve event.'
  }
}

async function rejectEvent() {
  if (!rejectReason.value.trim()) {
    modalError.value = 'Please provide a rejection reason.'
    return
  }

  try {
    await rejectEventApi(event.value.id, rejectReason.value.trim())
    router.push('/admin/approval-queue')
  } catch (err) {
    modalError.value = err.response?.data?.error?.message || 'Failed to reject event.'
  }
}

function closeModal() {
  showModal.value = false
  rejectReason.value = ''
  modalError.value = ''
}

function formatDate(value) {
  if (!value) return 'Not set'
  return new Date(value).toLocaleDateString('en-GB', {
    day: 'numeric',
    month: 'short',
    year: 'numeric',
  })
}

function formatTime(value) {
  if (!value) return '--'
  return new Date(value).toLocaleTimeString('en-US', {
    hour: 'numeric',
    minute: '2-digit',
  })
}
</script>

<style scoped>
.back-link {
  display: inline-flex;
  margin-bottom: 14px;
  color: var(--muted);
  text-decoration: none;
  font-size: 0.86rem;
}

.approval-header {
  margin-bottom: 22px;
}

.approval-header h1 {
  margin: 8px 0 6px;
  font-size: 1.6rem;
}

.approval-header p {
  margin: 0;
  color: var(--muted);
  font-size: 0.9rem;
}

.approval-layout {
  display: grid;
  grid-template-columns: minmax(0, 1fr) 300px;
  gap: 18px;
  align-items: start;
}

.approval-card,
.decision-card {
  border: 1px solid var(--border);
  border-radius: var(--radius-lg);
  background: var(--surface);
  box-shadow: var(--shadow);
  padding: 24px;
}

.poster-preview {
  min-height: 220px;
  border-radius: var(--radius-md);
  background: linear-gradient(135deg, var(--primary), var(--primary-dark));
  color: #fff;
  display: flex;
  align-items: end;
  padding: 22px;
  margin-bottom: 18px;
}

.poster-preview h2 {
  color: #fff;
  margin: 10px 0 6px;
}

.poster-preview p {
  margin: 0;
  color: rgba(255, 255, 255, 0.82);
}

.info-grid {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 12px;
}

.info-item {
  padding: 12px;
  border-radius: var(--radius-sm);
  background: var(--surface-soft);
}

.info-item span {
  display: block;
  color: var(--muted);
  font-size: 0.74rem;
  font-weight: 700;
  margin-bottom: 4px;
  text-transform: uppercase;
  letter-spacing: 0.05em;
}

.info-item strong {
  display: block;
  color: var(--text);
  font-size: 0.9rem;
}

.section-title {
  margin: 24px 0 10px;
  font-size: 1rem;
}

.detail-text {
  color: var(--muted);
  line-height: 1.6;
}

.decision-card h2 {
  margin: 0 0 14px;
  font-size: 1rem;
}

.decision-actions {
  display: grid;
  gap: 10px;
  margin-top: 18px;
}

.full-width {
  width: 100%;
  justify-content: center;
}

.review-note,
.approved-note,
.rejected-note {
  padding: 14px;
  border-radius: var(--radius-sm);
  font-size: 0.86rem;
}

.review-note p,
.approved-note p,
.rejected-note p {
  margin: 6px 0 0;
}

.review-note {
  background: var(--warning-soft);
  border: 1px solid #fde68a;
  color: #92400e;
}

.approved-note {
  background: var(--success-soft);
  border: 1px solid #bbf7d0;
  color: #065f46;
}

.rejected-note {
  background: var(--danger-soft);
  border: 1px solid #fecaca;
  color: #991b1b;
}

@media (max-width: 900px) {
  .approval-layout {
    grid-template-columns: 1fr;
  }

  .info-grid {
    grid-template-columns: 1fr;
  }
}
</style>
