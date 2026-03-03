<script setup>
import { computed, onMounted, ref } from 'vue'
import AdminPageShell from '@/components/admin/shared/AdminPageShell.vue'
import { useToast } from '@/composables/useToast'
import { listContests, createContest, selectContestWinner } from '@/services/api/admin/contests'
import { getContestParticipants } from '@/services/contests'

const toast = useToast()

const loading = ref(false)
const saving = ref(false)
const selectingWinner = ref(false)
const error = ref('')
const contests = ref([])

const participantsModalOpen = ref(false)
const participantsLoading = ref(false)
const participants = ref([])
const selectedContest = ref(null)

const form = ref({
  name: '',
  description: '',
  hashtag: 'sutazim',
  starts_at: '',
  ends_at: '',
  status: 'draft',
})

const statusLabel = {
  draft: 'Návrh',
  active: 'Aktívna',
  finished: 'Ukončená',
}

const canSubmit = computed(() => {
  return Boolean(form.value.name && form.value.starts_at && form.value.ends_at && !saving.value)
})

function formatDate(value) {
  if (!value) return '-'
  const date = new Date(value)
  if (Number.isNaN(date.getTime())) return String(value)
  return date.toLocaleString('sk-SK', { dateStyle: 'medium', timeStyle: 'short' })
}

async function loadContests() {
  loading.value = true
  error.value = ''

  try {
    const response = await listContests({ per_page: 50 })
    contests.value = Array.isArray(response.data?.data) ? response.data.data : []
  } catch (e) {
    error.value = e?.response?.data?.message || 'Nepodarilo sa načítať súťaže.'
  } finally {
    loading.value = false
  }
}

async function submitContest() {
  if (!canSubmit.value) return

  saving.value = true
  try {
    await createContest({
      name: form.value.name,
      description: form.value.description || null,
      hashtag: form.value.hashtag,
      starts_at: form.value.starts_at,
      ends_at: form.value.ends_at,
      status: form.value.status,
    })

    toast.success('Súťaž bola vytvorená.')
    form.value = {
      name: '',
      description: '',
      hashtag: 'sutazim',
      starts_at: '',
      ends_at: '',
      status: 'draft',
    }
    await loadContests()
  } catch (e) {
    toast.error(e?.response?.data?.message || 'Vytvorenie súťaže zlyhalo.')
  } finally {
    saving.value = false
  }
}

async function openParticipants(contest) {
  selectedContest.value = contest
  participants.value = []
  participantsModalOpen.value = true
  participantsLoading.value = true

  try {
    const response = await getContestParticipants(contest.id, 100)
    participants.value = Array.isArray(response?.data) ? response.data : []
  } catch (e) {
    toast.error(e?.response?.data?.message || 'Nepodarilo sa načítať účastníkov.')
  } finally {
    participantsLoading.value = false
  }
}

function closeParticipantsModal() {
  participantsModalOpen.value = false
  participants.value = []
  selectedContest.value = null
}

async function pickWinner(postId) {
  if (!selectedContest.value || selectingWinner.value) return

  selectingWinner.value = true
  try {
    await selectContestWinner(selectedContest.value.id, postId)
    toast.success('Výherca bol vybraný.')
    closeParticipantsModal()
    await loadContests()
  } catch (e) {
    const validationMessage = e?.response?.data?.errors?.post_id?.[0]
      || e?.response?.data?.errors?.contest?.[0]
      || e?.response?.data?.message
      || 'Výber výhercu zlyhal.'
    toast.error(validationMessage)
  } finally {
    selectingWinner.value = false
  }
}

onMounted(loadContests)
</script>

<template>
  <AdminPageShell title="Súťaže" subtitle="Vytvor súťaž, skontroluj oprávnené príspevky a vyber výhercu.">
    <section class="panel">
      <h2>Vytvoriť súťaž</h2>
      <form class="formGrid" @submit.prevent="submitContest">
        <label>
          <span>Názov</span>
          <input v-model="form.name" type="text" required />
        </label>
        <label>
          <span>Hashtag (bez #)</span>
          <input v-model="form.hashtag" type="text" required />
        </label>
        <label class="full">
          <span>Popis</span>
          <textarea v-model="form.description" rows="3" />
        </label>
        <label>
          <span>Začína</span>
          <input v-model="form.starts_at" type="datetime-local" required />
        </label>
        <label>
          <span>Končí</span>
          <input v-model="form.ends_at" type="datetime-local" required />
        </label>
        <label>
          <span>Stav</span>
          <select v-model="form.status">
            <option value="draft">Návrh</option>
            <option value="active">Aktívna</option>
            <option value="finished">Ukončená</option>
          </select>
        </label>
        <div class="actions full">
          <button type="submit" class="btn primary" :disabled="!canSubmit">
            {{ saving ? 'Ukladám...' : 'Vytvoriť súťaž' }}
          </button>
        </div>
      </form>
    </section>

    <section class="panel">
      <header class="panelHead">
        <h2>Zoznam súťaží</h2>
        <button type="button" class="btn" :disabled="loading" @click="loadContests">
          {{ loading ? 'Načítavam...' : 'Obnoviť' }}
        </button>
      </header>

      <p v-if="error" class="error">{{ error }}</p>
      <div v-else-if="loading" class="muted">Načítavam súťaže...</div>
      <div v-else-if="contests.length === 0" class="muted">Zatiaľ žiadne súťaže.</div>
      <div v-else class="tableWrap">
        <table>
          <thead>
            <tr>
              <th>Názov</th>
              <th>Hashtag</th>
              <th>Trvanie</th>
              <th>Stav</th>
              <th>Akcie</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="contest in contests" :key="contest.id">
              <td>{{ contest.name }}</td>
              <td>#{{ contest.hashtag }}</td>
              <td>{{ formatDate(contest.starts_at) }} - {{ formatDate(contest.ends_at) }}</td>
              <td>
                <span class="status" :class="contest.status">{{ statusLabel[contest.status] || contest.status }}</span>
              </td>
              <td class="rowActions">
                <button type="button" class="btn small" @click="openParticipants(contest)">Zobraziť účastníkov</button>
                <button
                  type="button"
                  class="btn small primary"
                  :disabled="contest.status === 'finished'"
                  @click="openParticipants(contest)"
                >
                  Vybrať výhercu
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>

    <div v-if="participantsModalOpen" class="modalBackdrop" @click.self="closeParticipantsModal">
      <div class="modal">
        <header class="modalHead">
          <div>
            <h3>{{ selectedContest?.name }}</h3>
            <p class="muted">Oprávnené príspevky pre #{{ selectedContest?.hashtag }}</p>
          </div>
          <button class="btn small" type="button" @click="closeParticipantsModal">Zavrieť</button>
        </header>

        <div v-if="participantsLoading" class="muted">Načítavam účastníkov...</div>
        <div v-else-if="participants.length === 0" class="muted">Nenašli sa žiadni oprávnení účastníci.</div>
        <div v-else class="participantsList">
          <article v-for="participant in participants" :key="participant.post_id" class="participantCard">
            <div>
              <p class="participantTitle">Post #{{ participant.post_id }} by @{{ participant.username }}</p>
              <p class="muted">{{ formatDate(participant.created_at) }}</p>
            </div>
            <button
              type="button"
              class="btn primary small"
              :disabled="selectingWinner || selectedContest?.status === 'finished'"
              @click="pickWinner(participant.post_id)"
            >
              {{ selectingWinner ? 'Vyberám...' : 'Vybrať výhercu' }}
            </button>
          </article>
        </div>
      </div>
    </div>
  </AdminPageShell>
</template>

<style scoped>
.panel {
  border: 1px solid rgb(var(--color-surface-rgb) / 0.14);
  border-radius: 12px;
  background: rgb(var(--color-bg-rgb) / 0.38);
  padding: 14px;
}

.panelHead {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 10px;
  margin-bottom: 12px;
}

.panel h2 {
  margin: 0 0 12px;
  font-size: 1.1rem;
}

.formGrid {
  display: grid;
  gap: 10px;
  grid-template-columns: repeat(2, minmax(0, 1fr));
}

.formGrid label {
  display: grid;
  gap: 6px;
  font-size: 0.85rem;
}

.formGrid .full {
  grid-column: 1 / -1;
}

input,
textarea,
select {
  width: 100%;
  border-radius: 10px;
  border: 1px solid rgb(var(--color-surface-rgb) / 0.2);
  background: rgb(var(--color-bg-rgb) / 0.5);
  color: inherit;
  padding: 8px 10px;
}

.actions {
  display: flex;
  justify-content: flex-end;
}

.btn {
  border: 1px solid rgb(var(--color-surface-rgb) / 0.2);
  border-radius: 10px;
  padding: 7px 12px;
  background: transparent;
  color: inherit;
  cursor: pointer;
}

.btn.small {
  padding: 5px 9px;
  font-size: 0.78rem;
}

.btn.primary {
  border-color: rgb(var(--color-primary-rgb) / 0.5);
  background: rgb(var(--color-primary-rgb) / 0.18);
}

.btn:disabled {
  opacity: 0.55;
  cursor: not-allowed;
}

.error {
  color: #fda4af;
}

.muted {
  opacity: 0.75;
  font-size: 0.9rem;
}

.tableWrap {
  overflow-x: auto;
}

table {
  width: 100%;
  border-collapse: collapse;
}

th,
td {
  text-align: left;
  border-bottom: 1px solid rgb(var(--color-surface-rgb) / 0.1);
  padding: 10px 8px;
  font-size: 0.87rem;
}

.rowActions {
  display: flex;
  gap: 8px;
  flex-wrap: wrap;
}

.status {
  display: inline-flex;
  border-radius: 999px;
  padding: 3px 8px;
  font-size: 0.74rem;
  border: 1px solid rgb(var(--color-surface-rgb) / 0.22);
}

.status.active {
  border-color: rgb(34 197 94 / 0.6);
  background: rgb(34 197 94 / 0.16);
}

.status.finished {
  border-color: rgb(59 130 246 / 0.6);
  background: rgb(59 130 246 / 0.16);
}

.status.draft {
  border-color: rgb(251 191 36 / 0.6);
  background: rgb(251 191 36 / 0.16);
}

.modalBackdrop {
  position: fixed;
  inset: 0;
  background: rgb(0 0 0 / 0.55);
  display: grid;
  place-items: center;
  z-index: 40;
  padding: 18px;
}

.modal {
  width: min(760px, 96vw);
  max-height: 82vh;
  overflow: auto;
  border-radius: 14px;
  border: 1px solid rgb(var(--color-surface-rgb) / 0.18);
  background: rgb(var(--color-bg-rgb) / 0.98);
  padding: 14px;
}

.modalHead {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 10px;
  margin-bottom: 10px;
}

.modalHead h3 {
  margin: 0;
}

.participantsList {
  display: grid;
  gap: 9px;
}

.participantCard {
  border: 1px solid rgb(var(--color-surface-rgb) / 0.15);
  border-radius: 10px;
  background: rgb(var(--color-bg-rgb) / 0.45);
  padding: 10px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 10px;
}

.participantTitle {
  margin: 0;
}

@media (max-width: 860px) {
  .formGrid {
    grid-template-columns: 1fr;
  }
}
</style>
