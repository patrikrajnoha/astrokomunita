<script setup>
import { computed, onMounted, ref, watch } from "vue";
import { useRoute, useRouter } from "vue-router";
import api from "@/services/api";
import { eventCandidates } from "@/services/eventCandidates";
import { useConfirm } from '@/composables/useConfirm'
import { useToast } from '@/composables/useToast'
import { candidateDisplayShort, candidateDisplayTitle } from '@/utils/translatedFields'

const route = useRoute();
const router = useRouter();
const { confirm } = useConfirm()
const toast = useToast()

const activeTab = ref("crawled");

const loading = ref(false);
const error = ref(null);
const publishProgressActive = ref(false);
const publishProgressLabel = ref("");
const publishProgressPercent = ref(0);

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
const publishMode = ref("crawled");

const showManualForm = ref(false);
const manualEditingId = ref(null);
const manualForm = ref({
  title: "",
  description: "",
  event_type: "meteor_shower",
  starts_at: "",
  ends_at: "",
});

const manualTypeOptions = [
  { value: "meteor_shower", label: "Meteoricky roj" },
  { value: "eclipse_lunar", label: "Zatmenie Mesiaca" },
  { value: "eclipse_solar", label: "Zatmenie Slnka" },
  { value: "planetary_event", label: "Planetarny ukaz" },
  { value: "other", label: "Ina udalost" },
];

const manualFormErrors = computed(() => {
  const errors = [];
  if (!String(manualForm.value.title || "").trim()) {
    errors.push("Nazov je povinny.");
  }
  if (!manualForm.value.starts_at) {
    errors.push("Cas zaciatku je povinny.");
  }
  if (manualForm.value.starts_at && manualForm.value.ends_at) {
    const start = new Date(manualForm.value.starts_at);
    const end = new Date(manualForm.value.ends_at);
    if (!Number.isNaN(start.getTime()) && !Number.isNaN(end.getTime()) && end < start) {
      errors.push("Koniec nemoze byt skor ako zaciatok.");
    }
  }
  return errors;
});

const manualCanSave = computed(() => manualFormErrors.value.length === 0);

const runFilter = computed(() => {
  const runId = Number(route.query?.run_id);
  if (!Number.isFinite(runId) || runId <= 0) {
    return null;
  }

  const sourceKey = String(route.query?.source_key || route.query?.source || "")
    .trim()
    .toLowerCase();

  const yearValue = Number(route.query?.year);
  const year = Number.isFinite(yearValue) && yearValue >= 2000 ? yearValue : null;

  return {
    runId,
    sourceKey,
    year,
  };
});

const visiblePendingCandidateIds = computed(() => {
  const rows = Array.isArray(data.value?.data) ? data.value.data : [];
  return rows
    .filter((row) => String(row?.status || "").toLowerCase() === "pending")
    .map((row) => Number(row.id))
    .filter((id) => Number.isFinite(id) && id > 0);
});

const crawledStats = computed(() => {
  const rows = Array.isArray(data.value?.data) ? data.value.data : [];
  const stats = {
    total: rows.length,
    pending: 0,
    approved: 0,
    rejected: 0,
    translated: 0,
    failedTranslation: 0,
  };

  for (const row of rows) {
    const rowStatus = String(row?.status || "").toLowerCase();
    if (rowStatus === "pending") stats.pending += 1;
    if (rowStatus === "approved") stats.approved += 1;
    if (rowStatus === "rejected") stats.rejected += 1;

    const translationStatus = String(row?.translation_status || "").toLowerCase();
    if (translationStatus === "done" || translationStatus === "translated") stats.translated += 1;
    if (translationStatus === "failed" || translationStatus === "error") stats.failedTranslation += 1;
  }

  return stats;
});

const manualStats = computed(() => {
  const rows = Array.isArray(manualData.value?.data) ? manualData.value.data : [];
  return {
    total: rows.length,
    draft: rows.filter((row) => String(row?.status || "").toLowerCase() === "draft").length,
    published: rows.filter((row) => String(row?.status || "").toLowerCase() === "published").length,
  };
});

// --- helpers ---
function normalizeTranslationStatus(value) {
  const statusValue = String(value || "").trim().toLowerCase();
  if (statusValue === "done" || statusValue === "translated") return "Prelozene";
  if (statusValue === "failed" || statusValue === "error") return "Zlyhalo";
  return "Caka";
}

function translationStatusStyle(value) {
  const normalized = normalizeTranslationStatus(value);
  if (normalized === "Prelozene") {
    return "display:inline-flex; align-items:center; padding:2px 8px; border-radius:999px; border:1px solid rgba(22,163,74,.35); background:rgba(22,163,74,.12); font-size:12px;";
  }
  if (normalized === "Zlyhalo") {
    return "display:inline-flex; align-items:center; padding:2px 8px; border-radius:999px; border:1px solid rgba(239,68,68,.35); background:rgba(239,68,68,.12); font-size:12px;";
  }
  return "display:inline-flex; align-items:center; padding:2px 8px; border-radius:999px; border:1px solid rgba(245,158,11,.35); background:rgba(245,158,11,.12); font-size:12px;";
}

function applyRunFilterFromRoute() {
  if (!runFilter.value) return;

  if (runFilter.value.sourceKey) {
    source.value = runFilter.value.sourceKey;
  }
  status.value = "pending";
  page.value = 1;
}

function clearRunFilter() {
  const query = { ...route.query };
  delete query.run_id;
  delete query.source_key;
  delete query.source;
  delete query.year;
  router.replace({ query });
}

function formatDate(value) {
  if (!value) return "-";
  const d = new Date(value);
  if (isNaN(d.getTime())) return String(value);
  return d.toLocaleString("sk-SK", { dateStyle: "medium", timeStyle: "short" });
}

function formatConfidence(value) {
  if (value === null || value === undefined || value === "") return "-";
  const numeric = Number(value);
  if (Number.isNaN(numeric)) return "-";
  return numeric.toFixed(2);
}

function normalizeSources(values) {
  if (!Array.isArray(values)) return [];
  return values
    .map((item) => String(item || "").trim().toLowerCase())
    .filter((item) => item.length > 0);
}

function sourceLabel(source) {
  const key = String(source || "").toLowerCase();
  if (key === "astropixels") return "AstroPixels";
  if (key === "imo") return "IMO";
  if (key === "nasa_watch_the_skies") return "NASA WTS";
  if (key === "nasa") return "NASA";
  return key || "-";
}

function sourceBadgeStyle(source) {
  const key = String(source || "").toLowerCase();
  if (key === "astropixels") {
    return "display:inline-flex; align-items:center; padding:2px 8px; border-radius:999px; border:1px solid rgba(30,64,175,.35); background:rgba(30,64,175,.12); font-size:12px;";
  }
  if (key === "imo") {
    return "display:inline-flex; align-items:center; padding:2px 8px; border-radius:999px; border:1px solid rgba(6,95,70,.35); background:rgba(6,95,70,.12); font-size:12px;";
  }
  if (key === "nasa") {
    return "display:inline-flex; align-items:center; padding:2px 8px; border-radius:999px; border:1px solid rgba(107,33,168,.35); background:rgba(107,33,168,.12); font-size:12px;";
  }
  return "display:inline-flex; align-items:center; padding:2px 8px; border-radius:999px; border:1px solid rgb(var(--color-surface-rgb) / .2); background:rgb(var(--color-surface-rgb) / .08); font-size:12px;";
}

function openCandidate(id) {
  router.push(`/admin/candidates/${id}`);
}

function startPublishProgress(label, totalSteps = 1) {
  publishProgressActive.value = true;
  publishProgressLabel.value = label;
  publishProgressPercent.value = totalSteps > 0 ? 1 : 0;
}

function advancePublishProgress(doneSteps, totalSteps) {
  if (!publishProgressActive.value || totalSteps <= 0) return;
  const safeDone = Math.max(0, Math.min(totalSteps, Number(doneSteps) || 0));
  publishProgressPercent.value = Math.max(1, Math.round((safeDone / totalSteps) * 100));
}

function finishPublishProgress() {
  if (!publishProgressActive.value) return;
  publishProgressPercent.value = 100;
  window.setTimeout(() => {
    publishProgressActive.value = false;
    publishProgressLabel.value = "";
    publishProgressPercent.value = 0;
  }, 500);
}

async function publishCandidateQuick(candidate) {
  if (!candidate?.id || String(candidate?.status || '') !== 'pending') return;

  const ok = await confirm({
    title: 'Publikovat kandidata',
    message: `Publikovat "${candidateDisplayTitle(candidate)}" do udalosti?`,
    confirmText: 'Publikovat',
    cancelText: 'Zrusit',
  });
  if (!ok) return;

  loading.value = true;
  error.value = null;
  startPublishProgress("Publikovanie kandidata...", 1);
  try {
    await eventCandidates.approve(candidate.id);
    advancePublishProgress(1, 1);
    toast.success('Kandidat bol publikovany.');
    await load();
  } catch (e) {
    error.value = e?.response?.data?.message || 'Publikovanie zlyhalo';
    toast.error(error.value);
  } finally {
    finishPublishProgress();
    loading.value = false;
  }
}

async function publishAllVisiblePending() {
  const ids = visiblePendingCandidateIds.value;
  if (ids.length === 0) {
    toast.warn("Na tejto stranke nie su ziadni pending kandidati.");
    return;
  }

  const ok = await confirm({
    title: "Publikovat vsetko",
    message: `Naozaj publikovat ${ids.length} pending kandidatov na aktualnej stranke?`,
    confirmText: "Publikovat",
    cancelText: "Zrusit",
    variant: "danger",
  });
  if (!ok) return;

  loading.value = true;
  error.value = null;

  let successCount = 0;
  let failCount = 0;
  const total = ids.length;

  try {
    startPublishProgress("Publikujem viditelnych kandidatov...", total);
    let doneCount = 0;
    for (const candidateId of ids) {
      try {
        await eventCandidates.approve(candidateId);
        successCount += 1;
      } catch {
        failCount += 1;
      }
      doneCount += 1;
      advancePublishProgress(doneCount, total);
    }

    if (failCount === 0) {
      toast.success(`Publikovanych ${successCount} kandidatov.`);
    } else {
      toast.warn(`Publikovane: ${successCount}, zlyhalo: ${failCount}.`);
    }

    await load();
  } catch (e) {
    error.value = e?.response?.data?.message || "Hromadne publikovanie zlyhalo";
    toast.error(error.value);
  } finally {
    finishPublishProgress();
    loading.value = false;
  }
}

async function publishAllByFilter() {
  const ok = await confirm({
    title: "Publikovat vsetko podla filtra",
    message: "Naozaj publikovat vsetky pending udalosti podla aktualneho filtra? (max 1000)",
    confirmText: "Publikovat",
    cancelText: "Zrusit",
    variant: "danger",
  });
  if (!ok) return;

  loading.value = true;
  error.value = null;
  startPublishProgress("Publikujem podla filtra...", 1);

  try {
    const params = buildParams();
    const payload = {
      status: params.status,
      type: params.type,
      source: params.source,
      source_key: params.source_key,
      run_id: params.run_id,
      q: params.q,
      year: runFilter.value?.year || undefined,
      limit: 1000,
    };

    const result = await eventCandidates.approveBatch(payload);
    advancePublishProgress(1, 1);
    if (result.failed > 0) {
      toast.warn(`Publikovane: ${result.published}, zlyhalo: ${result.failed}.`);
    } else {
      toast.success(`Publikovanych ${result.published} kandidatov.`);
    }
    await load();
  } catch (e) {
    error.value = e?.response?.data?.message || "Hromadne publikovanie podla filtra zlyhalo";
    toast.error(error.value);
  } finally {
    finishPublishProgress();
    loading.value = false;
  }
}

function buildManualBatchPayload() {
  return {
    status: manualStatus.value || "draft",
    type: manualType.value || undefined,
    q: manualQ.value?.trim() ? manualQ.value.trim() : undefined,
    year: runFilter.value?.year || undefined,
    limit: 1000,
  };
}

async function publishBySelectedMode() {
  const labels = {
    crawled: "crawlovane",
    manual: "manualne",
    all: "crawlovane aj manualne",
  };

  const ok = await confirm({
    title: "Publikovat podla rezimu",
    message: `Naozaj publikovat ${labels[publishMode.value] || "vybrane"} udalosti podla filtra? (max 1000 na typ)` ,
    confirmText: "Publikovat",
    cancelText: "Zrusit",
    variant: "danger",
  });
  if (!ok) return;

  loading.value = true;
  error.value = null;
  const modeSteps = publishMode.value === "all" ? 2 : 1;
  startPublishProgress("Publikujem podla rezimu...", modeSteps);
  let completedSteps = 0;

  try {
    const crawledPayload = {
      status: buildParams().status,
      type: buildParams().type,
      source: buildParams().source,
      source_key: buildParams().source_key,
      run_id: buildParams().run_id,
      q: buildParams().q,
      year: runFilter.value?.year || undefined,
      limit: 1000,
    };
    const manualPayload = buildManualBatchPayload();

    let crawledResult = { published: 0, failed: 0 };
    let manualResult = { published: 0, failed: 0 };

    if (publishMode.value === "crawled" || publishMode.value === "all") {
      crawledResult = await eventCandidates.approveBatch(crawledPayload);
      completedSteps += 1;
      advancePublishProgress(completedSteps, modeSteps);
    }

    if (publishMode.value === "manual" || publishMode.value === "all") {
      manualResult = await eventCandidates.publishManualBatch(manualPayload);
      completedSteps += 1;
      advancePublishProgress(completedSteps, modeSteps);
    }

    const totalPublished = Number(crawledResult.published || 0) + Number(manualResult.published || 0);
    const totalFailed = Number(crawledResult.failed || 0) + Number(manualResult.failed || 0);

    if (totalFailed > 0) {
      toast.warn(`Publikovane spolu: ${totalPublished}, zlyhalo: ${totalFailed}.`);
    } else {
      toast.success(`Publikovanych spolu: ${totalPublished}.`);
    }

    await Promise.all([load(), loadManual()]);
  } catch (e) {
    error.value = e?.response?.data?.message || "Hromadne publikovanie podla rezimu zlyhalo";
    toast.error(error.value);
  } finally {
    finishPublishProgress();
    loading.value = false;
  }
}

async function retranslateVisiblePending() {
  const ids = visiblePendingCandidateIds.value;
  if (ids.length === 0) {
    toast.warn("Na tejto stranke nie su ziadni pending kandidati.");
    return;
  }

  const ok = await confirm({
    title: "Prelozit znova viditelnych",
    message: `Spustit novy preklad pre ${ids.length} pending kandidatov na aktualnej stranke?`,
    confirmText: "Spustit",
    cancelText: "Zrusit",
  });
  if (!ok) return;

  loading.value = true;
  error.value = null;

  let successCount = 0;
  let failCount = 0;

  try {
    for (const candidateId of ids) {
      try {
        await eventCandidates.retranslate(candidateId);
        successCount += 1;
      } catch {
        failCount += 1;
      }
    }

    if (failCount === 0) {
      toast.success(`Preklad bol spusteny pre ${successCount} kandidatov.`);
    } else {
      toast.warn(`Preklad spusteny: ${successCount}, zlyhalo: ${failCount}.`);
    }

    await load();
  } catch (e) {
    error.value = e?.response?.data?.message || "Hromadny retranslate zlyhal";
    toast.error(error.value);
  } finally {
    loading.value = false;
  }
}

async function retranslateByFilter() {
  const ok = await confirm({
    title: "Prelozit znova podla filtra",
    message: "Spustit novy preklad pre kandidatov podla aktualneho filtra? (max 1000)",
    confirmText: "Spustit",
    cancelText: "Zrusit",
  });
  if (!ok) return;

  loading.value = true;
  error.value = null;

  try {
    const params = buildParams();
    const payload = {
      status: params.status,
      type: params.type,
      source: params.source,
      source_key: params.source_key,
      run_id: params.run_id,
      q: params.q,
      year: runFilter.value?.year || undefined,
      limit: 1000,
    };

    const result = await eventCandidates.retranslateBatch(payload);
    if (result.failed > 0) {
      toast.warn(`Retranslate podla filtra: queued ${result.queued}, failed ${result.failed}.`);
    } else {
      toast.success(`Retranslate podla filtra: queued ${result.queued}.`);
    }
    await load();
  } catch (e) {
    error.value = e?.response?.data?.message || "Hromadny retranslate podla filtra zlyhal";
    toast.error(error.value);
  } finally {
    loading.value = false;
  }
}

function openCrawlingHub() {
  router.push('/admin/event-sources');
}

function resetToFirstPage() {
  page.value = 1;
}

function buildParams() {
  const sourceValue = source.value?.trim() ? source.value.trim() : undefined;

  return {
    status: status.value || undefined,
    type: type.value || undefined,
    source: sourceValue,
    source_key: sourceValue,
    run_id: runFilter.value?.runId ? Number(runFilter.value.runId) : undefined,
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
    error.value = e?.response?.data?.message || "Chyba pri nacitani kandidatov";
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
    manualError.value = e?.response?.data?.message || "Chyba pri nacitani draftov";
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

// Source and q are triggered on Enter/Search only to avoid fetches on each keystroke.

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

function nowLocalInput() {
  return toLocalInput(new Date().toISOString());
}

function addHoursToLocalInput(value, hours) {
  const base = value ? new Date(value) : new Date();
  if (Number.isNaN(base.getTime())) return "";
  base.setHours(base.getHours() + hours);
  return toLocalInput(base.toISOString());
}

function setManualStartNow() {
  manualForm.value.starts_at = nowLocalInput();
}

function setManualEndByHours(hours) {
  manualForm.value.ends_at = addHoursToLocalInput(manualForm.value.starts_at || nowLocalInput(), hours);
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
  if (!manualCanSave.value) {
    manualError.value = manualFormErrors.value[0] || "Skontroluj formular.";
    return;
  }

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
    manualError.value = e?.response?.data?.message || "Ulozenie zlyhalo";
  } finally {
    manualLoading.value = false;
  }
}

async function deleteManual(row) {
  if (!row?.id) return;
  const ok = await confirm({
    title: 'Zmazat draft',
    message: `Zmazat draft "${row.title}"?`,
    confirmText: 'Zmazat',
    cancelText: 'Zrusit',
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
    confirmText: 'Publikovat',
    cancelText: 'Zrusit',
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

watch(
  () => route.query,
  () => {
    if (activeTab.value !== "crawled") return;
    applyRunFilterFromRoute();
    load();
  },
  { deep: true }
);

onMounted(() => {
  applyRunFilterFromRoute();
  load();
});
</script>

<template>
  <div style="max-width: 980px; margin: 0 auto; padding: 10px 8px;">
    <div style="display:flex; align-items:flex-end; justify-content:space-between; gap:10px;">
      <div>
        <h1 style="margin:0 0 4px;">Kandidati udalosti</h1>
      </div>

      <div style="display:flex; gap:6px; flex-wrap:wrap;">
        <button
          @click="openCrawlingHub"
          :disabled="loading || manualLoading"
          style="padding:7px 10px; border:1px solid rgb(var(--color-primary-rgb) / .35); border-radius:8px; background:rgb(var(--color-primary-rgb) / .12); color:inherit;"
        >
          Centrum crawlovania
        </button>
        <button
          @click="setTab('crawled')"
          :disabled="loading || manualLoading"
          :style="activeTab === 'crawled'
            ? 'padding:7px 10px; border:1px solid rgb(var(--color-surface-rgb) / .4); border-radius:8px; background:rgb(var(--color-surface-rgb) / .08); color:inherit;'
            : 'padding:7px 10px; border:1px solid rgb(var(--color-surface-rgb) / .2); border-radius:8px; background:transparent; color:inherit;'"
        >
          Crawlovani kandidati
        </button>
        <button
          @click="setTab('manual')"
          :disabled="loading || manualLoading"
          :style="activeTab === 'manual'
            ? 'padding:7px 10px; border:1px solid rgb(var(--color-surface-rgb) / .4); border-radius:8px; background:rgb(var(--color-surface-rgb) / .08); color:inherit;'
            : 'padding:7px 10px; border:1px solid rgb(var(--color-surface-rgb) / .2); border-radius:8px; background:transparent; color:inherit;'"
        >
          Manualne navrhy
        </button>
      </div>
    </div>

    <div v-if="activeTab === 'crawled'">
      <div class="uxOverview">
        <div class="uxOverview__item">
          <span>Na stranke</span>
          <strong>{{ crawledStats.total }}</strong>
        </div>
        <div class="uxOverview__item">
          <span>Pending</span>
          <strong>{{ crawledStats.pending }}</strong>
        </div>
        <div class="uxOverview__item">
          <span>Prelozene</span>
          <strong>{{ crawledStats.translated }}</strong>
        </div>
        <div class="uxOverview__item">
          <span>Chyba prekladu</span>
          <strong>{{ crawledStats.failedTranslation }}</strong>
        </div>
      </div>

      <div
        v-if="runFilter"
        style="margin-top: 12px; display:flex; align-items:center; gap:8px; flex-wrap:wrap;"
      >
        <span style="display:inline-flex; align-items:center; padding:4px 10px; border-radius:999px; border:1px solid rgb(var(--color-primary-rgb) / .35); background:rgb(var(--color-primary-rgb) / .12); font-size:12px;">
          Run #{{ runFilter.runId }} / {{ sourceLabel(runFilter.sourceKey || '-') }}<span v-if="runFilter.year"> / {{ runFilter.year }}</span>
        </span>
        <button
          type="button"
          @click="clearRunFilter"
          style="padding:6px 10px; border:1px solid rgb(var(--color-surface-rgb) / .2); border-radius:8px; background:transparent; color:inherit;"
        >
          Zrusit filter runu
        </button>
      </div>

      <div class="uxActionBar">
        <select
          v-model="publishMode"
          :disabled="loading"
          style="padding:8px 10px; border:1px solid rgb(var(--color-surface-rgb) / .2); border-radius:8px; background:transparent; color:inherit; margin-right:8px;"
        >
          <option value="crawled">Publikovat: Crawlovane</option>
          <option value="manual">Publikovat: Manualne</option>
          <option value="all">Publikovat: Oboje</option>
        </select>
        <button
          @click="publishBySelectedMode"
          :disabled="loading"
          style="padding:8px 12px; border:1px solid rgb(var(--color-success-rgb) / .35); border-radius:8px; background:rgb(var(--color-success-rgb) / .10); color:inherit; margin-right:8px;"
        >
          Publikovat podla rezimu
        </button>
        <button
          @click="publishAllByFilter"
          :disabled="loading"
          style="padding:8px 12px; border:1px solid rgb(var(--color-success-rgb) / .35); border-radius:8px; background:rgb(var(--color-success-rgb) / .10); color:inherit; margin-right:8px;"
        >
          Publikovat vsetko (podla filtra)
        </button>
        <button
          @click="publishAllVisiblePending"
          :disabled="loading || visiblePendingCandidateIds.length === 0"
          style="padding:8px 12px; border:1px solid rgb(var(--color-success-rgb) / .35); border-radius:8px; background:rgb(var(--color-success-rgb) / .10); color:inherit; margin-right:8px;"
        >
          Publikovat vsetko (viditelne)
        </button>
        <button
          @click="retranslateByFilter"
          :disabled="loading"
          style="padding:8px 12px; border:1px solid rgb(var(--color-primary-rgb) / .35); border-radius:8px; background:rgb(var(--color-primary-rgb) / .12); color:inherit; margin-right:8px;"
        >
          Prelozit znova (podla filtra)
        </button>
        <button
          @click="retranslateVisiblePending"
          :disabled="loading || visiblePendingCandidateIds.length === 0"
          style="padding:8px 12px; border:1px solid rgb(var(--color-primary-rgb) / .35); border-radius:8px; background:rgb(var(--color-primary-rgb) / .12); color:inherit; margin-right:8px;"
        >
          Prelozit znova (viditelne)
        </button>
        <button
          @click="clearFilters"
          :disabled="loading"
          style="padding:8px 12px; border:1px solid rgb(var(--color-surface-rgb) / .2); border-radius:8px; background:transparent; color:inherit;"
        >
          Resetovat filtre
        </button>
      </div>

      <div
        v-if="publishProgressActive"
        style="margin-top: 10px; border: 1px solid rgb(var(--color-surface-rgb) / .14); border-radius: 10px; padding: 8px 10px; background: rgb(var(--color-surface-rgb) / .04);"
      >
        <div style="display:flex; justify-content:space-between; gap:10px; font-size:12px; margin-bottom:6px;">
          <span>{{ publishProgressLabel || 'Publikujem...' }}</span>
          <strong>{{ publishProgressPercent }}%</strong>
        </div>
        <div style="height: 7px; border-radius: 999px; background: rgb(var(--color-surface-rgb) / .16); overflow: hidden;">
          <div
            style="height: 100%; background: linear-gradient(90deg, rgb(var(--color-primary-rgb) / .95), rgb(var(--color-success-rgb) / .95)); transition: width .25s ease;"
            :style="{ width: `${publishProgressPercent}%` }"
          ></div>
        </div>
      </div>

      <div
        style="
          margin-top: 10px;
          padding: 7px;
          border: 1px solid rgb(var(--color-surface-rgb) / .12);
          border-radius: 12px;
          display: grid;
          grid-template-columns: repeat(12, 1fr);
          gap: 6px;
        "
      >
        <div style="grid-column: span 3;">
          <label style="display:block; font-size:12px; opacity:.8; margin-bottom:4px;">Stav</label>
          <select
            v-model="status"
            :disabled="loading"
            style="width:100%; padding:7px; border-radius:9px; border:1px solid rgb(var(--color-surface-rgb) / .18); background:transparent; color:inherit;"
          >
            <option value="pending">Cakajuce</option>
            <option value="approved">Schvalene</option>
            <option value="rejected">Zamietnute</option>
          </select>
        </div>

        <div style="grid-column: span 3;">
          <label style="display:block; font-size:12px; opacity:.8; margin-bottom:4px;">Typ</label>
          <select
            v-model="type"
            :disabled="loading"
            style="width:100%; padding:7px; border-radius:9px; border:1px solid rgb(var(--color-surface-rgb) / .18); background:transparent; color:inherit;"
          >
            <option value="">vsetky</option>
            <option value="eclipse_lunar">eclipse_lunar</option>
            <option value="eclipse_solar">eclipse_solar</option>
            <option value="meteor_shower">meteor_shower</option>
            <option value="planetary_event">planetary_event</option>
            <option value="other">other</option>
          </select>
        </div>

        <div style="grid-column: span 3;">
          <label style="display:block; font-size:12px; opacity:.8; margin-bottom:4px;">Zdroj</label>
          <input
            v-model="source"
            :disabled="loading"
            placeholder="napr. astropixels"
            @keyup.enter="resetToFirstPage(); load()"
            style="width:100%; padding:7px; border-radius:9px; border:1px solid rgb(var(--color-surface-rgb) / .18); background:transparent; color:inherit;"
          />
        </div>

        <div style="grid-column: span 3;">
          <label style="display:block; font-size:12px; opacity:.8; margin-bottom:4px;">Na stranku</label>
          <select
            v-model.number="per_page"
            :disabled="loading"
            style="width:100%; padding:7px; border-radius:9px; border:1px solid rgb(var(--color-surface-rgb) / .18); background:transparent; color:inherit;"
          >
            <option :value="10">10</option>
            <option :value="20">20</option>
            <option :value="50">50</option>
            <option :value="100">100</option>
          </select>
        </div>

        <div style="grid-column: span 9;">
          <label style="display:block; font-size:12px; opacity:.8; margin-bottom:4px;">Hladaj</label>
          <input
            v-model="q"
            :disabled="loading"
            placeholder="hladaj v title/short/description (q)"
            @keyup.enter="resetToFirstPage(); load()"
            style="width:100%; padding:7px; border-radius:9px; border:1px solid rgb(var(--color-surface-rgb) / .18); background:transparent; color:inherit;"
          />
        </div>

        <div style="grid-column: span 3; display:flex; align-items:flex-end; gap:10px;">
          <button
            @click="resetToFirstPage(); load()"
            :disabled="loading"
            style="width:100%; padding:7px 9px; border-radius:9px; border:1px solid rgb(var(--color-surface-rgb) / .18); background:rgb(var(--color-surface-rgb) / .08); color:inherit;"
          >
            Hladat
          </button>
        </div>
      </div>

      <div v-if="error" style="margin-top: 12px; color: var(--color-danger);">
        {{ error }}
      </div>

      <div v-if="loading" style="margin-top: 12px; opacity: .85;">
        Nacitavam...
      </div>

      <div
        v-if="data && !loading"
        style="
          margin-top: 12px;
          border: 1px solid rgb(var(--color-surface-rgb) / .12);
          border-radius: 12px;
          overflow: hidden;
        "
      >
        <table style="width:100%; border-collapse:collapse;">
          <thead style="background: rgb(var(--color-surface-rgb) / .05);">
            <tr>
              <th style="text-align:left; padding:8px; font-size:12px; opacity:.85;">ID</th>
              <th style="text-align:left; padding:6px; font-size:12px; opacity:.85;">Typ</th>
              <th style="text-align:left; padding:6px; font-size:12px; opacity:.85;">Nazov</th>
              <th style="text-align:left; padding:6px; font-size:12px; opacity:.85;">Zdroj</th>
              <th style="text-align:left; padding:6px; font-size:12px; opacity:.85;">Dovera</th>
              <th style="text-align:left; padding:6px; font-size:12px; opacity:.85;">Sparovane zdroje</th>
              <th style="text-align:left; padding:6px; font-size:12px; opacity:.85;">Zaciatok</th>
              <th style="text-align:left; padding:6px; font-size:12px; opacity:.85;">Stav</th>
              <th style="text-align:left; padding:6px; font-size:12px; opacity:.85;">Preklad a akcie</th>
            </tr>
          </thead>

          <tbody>
            <tr
              v-for="c in data.data"
              :key="c.id"
              style="border-top: 1px solid rgb(var(--color-surface-rgb) / .08);"
            >
              <td style="padding:6px; white-space:nowrap;">{{ c.id }}</td>
              <td style="padding:6px; white-space:nowrap;">{{ c.type }}</td>
              <td style="padding:6px;">
                <div style="font-weight:600;">{{ candidateDisplayTitle(c) }}</div>
                <div v-if="candidateDisplayShort(c) && candidateDisplayShort(c) !== '-'" style="opacity:.75; font-size:12px; margin-top:4px;">
                  {{ candidateDisplayShort(c) }}
                </div>
              </td>
              <td style="padding:6px; white-space:nowrap;">
                <span :style="sourceBadgeStyle(c.source_name)">{{ sourceLabel(c.source_name) }}</span>
              </td>
              <td style="padding:6px; white-space:nowrap;">{{ formatConfidence(c.confidence_score) }}</td>
              <td style="padding:6px;">
                <div style="display:flex; flex-wrap:wrap; gap:6px;">
                  <span
                    v-for="src in normalizeSources(c.matched_sources)"
                    :key="`matched-${c.id}-${src}`"
                    :style="sourceBadgeStyle(src)"
                  >
                    {{ sourceLabel(src) }}
                  </span>
                  <span v-if="normalizeSources(c.matched_sources).length === 0" style="opacity:.75;">-</span>
                </div>
              </td>
              <td style="padding:6px; white-space:nowrap;">{{ formatDate(c.start_at) }}</td>
              <td style="padding:6px; white-space:nowrap;">{{ c.status }}</td>
              <td style="padding:6px; white-space:nowrap;">
                <span :style="translationStatusStyle(c.translation_status)">
                  {{ normalizeTranslationStatus(c.translation_status) }}
                </span>
                <div style="display:flex; gap:6px; margin-top:6px;">
                  <button
                    v-if="c.status === 'pending'"
                    @click="publishCandidateQuick(c)"
                    :disabled="loading"
                    style="padding:6px 8px; border-radius:8px; border:1px solid rgb(var(--color-success-rgb) / .35); background:rgb(var(--color-success-rgb) / .10); color:inherit;"
                  >
                    Publikovat
                  </button>
                  <button
                    @click="openCandidate(c.id)"
                    style="padding:6px 8px; border-radius:8px; border:1px solid rgb(var(--color-surface-rgb) / .18); background:rgb(var(--color-surface-rgb) / .08); color:inherit;"
                  >
                    Otvorit
                  </button>
                </div>
              </td>
            </tr>

            <tr v-if="data.data.length === 0">
              <td colspan="9" style="padding:0;"></td>
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
          Strana {{ data.current_page }} / {{ data.last_page }} (spolu {{ data.total }})
        </div>

        <div style="display:flex; gap:10px;">
          <button
            @click="prevPage"
            :disabled="loading || page <= 1"
            style="padding:8px 12px; border-radius:10px; border:1px solid rgb(var(--color-surface-rgb) / .18); background:transparent; color:inherit;"
          >
            Pred
          </button>
          <button
            @click="nextPage"
            :disabled="loading || (data && page >= data.last_page)"
            style="padding:8px 12px; border-radius:10px; border:1px solid rgb(var(--color-surface-rgb) / .18); background:transparent; color:inherit;"
          >
            Dalsia
          </button>
        </div>
      </div>
    </div>

    <div v-else>
      <div class="uxOverview">
        <div class="uxOverview__item">
          <span>Na stranke</span>
          <strong>{{ manualStats.total }}</strong>
        </div>
        <div class="uxOverview__item">
          <span>Draft</span>
          <strong>{{ manualStats.draft }}</strong>
        </div>
        <div class="uxOverview__item">
          <span>Publikovane</span>
          <strong>{{ manualStats.published }}</strong>
        </div>
      </div>

      <div style="display:flex; justify-content:space-between; align-items:center; margin-top: 12px;">
        <button
          @click="openManualFormCreate"
          :disabled="manualLoading"
          style="padding:8px 12px; border:1px solid rgb(var(--color-surface-rgb) / .2); border-radius:8px; background:transparent; color:inherit;"
        >
          Vytvorit manualnu udalost
        </button>
        <button
          @click="clearManualFilters"
          :disabled="manualLoading"
          style="padding:8px 12px; border:1px solid rgb(var(--color-surface-rgb) / .2); border-radius:8px; background:transparent; color:inherit;"
        >
          Resetovat filtre
        </button>
      </div>

      <div
        style="
          margin-top: 12px;
          padding: 8px;
          border: 1px solid rgb(var(--color-surface-rgb) / .12);
          border-radius: 12px;
          display: grid;
          grid-template-columns: repeat(12, 1fr);
          gap: 8px;
        "
      >
        <div style="grid-column: span 3;">
          <label style="display:block; font-size:12px; opacity:.8; margin-bottom:4px;">Stav</label>
          <select
            v-model="manualStatus"
            :disabled="manualLoading"
            style="width:100%; padding:8px; border-radius:10px; border:1px solid rgb(var(--color-surface-rgb) / .18); background:transparent; color:inherit;"
          >
            <option value="draft">Navrh</option>
            <option value="published">Publikovane</option>
          </select>
        </div>

        <div style="grid-column: span 3;">
          <label style="display:block; font-size:12px; opacity:.8; margin-bottom:4px;">Typ</label>
          <select
            v-model="manualType"
            :disabled="manualLoading"
            style="width:100%; padding:8px; border-radius:10px; border:1px solid rgb(var(--color-surface-rgb) / .18); background:transparent; color:inherit;"
          >
            <option value="">vsetky</option>
            <option value="eclipse_lunar">eclipse_lunar</option>
            <option value="eclipse_solar">eclipse_solar</option>
            <option value="meteor_shower">meteor_shower</option>
            <option value="planetary_event">planetary_event</option>
            <option value="other">other</option>
          </select>
        </div>

        <div style="grid-column: span 3;">
          <label style="display:block; font-size:12px; opacity:.8; margin-bottom:4px;">Na stranku</label>
          <select
            v-model.number="manualPerPage"
            :disabled="manualLoading"
            style="width:100%; padding:8px; border-radius:10px; border:1px solid rgb(var(--color-surface-rgb) / .18); background:transparent; color:inherit;"
          >
            <option :value="10">10</option>
            <option :value="20">20</option>
            <option :value="50">50</option>
            <option :value="100">100</option>
          </select>
        </div>

        <div style="grid-column: span 9;">
          <label style="display:block; font-size:12px; opacity:.8; margin-bottom:4px;">Hladaj</label>
          <input
            v-model="manualQ"
            :disabled="manualLoading"
            placeholder="hladaj v title (q)"
            @keyup.enter="resetManualToFirstPage(); loadManual()"
            style="width:100%; padding:8px; border-radius:10px; border:1px solid rgb(var(--color-surface-rgb) / .18); background:transparent; color:inherit;"
          />
        </div>

        <div style="grid-column: span 3; display:flex; align-items:flex-end; gap:10px;">
          <button
            @click="resetManualToFirstPage(); loadManual()"
            :disabled="manualLoading"
            style="width:100%; padding:8px 10px; border-radius:10px; border:1px solid rgb(var(--color-surface-rgb) / .18); background:rgb(var(--color-surface-rgb) / .08); color:inherit;"
          >
            Hladat
          </button>
        </div>
      </div>

      <div v-if="showManualForm" style="margin-top: 12px; border:1px solid rgb(var(--color-surface-rgb) / .14); border-radius:14px; background:rgb(var(--color-surface-rgb) / .03); padding:14px;">
        <div style="display:flex; justify-content:space-between; align-items:flex-start; gap:10px; flex-wrap:wrap; margin-bottom:10px;">
          <div>
            <div style="font-weight:700;">{{ manualEditingId ? 'Upravit manualny navrh' : 'Novy manualny navrh' }}</div>
            <div style="font-size:12px; opacity:.78; margin-top:2px;">Vypln nazov, typ a termin. Zvysok mozes doplnit neskor.</div>
          </div>
          <div style="display:flex; gap:6px; flex-wrap:wrap;">
            <button
              @click="setManualStartNow"
              :disabled="manualLoading"
              style="padding:6px 9px; border-radius:999px; border:1px solid rgb(var(--color-primary-rgb) / .3); background:rgb(var(--color-primary-rgb) / .11); color:inherit; font-size:12px;"
            >
              Zaciatok teraz
            </button>
            <button
              @click="setManualEndByHours(1)"
              :disabled="manualLoading"
              style="padding:6px 9px; border-radius:999px; border:1px solid rgb(var(--color-surface-rgb) / .2); background:transparent; color:inherit; font-size:12px;"
            >
              Koniec +1h
            </button>
            <button
              @click="setManualEndByHours(2)"
              :disabled="manualLoading"
              style="padding:6px 9px; border-radius:999px; border:1px solid rgb(var(--color-surface-rgb) / .2); background:transparent; color:inherit; font-size:12px;"
            >
              Koniec +2h
            </button>
          </div>
        </div>

        <div style="display:grid; grid-template-columns: repeat(12, 1fr); gap:10px;">
          <div style="grid-column: span 8;">
            <label style="display:block; font-size:12px; opacity:.82; margin-bottom:4px;">Nazov udalosti *</label>
            <input
              v-model="manualForm.title"
              type="text"
              placeholder="napr. Pozorovanie Mesiaca na hvezdarni"
              :disabled="manualLoading"
              style="width:100%; padding:10px; border-radius:10px; border:1px solid rgb(var(--color-surface-rgb) / .2); background:transparent; color:inherit;"
            />
          </div>

          <div style="grid-column: span 4;">
            <label style="display:block; font-size:12px; opacity:.82; margin-bottom:4px;">Typ *</label>
            <select
              v-model="manualForm.event_type"
              :disabled="manualLoading"
              style="width:100%; padding:10px; border-radius:10px; border:1px solid rgb(var(--color-surface-rgb) / .2); background:transparent; color:inherit;"
            >
              <option
                v-for="opt in manualTypeOptions"
                :key="opt.value"
                :value="opt.value"
              >
                {{ opt.label }}
              </option>
            </select>
          </div>

          <div style="grid-column: span 6;">
            <label style="display:block; font-size:12px; opacity:.82; margin-bottom:4px;">Zaciatok *</label>
            <input
              v-model="manualForm.starts_at"
              type="datetime-local"
              :disabled="manualLoading"
              style="width:100%; padding:10px; border-radius:10px; border:1px solid rgb(var(--color-surface-rgb) / .2); background:transparent; color:inherit;"
            />
          </div>

          <div style="grid-column: span 6;">
            <label style="display:block; font-size:12px; opacity:.82; margin-bottom:4px;">Koniec (volitelne)</label>
            <input
              v-model="manualForm.ends_at"
              type="datetime-local"
              :disabled="manualLoading"
              style="width:100%; padding:10px; border-radius:10px; border:1px solid rgb(var(--color-surface-rgb) / .2); background:transparent; color:inherit;"
            />
          </div>

          <div style="grid-column: span 12;">
            <label style="display:block; font-size:12px; opacity:.82; margin-bottom:4px;">Popis</label>
            <textarea
              v-model="manualForm.description"
              rows="4"
              placeholder="Kratky popis, co sa deje a kedy je najlepsie pozorovanie."
              :disabled="manualLoading"
              style="width:100%; padding:10px; border-radius:10px; border:1px solid rgb(var(--color-surface-rgb) / .2); background:transparent; color:inherit;"
            ></textarea>
          </div>
        </div>

        <div v-if="manualFormErrors.length > 0" style="margin-top:10px; padding:8px 10px; border:1px solid rgb(239 68 68 / .35); border-radius:10px; background:rgb(239 68 68 / .08); font-size:12px;">
          {{ manualFormErrors[0] }}
        </div>

        <div style="display:flex; gap:10px; justify-content:flex-end; margin-top:12px;">
          <button
            @click="closeManualForm"
            :disabled="manualLoading"
            style="padding:8px 12px; border-radius:10px; border:1px solid rgb(var(--color-surface-rgb) / .2); background:transparent; color:inherit;"
          >
            Zrusit
          </button>
          <button
            @click="saveManual"
            :disabled="manualLoading || !manualCanSave"
            style="padding:8px 12px; border-radius:10px; border:1px solid rgb(var(--color-primary-rgb) / .32); background:rgb(var(--color-primary-rgb) / .12); color:inherit;"
          >
            {{ manualLoading ? 'Uklada sa...' : 'Ulozit navrh' }}
          </button>
        </div>
      </div>

      <div v-if="manualError" style="margin-top: 12px; color: var(--color-danger);">
        {{ manualError }}
      </div>

      <div v-if="manualLoading" style="margin-top: 12px; opacity: .85;">
        Nacitavam...
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
              <th style="text-align:left; padding:8px; font-size:12px; opacity:.85;">Nazov</th>
              <th style="text-align:left; padding:8px; font-size:12px; opacity:.85;">Typ</th>
              <th style="text-align:left; padding:8px; font-size:12px; opacity:.85;">Zaciatok</th>
              <th style="text-align:left; padding:8px; font-size:12px; opacity:.85;">Stav</th>
              <th style="text-align:right; padding:8px; font-size:12px; opacity:.85;">Akcie</th>
            </tr>
          </thead>

          <tbody>
            <tr
              v-for="row in manualData.data"
              :key="row.id"
              style="border-top: 1px solid rgb(var(--color-surface-rgb) / .08);"
            >
              <td style="padding:8px;">{{ row.title }}</td>
              <td style="padding:8px; white-space:nowrap;">{{ row.event_type }}</td>
              <td style="padding:8px; white-space:nowrap;">{{ formatDate(row.starts_at) }}</td>
              <td style="padding:8px; white-space:nowrap;">{{ row.status }}</td>
              <td style="padding:8px; text-align:right; white-space:nowrap;">
                <button
                  @click="openManualFormEdit(row)"
                  :disabled="manualLoading"
                  style="padding:6px 10px; border-radius:10px; border:1px solid rgb(var(--color-surface-rgb) / .18); background:transparent; color:inherit;"
                >
                  Upravit
                </button>
                <button
                  @click="deleteManual(row)"
                  :disabled="manualLoading"
                  style="margin-left:6px; padding:6px 10px; border-radius:10px; border:1px solid rgb(var(--color-surface-rgb) / .18); background:transparent; color:inherit;"
                >
                  Zmazat
                </button>
                <button
                  @click="publishManual(row)"
                  :disabled="manualLoading || row.status === 'published'"
                  style="margin-left:6px; padding:6px 10px; border-radius:10px; border:1px solid rgb(var(--color-surface-rgb) / .18); background:rgb(var(--color-surface-rgb) / .08); color:inherit;"
                >
                  Publikovat
                </button>
              </td>
            </tr>

            <tr v-if="manualData.data.length === 0">
              <td colspan="5" style="padding:16px; opacity:.8;">
                Ziadne drafty.
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
          Strana {{ manualData.current_page }} / {{ manualData.last_page }} (spolu {{ manualData.total }})
        </div>

        <div style="display:flex; gap:10px;">
          <button
            @click="prevManualPage"
            :disabled="manualLoading || manualPage <= 1"
            style="padding:8px 12px; border-radius:10px; border:1px solid rgb(var(--color-surface-rgb) / .18); background:transparent; color:inherit;"
          >
            Pred
          </button>
          <button
            @click="nextManualPage"
            :disabled="manualLoading || (manualData && manualPage >= manualData.last_page)"
            style="padding:8px 12px; border-radius:10px; border:1px solid rgb(var(--color-surface-rgb) / .18); background:transparent; color:inherit;"
          >
            Dalsia
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.uxOverview {
  margin-top: 10px;
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 6px;
}

.uxOverview__item {
  border: 1px solid rgb(var(--color-surface-rgb) / 0.14);
  border-radius: 10px;
  background: rgb(var(--color-surface-rgb) / 0.04);
  padding: 6px 8px;
  display: grid;
  gap: 2px;
}

.uxOverview__item span {
  font-size: 12px;
  opacity: 0.8;
}

.uxOverview__item strong {
  font-size: 14px;
}

.uxActionBar {
  margin-top: 10px;
  display: flex;
  justify-content: flex-end;
  gap: 6px;
  flex-wrap: wrap;
  position: sticky;
  top: 8px;
  z-index: 4;
  padding: 6px;
  border: 1px solid rgb(var(--color-surface-rgb) / 0.12);
  border-radius: 10px;
  background: rgb(var(--color-bg-rgb) / 0.88);
  backdrop-filter: blur(4px);
}

@media (max-width: 900px) {
  .uxOverview {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }

  .uxActionBar {
    position: static;
  }
}
</style>
