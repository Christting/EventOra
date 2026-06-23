<template>
  <main class="app-shell">
    <section class="page-section">
      <router-link class="back-link" to="/admin">
        ← Back to Dashboard
      </router-link>

      <div class="section-heading">
        <div>
          <p class="eyebrow">Faculty Admin</p>
          <h2>Event Approval Queue</h2>
          <p class="section-subtitle">
            Review pending society events before they are published.
          </p>
        </div>
      </div>

      <div class="pending-summary">
        <div>
          <span class="summary-label">Pending Review</span>
          <strong>{{ pendingCount }}</strong>
        </div>
        <p>Events waiting for faculty admin decision</p>
      </div>

      <p v-if="loadingEvents" class="muted-text">Loading pending events...</p>
      <p v-else-if="loadError" class="auth-error">{{ loadError }}</p>

      <div v-else class="approval-list">
        <article
          v-for="ev in sortedApprovalEvents"
          :key="ev.id"
          class="approval-card"
          :class="`approval-card-${cardStatusClass(ev.status)}`"
        >
          <div class="status-bar"></div>

          <div class="approval-card-content">
            <div class="card-top">
              <div>
                <div class="badge-row">
                  <span class="mini-badge category">{{ ev.category }}</span>
                  <span :class="['mini-badge', cardStatusClass(ev.status)]">
                    {{ statusText(ev.status) }}
                  </span>
                </div>

                <h3>{{ ev.title }}</h3>
                <p class="event-meta">
                  {{ ev.society_name }}
                  <span v-if="ev.created_at"> · Submitted {{ formatDate(ev.created_at) }}</span>
                </p>
              </div>

              <router-link
                class="details-btn desktop-details"
                :to="`/admin/approval-detail/${ev.id}`"
              >
                View Full Details
              </router-link>
            </div>

            <div class="info-grid">
              <div class="info-box">
                <span>Date</span>
                <strong>{{ formatDate(ev.start_datetime) }}</strong>
              </div>
              <div class="info-box">
                <span>Venue</span>
                <strong>{{ ev.venue || '—' }}</strong>
              </div>
              <div class="info-box">
                <span>Capacity</span>
                <strong>{{ ev.capacity }} pax</strong>
              </div>
              <div class="info-box">
                <span>Price</span>
                <strong>{{ priceLabel(ev) }}</strong>
              </div>
            </div>

            <div v-if="ev.status === 'pending_approval'" class="description-box">
              {{ ev.description || 'No description provided.' }}
            </div>

            <div v-if="ev.status === 'pending_approval'" class="action-row">
              <div class="left-actions">
                <button
                  class="approve-btn"
                  type="button"
                  :disabled="actionInProgress === ev.id"
                  @click="approveEvent(ev)"
                >
                  {{ actionInProgress === ev.id ? 'Approving...' : '✓ Approve' }}
                </button>

                <button
                  class="reject-btn"
                  type="button"
                  :disabled="actionInProgress === ev.id"
                  @click="openRejectModal(ev)"
                >
                  Reject
                </button>
              </div>

              <router-link
                class="details-btn mobile-details"
                :to="`/admin/approval-detail/${ev.id}`"
              >
                View Full Details
              </router-link>
            </div>

            <div v-else class="review-result">
              <div>
                <strong>{{ ev.status === 'published' ? 'Approved' : 'Rejected' }}</strong>
                <p v-if="ev.status === 'published'">
                  This event has been approved and is now published.
                </p>
                <p v-else>Reason: {{ ev.reason || 'No reason provided.' }}</p>
              </div>
            </div>
          </div>
        </article>
      </div>
    </section>

    <section class="page-section">
      <h2>Activity Monitoring</h2>
      <div class="stats-grid" style="margin-top:1rem;">
        <div class="stat-card"><span>Total Events (This Month)</span><strong>18</strong></div>
        <div class="stat-card"><span>Active Societies</span><strong>12</strong></div>
        <div class="stat-card"><span>Total Registrations</span><strong>342</strong></div>
        <div class="stat-card"><span>Attendance Rate</span><strong>74%</strong></div>
      </div>

      <div class="capacity-bar" style="margin: 1rem 0;">
        <span style="width:74%"></span>
      </div>

      <p>Most popular category: <strong>Academic</strong> (42% of registrations)</p>
    </section>

    <div
      v-if="showModal"
      class="modal-overlay"
      role="dialog"
      aria-modal="true"
      aria-label="Reject event dialog"
      @click.self="closeModal"
    >
      <div class="modal-box">
        <div class="modal-header">
          <div>
            <p class="eyebrow">Reject event</p>
            <h3>{{ selectedEvent?.title }}</h3>
            <span style="color:var(--muted);font-size:0.82rem;">
              {{ selectedEvent?.society_name }}
            </span>
          </div>
          <span class="badge badge-red">Reason required</span>
        </div>

        <div class="modal-warning">
          Rejecting this event will notify the organiser and ask them to revise the submission.
        </div>

        <label class="input-label" style="margin-top:16px;">
          Rejection reason <span style="color:var(--danger);">*</span>
          <textarea
            v-model="rejectReason"
            rows="4"
            placeholder="e.g. Venue booking confirmation is missing."
            style="padding:10px;border:1px solid var(--border);border-radius:var(--radius-sm);font-size:0.88rem;resize:vertical;width:100%;box-sizing:border-box;margin-top:6px;"
          ></textarea>
        </label>

        <p v-if="modalError" class="auth-error">{{ modalError }}</p>

        <div class="modal-actions" style="margin-top:20px;">
          <button class="button button-ghost" @click="closeModal">Cancel</button>
          <button class="button button-danger" :disabled="isSubmittingReject" @click="confirmReject">
            {{ isSubmittingReject ? 'Submitting...' : 'Confirm Rejection' }}
          </button>
        </div>
      </div>
    </div>

    <div v-if="toast.message" :class="['eo-toast', toast.type]">
      {{ toast.message }}
    </div>
  </main>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import { getPendingEventsApi, approveEventApi, rejectEventApi } from '@/api/admin'

// ===== Real backend data (replaces doc9's local mock store) =====
const approvalEvents = ref([])
const loadingEvents = ref(true)
const loadError = ref('')

// Tracks which row currently has an approve/reject request in flight,
// so only that row's buttons disable - not the whole list.
const actionInProgress = ref(null)

async function loadPendingEvents() {
  loadingEvents.value = true
  loadError.value = ''

  try {
    const response = await getPendingEventsApi()
    approvalEvents.value = response.data.data
  } catch (err) {
    loadError.value = err.response?.data?.error?.message || 'Failed to load approval events. Please try again later.'
  } finally {
    loadingEvents.value = false
  }
}

onMounted(loadPendingEvents)

// ===== Modal state =====
const showModal = ref(false)
const selectedEvent = ref(null)
const rejectReason = ref('')
const modalError = ref('')
const isSubmittingReject = ref(false)
const toast = ref({ message: '', type: 'success' })

// ===== Derived data =====
// IMPORTANT: backend's real status enum is draft/pending_approval/
// published/completed/rejected/cancelled (PR1 Data Dictionary) - this is
// DIFFERENT from doc9's mock store, which invented pending/approved/
// rejected. The pendingCount and sort below use the REAL enum values.
const pendingCount = computed(() =>
  approvalEvents.value.filter((ev) => ev.status === 'pending_approval').length
)

const sortedApprovalEvents = computed(() => {
  const order = { pending_approval: 1, published: 2, rejected: 3 }
  return [...approvalEvents.value].sort(
    (a, b) => (order[a.status] ?? 9) - (order[b.status] ?? 9)
  )
})

// Maps the real backend status onto the 3 CSS variants the card styling
// expects (pending / approved / rejected), since the actual enum has
// more states (draft, completed, cancelled) that never reach this queue
// anyway - the admin approval API only ever returns pending_approval,
// published, or rejected events.
function cardStatusClass(status) {
  if (status === 'published') return 'approved'
  if (status === 'rejected') return 'rejected'
  return 'pending'
}

function statusText(status) {
  if (status === 'published') return 'Approved'
  if (status === 'rejected') return 'Rejected'
  return 'Pending Review'
}

// fee_type/fee_amount are real columns on the events table (PR1 Data
// Dictionary) - no need for a fake "price" field like doc9's mock store
// invented. fee_amount is DECIMAL(8,2) from MySQL, which PHP/JSON often
// serialises as a string, so Number() guards against "0.00" rendering
// as "RM 0.00".toFixed failing silently.
function priceLabel(ev) {
  if (ev.fee_type === 'free') return 'Free'
  return `RM ${Number(ev.fee_amount ?? 0).toFixed(2)}`
}

function formatDate(datetime) {
  if (!datetime) return '—'
  return new Date(datetime).toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' })
}

// ===== Actions (real API calls, not local store mutation) =====
async function approveEvent(ev) {
  actionInProgress.value = ev.id

  try {
    await approveEventApi(ev.id)
    // Re-fetch rather than mutate ev.status locally - an approved event
    // should disappear from this queue entirely once it's published,
    // which a fresh load expresses more reliably than a local patch.
    await loadPendingEvents()
    showToast('Event approved successfully.', 'success')
  } catch (err) {
    const message = err.response?.data?.error?.message || 'Failed to approve event.'
    showToast(message, 'danger')
  } finally {
    actionInProgress.value = null
  }
}

function openRejectModal(ev) {
  selectedEvent.value = ev
  rejectReason.value = ''
  modalError.value = ''
  showModal.value = true
}

function closeModal() {
  showModal.value = false
  selectedEvent.value = null
  rejectReason.value = ''
  modalError.value = ''
}

async function confirmReject() {
  if (!rejectReason.value.trim()) {
    modalError.value = 'Please provide a rejection reason.'
    return
  }

  isSubmittingReject.value = true

  try {
    await rejectEventApi(selectedEvent.value.id, rejectReason.value.trim())
    await loadPendingEvents()
    showToast('Event rejected. Reason has been recorded.', 'danger')
    closeModal()
  } catch (err) {
    modalError.value = err.response?.data?.error?.message || 'Failed to reject event.'
  } finally {
    isSubmittingReject.value = false
  }
}

function showToast(message, type) {
  toast.value = { message, type }
  setTimeout(() => {
    toast.value = { message: '', type: 'success' }
  }, 2500)
}
</script>

<style scoped>
.back-link {
  display: inline-flex;
  align-items: center;
  margin-bottom: 14px;
  color: var(--muted);
  text-decoration: none;
  font-size: 0.86rem;
  font-weight: 700;
}

.back-link:hover {
  color: var(--primary);
}

.section-subtitle,
.muted-text {
  margin: 4px 0 0;
  color: var(--muted);
  font-size: 0.9rem;
}

.pending-summary {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 12px;
  margin: 16px 0 18px;
  padding: 14px 16px;
  border: 1px solid #fed7aa;
  border-radius: 14px;
  background: #fff7ed;
  color: #92400e;
}

.pending-summary div {
  display: flex;
  align-items: center;
  gap: 10px;
}

.summary-label {
  font-size: 0.8rem;
  font-weight: 800;
  text-transform: uppercase;
  letter-spacing: 0.06em;
}

.pending-summary strong {
  font-size: 1.5rem;
  color: #c2410c;
}

.pending-summary p {
  margin: 0;
  font-size: 0.88rem;
}

.approval-list {
  display: grid;
  gap: 16px;
}

.approval-card {
  display: grid;
  grid-template-columns: 6px 1fr;
  overflow: hidden;
  border: 1px solid var(--border);
  border-radius: 16px;
  background: var(--surface);
  box-shadow: var(--shadow);
}

.status-bar {
  background: #f59e0b;
}

.approval-card-approved .status-bar {
  background: #10b981;
}

.approval-card-rejected .status-bar {
  background: #ef4444;
}

.approval-card-approved,
.approval-card-rejected {
  opacity: 0.86;
}

.approval-card-content {
  padding: 20px;
}

.card-top {
  display: flex;
  justify-content: space-between;
  gap: 16px;
  align-items: flex-start;
}

.badge-row {
  display: flex;
  gap: 8px;
  margin-bottom: 10px;
}

.mini-badge {
  padding: 5px 10px;
  border-radius: 999px;
  font-size: 0.72rem;
  font-weight: 800;
}

.category {
  background: #eef2ff;
  color: var(--primary);
}

.pending {
  background: #fff7ed;
  color: #c2410c;
}

.approved {
  background: #ecfdf5;
  color: #047857;
}

.rejected {
  background: #fef2f2;
  color: #b91c1c;
}

.approval-card h3 {
  margin: 0 0 4px;
  font-size: 1.05rem;
}

.event-meta {
  margin: 0 0 18px;
  color: var(--muted);
  font-size: 0.86rem;
}

.info-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 10px;
}

.info-box,
.description-box {
  padding: 12px;
  border-radius: 10px;
  background: var(--surface-soft);
}

.info-box span {
  display: block;
  margin-bottom: 8px;
  color: var(--muted);
  font-size: 0.78rem;
}

.info-box strong {
  font-size: 0.9rem;
}

.description-box {
  margin-top: 12px;
  color: var(--muted);
  font-size: 0.86rem;
}

.action-row,
.review-result {
  display: flex;
  justify-content: space-between;
  gap: 12px;
  align-items: center;
  margin-top: 16px;
}

.left-actions {
  display: flex;
  gap: 8px;
  flex-wrap: wrap;
}

.approve-btn,
.reject-btn,
.details-btn {
  border-radius: 9px;
  padding: 10px 16px;
  font-weight: 700;
  font-size: 0.86rem;
  cursor: pointer;
  text-decoration: none;
}

.approve-btn {
  border: 0;
  background: #10b981;
  color: white;
}

.approve-btn:disabled,
.reject-btn:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.reject-btn {
  border: 1px solid #fecaca;
  background: white;
  color: #dc2626;
}

.details-btn {
  border: 1px solid var(--border);
  background: white;
  color: var(--muted);
  white-space: nowrap;
}

.mobile-details {
  display: none;
}

.review-result {
  padding: 12px;
  border-radius: 12px;
  background: var(--surface-soft);
}

.review-result p {
  margin: 4px 0 0;
  color: var(--muted);
  font-size: 0.86rem;
}

.modal-warning {
  margin-top: 16px;
  padding: 12px;
  border: 1px solid #fde68a;
  border-radius: 12px;
  background: #fff7ed;
  color: #92400e;
  font-size: 0.86rem;
}

.eo-toast {
  position: fixed;
  right: 24px;
  bottom: 24px;
  z-index: 9999;
  padding: 14px 20px;
  border-radius: 12px;
  color: white;
  font-weight: 700;
  box-shadow: var(--shadow-lg);
}

.eo-toast.success {
  background: var(--success);
}

.eo-toast.danger {
  background: var(--danger);
}

@media (max-width: 900px) {
  .pending-summary,
  .card-top,
  .action-row,
  .review-result {
    display: block;
  }

  .desktop-details {
    display: none;
  }

  .mobile-details {
    display: inline-flex;
    margin-top: 10px;
  }

  .info-grid {
    grid-template-columns: repeat(2, 1fr);
  }
}
</style>