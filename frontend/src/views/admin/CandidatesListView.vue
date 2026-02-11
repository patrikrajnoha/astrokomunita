<script setup>
import { onMounted, ref, watch } from "vue";
import { useRouter } from "vue-router";
import api from "@/services/api";
import { eventCandidates } from "@/services/eventCandidates";
import { useConfirm } from '@/composables/useConfirm'
import { useToast } from '@/composables/useToast'

const router = useRouter();
const { confirm } = useConfirm()
const toast = useToast()

const activeTab = ref("crawled");

const loading = ref(false);
const error = ref(null);

const status = ref("pending"); // crawled: default MVP
const type = ref("");          // crawled: all
const source = ref("");        // crawled: text input
const q = ref("");             // crawled: search input

const page = ref(1);
const per_page = ref(20);

const data = ref(null);

const manualLoading = ref(false);
const manualError = ref(null);
const manualStatus = ref("draft");
const manualType = ref("");
const manualQ = ref("");
const manualPage = ref(1);
const manualPerPage = ref(20);
const manualData = ref(null);

const showManualForm = ref(false);
const manualEditingId = ref(null);
const manualForm = ref({
  title: "",
  description: "",
  event_type: "meteor_shower",
  starts_at: "",
  ends_at: "",
});

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
    error.value = e?.response?.data?.message || "Chyba pri na??tan? kandid?tov";
  } finally {
    loading.value = false;
  }
}

function resetManualToFirstPage() {
  manualPage.value = 1;
}

function buildManualParams() {
  return {
    status: manualStatus.value || undefined,
    type: manualType.value || undefined,
    q: manualQ.value?.trim() ? manualQ.value.trim() : undefined,
    page: manualPage.value,
    per_page: manualPerPage.value,
  };
}

async function loadManual() {
  manualLoading.value = true;
  manualError.value = null;

  try {
    const res = await api.get("/admin/manual-events", { params: buildManualParams() });
    manualData.value = res.data;
  } catch (e) {
    manualError.value = e?.response?.data?.message || "Chyba pri na??tan? draftov";
  } finally {
    manualLoading.value = false;
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

function clearManualFilters() {
  manualStatus.value = "draft";
  manualType.value = "";
  manualQ.value = "";
  manualPage.value = 1;
  manualPerPage.value = 20;
  loadManual();
}

// Auto-reload pri zmene filtrov (bez ?skratky?: iba to, ?o je prirodzen?)
watch([status, type, per_page], () => {
  resetToFirstPage();
  if (activeTab.value === "crawled") load();
});

// Source a q nech?me na Enter / Search button (aby sa to nena??tavalo pri ka?dom p?smene)

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

watch([manualStatus, manualType, manualPerPage], () => {
  resetManualToFirstPage();
  if (activeTab.value === "manual") loadManual();
});

function prevManualPage() {
  if (!manualData.value || manualPage.value <= 1) return;
  manualPage.value -= 1;
  loadManual();
}

function nextManualPage() {
  if (!manualData.value || manualPage.value >= manualData.value.last_page) return;
  manualPage.value += 1;
  loadManual();
}

function openManualFormCreate() {
  manualEditingId.value = null;
  manualForm.value = {
    title: "",
    description: "",
    event_type: "meteor_shower",
    starts_at: "",
    ends_at: "",
  };
  showManualForm.value = true;
}

function openManualFormEdit(row) {
  manualEditingId.value = row.id;
  manualForm.value = {
    title: row.title || "",
    description: row.description || "",
    event_type: row.event_type || "meteor_shower",
    starts_at: toLocalInput(row.starts_at),
    ends_at: toLocalInput(row.ends_at),
  };
  showManualForm.value = true;
}

function closeManualForm() {
  showManualForm.value = false;
}

function toLocalInput(value) {
  if (!value) return "";
  const d = new Date(value);
  if (isNaN(d.getTime())) return "";
  const pad = (n) => String(n).padStart(2, "0");
  return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`;
}

function updateManualRow(updated) {
  if (!manualData.value || !updated) return;
  const rows = manualData.value.data || [];
  const idx = rows.findIndex((r) => r.id === updated.id);
  if (idx >= 0) {
    rows[idx] = { ...rows[idx], ...updated };
  }
}

async function saveManual() {
  manualLoading.value = true;
  manualError.value = null;

  const payload = {
    title: manualForm.value.title,
    description: manualForm.value.description || null,
    event_type: manualForm.value.event_type,
    starts_at: manualForm.value.starts_at,
    ends_at: manualForm.value.ends_at || null,
  };

  try {
    if (manualEditingId.value) {
      const res = await api.put(`/admin/manual-events/${manualEditingId.value}`, payload);
      updateManualRow(res.data);
    } else {
      const res = await api.post("/admin/manual-events", payload);
      manualData.value = manualData.value || { data: [], current_page: 1, last_page: 1, total: 0 };
      manualData.value.data = [res.data, ...(manualData.value.data || [])];
      manualData.value.total = (manualData.value.total || 0) + 1;
    }
    showManualForm.value = false;
  } catch (e) {
    manualError.value = e?.response?.data?.message || "Ulo?enie zlyhalo";
  } finally {
    manualLoading.value = false;
  }
}

async function deleteManual(row) {
  if (!row?.id) return;
  const ok = await confirm({
    title: 'Zmazat draft',
    message: `Zmazat draft "${row.title}"?`,
    confirmText: 'Delete',
    cancelText: 'Cancel',
    variant: 'danger',
  });
  if (!ok) return;

  manualLoading.value = true;
  manualError.value = null;

  try {
    await api.delete(`/admin/manual-events/${row.id}`);
    if (manualData.value?.data) {
      manualData.value.data = manualData.value.data.filter((r) => r.id !== row.id);
    }
    toast.success('Draft bol zmazany.');
  } catch (e) {
    manualError.value = e?.response?.data?.message || "Mazanie zlyhalo";
    toast.error(manualError.value);
  } finally {
    manualLoading.value = false;
  }
}

async function publishManual(row) {
  if (!row?.id) return;
  const ok = await confirm({
    title: 'Publikovat draft',
    message: `Publikovat "${row.title}" do events?`,
    confirmText: 'Publish',
    cancelText: 'Cancel',
  });
  if (!ok) return;

  manualLoading.value = true;
  manualError.value = null;

  try {
    const res = await api.post(`/admin/manual-events/${row.id}/publish`);
    updateManualRow({
      id: row.id,
      status: "published",
      published_event_id: res.data?.data?.id ?? res.data?.id ?? null,
    });
    toast.success('Draft bol publikovany.');
  } catch (e) {
    manualError.value = e?.response?.data?.message || "Publish zlyhal";
    toast.error(manualError.value);
  } finally {
    manualLoading.value = false;
  }
}

function setTab(tab) {
  activeTab.value = tab;
  if (tab === "crawled" && !data.value) load();
  if (tab === "manual" && !manualData.value) loadManual();
}

onMounted(load);
</script>

<template>
  <div style="max-width: 1100px; margin: 0 auto; padding: 24px 16px;">
    <div style="display:flex; align-items:flex-end; justify-content:space-between; gap:16px;">
      <div>
        <h1 style="margin:0 0 6px;">Event candidates</h1>
      </div>

      <div style="display:flex; gap:10px;">
        <button
          @click="setTab('crawled')"
          :disabled="loading || manualLoading"
          :style="activeTab === 'crawled'
            ? 'padding:8px 12px; border:1px solid rgb(var(--color-surface-rgb) / .4); border-radius:8px; background:rgb(var(--color-surface-rgb) / .08); color:inherit;'
            : 'padding:8px 12px; border:1px solid rgb(var(--color-surface-rgb) / .2); border-radius:8px; background:transparent; color:inherit;'"
        >
          Crawled candidates
        </button>
        <button
          @click="setTab('manual')"
          :disabled="loading || manualLoading"
          :style="activeTab === 'manual'
            ? 'padding:8px 12px; border:1px solid rgb(var(--color-surface-rgb) / .4); border-radius:8px; background:rgb(var(--color-surface-rgb) / .08); color:inherit;'
            : 'padding:8px 12px; border:1px solid rgb(var(--color-surface-rgb) / .2); border-radius:8px; background:transparent; color:inherit;'"
        >
          Manual drafts
        </button>
      </div>
    </div>

    <div v-if="activeTab === 'crawled'">
      <div style="display:flex; justify-content:flex-end; margin-top: 12px;">
        <button
          @click="clearFilters"
          :disabled="loading"
          style="padding:8px 12px; border:1px solid rgb(var(--color-surface-rgb) / .2); border-radius:8px; background:transparent; color:inherit;"
        >
          Reset
        </button>
      </div>

      <div
        style="
          margin-top: 12px;
          padding: 12px;
          border: 1px solid rgb(var(--color-surface-rgb) / .12);
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
            style="width:100%; padding:10px; border-radius:10px; border:1px solid rgb(var(--color-surface-rgb) / .18); background:transparent; color:inherit;"
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
            style="width:100%; padding:10px; border-radius:10px; border:1px solid rgb(var(--color-surface-rgb) / .18); background:transparent; color:inherit;"
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
            style="width:100%; padding:10px; border-radius:10px; border:1px solid rgb(var(--color-surface-rgb) / .18); background:transparent; color:inherit;"
          />
        </div>

        <div style="grid-column: span 3;">
          <label style="display:block; font-size:12px; opacity:.8; margin-bottom:6px;">Per page</label>
          <select
            v-model.number="per_page"
            :disabled="loading"
            style="width:100%; padding:10px; border-radius:10px; border:1px solid rgb(var(--color-surface-rgb) / .18); background:transparent; color:inherit;"
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
            placeholder="h?adaj v title/short/? (q)"
            @keyup.enter="resetToFirstPage(); load()"
            style="width:100%; padding:10px; border-radius:10px; border:1px solid rgb(var(--color-surface-rgb) / .18); background:transparent; color:inherit;"
          />
        </div>

        <div style="grid-column: span 3; display:flex; align-items:flex-end; gap:10px;">
          <button
            @click="resetToFirstPage(); load()"
            :disabled="loading"
            style="width:100%; padding:10px 12px; border-radius:10px; border:1px solid rgb(var(--color-surface-rgb) / .18); background:rgb(var(--color-surface-rgb) / .08); color:inherit;"
          >
            Search
          </button>
        </div>
      </div>

      <div v-if="error" style="margin-top: 12px; color: var(--color-danger);">
        {{ error }}
      </div>

      <div v-if="loading" style="margin-top: 12px; opacity: .85;">
        Loading...
      </div>

      <div
        v-if="data && !loading"
        style="
          margin-top: 16px;
          border: 1px solid rgb(var(--color-surface-rgb) / .12);
          border-radius: 12px;
          overflow: hidden;
        "
      >
        <table style="width:100%; border-collapse:collapse;">
          <thead style="background: rgb(var(--color-surface-rgb) / .05);">
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
              style="border-top: 1px solid rgb(var(--color-surface-rgb) / .08);"
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
                  style="padding:8px 10px; border-radius:10px; border:1px solid rgb(var(--color-surface-rgb) / .18); background:rgb(var(--color-surface-rgb) / .08); color:inherit;"
                >
                  Open
                </button>
              </td>
            </tr>

            <tr v-if="data.data.length === 0">
              <td colspan="7" style="padding:0;"></td>
            </tr>
          </tbody>
        </table>
      </div>

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
            style="padding:8px 12px; border-radius:10px; border:1px solid rgb(var(--color-surface-rgb) / .18); background:transparent; color:inherit;"
          >
            Prev
          </button>
          <button
            @click="nextPage"
            :disabled="loading || (data && page >= data.last_page)"
            style="padding:8px 12px; border-radius:10px; border:1px solid rgb(var(--color-surface-rgb) / .18); background:transparent; color:inherit;"
          >
            Next
          </button>
        </div>
      </div>
    </div>

    <div v-else>
      <div style="display:flex; justify-content:space-between; align-items:center; margin-top: 12px;">
        <button
          @click="openManualFormCreate"
          :disabled="manualLoading"
          style="padding:8px 12px; border:1px solid rgb(var(--color-surface-rgb) / .2); border-radius:8px; background:transparent; color:inherit;"
        >
          Create manual event
        </button>
        <button
          @click="clearManualFilters"
          :disabled="manualLoading"
          style="padding:8px 12px; border:1px solid rgb(var(--color-surface-rgb) / .2); border-radius:8px; background:transparent; color:inherit;"
        >
          Reset
        </button>
      </div>

      <div
        style="
          margin-top: 12px;
          padding: 12px;
          border: 1px solid rgb(var(--color-surface-rgb) / .12);
          border-radius: 12px;
          display: grid;
          grid-template-columns: repeat(12, 1fr);
          gap: 12px;
        "
      >
        <div style="grid-column: span 3;">
          <label style="display:block; font-size:12px; opacity:.8; margin-bottom:6px;">Status</label>
          <select
            v-model="manualStatus"
            :disabled="manualLoading"
            style="width:100%; padding:10px; border-radius:10px; border:1px solid rgb(var(--color-surface-rgb) / .18); background:transparent; color:inherit;"
          >
            <option value="draft">draft</option>
            <option value="published">published</option>
          </select>
        </div>

        <div style="grid-column: span 3;">
          <label style="display:block; font-size:12px; opacity:.8; margin-bottom:6px;">Type</label>
          <select
            v-model="manualType"
            :disabled="manualLoading"
            style="width:100%; padding:10px; border-radius:10px; border:1px solid rgb(var(--color-surface-rgb) / .18); background:transparent; color:inherit;"
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
          <label style="display:block; font-size:12px; opacity:.8; margin-bottom:6px;">Per page</label>
          <select
            v-model.number="manualPerPage"
            :disabled="manualLoading"
            style="width:100%; padding:10px; border-radius:10px; border:1px solid rgb(var(--color-surface-rgb) / .18); background:transparent; color:inherit;"
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
            v-model="manualQ"
            :disabled="manualLoading"
            placeholder="h?adaj v title (q)"
            @keyup.enter="resetManualToFirstPage(); loadManual()"
            style="width:100%; padding:10px; border-radius:10px; border:1px solid rgb(var(--color-surface-rgb) / .18); background:transparent; color:inherit;"
          />
        </div>

        <div style="grid-column: span 3; display:flex; align-items:flex-end; gap:10px;">
          <button
            @click="resetManualToFirstPage(); loadManual()"
            :disabled="manualLoading"
            style="width:100%; padding:10px 12px; border-radius:10px; border:1px solid rgb(var(--color-surface-rgb) / .18); background:rgb(var(--color-surface-rgb) / .08); color:inherit;"
          >
            Search
          </button>
        </div>
      </div>

      <div v-if="showManualForm" style="margin-top: 12px; border:1px solid rgb(var(--color-surface-rgb) / .12); border-radius:12px; padding:12px;">
        <div style="font-weight:600; margin-bottom:8px;">
          {{ manualEditingId ? 'Edit draft' : 'Create draft' }}
        </div>
        <div style="display:grid; gap:10px;">
          <input
            v-model="manualForm.title"
            type="text"
            placeholder="Title"
            :disabled="manualLoading"
            style="width:100%; padding:10px; border-radius:10px; border:1px solid rgb(var(--color-surface-rgb) / .18); background:transparent; color:inherit;"
          />
          <textarea
            v-model="manualForm.description"
            rows="3"
            placeholder="Description"
            :disabled="manualLoading"
            style="width:100%; padding:10px; border-radius:10px; border:1px solid rgb(var(--color-surface-rgb) / .18); background:transparent; color:inherit;"
          ></textarea>
          <div style="display:grid; grid-template-columns: repeat(12, 1fr); gap:12px;">
            <select
              v-model="manualForm.event_type"
              :disabled="manualLoading"
              style="grid-column: span 4; width:100%; padding:10px; border-radius:10px; border:1px solid rgb(var(--color-surface-rgb) / .18); background:transparent; color:inherit;"
            >
              <option value="meteor_shower">meteor_shower</option>
              <option value="eclipse_lunar">eclipse_lunar</option>
              <option value="eclipse_solar">eclipse_solar</option>
              <option value="planetary_event">planetary_event</option>
              <option value="other">other</option>
            </select>
            <input
              v-model="manualForm.starts_at"
              type="datetime-local"
              :disabled="manualLoading"
              style="grid-column: span 4; width:100%; padding:10px; border-radius:10px; border:1px solid rgb(var(--color-surface-rgb) / .18); background:transparent; color:inherit;"
            />
            <input
              v-model="manualForm.ends_at"
              type="datetime-local"
              :disabled="manualLoading"
              style="grid-column: span 4; width:100%; padding:10px; border-radius:10px; border:1px solid rgb(var(--color-surface-rgb) / .18); background:transparent; color:inherit;"
            />
          </div>
          <div style="display:flex; gap:10px; justify-content:flex-end;">
            <button
              @click="closeManualForm"
              :disabled="manualLoading"
              style="padding:8px 12px; border-radius:10px; border:1px solid rgb(var(--color-surface-rgb) / .18); background:transparent; color:inherit;"
            >
              Cancel
            </button>
            <button
              @click="saveManual"
              :disabled="manualLoading"
              style="padding:8px 12px; border-radius:10px; border:1px solid rgb(var(--color-surface-rgb) / .18); background:rgb(var(--color-surface-rgb) / .08); color:inherit;"
            >
              Save
            </button>
          </div>
        </div>
      </div>

      <div v-if="manualError" style="margin-top: 12px; color: var(--color-danger);">
        {{ manualError }}
      </div>

      <div v-if="manualLoading" style="margin-top: 12px; opacity: .85;">
        Loading...
      </div>

      <div
        v-if="manualData && !manualLoading"
        style="
          margin-top: 16px;
          border: 1px solid rgb(var(--color-surface-rgb) / .12);
          border-radius: 12px;
          overflow: hidden;
        "
      >
        <table style="width:100%; border-collapse:collapse;">
          <thead style="background: rgb(var(--color-surface-rgb) / .05);">
            <tr>
              <th style="text-align:left; padding:12px; font-size:12px; opacity:.85;">Title</th>
              <th style="text-align:left; padding:12px; font-size:12px; opacity:.85;">Type</th>
              <th style="text-align:left; padding:12px; font-size:12px; opacity:.85;">Starts</th>
              <th style="text-align:left; padding:12px; font-size:12px; opacity:.85;">Status</th>
              <th style="text-align:right; padding:12px; font-size:12px; opacity:.85;">Actions</th>
            </tr>
          </thead>

          <tbody>
            <tr
              v-for="row in manualData.data"
              :key="row.id"
              style="border-top: 1px solid rgb(var(--color-surface-rgb) / .08);"
            >
              <td style="padding:12px;">{{ row.title }}</td>
              <td style="padding:12px; white-space:nowrap;">{{ row.event_type }}</td>
              <td style="padding:12px; white-space:nowrap;">{{ formatDate(row.starts_at) }}</td>
              <td style="padding:12px; white-space:nowrap;">{{ row.status }}</td>
              <td style="padding:12px; text-align:right; white-space:nowrap;">
                <button
                  @click="openManualFormEdit(row)"
                  :disabled="manualLoading"
                  style="padding:6px 10px; border-radius:10px; border:1px solid rgb(var(--color-surface-rgb) / .18); background:transparent; color:inherit;"
                >
                  Edit
                </button>
                <button
                  @click="deleteManual(row)"
                  :disabled="manualLoading"
                  style="margin-left:6px; padding:6px 10px; border-radius:10px; border:1px solid rgb(var(--color-surface-rgb) / .18); background:transparent; color:inherit;"
                >
                  Delete
                </button>
                <button
                  @click="publishManual(row)"
                  :disabled="manualLoading || row.status === 'published'"
                  style="margin-left:6px; padding:6px 10px; border-radius:10px; border:1px solid rgb(var(--color-surface-rgb) / .18); background:rgb(var(--color-surface-rgb) / .08); color:inherit;"
                >
                  Publish
                </button>
              </td>
            </tr>

            <tr v-if="manualData.data.length === 0">
              <td colspan="5" style="padding:16px; opacity:.8;">
                ?iadne drafty.
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <div
        v-if="manualData"
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
          Page {{ manualData.current_page }} / {{ manualData.last_page }} (total {{ manualData.total }})
        </div>

        <div style="display:flex; gap:10px;">
          <button
            @click="prevManualPage"
            :disabled="manualLoading || manualPage <= 1"
            style="padding:8px 12px; border-radius:10px; border:1px solid rgb(var(--color-surface-rgb) / .18); background:transparent; color:inherit;"
          >
            Prev
          </button>
          <button
            @click="nextManualPage"
            :disabled="manualLoading || (manualData && manualPage >= manualData.last_page)"
            style="padding:8px 12px; border-radius:10px; border:1px solid rgb(var(--color-surface-rgb) / .18); background:transparent; color:inherit;"
          >
            Next
          </button>
        </div>
      </div>
    </div>
  </div>
</template>
