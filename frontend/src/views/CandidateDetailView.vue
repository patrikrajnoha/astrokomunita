<script setup>
import { computed, onMounted, ref } from "vue";
import { useRoute, useRouter } from "vue-router";
import { eventCandidates } from "../services/eventCandidates";

const route = useRoute();
const router = useRouter();

const id = computed(() => Number(route.params.id));

const loading = ref(false);
const error = ref(null);
const candidate = ref(null);

const showRaw = ref(false);

// --- helpers ---
function formatDate(value) {
  if (!value) return "-";
  const d = new Date(value);
  if (isNaN(d.getTime())) return String(value);
  return d.toLocaleString("sk-SK", { dateStyle: "medium", timeStyle: "short" });
}

function canReview() {
  return candidate.value && candidate.value.status === "pending" && !loading.value;
}

async function load() {
  loading.value = true;
  error.value = null;
  try {
    candidate.value = await eventCandidates.get(id.value);
  } catch (e) {
    error.value = e?.response?.data?.message || "Chyba pri načítaní detailu";
  } finally {
    loading.value = false;
  }
}

async function approve() {
  if (!candidate.value) return;

  const ok = window.confirm("Naozaj chceš schváliť tohto kandidáta?");
  if (!ok) return;

  loading.value = true;
  error.value = null;
  try {
    await eventCandidates.approve(candidate.value.id);
    router.push("/admin/candidates");
  } catch (e) {
    error.value = e?.response?.data?.message || "Approve zlyhalo";
  } finally {
    loading.value = false;
  }
}

async function reject() {
  if (!candidate.value) return;

  const ok = window.confirm("Naozaj chceš zamietnuť tohto kandidáta?");
  if (!ok) return;

  loading.value = true;
  error.value = null;
  try {
    await eventCandidates.reject(candidate.value.id);
    router.push("/admin/candidates");
  } catch (e) {
    error.value = e?.response?.data?.message || "Reject zlyhalo";
  } finally {
    loading.value = false;
  }
}

onMounted(load);
</script>

<template>
  <div style="max-width: 900px; margin: 0 auto; padding: 24px 16px;">
    <div style="display:flex; justify-content:space-between; align-items:flex-end; gap:12px;">
      <div>
        <button
          @click="router.back()"
          style="padding:8px 12px; border-radius:10px; border:1px solid rgb(var(--color-surface-rgb) / .18); background:transparent; color:inherit;"
        >
          ← Back
        </button>

        <h1 style="margin:12px 0 6px;">Candidate #{{ id }}</h1>
        <div v-if="candidate" style="opacity:.8; font-size: 14px;">
          {{ candidate.title }}
        </div>
      </div>

      <div v-if="candidate" style="text-align:right; opacity:.85; font-size: 14px;">
        <div><b>Status:</b> {{ candidate.status }}</div>
        <div><b>Type:</b> {{ candidate.type }}</div>
      </div>
    </div>

    <div v-if="error" style="margin-top: 12px; color: var(--color-danger);">
      {{ error }}
    </div>
    <div v-if="loading" style="margin-top: 12px; opacity: .85;">
      Loading...
    </div>

    <div v-if="candidate && !loading" style="margin-top: 16px; display:grid; gap: 12px;">
      <!-- Meta -->
      <section
        style="padding: 12px; border: 1px solid rgb(var(--color-surface-rgb) / .12); border-radius: 12px;"
      >
        <h3 style="margin:0 0 10px;">Meta</h3>

        <div style="display:grid; grid-template-columns: 160px 1fr; gap:8px 12px; font-size: 14px;">
          <div style="opacity:.75;">ID</div><div>{{ candidate.id }}</div>

          <div style="opacity:.75;">Type</div>
          <div>{{ candidate.type }} <span style="opacity:.7">(raw: {{ candidate.raw_type || "-" }})</span></div>

          <div style="opacity:.75;">Short</div><div>{{ candidate.short || "-" }}</div>

          <div style="opacity:.75;">Created</div><div>{{ formatDate(candidate.created_at) }}</div>
          <div style="opacity:.75;">Updated</div><div>{{ formatDate(candidate.updated_at) }}</div>
        </div>
      </section>

      <!-- Time -->
      <section
        style="padding: 12px; border: 1px solid rgb(var(--color-surface-rgb) / .12); border-radius: 12px;"
      >
        <h3 style="margin:0 0 10px;">Čas</h3>

        <div style="display:grid; grid-template-columns: 160px 1fr; gap:8px 12px; font-size: 14px;">
          <div style="opacity:.75;">Start</div><div>{{ formatDate(candidate.start_at) }}</div>
          <div style="opacity:.75;">End</div><div>{{ formatDate(candidate.end_at) }}</div>
          <div style="opacity:.75;">Max</div><div>{{ formatDate(candidate.max_at) }}</div>
        </div>
      </section>

      <!-- Source -->
      <section
        style="padding: 12px; border: 1px solid rgb(var(--color-surface-rgb) / .12); border-radius: 12px;"
      >
        <h3 style="margin:0 0 10px;">Zdroj</h3>

        <div style="display:grid; grid-template-columns: 160px 1fr; gap:8px 12px; font-size: 14px;">
          <div style="opacity:.75;">Source name</div><div>{{ candidate.source_name }}</div>
          <div style="opacity:.75;">Source URL</div>
          <div>
            <a :href="candidate.source_url" target="_blank" rel="noreferrer">
              open source
            </a>
          </div>
          <div style="opacity:.75;">Source UID</div><div style="word-break:break-all;">{{ candidate.source_uid }}</div>
        </div>
      </section>

      <!-- Review -->
      <section
        style="padding: 12px; border: 1px solid rgb(var(--color-surface-rgb) / .12); border-radius: 12px;"
      >
        <h3 style="margin:0 0 10px;">Review</h3>

        <div style="display:flex; gap:10px; flex-wrap:wrap;">
          <button
            @click="approve"
            :disabled="!canReview()"
            style="padding:10px 12px; border-radius:10px; border:1px solid rgb(var(--color-surface-rgb) / .18); background:rgb(var(--color-success-rgb) / .10); color:inherit;"
          >
            Approve
          </button>

          <button
            @click="reject"
            :disabled="!canReview()"
            style="padding:10px 12px; border-radius:10px; border:1px solid rgb(var(--color-surface-rgb) / .18); background:rgb(var(--color-danger-rgb) / .10); color:inherit;"
          >
            Reject
          </button>
        </div>

        <div style="margin-top:10px; font-size: 13px; opacity:.8;">
          Tento krok mení iba status kandidáta (pending → approved/rejected).
        </div>
      </section>

      <!-- Raw payload -->
      <section
        style="padding: 12px; border: 1px solid rgb(var(--color-surface-rgb) / .12); border-radius: 12px;"
      >
        <div style="display:flex; justify-content:space-between; align-items:center; gap:12px;">
          <h3 style="margin:0;">Raw payload</h3>

          <button
            @click="showRaw = !showRaw"
            style="padding:8px 12px; border-radius:10px; border:1px solid rgb(var(--color-surface-rgb) / .18); background:rgb(var(--color-surface-rgb) / .08); color:inherit;"
          >
            {{ showRaw ? "Hide" : "Show" }}
          </button>
        </div>

        <div style="margin-top:8px; font-size:13px; opacity:.8;">
          Pozor: môže ísť o veľký HTML obsah zo zdroja. V MVP ho len zobrazujeme, neparsujeme.
        </div>

        <pre
          v-if="showRaw"
          style="margin-top:10px; white-space:pre-wrap; max-height:320px; overflow:auto; border:1px solid rgb(var(--color-surface-rgb) / .18); border-radius:10px; padding:10px;"
        >{{ candidate.raw_payload ?? "" }}</pre>
      </section>
    </div>
  </div>
</template>
