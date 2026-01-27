<script setup>
import { onMounted, ref, watch } from "vue";
import { useRouter } from "vue-router";
import { eventCandidates } from "../services/eventCandidates";

const router = useRouter();

const loading = ref(false);
const error = ref(null);

const status = ref("pending"); // default MVP
const type = ref("");          // all
const source = ref("");        // text input
const q = ref("");             // search input

const page = ref(1);
const per_page = ref(20);

const data = ref(null);

// --- helpers ---
function formatDate(value) {
  if (!value) return "-";
  const d = new Date(value);
  if (isNaN(d.getTime())) return String(value);
  return d.toLocaleString("sk-SK", { dateStyle: "medium", timeStyle: "short" });
}

function openCandidate(id) {
  router.push(`/admin/candidates/${id}`);
}

function resetToFirstPage() {
  page.value = 1;
}

function buildParams() {
  return {
    status: status.value || undefined,
    type: type.value || undefined,
    source: source.value?.trim() ? source.value.trim() : undefined,
    q: q.value?.trim() ? q.value.trim() : undefined,
    page: page.value,
    per_page: per_page.value,
  };
}

async function load() {
  loading.value = true;
  error.value = null;

  try {
    data.value = await eventCandidates.list(buildParams());
  } catch (e) {
    error.value = e?.response?.data?.message || "Chyba pri načítaní kandidátov";
  } finally {
    loading.value = false;
  }
}

function clearFilters() {
  status.value = "pending";
  type.value = "";
  source.value = "";
  q.value = "";
  page.value = 1;
  per_page.value = 20;
  load();
}

// Auto-reload pri zmene filtrov (bez “skratky”: iba to, čo je prirodzené)
watch([status, type, per_page], () => {
  resetToFirstPage();
  load();
});

// Source a q necháme na Enter / Search button (aby sa to nenačítavalo pri každom písmene)

function prevPage() {
  if (!data.value || page.value <= 1) return;
  page.value -= 1;
  load();
}

function nextPage() {
  if (!data.value || page.value >= data.value.last_page) return;
  page.value += 1;
  load();
}

onMounted(load);
</script>

<template>
  <div style="max-width: 1100px; margin: 0 auto; padding: 24px 16px;">
    <div style="display:flex; align-items:flex-end; justify-content:space-between; gap:16px;">
      <div>
        <h1 style="margin:0 0 6px;">Event candidates</h1>
        <div style="opacity:.8; font-size: 14px;">
          Inbox na schválenie / zamietnutie kandidátov (MVP).
        </div>
      </div>

      <button
        @click="clearFilters"
        :disabled="loading"
        style="padding:8px 12px; border:1px solid rgba(255,255,255,.2); border-radius:8px; background:transparent; color:inherit;"
      >
        Reset
      </button>
    </div>

    <!-- Filters -->
    <div
      style="
        margin-top: 16px;
        padding: 12px;
        border: 1px solid rgba(255,255,255,.12);
        border-radius: 12px;
        display: grid;
        grid-template-columns: repeat(12, 1fr);
        gap: 12px;
      "
    >
      <div style="grid-column: span 3;">
        <label style="display:block; font-size:12px; opacity:.8; margin-bottom:6px;">Status</label>
        <select
          v-model="status"
          :disabled="loading"
          style="width:100%; padding:10px; border-radius:10px; border:1px solid rgba(255,255,255,.18); background:transparent; color:inherit;"
        >
          <option value="pending">pending</option>
          <option value="approved">approved</option>
          <option value="rejected">rejected</option>
        </select>
      </div>

      <div style="grid-column: span 3;">
        <label style="display:block; font-size:12px; opacity:.8; margin-bottom:6px;">Type</label>
        <select
          v-model="type"
          :disabled="loading"
          style="width:100%; padding:10px; border-radius:10px; border:1px solid rgba(255,255,255,.18); background:transparent; color:inherit;"
        >
          <option value="">all</option>
          <option value="eclipse_lunar">eclipse_lunar</option>
          <option value="eclipse_solar">eclipse_solar</option>
          <option value="meteor_shower">meteor_shower</option>
          <option value="planetary_event">planetary_event</option>
          <option value="other">other</option>
        </select>
      </div>

      <div style="grid-column: span 3;">
        <label style="display:block; font-size:12px; opacity:.8; margin-bottom:6px;">Source</label>
        <input
          v-model="source"
          :disabled="loading"
          placeholder="napr. astropixels"
          @keyup.enter="resetToFirstPage(); load()"
          style="width:100%; padding:10px; border-radius:10px; border:1px solid rgba(255,255,255,.18); background:transparent; color:inherit;"
        />
      </div>

      <div style="grid-column: span 3;">
        <label style="display:block; font-size:12px; opacity:.8; margin-bottom:6px;">Per page</label>
        <select
          v-model.number="per_page"
          :disabled="loading"
          style="width:100%; padding:10px; border-radius:10px; border:1px solid rgba(255,255,255,.18); background:transparent; color:inherit;"
        >
          <option :value="10">10</option>
          <option :value="20">20</option>
          <option :value="50">50</option>
          <option :value="100">100</option>
        </select>
      </div>

      <div style="grid-column: span 9;">
        <label style="display:block; font-size:12px; opacity:.8; margin-bottom:6px;">Search</label>
        <input
          v-model="q"
          :disabled="loading"
          placeholder="hľadaj v title/short/… (q)"
          @keyup.enter="resetToFirstPage(); load()"
          style="width:100%; padding:10px; border-radius:10px; border:1px solid rgba(255,255,255,.18); background:transparent; color:inherit;"
        />
      </div>

      <div style="grid-column: span 3; display:flex; align-items:flex-end; gap:10px;">
        <button
          @click="resetToFirstPage(); load()"
          :disabled="loading"
          style="width:100%; padding:10px 12px; border-radius:10px; border:1px solid rgba(255,255,255,.18); background:rgba(255,255,255,.08); color:inherit;"
        >
          Search
        </button>
      </div>
    </div>

    <!-- State -->
    <div v-if="error" style="margin-top: 12px; color: #ff6b6b;">
      {{ error }}
    </div>

    <div v-if="loading" style="margin-top: 12px; opacity: .85;">
      Loading...
    </div>

    <!-- Table -->
    <div
      v-if="data && !loading"
      style="
        margin-top: 16px;
        border: 1px solid rgba(255,255,255,.12);
        border-radius: 12px;
        overflow: hidden;
      "
    >
      <table style="width:100%; border-collapse:collapse;">
        <thead style="background: rgba(255,255,255,.05);">
          <tr>
            <th style="text-align:left; padding:12px; font-size:12px; opacity:.85;">ID</th>
            <th style="text-align:left; padding:12px; font-size:12px; opacity:.85;">Type</th>
            <th style="text-align:left; padding:12px; font-size:12px; opacity:.85;">Title</th>
            <th style="text-align:left; padding:12px; font-size:12px; opacity:.85;">Source</th>
            <th style="text-align:left; padding:12px; font-size:12px; opacity:.85;">Start</th>
            <th style="text-align:left; padding:12px; font-size:12px; opacity:.85;">Status</th>
            <th style="text-align:right; padding:12px; font-size:12px; opacity:.85;">Action</th>
          </tr>
        </thead>

        <tbody>
          <tr
            v-for="c in data.data"
            :key="c.id"
            style="border-top: 1px solid rgba(255,255,255,.08);"
          >
            <td style="padding:12px; white-space:nowrap;">{{ c.id }}</td>
            <td style="padding:12px; white-space:nowrap;">{{ c.type }}</td>
            <td style="padding:12px;">
              <div style="font-weight:600;">{{ c.title }}</div>
              <div v-if="c.short" style="opacity:.75; font-size:12px; margin-top:4px;">
                {{ c.short }}
              </div>
            </td>
            <td style="padding:12px; white-space:nowrap;">{{ c.source_name }}</td>
            <td style="padding:12px; white-space:nowrap;">{{ formatDate(c.start_at) }}</td>
            <td style="padding:12px; white-space:nowrap;">{{ c.status }}</td>
            <td style="padding:12px; text-align:right;">
              <button
                @click="openCandidate(c.id)"
                style="padding:8px 10px; border-radius:10px; border:1px solid rgba(255,255,255,.18); background:rgba(255,255,255,.08); color:inherit;"
              >
                Open
              </button>
            </td>
          </tr>

          <tr v-if="data.data.length === 0">
            <td colspan="7" style="padding:16px; opacity:.8;">
              Žiadne výsledky pre zvolené filtre.
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Pagination -->
    <div
      v-if="data"
      style="
        margin-top: 14px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        flex-wrap: wrap;
      "
    >
      <div style="opacity:.85; font-size: 14px;">
        Page {{ data.current_page }} / {{ data.last_page }} (total {{ data.total }})
      </div>

      <div style="display:flex; gap:10px;">
        <button
          @click="prevPage"
          :disabled="loading || page <= 1"
          style="padding:8px 12px; border-radius:10px; border:1px solid rgba(255,255,255,.18); background:transparent; color:inherit;"
        >
          Prev
        </button>
        <button
          @click="nextPage"
          :disabled="loading || (data && page >= data.last_page)"
          style="padding:8px 12px; border-radius:10px; border:1px solid rgba(255,255,255,.18); background:transparent; color:inherit;"
        >
          Next
        </button>
      </div>
    </div>
  </div>
</template>
