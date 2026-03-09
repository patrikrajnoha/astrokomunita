<script setup>
import { computed, onMounted, onUnmounted, ref, watch } from "vue";
import { useRoute, useRouter } from "vue-router";
import api from "@/services/api";
import { eventCandidates } from "@/services/eventCandidates";
import { useConfirm } from '@/composables/useConfirm'
import { useToast } from '@/composables/useToast'
import { useAuthStore } from '@/stores/auth'
import BaseModal from '@/components/ui/BaseModal.vue'
import { candidateDisplayShort, candidateDisplayTitle } from '@/utils/translatedFields'
import {
  resolveUserCoordinates,
  resolveUserLocationLabel,
  resolveUserPreferredTimezone,
} from '@/utils/userTimezone'

const route = useRoute();
const router = useRouter();
const { confirm } = useConfirm()
const toast = useToast()
const auth = useAuthStore()

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
const showAdvancedFilters = ref(false);
const currentDate = new Date();
const timePreset = ref("none");
const filterYear = ref(currentDate.getFullYear());
const filterMonth = ref(currentDate.getMonth() + 1);
const filterWeek = ref(getIsoWeek(currentDate));

const page = ref(1);
const per_page = ref(20);

const data = ref(null);
const duplicatePreview = ref(null);
const duplicateLoading = ref(false);
const duplicateMerging = ref(false);
const duplicateDryRunning = ref(false);
const duplicateGroupLimit = ref(8);
const duplicatePerGroup = ref(3);
const candidateDetailOpen = ref(false);
const candidateDetailLoading = ref(false);
const candidateDetailError = ref("");
const candidateDetail = ref(null);

const manualLoading = ref(false);
const manualError = ref(null);
const manualStatus = ref("draft");
const manualType = ref("");
const manualQ = ref("");
const manualPage = ref(1);
const manualPerPage = ref(20);
const manualData = ref(null);
const publishMode = ref("crawled");
const astronomyContext = ref(null);
const astronomyContextLoading = ref(false);
const showObservationContext = ref(false);
const translationRefreshDelayMs = 5000;
let translationRefreshTimerId = null;

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
  { value: "other", label: "Iná udalosť" },
];

const timePresetOptions = [
  { value: "none", label: "Vsetko" },
  { value: "week", label: "Tyzden" },
  { value: "month", label: "Mesiac" },
  { value: "year", label: "Rok" },
  { value: "next_7_days", label: "Najblizsich 7 dni" },
  { value: "next_30_days", label: "Najblizsich 30 dni" },
];
const monthOptions = [
  { value: 1, label: "Januar" },
  { value: 2, label: "Februar" },
  { value: 3, label: "Marec" },
  { value: 4, label: "April" },
  { value: 5, label: "Maj" },
  { value: 6, label: "Jun" },
  { value: 7, label: "Jul" },
  { value: 8, label: "August" },
  { value: 9, label: "September" },
  { value: 10, label: "Oktober" },
  { value: 11, label: "November" },
  { value: 12, label: "December" },
];

const manualFormErrors = computed(() => {
  const errors = [];
  if (!String(manualForm.value.title || "").trim()) {
    errors.push("Názov je povinný.");
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
const preferredTimezone = computed(() => resolveUserPreferredTimezone(auth.user));
const preferredLocationLabel = computed(() => resolveUserLocationLabel(auth.user));
const preferredCoordinates = computed(() => resolveUserCoordinates(auth.user));
const timezoneInfoLabel = computed(() => `${preferredLocationLabel.value} (${preferredTimezone.value})`);
const astronomyContextAvailable = computed(() => astronomyContext.value && typeof astronomyContext.value === "object");

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

const hasPendingTranslationsOnPage = computed(() => {
  const rows = Array.isArray(data.value?.data) ? data.value.data : [];
  return rows.some((row) => isPendingTranslation(row));
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

const duplicateSummary = computed(() => duplicatePreview.value?.summary || {
  group_count: 0,
  duplicate_candidates: 0,
  limit_groups: duplicateGroupLimit.value,
  per_group: duplicatePerGroup.value,
});

const duplicateGroups = computed(() => {
  const groups = duplicatePreview.value?.groups;
  return Array.isArray(groups) ? groups : [];
});

const canMergeDuplicates = computed(() => {
  return !duplicateMerging.value && !duplicateDryRunning.value && duplicateGroups.value.length > 0;
});
const showConfidenceColumn = computed(() => Boolean(auth.isAdmin));
const showYearFilter = computed(() => ["week", "month", "year"].includes(String(timePreset.value || "")));
const showMonthFilter = computed(() => String(timePreset.value || "") === "month");
const showWeekFilter = computed(() => String(timePreset.value || "") === "week");
const detailModalTitle = computed(() => {
  if (!candidateDetail.value) {
    return "Detail kandidata";
  }
  return candidateDisplayTitle(candidateDetail.value) || "Detail kandidata";
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
    return "display:inline-flex; align-items:center; padding:2px 7px; border-radius:999px; border:1px solid rgba(22,163,74,.25); background:rgba(22,163,74,.05); font-size:12px;";
  }
  if (normalized === "Zlyhalo") {
    return "display:inline-flex; align-items:center; padding:2px 7px; border-radius:999px; border:1px solid rgba(239,68,68,.25); background:rgba(239,68,68,.05); font-size:12px;";
  }
  return "display:inline-flex; align-items:center; padding:2px 7px; border-radius:999px; border:1px solid rgba(245,158,11,.25); background:rgba(245,158,11,.05); font-size:12px;";
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
  return d.toLocaleString("sk-SK", {
    dateStyle: "medium",
    timeStyle: "short",
    timeZone: preferredTimezone.value,
  });
}

function isPendingTranslation(candidate) {
  const statusValue = String(candidate?.translation_status || "").trim().toLowerCase();
  return statusValue === "pending";
}

function candidatePreviewShort(candidate) {
  if (!isPendingTranslation(candidate)) {
    return candidateDisplayShort(candidate);
  }

  const translated = String(candidate?.translated_description || candidate?.translated_title || "").trim();
  if (translated !== "") {
    return translated;
  }

  return "Preklad prebieha...";
}

function formatAstronomyTime(value) {
  if (typeof value !== "string" || value.trim() === "") return "-";
  const parsed = new Date(value);
  if (Number.isNaN(parsed.getTime())) return "-";

  return new Intl.DateTimeFormat("sk-SK", {
    hour: "2-digit",
    minute: "2-digit",
    hour12: false,
    timeZone: preferredTimezone.value,
  }).format(parsed);
}

function moonPhaseLabel(value) {
  const normalized = String(value || "").trim().toLowerCase();
  if (normalized === "new_moon") return "Nov";
  if (normalized === "waxing_crescent") return "Dorastajuci kosacik";
  if (normalized === "first_quarter") return "Prva stvrt";
  if (normalized === "waxing_gibbous") return "Dorastajuci Mesiac";
  if (normalized === "full_moon") return "Spln";
  if (normalized === "waning_gibbous") return "Ubudajuci Mesiac";
  if (normalized === "last_quarter") return "Posledna stvrt";
  if (normalized === "waning_crescent") return "Ubudajuci kosacik";
  return "-";
}

async function loadAstronomyContext() {
  astronomyContextLoading.value = true;
  try {
    const params = { tz: preferredTimezone.value };
    const coordinates = preferredCoordinates.value;
    if (coordinates) {
      params.lat = coordinates.lat;
      params.lon = coordinates.lon;
    }

    const response = await api.get("/sky/astronomy", {
      params,
      meta: { skipErrorToast: true },
    });
    astronomyContext.value = response?.data || null;
  } catch {
    astronomyContext.value = null;
  } finally {
    astronomyContextLoading.value = false;
  }
}

function formatConfidence(value) {
  if (value === null || value === undefined || value === "") return "-";
  const numeric = Number(value);
  if (Number.isNaN(numeric)) return "-";
  return numeric.toFixed(2);
}

function clampInteger(value, min, max, fallback) {
  const numeric = Number(value);
  if (!Number.isFinite(numeric)) return fallback;
  return Math.min(max, Math.max(min, Math.trunc(numeric)));
}

function toIsoDate(value) {
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return "";
  const year = String(date.getFullYear());
  const month = String(date.getMonth() + 1).padStart(2, "0");
  const day = String(date.getDate()).padStart(2, "0");
  return `${year}-${month}-${day}`;
}

function startOfDay(value) {
  const date = new Date(value);
  date.setHours(0, 0, 0, 0);
  return date;
}

function endOfDay(value) {
  const date = new Date(value);
  date.setHours(23, 59, 59, 999);
  return date;
}

function getIsoWeek(value) {
  const date = new Date(value);
  date.setHours(0, 0, 0, 0);
  date.setDate(date.getDate() + 3 - ((date.getDay() + 6) % 7));
  const weekOne = new Date(date.getFullYear(), 0, 4);
  weekOne.setHours(0, 0, 0, 0);
  const diffDays = (date.getTime() - weekOne.getTime()) / 86400000;
  return 1 + Math.round((diffDays - 3 + ((weekOne.getDay() + 6) % 7)) / 7);
}

function resolveIsoWeekRange(year, week) {
  const safeYear = clampInteger(year, 2000, 2100, new Date().getFullYear());
  const safeWeek = clampInteger(week, 1, 53, getIsoWeek(new Date()));

  const jan4 = new Date(safeYear, 0, 4);
  const jan4Day = jan4.getDay() || 7;
  const weekOneMonday = new Date(safeYear, 0, 4 - jan4Day + 1);
  const weekStart = startOfDay(new Date(weekOneMonday));
  weekStart.setDate(weekOneMonday.getDate() + (safeWeek - 1) * 7);

  const weekEnd = endOfDay(new Date(weekStart));
  weekEnd.setDate(weekStart.getDate() + 6);

  return {
    from: toIsoDate(weekStart),
    to: toIsoDate(weekEnd),
    week: safeWeek,
    year: safeYear,
  };
}

function resolveTimeFilterParams() {
  const preset = String(timePreset.value || "none");
  const now = new Date();
  const safeYear = clampInteger(filterYear.value, 2000, 2100, now.getFullYear());
  const safeMonth = clampInteger(filterMonth.value, 1, 12, now.getMonth() + 1);
  const safeWeek = clampInteger(filterWeek.value, 1, 53, getIsoWeek(now));

  if (preset === "month") {
    return {
      year: safeYear,
      month: safeMonth,
      week: undefined,
      date_from: undefined,
      date_to: undefined,
    };
  }

  if (preset === "year") {
    return {
      year: safeYear,
      month: undefined,
      week: undefined,
      date_from: toIsoDate(startOfDay(new Date(safeYear, 0, 1))),
      date_to: toIsoDate(endOfDay(new Date(safeYear, 11, 31))),
    };
  }

  if (preset === "week") {
    const range = resolveIsoWeekRange(safeYear, safeWeek);
    return {
      year: range.year,
      month: undefined,
      week: range.week,
      date_from: range.from,
      date_to: range.to,
    };
  }

  if (preset === "next_7_days") {
    const from = startOfDay(now);
    const to = endOfDay(new Date(from));
    to.setDate(to.getDate() + 6);
    return {
      year: undefined,
      month: undefined,
      week: undefined,
      date_from: toIsoDate(from),
      date_to: toIsoDate(to),
    };
  }

  if (preset === "next_30_days") {
    const from = startOfDay(now);
    const to = endOfDay(new Date(from));
    to.setDate(to.getDate() + 29);
    return {
      year: undefined,
      month: undefined,
      week: undefined,
      date_from: toIsoDate(from),
      date_to: toIsoDate(to),
    };
  }

  return {
    year: undefined,
    month: undefined,
    week: undefined,
    date_from: undefined,
    date_to: undefined,
  };
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
  if (key === "nasa_watch_the_skies" || key === "nasa_wts") return "NASA WTS";
  if (key === "nasa") return "NASA";
  return key || "-";
}

function sourceBadgeStyle(source) {
  const key = String(source || "").toLowerCase();
  if (key === "astropixels") {
    return "display:inline-flex; align-items:center; padding:1px 7px; border-radius:999px; border:1px solid rgba(30,64,175,.24); background:rgba(30,64,175,.04); font-size:12px;";
  }
  if (key === "imo") {
    return "display:inline-flex; align-items:center; padding:1px 7px; border-radius:999px; border:1px solid rgba(6,95,70,.24); background:rgba(6,95,70,.04); font-size:12px;";
  }
  if (key === "nasa" || key === "nasa_wts" || key === "nasa_watch_the_skies") {
    return "display:inline-flex; align-items:center; padding:1px 7px; border-radius:999px; border:1px solid rgba(107,33,168,.24); background:rgba(107,33,168,.04); font-size:12px;";
  }
  return "display:inline-flex; align-items:center; padding:1px 7px; border-radius:999px; border:1px solid rgb(var(--color-surface-rgb) / .16); background:transparent; font-size:12px;";
}

function statusBadgeStyle(value) {
  const key = String(value || "").toLowerCase();
  if (key === "approved") {
    return "display:inline-flex; align-items:center; padding:2px 7px; border-radius:999px; border:1px solid rgba(22,163,74,.24); background:rgba(22,163,74,.05); font-size:12px;";
  }
  if (key === "rejected" || key === "duplicate") {
    return "display:inline-flex; align-items:center; padding:2px 7px; border-radius:999px; border:1px solid rgba(239,68,68,.24); background:rgba(239,68,68,.05); font-size:12px;";
  }
  return "display:inline-flex; align-items:center; padding:2px 7px; border-radius:999px; border:1px solid rgba(245,158,11,.24); background:rgba(245,158,11,.05); font-size:12px;";
}

async function openCandidate(id) {
  const candidateId = Number(id);
  if (!Number.isFinite(candidateId) || candidateId <= 0) return;

  candidateDetailOpen.value = true;
  candidateDetailLoading.value = true;
  candidateDetailError.value = "";
  candidateDetail.value = null;

  try {
    candidateDetail.value = await eventCandidates.get(candidateId);
  } catch (fetchError) {
    candidateDetailError.value = fetchError?.response?.data?.message || "Detail kandidata sa nepodarilo nacitat.";
  } finally {
    candidateDetailLoading.value = false;
  }
}

function resetCandidateDetailModal() {
  candidateDetailLoading.value = false;
  candidateDetailError.value = "";
  candidateDetail.value = null;
}

function openCandidateFullDetail() {
  const candidateId = Number(candidateDetail.value?.id);
  if (!Number.isFinite(candidateId) || candidateId <= 0) return;

  candidateDetailOpen.value = false;
  router.push({
    name: "admin.candidate.detail",
    params: { id: String(candidateId) },
  });
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

function stopTranslationRefreshPoll() {
  if (translationRefreshTimerId === null) return;
  window.clearTimeout(translationRefreshTimerId);
  translationRefreshTimerId = null;
}

function scheduleTranslationRefreshPoll() {
  stopTranslationRefreshPoll();

  if (activeTab.value !== "crawled") return;
  if (!hasPendingTranslationsOnPage.value) return;
  if (loading.value) return;

  translationRefreshTimerId = window.setTimeout(async () => {
    translationRefreshTimerId = null;
    if (activeTab.value !== "crawled" || loading.value) {
      scheduleTranslationRefreshPoll();
      return;
    }

    await load();
    scheduleTranslationRefreshPoll();
  }, translationRefreshDelayMs);
}

async function publishCandidateQuick(candidate) {
  if (!candidate?.id || String(candidate?.status || '') !== 'pending') return;

  const ok = await confirm({
    title: 'Publikovať kandidáta',
    message: `Publikovať "${candidateDisplayTitle(candidate)}" do udalosti?`,
    confirmText: 'Publikovať',
    cancelText: 'Zrušiť',
  });
  if (!ok) return;

  loading.value = true;
  error.value = null;
  startPublishProgress("Publikovanie kandidáta...", 1);
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

async function retranslateCandidateQuick(candidate) {
  if (!candidate?.id || loading.value) return;

  loading.value = true;
  error.value = null;
  try {
    await eventCandidates.retranslate(candidate.id);
    toast.success("Retranslate spusteny.");
    await load();
  } catch (e) {
    error.value = e?.response?.data?.message || "Retranslate zlyhal";
    toast.error(error.value);
  } finally {
    loading.value = false;
  }
}

async function publishAllVisiblePending() {
  const ids = visiblePendingCandidateIds.value;
  if (ids.length === 0) {
    toast.warn("Na tejto stránke nie sú žiadni pending kandidáti.");
    return;
  }

  const ok = await confirm({
    title: "Publikovať všetko",
    message: `Naozaj publikovať ${ids.length} pending kandidátov na aktuálnej stránke?`,
    confirmText: "Publikovať",
    cancelText: "Zrušiť",
    variant: "danger",
  });
  if (!ok) return;

  loading.value = true;
  error.value = null;

  let successCount = 0;
  let failCount = 0;
  const total = ids.length;

  try {
    startPublishProgress("Publikujem viditeľných kandidátov...", total);
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
      toast.success(`Publikovaných ${successCount} kandidátov.`);
    } else {
      toast.warn(`Publikované: ${successCount}, zlyhalo: ${failCount}.`);
    }

    await load();
  } catch (e) {
    error.value = e?.response?.data?.message || "Hromadné publikovanie zlyhalo";
    toast.error(error.value);
  } finally {
    finishPublishProgress();
    loading.value = false;
  }
}

async function publishAllByFilter() {
  const ok = await confirm({
    title: "Publikovať všetko podľa filtra",
    message: "Naozaj publikovať všetky pending udalosti podľa aktuálneho filtra? (max 1000)",
    confirmText: "Publikovať",
    cancelText: "Zrušiť",
    variant: "danger",
  });
  if (!ok) return;

  loading.value = true;
  error.value = null;
  startPublishProgress("Publikujem podľa filtra...", 1);

  try {
    const params = buildParams();
    const payload = {
      status: params.status,
      type: params.type,
      source: params.source,
      source_key: params.source_key,
      run_id: params.run_id,
      q: params.q,
      year: params.year,
      month: params.month,
      week: params.week,
      date_from: params.date_from,
      date_to: params.date_to,
      limit: 1000,
    };

    const result = await eventCandidates.approveBatch(payload);
    advancePublishProgress(1, 1);
    if (result.failed > 0) {
      toast.warn(`Publikované: ${result.published}, zlyhalo: ${result.failed}.`);
    } else {
      toast.success(`Publikovaných ${result.published} kandidátov.`);
    }
    await load();
  } catch (e) {
    error.value = e?.response?.data?.message || "Hromadné publikovanie podľa filtra zlyhalo";
    toast.error(error.value);
  } finally {
    finishPublishProgress();
    loading.value = false;
  }
}

function buildManualBatchPayload() {
  const timeFilters = resolveTimeFilterParams();
  return {
    status: manualStatus.value || "draft",
    type: manualType.value || undefined,
    q: manualQ.value?.trim() ? manualQ.value.trim() : undefined,
    year: timeFilters.year,
    month: timeFilters.month,
    limit: 1000,
  };
}

async function publishBySelectedMode() {
  const labels = {
    crawled: "crawlované",
    manual: "manuálne",
    all: "crawlované aj manuálne",
  };

  const ok = await confirm({
    title: "Publikovať podľa režimu",
    message: `Naozaj publikovať ${labels[publishMode.value] || "vybrané"} udalosti podľa filtra? (max 1000 na typ)` ,
    confirmText: "Publikovať",
    cancelText: "Zrušiť",
    variant: "danger",
  });
  if (!ok) return;

  loading.value = true;
  error.value = null;
  const modeSteps = publishMode.value === "all" ? 2 : 1;
  startPublishProgress("Publikujem podľa režimu...", modeSteps);
  let completedSteps = 0;

  try {
    const params = buildParams();
    const crawledPayload = {
      status: params.status,
      type: params.type,
      source: params.source,
      source_key: params.source_key,
      run_id: params.run_id,
      q: params.q,
      year: params.year,
      month: params.month,
      week: params.week,
      date_from: params.date_from,
      date_to: params.date_to,
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
      toast.warn(`Publikované spolu: ${totalPublished}, zlyhalo: ${totalFailed}.`);
    } else {
      toast.success(`Publikovaných spolu: ${totalPublished}.`);
    }

    await Promise.all([load(), loadManual()]);
  } catch (e) {
    error.value = e?.response?.data?.message || "Hromadné publikovanie podľa režimu zlyhalo";
    toast.error(error.value);
  } finally {
    finishPublishProgress();
    loading.value = false;
  }
}

async function retranslateVisiblePending() {
  const ids = visiblePendingCandidateIds.value;
  if (ids.length === 0) {
    toast.warn("Na tejto stránke nie sú žiadni pending kandidáti.");
    return;
  }

  const ok = await confirm({
    title: "Preložiť znova viditeľných",
    message: `Spustiť nový preklad pre ${ids.length} pending kandidátov na aktuálnej stránke?`,
    confirmText: "Spustiť",
    cancelText: "Zrušiť",
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
      toast.success(`Preklad bol spustený pre ${successCount} kandidátov.`);
    } else {
      toast.warn(`Preklad spustený: ${successCount}, zlyhalo: ${failCount}.`);
    }

    await load();
  } catch (e) {
    error.value = e?.response?.data?.message || "Hromadný retranslate zlyhal";
    toast.error(error.value);
  } finally {
    loading.value = false;
  }
}

async function retranslateByFilter() {
  const ok = await confirm({
    title: "Preložiť znova podľa filtra",
    message: "Spustiť nový preklad pre kandidátov podľa aktuálneho filtra? (max 1000)",
    confirmText: "Spustiť",
    cancelText: "Zrušiť",
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
      year: params.year,
      month: params.month,
      week: params.week,
      date_from: params.date_from,
      date_to: params.date_to,
      limit: 1000,
    };

    const result = await eventCandidates.retranslateBatch(payload);
    if (result.failed > 0) {
      toast.warn(`Retranslate podľa filtra: queued ${result.queued}, failed ${result.failed}.`);
    } else {
      toast.success(`Retranslate podľa filtra: queued ${result.queued}.`);
    }
    await load();
  } catch (e) {
    error.value = e?.response?.data?.message || "Hromadný retranslate podľa filtra zlyhal";
    toast.error(error.value);
  } finally {
    loading.value = false;
  }
}

function openCrawlingHub() {
  router.push({ name: 'admin.event-sources' });
}

function resetToFirstPage() {
  page.value = 1;
}

function buildParams() {
  const sourceValue = source.value?.trim() ? source.value.trim() : undefined;
  const timeFilters = resolveTimeFilterParams();

  return {
    status: status.value || undefined,
    type: type.value || undefined,
    source: sourceValue,
    source_key: sourceValue,
    run_id: runFilter.value?.runId ? Number(runFilter.value.runId) : undefined,
    q: q.value?.trim() ? q.value.trim() : undefined,
    year: timeFilters.year,
    month: timeFilters.month,
    week: timeFilters.week,
    date_from: timeFilters.date_from,
    date_to: timeFilters.date_to,
    page: page.value,
    per_page: per_page.value,
  };
}

function buildDuplicateParams() {
  const params = buildParams();
  const limitGroups = Math.max(1, Math.min(50, Number(duplicateGroupLimit.value) || 8));
  const perGroup = Math.max(2, Math.min(10, Number(duplicatePerGroup.value) || 3));

  return {
    status: "pending",
    type: params.type,
    source: params.source,
    source_key: params.source_key,
    run_id: params.run_id,
    q: params.q,
    year: params.year,
    month: params.month,
    week: params.week,
    date_from: params.date_from,
    date_to: params.date_to,
    limit_groups: limitGroups,
    per_group: perGroup,
  };
}

async function loadDuplicatePreview() {
  if (activeTab.value !== "crawled") return;

  if (String(status.value || "").toLowerCase() !== "pending") {
    duplicatePreview.value = null;
    return;
  }

  duplicateLoading.value = true;
  try {
    duplicatePreview.value = await eventCandidates.duplicatesPreview(buildDuplicateParams());
  } catch {
    duplicatePreview.value = null;
  } finally {
    duplicateLoading.value = false;
  }
}

async function mergeDuplicateGroups() {
  if (!canMergeDuplicates.value) return;

  const plannedGroups = Number(duplicateSummary.value?.group_count || 0);
  const plannedCandidates = Number(duplicateSummary.value?.duplicate_candidates || 0);

  const ok = await confirm({
    title: "Zlucit duplicity",
    message: `Oznacit duplicity ako duplicate? Skupiny: ${plannedGroups}, kandidati: ${plannedCandidates}.`,
    confirmText: "Zlucit",
    cancelText: "Zrusit",
    variant: "danger",
  });
  if (!ok) return;

  duplicateMerging.value = true;
  try {
    const params = buildDuplicateParams();
    const result = await eventCandidates.mergeDuplicates({
      ...params,
      limit_groups: params.limit_groups,
      dry_run: false,
    });
    const merged = Number(result?.summary?.merged_candidates || 0);
    toast.success(`Deduplikacia hotova, oznacene duplicate: ${merged}.`);
    await load();
  } catch (e) {
    const message = e?.response?.data?.message || "Deduplikacia zlyhala";
    error.value = message;
    toast.error(message);
  } finally {
    duplicateMerging.value = false;
  }
}

async function dryRunDuplicateMerge() {
  if (!canMergeDuplicates.value) return;

  duplicateDryRunning.value = true;
  try {
    const params = buildDuplicateParams();
    const result = await eventCandidates.mergeDuplicates({
      ...params,
      limit_groups: params.limit_groups,
      dry_run: true,
    });

    const groups = Number(result?.summary?.group_count || 0);
    const merged = Number(result?.summary?.merged_candidates || 0);
    toast.success(`Dry-run: skupiny ${groups}, navrh duplicit ${merged}.`);
    await loadDuplicatePreview();
  } catch (e) {
    const message = e?.response?.data?.message || "Dry-run deduplikacie zlyhal";
    error.value = message;
    toast.error(message);
  } finally {
    duplicateDryRunning.value = false;
  }
}

async function load() {
  loading.value = true;
  error.value = null;

  try {
    data.value = await eventCandidates.list(buildParams());
    await loadDuplicatePreview();
  } catch (e) {
    error.value = e?.response?.data?.message || "Chyba pri načítaní kandidátov";
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
    manualError.value = e?.response?.data?.message || "Chyba pri načítaní draftov";
  } finally {
    manualLoading.value = false;
  }
}

function clearFilters() {
  const now = new Date();
  status.value = "pending";
  type.value = "";
  source.value = "";
  q.value = "";
  timePreset.value = "none";
  filterYear.value = now.getFullYear();
  filterMonth.value = now.getMonth() + 1;
  filterWeek.value = getIsoWeek(now);
  page.value = 1;
  per_page.value = 20;
  showAdvancedFilters.value = false;
  load();
}

function quickSetStatus(nextStatus) {
  if (!nextStatus || status.value === nextStatus) return;
  status.value = nextStatus;
  resetToFirstPage();
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
watch([status, type, per_page, timePreset, filterYear, filterMonth, filterWeek], () => {
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
    manualError.value = manualFormErrors.value[0] || "Skontroluj formulár.";
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
    manualError.value = e?.response?.data?.message || "Uloženie zlyhalo";
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
    cancelText: 'Zrušiť',
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
    title: 'Publikovať draft',
    message: `Publikovať "${row.title}" do events?`,
    confirmText: 'Publikovať',
    cancelText: 'Zrušiť',
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
  if (tab !== "crawled") {
    duplicatePreview.value = null;
  }
  if (tab === "crawled") {
    if (!data.value) {
      load();
    } else {
      loadDuplicatePreview();
    }
  }
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

watch(
  () => [activeTab.value, hasPendingTranslationsOnPage.value, loading.value],
  () => {
    if (activeTab.value !== "crawled" || loading.value || !hasPendingTranslationsOnPage.value) {
      stopTranslationRefreshPoll();
      return;
    }

    scheduleTranslationRefreshPoll();
  }
);

watch(
  () => [preferredTimezone.value, preferredCoordinates.value?.lat, preferredCoordinates.value?.lon],
  () => {
    loadAstronomyContext();
  },
  { immediate: true }
);

onMounted(() => {
  applyRunFilterFromRoute();
  load();
});

onUnmounted(() => {
  stopTranslationRefreshPoll();
});
</script>

<template>
  <div class="candidatesPage">
    <div class="pageHeader">
      <div>
        <h1 class="pageTitle">Kandidáti udalostí</h1>
      </div>

      <div class="headerActions">
        <div class="tabSwitch" role="tablist" aria-label="Typ kandidátov">
          <button
            @click="setTab('crawled')"
            :disabled="loading || manualLoading"
            :class="['toolbarButton', 'toolbarButton--tab', { 'toolbarButton--active': activeTab === 'crawled' }]"
          >
            Crawlovaní kandidáti
          </button>
          <button
            @click="setTab('manual')"
            :disabled="loading || manualLoading"
            :class="['toolbarButton', 'toolbarButton--tab', { 'toolbarButton--active': activeTab === 'manual' }]"
          >
            Manuálne návrhy
          </button>
        </div>
        <button
          @click="openCrawlingHub"
          :disabled="loading || manualLoading"
          class="toolbarButton toolbarButton--ghost"
        >
          Centrum crawlovania
        </button>
      </div>
    </div>

    <div v-if="activeTab === 'crawled'">
      <div class="uxOverview">
        <div class="uxOverview__item">
          <span>Na stránke</span>
          <strong>{{ crawledStats.total }}</strong>
        </div>
        <div class="uxOverview__item">
          <span>Pending</span>
          <strong>{{ crawledStats.pending }}</strong>
        </div>
        <div class="uxOverview__item">
          <span>Schvalene</span>
          <strong>{{ crawledStats.approved }}</strong>
        </div>
        <div class="uxOverview__item">
          <span>Preklad fail</span>
          <strong>{{ crawledStats.failedTranslation }}</strong>
        </div>
      </div>

      <div class="contextToggleRow">
        <button
          type="button"
          class="toolbarButton toolbarButton--ghost"
          :disabled="loading"
          @click="showObservationContext = !showObservationContext"
        >
          {{ showObservationContext ? 'Skryt podmienky pozorovania' : 'Zobrazit podmienky pozorovania' }}
        </button>
      </div>

      <div v-if="showObservationContext" class="timeContextBar">
        <div class="timeContextBar__item">
          <span>Casove pasmo</span>
          <strong>{{ timezoneInfoLabel }}</strong>
        </div>
        <div class="timeContextBar__item">
          <span>Zapad slnka</span>
          <strong>
            {{ astronomyContextAvailable ? formatAstronomyTime(astronomyContext?.sunset_at) : "-" }}
          </strong>
        </div>
        <div class="timeContextBar__item">
          <span>Koniec obcianskeho sumraku</span>
          <strong>
            {{ astronomyContextAvailable ? formatAstronomyTime(astronomyContext?.civil_twilight_end_at) : "-" }}
          </strong>
        </div>
        <div class="timeContextBar__item">
          <span>Faza Mesiaca</span>
          <strong>
            {{ astronomyContextAvailable ? moonPhaseLabel(astronomyContext?.moon_phase) : "-" }}
          </strong>
        </div>
        <div v-if="astronomyContextLoading" class="timeContextBar__loading">
          Nacitavam astronomicky kontext...
        </div>
      </div>

      <div v-if="runFilter" class="runFilterBar">
        <span class="runFilterChip">
          Run #{{ runFilter.runId }} / {{ sourceLabel(runFilter.sourceKey || '-') }}<span v-if="runFilter.year"> / {{ runFilter.year }}</span>
        </span>
        <button
          type="button"
          @click="clearRunFilter"
          class="toolbarButton toolbarButton--ghost"
        >
          Zrusit run filter
        </button>
      </div>

      <div class="statusPills">
        <button
          type="button"
          @click="quickSetStatus('pending')"
          :class="['statusPill', { 'statusPill--active': status === 'pending' }]"
          :disabled="loading"
        >
          Pending ({{ crawledStats.pending }})
        </button>
        <button
          type="button"
          @click="quickSetStatus('approved')"
          :class="['statusPill', { 'statusPill--active': status === 'approved' }]"
          :disabled="loading"
        >
          Schvalene ({{ crawledStats.approved }})
        </button>
        <button
          type="button"
          @click="quickSetStatus('rejected')"
          :class="['statusPill', { 'statusPill--active': status === 'rejected' }]"
          :disabled="loading"
        >
          Zamietnute ({{ crawledStats.rejected }})
        </button>
      </div>

      <div class="uxActionBar">
        <div class="uxActionBar__group">
          <select v-model="publishMode" :disabled="loading" class="toolbarSelect">
            <option value="crawled">Publikovat: Crawlovane</option>
            <option value="manual">Publikovat: Manualne</option>
            <option value="all">Publikovat: Oboje</option>
          </select>
          <button @click="publishBySelectedMode" :disabled="loading" class="toolbarButton toolbarButton--success">
            Publikovat mode
          </button>
          <button @click="publishAllByFilter" :disabled="loading" class="toolbarButton toolbarButton--success">
            Publikovat filter
          </button>
          <button @click="publishAllVisiblePending" :disabled="loading || visiblePendingCandidateIds.length === 0" class="toolbarButton toolbarButton--success">
            Publikovat viditelne
          </button>
        </div>
        <div class="uxActionBar__group">
          <button @click="retranslateByFilter" :disabled="loading" class="toolbarButton toolbarButton--primary">
            Retr. filter
          </button>
          <button @click="retranslateVisiblePending" :disabled="loading || visiblePendingCandidateIds.length === 0" class="toolbarButton toolbarButton--primary">
            Retr. viditelne
          </button>
          <button @click="clearFilters" :disabled="loading" class="toolbarButton toolbarButton--ghost">
            Reset
          </button>
        </div>
      </div>

      <div class="duplicatesPanel">
        <div class="duplicatesPanel__head">
          <strong>Deduplikacia pending kandidatom</strong>
          <div class="duplicatesPanel__actions">
            <label>
              Skupiny
              <input
                v-model.number="duplicateGroupLimit"
                type="number"
                min="1"
                max="50"
                :disabled="loading || duplicateLoading || duplicateMerging || duplicateDryRunning"
                class="filterInput"
              />
            </label>
            <label>
              Kandidati/skup.
              <input
                v-model.number="duplicatePerGroup"
                type="number"
                min="2"
                max="10"
                :disabled="loading || duplicateLoading || duplicateMerging || duplicateDryRunning"
                class="filterInput"
              />
            </label>
            <button
              @click="loadDuplicatePreview"
              :disabled="loading || duplicateLoading || duplicateMerging || duplicateDryRunning"
              class="toolbarButton toolbarButton--ghost"
            >
              {{ duplicateLoading ? 'Kontrolujem...' : 'Obnovit' }}
            </button>
            <button
              @click="dryRunDuplicateMerge"
              :disabled="!canMergeDuplicates || loading || duplicateLoading"
              class="toolbarButton toolbarButton--primary"
            >
              {{ duplicateDryRunning ? 'Dry-run...' : 'Dry-run merge' }}
            </button>
            <button
              @click="mergeDuplicateGroups"
              :disabled="!canMergeDuplicates || loading || duplicateLoading"
              class="toolbarButton toolbarButton--success"
            >
              {{ duplicateMerging ? 'Zlucujem...' : 'Zlucit duplicity' }}
            </button>
          </div>
        </div>

        <div class="duplicatesPanel__summary">
          <span>Skupiny: {{ duplicateSummary.group_count }}</span>
          <span>Duplicity: {{ duplicateSummary.duplicate_candidates }}</span>
        </div>

        <div v-if="duplicateGroups.length > 0" class="duplicatesPanel__groups">
          <article v-for="group in duplicateGroups" :key="group.canonical_key" class="dupGroup">
            <div class="dupGroup__key">{{ group.canonical_key }}</div>
            <div class="dupGroup__row">
              keep #{{ group.keeper.id }} {{ group.keeper.title }}
              <span class="cellMuted">({{ sourceLabel(group.keeper.source_name) }}, {{ formatDate(group.keeper.start_at) }})</span>
            </div>
            <div
              v-for="duplicate in group.duplicates"
              :key="`dup-${group.canonical_key}-${duplicate.id}`"
              class="dupGroup__row cellMuted"
            >
              dup #{{ duplicate.id }} {{ duplicate.title }}
              <span>({{ sourceLabel(duplicate.source_name) }}, {{ formatDate(duplicate.start_at) }})</span>
            </div>
            <div v-if="group.hidden_duplicates > 0" class="dupGroup__row cellMuted">
              +{{ group.hidden_duplicates }} dalsich duplicit
            </div>
          </article>
        </div>
        <div v-else class="duplicatesPanel__empty">
          {{ duplicateLoading ? 'Kontrolujem duplicity...' : 'Pre aktualny filter zatial nie su najdene zlucitelne duplicity.' }}
        </div>
      </div>

      <div v-if="publishProgressActive" class="publishProgress">
        <div class="publishProgress__meta">
          <span>{{ publishProgressLabel || 'Publikujem...' }}</span>
          <strong>{{ publishProgressPercent }}%</strong>
        </div>
        <div class="publishProgress__track">
          <div class="publishProgress__bar" :style="{ width: `${publishProgressPercent}%` }"></div>
        </div>
      </div>

      <div class="filterPanel">
        <div class="filterGrid">
          <div class="filterField filterField--wide">
            <label>Hladaj</label>
            <input
              v-model="q"
              :disabled="loading"
              placeholder="title / short / description"
              @keyup.enter="resetToFirstPage(); load()"
              class="filterInput"
            />
          </div>

          <div class="filterField">
            <label>Na stranku</label>
            <select v-model.number="per_page" :disabled="loading" class="filterInput">
              <option :value="10">10</option>
              <option :value="20">20</option>
              <option :value="50">50</option>
              <option :value="100">100</option>
            </select>
          </div>

          <div class="filterField">
            <label>Stav</label>
            <select v-model="status" :disabled="loading" class="filterInput">
              <option value="pending">Pending</option>
              <option value="approved">Schvalene</option>
              <option value="rejected">Zamietnute</option>
            </select>
          </div>

          <div class="filterField">
            <label>Obdobie</label>
            <select v-model="timePreset" :disabled="loading" class="filterInput">
              <option v-for="option in timePresetOptions" :key="option.value" :value="option.value">
                {{ option.label }}
              </option>
            </select>
          </div>

          <div v-if="showYearFilter" class="filterField">
            <label>Rok</label>
            <input
              v-model.number="filterYear"
              type="number"
              min="2000"
              max="2100"
              :disabled="loading"
              class="filterInput"
            />
          </div>

          <div v-if="showMonthFilter" class="filterField">
            <label>Mesiac</label>
            <select v-model.number="filterMonth" :disabled="loading" class="filterInput">
              <option v-for="option in monthOptions" :key="`month-${option.value}`" :value="option.value">
                {{ option.label }}
              </option>
            </select>
          </div>

          <div v-if="showWeekFilter" class="filterField">
            <label>Tyzden</label>
            <input
              v-model.number="filterWeek"
              type="number"
              min="1"
              max="53"
              :disabled="loading"
              class="filterInput"
            />
          </div>

          <div class="filterActions">
            <button @click="resetToFirstPage(); load()" :disabled="loading" class="toolbarButton toolbarButton--primary">
              Hladat
            </button>
            <button @click="showAdvancedFilters = !showAdvancedFilters" :disabled="loading" class="toolbarButton toolbarButton--ghost">
              {{ showAdvancedFilters ? 'Skryt pokrocile' : 'Pokrocile filtre' }}
            </button>
          </div>

          <template v-if="showAdvancedFilters">
            <div class="filterField">
              <label>Typ</label>
              <select v-model="type" :disabled="loading" class="filterInput">
                <option value="">vsetky</option>
                <option value="eclipse_lunar">eclipse_lunar</option>
                <option value="eclipse_solar">eclipse_solar</option>
                <option value="meteor_shower">meteor_shower</option>
                <option value="planetary_event">planetary_event</option>
                <option value="observation_window">observation_window</option>
                <option value="other">other</option>
              </select>
            </div>

            <div class="filterField">
              <label>Zdroj</label>
              <input
                v-model="source"
                :disabled="loading"
                placeholder="astropixels / imo / nasa / nasa_wts"
                @keyup.enter="resetToFirstPage(); load()"
                class="filterInput"
              />
            </div>
          </template>
        </div>
      </div>

      <div v-if="error" class="inlineError">{{ error }}</div>
      <div v-if="loading" class="inlineLoading">Nacitavam...</div>

      <div v-if="data && !loading" class="candidatesTableWrap">
        <table class="candidatesTable">
          <thead>
            <tr>
              <th>ID</th>
              <th>Nazov</th>
              <th>Zdroj / typ</th>
              <th>Zaciatok / stav</th>
              <th v-if="showConfidenceColumn">Skore zdrojov</th>
              <th>Preklad</th>
              <th>Akcie</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="c in data.data" :key="c.id" class="candidatesRow">
              <td class="cellMono">{{ c.id }}</td>
              <td>
                <div class="candidateTitle">{{ candidateDisplayTitle(c) }}</div>
                <div v-if="candidatePreviewShort(c) && candidatePreviewShort(c) !== '-'" class="candidateShort">
                  {{ candidatePreviewShort(c) }}
                </div>
              </td>
              <td>
                <div class="cellStack">
                  <span :style="sourceBadgeStyle(c.source_name)">{{ sourceLabel(c.source_name) }}</span>
                  <span class="typeTag">{{ c.type || '-' }}</span>
                </div>
                <div class="matchedSources">
                  <span v-for="src in normalizeSources(c.matched_sources)" :key="`matched-${c.id}-${src}`" class="matchedSourceTag">
                    {{ sourceLabel(src) }}
                  </span>
                  <span v-if="normalizeSources(c.matched_sources).length === 0" class="cellMuted">-</span>
                </div>
              </td>
              <td>
                <div class="cellStack">
                  <span>{{ formatDate(c.start_at) }}</span>
                  <span :style="statusBadgeStyle(c.status)">{{ c.status }}</span>
                </div>
              </td>
              <td v-if="showConfidenceColumn" class="cellMono">{{ formatConfidence(c.confidence_score) }}</td>
              <td>
                <span :style="translationStatusStyle(c.translation_status)">{{ normalizeTranslationStatus(c.translation_status) }}</span>
              </td>
              <td>
                <div class="rowActions">
                  <button
                    v-if="c.status === 'pending'"
                    @click="publishCandidateQuick(c)"
                    :disabled="loading"
                    class="rowActionButton rowActionButton--success"
                  >
                    Publikovat
                  </button>
                  <button @click="retranslateCandidateQuick(c)" :disabled="loading" class="rowActionButton rowActionButton--primary">
                    Retr.
                  </button>
                  <button @click="openCandidate(c.id)" class="rowActionButton rowActionButton--ghost">
                    Detail
                  </button>
                </div>
              </td>
            </tr>

            <tr v-if="data.data.length === 0">
              <td :colspan="showConfidenceColumn ? 7 : 6" class="tableEmpty">Ziadne kandidaty pre aktualny filter.</td>
            </tr>
          </tbody>
        </table>
      </div>

      <div v-if="data && !loading" class="candidatesMobileList">
        <article v-for="c in data.data" :key="`mobile-${c.id}`" class="candidateMobileCard">
          <div class="candidateMobileCard__head">
            <span class="candidateMobileCard__id">#{{ c.id }}</span>
            <span :style="translationStatusStyle(c.translation_status)">
              {{ normalizeTranslationStatus(c.translation_status) }}
            </span>
          </div>
          <div class="candidateMobileCard__title">{{ candidateDisplayTitle(c) }}</div>
          <div v-if="candidatePreviewShort(c) && candidatePreviewShort(c) !== '-'" class="candidateShort">
            {{ candidatePreviewShort(c) }}
          </div>
          <div class="candidateMobileCard__meta">
            <span :style="sourceBadgeStyle(c.source_name)">{{ sourceLabel(c.source_name) }}</span>
            <span class="typeTag">{{ c.type || '-' }}</span>
            <span class="cellMuted">{{ formatDate(c.start_at) }}</span>
            <span v-if="showConfidenceColumn" class="cellMono">Skore {{ formatConfidence(c.confidence_score) }}</span>
          </div>
          <div class="matchedSources">
            <span v-for="src in normalizeSources(c.matched_sources)" :key="`matched-mobile-${c.id}-${src}`" class="matchedSourceTag">
              {{ sourceLabel(src) }}
            </span>
            <span v-if="normalizeSources(c.matched_sources).length === 0" class="cellMuted">-</span>
          </div>
          <div class="rowActions">
            <button
              v-if="c.status === 'pending'"
              @click="publishCandidateQuick(c)"
              :disabled="loading"
              class="rowActionButton rowActionButton--success"
            >
              Publikovat
            </button>
            <button @click="retranslateCandidateQuick(c)" :disabled="loading" class="rowActionButton rowActionButton--primary">
              Retr.
            </button>
            <button @click="openCandidate(c.id)" class="rowActionButton rowActionButton--ghost">
              Detail
            </button>
          </div>
        </article>
        <div v-if="data.data.length === 0" class="tableEmpty">
          Ziadne kandidaty pre aktualny filter.
        </div>
      </div>

      <div v-if="data" class="pagerRow">
        <div class="pagerMeta">
          Strana {{ data.current_page }} / {{ data.last_page }} (spolu {{ data.total }})
        </div>

        <div class="pagerActions">
          <button
            @click="prevPage"
            :disabled="loading || page <= 1"
            class="toolbarButton toolbarButton--ghost"
          >
            Pred
          </button>
          <button
            @click="nextPage"
            :disabled="loading || (data && page >= data.last_page)"
            class="toolbarButton toolbarButton--ghost"
          >
            Dalsia
          </button>
        </div>
      </div>

      <BaseModal
        v-model:open="candidateDetailOpen"
        :title="detailModalTitle"
        test-id="candidate-detail-modal"
        close-test-id="candidate-detail-modal-close"
        @close="resetCandidateDetailModal"
      >
        <div v-if="candidateDetailLoading" class="candidateDetailModalState">Nacitavam...</div>
        <div v-else-if="candidateDetailError" class="candidateDetailModalState candidateDetailModalState--error">
          {{ candidateDetailError }}
        </div>
        <div v-else-if="candidateDetail" class="candidateDetailModal">
          <div class="candidateDetailModalGrid">
            <div class="candidateDetailModalItem">
              <span>ID</span>
              <strong>#{{ candidateDetail.id }}</strong>
            </div>
            <div class="candidateDetailModalItem">
              <span>Stav</span>
              <strong>{{ candidateDetail.status || "-" }}</strong>
            </div>
            <div class="candidateDetailModalItem">
              <span>Zaciatok</span>
              <strong>{{ formatDate(candidateDetail.start_at) }}</strong>
            </div>
            <div class="candidateDetailModalItem">
              <span>Max</span>
              <strong>{{ formatDate(candidateDetail.max_at) }}</strong>
            </div>
            <div class="candidateDetailModalItem">
              <span>Zdroj</span>
              <strong>{{ sourceLabel(candidateDetail.source_name) }}</strong>
            </div>
            <div class="candidateDetailModalItem">
              <span>Typ</span>
              <strong>{{ candidateDetail.type || "-" }}</strong>
            </div>
            <div v-if="showConfidenceColumn" class="candidateDetailModalItem">
              <span>Skore zdrojov</span>
              <strong>{{ formatConfidence(candidateDetail.confidence_score) }}</strong>
            </div>
            <div class="candidateDetailModalItem">
              <span>Preklad</span>
              <strong>{{ normalizeTranslationStatus(candidateDetail.translation_status) }}</strong>
            </div>
          </div>

          <p v-if="candidatePreviewShort(candidateDetail) && candidatePreviewShort(candidateDetail) !== '-'" class="candidateDetailModalText">
            {{ candidatePreviewShort(candidateDetail) }}
          </p>

          <div class="candidateDetailModalSources">
            <span
              v-for="src in normalizeSources(candidateDetail.matched_sources)"
              :key="`detail-source-${candidateDetail.id}-${src}`"
              class="matchedSourceTag"
            >
              {{ sourceLabel(src) }}
            </span>
            <span v-if="normalizeSources(candidateDetail.matched_sources).length === 0" class="cellMuted">
              Bez matched source.
            </span>
          </div>

          <div class="candidateDetailModalActions">
            <button type="button" class="toolbarButton toolbarButton--ghost" @click="openCandidateFullDetail">
              Plny detail
            </button>
          </div>
        </div>
      </BaseModal>
    </div>
    <div v-else>
      <div class="uxOverview uxOverview--manual">
        <div class="uxOverview__item">
          <span>Na stranke</span>
          <strong>{{ manualStats.total }}</strong>
        </div>
        <div class="uxOverview__item">
          <span>Draft</span>
          <strong>{{ manualStats.draft }}</strong>
        </div>
        <div class="uxOverview__item">
          <span>Publikované</span>
          <strong>{{ manualStats.published }}</strong>
        </div>
      </div>

      <div class="manualToolbar">
        <button
          @click="openManualFormCreate"
          :disabled="manualLoading"
          class="toolbarButton toolbarButton--primary"
        >
          Vytvoriť manuálnu udalosť
        </button>
        <button
          @click="clearManualFilters"
          :disabled="manualLoading"
          class="toolbarButton toolbarButton--ghost"
        >
          Resetovať filtre
        </button>
      </div>

      <div class="filterPanel manualFilterPanel">
        <div class="filterGrid">
          <div class="filterField">
            <label>Stav</label>
            <select v-model="manualStatus" :disabled="manualLoading" class="filterInput">
              <option value="draft">Návrh</option>
              <option value="published">Publikované</option>
            </select>
          </div>

          <div class="filterField">
            <label>Typ</label>
            <select v-model="manualType" :disabled="manualLoading" class="filterInput">
              <option value="">všetky</option>
              <option value="eclipse_lunar">eclipse_lunar</option>
              <option value="eclipse_solar">eclipse_solar</option>
              <option value="meteor_shower">meteor_shower</option>
              <option value="planetary_event">planetary_event</option>
              <option value="other">other</option>
            </select>
          </div>

          <div class="filterField">
            <label>Na stránku</label>
            <select v-model.number="manualPerPage" :disabled="manualLoading" class="filterInput">
              <option :value="10">10</option>
              <option :value="20">20</option>
              <option :value="50">50</option>
              <option :value="100">100</option>
            </select>
          </div>

          <div class="filterField filterField--wide">
            <label>Hľadaj</label>
            <input
              v-model="manualQ"
              :disabled="manualLoading"
              placeholder="hľadaj v názve"
              @keyup.enter="resetManualToFirstPage(); loadManual()"
              class="filterInput"
            />
          </div>

          <div class="filterActions">
            <button
              @click="resetManualToFirstPage(); loadManual()"
              :disabled="manualLoading"
              class="toolbarButton toolbarButton--primary"
            >
              Hľadať
            </button>
          </div>
        </div>
      </div>

      <section v-if="showManualForm" class="manualFormPanel">
        <div class="manualFormHeader">
          <div>
            <div class="manualFormTitle">{{ manualEditingId ? 'Upraviť manuálny návrh' : 'Nový manuálny návrh' }}</div>
            <div class="manualFormHint">Vyplň názov, typ a termín. Zvyšok môžeš doplniť neskôr.</div>
          </div>
          <div class="manualQuickActions">
            <button
              @click="setManualStartNow"
              :disabled="manualLoading"
              class="quickChip quickChip--primary"
            >
              Začiatok teraz
            </button>
            <button
              @click="setManualEndByHours(1)"
              :disabled="manualLoading"
              class="quickChip"
            >
              Koniec +1 h
            </button>
            <button
              @click="setManualEndByHours(2)"
              :disabled="manualLoading"
              class="quickChip"
            >
              Koniec +2 h
            </button>
          </div>
        </div>

        <div class="manualFormGrid">
          <div class="manualField manualField--wide">
            <label>Názov udalosti *</label>
            <input
              v-model="manualForm.title"
              type="text"
              placeholder="napr. Pozorovanie Mesiaca na hvezdárni"
              :disabled="manualLoading"
              class="filterInput manualInput"
            />
          </div>

          <div class="manualField">
            <label>Typ *</label>
            <select
              v-model="manualForm.event_type"
              :disabled="manualLoading"
              class="filterInput manualInput"
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

          <div class="manualField">
            <label>Začiatok *</label>
            <input
              v-model="manualForm.starts_at"
              type="datetime-local"
              :disabled="manualLoading"
              class="filterInput manualInput"
            />
          </div>

          <div class="manualField">
            <label>Koniec (voliteľne)</label>
            <input
              v-model="manualForm.ends_at"
              type="datetime-local"
              :disabled="manualLoading"
              class="filterInput manualInput"
            />
          </div>

          <div class="manualField manualField--full">
            <label>Popis</label>
            <textarea
              v-model="manualForm.description"
              rows="4"
              placeholder="Krátky popis, čo sa deje a kedy je najlepšie pozorovanie."
              :disabled="manualLoading"
              class="filterInput manualTextarea"
            ></textarea>
          </div>
        </div>

        <div v-if="manualFormErrors.length > 0" class="manualFormError">
          {{ manualFormErrors[0] }}
        </div>

        <div class="manualFormActions">
          <button
            @click="closeManualForm"
            :disabled="manualLoading"
            class="toolbarButton toolbarButton--ghost"
          >
            Zrušiť
          </button>
          <button
            @click="saveManual"
            :disabled="manualLoading || !manualCanSave"
            class="toolbarButton toolbarButton--primary"
          >
            {{ manualLoading ? 'Ukladá sa...' : 'Uložiť návrh' }}
          </button>
        </div>
      </section>

      <div v-if="manualError" class="inlineError">
        {{ manualError }}
      </div>

      <div v-if="manualLoading" class="inlineLoading">
        Načítavam...
      </div>

      <div v-if="manualData && !manualLoading" class="manualTableWrap">
        <table class="manualTable">
          <thead>
            <tr>
              <th>Názov</th>
              <th>Typ</th>
              <th>Začiatok</th>
              <th>Stav</th>
              <th>Akcie</th>
            </tr>
          </thead>

          <tbody>
            <tr
              v-for="row in manualData.data"
              :key="row.id"
            >
              <td>{{ row.title }}</td>
              <td><span class="typeTag">{{ row.event_type }}</span></td>
              <td class="cellMono">{{ formatDate(row.starts_at) }}</td>
              <td>
                <span
                  :class="['manualStatusTag', { 'manualStatusTag--published': String(row.status || '').toLowerCase() === 'published' }]"
                >
                  {{ row.status }}
                </span>
              </td>
              <td>
                <div class="rowActions rowActions--right">
                  <button
                    @click="openManualFormEdit(row)"
                    :disabled="manualLoading"
                    class="rowActionButton"
                  >
                    Upraviť
                  </button>
                  <button
                    @click="deleteManual(row)"
                    :disabled="manualLoading"
                    class="rowActionButton"
                  >
                    Zmazať
                  </button>
                  <button
                    @click="publishManual(row)"
                    :disabled="manualLoading || row.status === 'published'"
                    class="rowActionButton rowActionButton--primary"
                  >
                    Publikovať
                  </button>
                </div>
              </td>
            </tr>

            <tr v-if="manualData.data.length === 0">
              <td colspan="5" class="tableEmpty">
                Žiadne drafty.
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <div v-if="manualData && !manualLoading" class="manualMobileList">
        <article v-for="row in manualData.data" :key="`manual-mobile-${row.id}`" class="manualMobileCard">
          <div class="manualMobileCard__head">
            <span class="cellMono">#{{ row.id }}</span>
            <span
              :class="['manualStatusTag', { 'manualStatusTag--published': String(row.status || '').toLowerCase() === 'published' }]"
            >
              {{ row.status }}
            </span>
          </div>
          <div class="manualMobileCard__title">{{ row.title }}</div>
          <div class="manualMobileCard__meta">
            <span class="typeTag">{{ row.event_type }}</span>
            <span class="cellMono">{{ formatDate(row.starts_at) }}</span>
          </div>
          <div class="rowActions">
            <button
              @click="openManualFormEdit(row)"
              :disabled="manualLoading"
              class="rowActionButton"
            >
              Upraviť
            </button>
            <button
              @click="deleteManual(row)"
              :disabled="manualLoading"
              class="rowActionButton"
            >
              Zmazať
            </button>
            <button
              @click="publishManual(row)"
              :disabled="manualLoading || row.status === 'published'"
              class="rowActionButton rowActionButton--primary"
            >
              Publikovať
            </button>
          </div>
        </article>
        <div v-if="manualData.data.length === 0" class="tableEmpty">
          Žiadne drafty.
        </div>
      </div>

      <div v-if="manualData" class="pagerRow">
        <div class="pagerMeta">
          Strana {{ manualData.current_page }} / {{ manualData.last_page }} (spolu {{ manualData.total }})
        </div>

        <div class="pagerActions">
          <button
            @click="prevManualPage"
            :disabled="manualLoading || manualPage <= 1"
            class="toolbarButton toolbarButton--ghost"
          >
            Pred
          </button>
          <button
            @click="nextManualPage"
            :disabled="manualLoading || (manualData && manualPage >= manualData.last_page)"
            class="toolbarButton toolbarButton--ghost"
          >
            Ďalšia
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.candidatesPage {
  max-width: 980px;
  margin: 0 auto;
  padding: 10px 8px;
}

.pageHeader {
  display: flex;
  align-items: flex-end;
  justify-content: space-between;
  gap: 10px;
  flex-wrap: wrap;
}

.pageTitle {
  margin: 0 0 4px;
  font-size: clamp(20px, 2.4vw, 25px);
  line-height: 1.12;
}

.headerActions {
  display: flex;
  gap: 6px;
  flex-wrap: wrap;
  margin-left: auto;
}

.tabSwitch {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  padding: 2px;
  border: 1px solid rgb(var(--color-surface-rgb) / 0.16);
  border-radius: 10px;
  background: rgb(var(--color-bg-rgb) / 0.94);
}

.uxOverview {
  margin-top: 10px;
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 6px;
}

.uxOverview__item {
  border: 1px solid rgb(var(--color-surface-rgb) / 0.1);
  border-radius: 10px;
  background: rgb(var(--color-bg-rgb) / 0.95);
  padding: 5px 8px;
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

.timeContextBar {
  margin-top: 8px;
  border: 1px solid rgb(var(--color-surface-rgb) / 0.1);
  border-radius: 10px;
  background: rgb(var(--color-bg-rgb) / 0.96);
  padding: 7px;
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 6px;
}

.timeContextBar__item {
  border: 1px solid rgb(var(--color-surface-rgb) / 0.08);
  border-radius: 8px;
  padding: 5px 7px;
  display: grid;
  gap: 2px;
}

.timeContextBar__item span {
  font-size: 11px;
  opacity: 0.8;
}

.timeContextBar__item strong {
  font-size: 13px;
}

.timeContextBar__loading {
  grid-column: 1 / -1;
  font-size: 12px;
  opacity: 0.78;
}

.contextToggleRow {
  margin-top: 8px;
}

.runFilterBar {
  margin-top: 10px;
  display: flex;
  align-items: center;
  gap: 8px;
  flex-wrap: wrap;
}

.runFilterChip {
  display: inline-flex;
  align-items: center;
  padding: 4px 10px;
  border-radius: 999px;
  border: 1px solid rgb(var(--color-primary-rgb) / 0.35);
  background: rgb(var(--color-primary-rgb) / 0.12);
  font-size: 12px;
}

.statusPills {
  margin-top: 10px;
  display: flex;
  gap: 6px;
  flex-wrap: wrap;
}

.statusPill {
  padding: 5px 10px;
  border-radius: 999px;
  border: 1px solid rgb(var(--color-surface-rgb) / 0.2);
  background: transparent;
  color: inherit;
  font-size: 12px;
  cursor: pointer;
}

.statusPill--active {
  border-color: rgb(var(--color-primary-rgb) / 0.35);
  background: rgb(var(--color-primary-rgb) / 0.12);
}

.uxActionBar {
  margin-top: 10px;
  display: flex;
  justify-content: space-between;
  gap: 6px;
  flex-wrap: wrap;
  position: static;
  padding: 6px;
  border: 1px solid rgb(var(--color-surface-rgb) / 0.1);
  border-radius: 10px;
  background: rgb(var(--color-bg-rgb) / 0.96);
}

.uxActionBar__group {
  display: flex;
  gap: 6px;
  flex-wrap: wrap;
  align-items: center;
}

.duplicatesPanel {
  margin-top: 8px;
  border: 1px solid rgb(var(--color-surface-rgb) / 0.1);
  border-radius: 10px;
  background: rgb(var(--color-bg-rgb) / 0.96);
  padding: 8px;
  display: grid;
  gap: 8px;
}

.duplicatesPanel__head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
  flex-wrap: wrap;
}

.duplicatesPanel__actions {
  display: flex;
  align-items: end;
  gap: 6px;
  flex-wrap: wrap;
}

.duplicatesPanel__actions label {
  display: grid;
  gap: 4px;
  font-size: 12px;
  opacity: 0.86;
}

.duplicatesPanel__actions .filterInput {
  width: 90px;
}

.duplicatesPanel__summary {
  display: flex;
  gap: 12px;
  font-size: 12px;
  opacity: 0.86;
}

.duplicatesPanel__groups {
  display: grid;
  gap: 6px;
}

.dupGroup {
  border: 1px solid rgb(var(--color-surface-rgb) / 0.08);
  border-radius: 8px;
  padding: 6px 8px;
  display: grid;
  gap: 4px;
}

.dupGroup__key {
  font-size: 11px;
  opacity: 0.72;
  overflow-wrap: anywhere;
}

.dupGroup__row {
  font-size: 12px;
}

.duplicatesPanel__empty {
  font-size: 12px;
  opacity: 0.75;
}

.toolbarSelect,
.toolbarButton,
.filterInput {
  border: 1px solid rgb(var(--color-surface-rgb) / 0.2);
  border-radius: 8px;
  background: transparent;
  color: inherit;
  font-size: 12px;
}

.toolbarSelect {
  padding: 8px 10px;
}

.toolbarButton {
  padding: 8px 10px;
  cursor: pointer;
}

.toolbarButton--success {
  border-color: rgb(var(--color-success-rgb) / 0.35);
  background: rgb(var(--color-success-rgb) / 0.1);
}

.toolbarButton--primary {
  border-color: rgb(var(--color-primary-rgb) / 0.35);
  background: rgb(var(--color-primary-rgb) / 0.12);
}

.toolbarButton--ghost {
  background: rgb(var(--color-surface-rgb) / 0.08);
}

.toolbarButton--active {
  border-color: rgb(var(--color-surface-rgb) / 0.4);
  background: rgb(var(--color-surface-rgb) / 0.12);
}

.toolbarButton--tab {
  border-color: transparent;
  background: transparent;
}

.filterPanel {
  margin-top: 10px;
  border: 1px solid rgb(var(--color-surface-rgb) / 0.1);
  border-radius: 12px;
  padding: 8px;
  background: rgb(var(--color-bg-rgb) / 0.96);
}

.filterGrid {
  display: grid;
  grid-template-columns: repeat(12, minmax(0, 1fr));
  gap: 8px;
}

.filterField {
  grid-column: span 3;
  display: grid;
  gap: 4px;
}

.filterField--wide {
  grid-column: span 6;
}

.filterField label {
  font-size: 12px;
  opacity: 0.8;
}

.filterInput {
  width: 100%;
  padding: 8px;
}

.filterActions {
  grid-column: span 3;
  display: flex;
  align-items: end;
  gap: 6px;
  justify-content: flex-end;
}

.uxOverview--manual {
  grid-template-columns: repeat(3, minmax(0, 1fr));
}

.manualToolbar {
  margin-top: 10px;
  display: flex;
  justify-content: space-between;
  gap: 6px;
  flex-wrap: wrap;
}

.manualFilterPanel {
  margin-top: 8px;
}

.manualFormPanel {
  margin-top: 10px;
  border: 1px solid rgb(var(--color-surface-rgb) / 0.1);
  border-radius: 12px;
  padding: 10px;
  background: rgb(var(--color-bg-rgb) / 0.96);
  display: grid;
  gap: 10px;
}

.manualFormHeader {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 8px;
  flex-wrap: wrap;
}

.manualFormTitle {
  font-weight: 700;
}

.manualFormHint {
  margin-top: 2px;
  font-size: 12px;
  opacity: 0.78;
}

.manualQuickActions {
  display: flex;
  gap: 6px;
  flex-wrap: wrap;
}

.quickChip {
  padding: 6px 10px;
  border-radius: 999px;
  border: 1px solid rgb(var(--color-surface-rgb) / 0.2);
  background: transparent;
  color: inherit;
  font-size: 12px;
  cursor: pointer;
}

.quickChip--primary {
  border-color: rgb(var(--color-primary-rgb) / 0.35);
  background: rgb(var(--color-primary-rgb) / 0.1);
}

.manualFormGrid {
  display: grid;
  grid-template-columns: repeat(12, minmax(0, 1fr));
  gap: 8px;
}

.manualField {
  grid-column: span 6;
  display: grid;
  gap: 4px;
}

.manualField--wide {
  grid-column: span 8;
}

.manualField--full {
  grid-column: span 12;
}

.manualField label {
  font-size: 12px;
  opacity: 0.82;
}

.manualInput {
  padding: 10px;
}

.manualTextarea {
  min-height: 96px;
  resize: vertical;
  padding: 10px;
}

.manualFormError {
  padding: 8px 10px;
  border: 1px solid rgb(239 68 68 / 0.35);
  border-radius: 10px;
  background: rgb(239 68 68 / 0.08);
  font-size: 12px;
}

.manualFormActions {
  display: flex;
  justify-content: flex-end;
  gap: 8px;
}

.publishProgress {
  margin-top: 10px;
  border: 1px solid rgb(var(--color-surface-rgb) / 0.1);
  border-radius: 10px;
  padding: 8px 10px;
  background: rgb(var(--color-bg-rgb) / 0.96);
}

.publishProgress__meta {
  display: flex;
  justify-content: space-between;
  gap: 10px;
  font-size: 12px;
  margin-bottom: 6px;
}

.publishProgress__track {
  height: 6px;
  border-radius: 999px;
  background: rgb(var(--color-surface-rgb) / 0.12);
  overflow: hidden;
}

.publishProgress__bar {
  height: 100%;
  background: linear-gradient(90deg, rgb(var(--color-primary-rgb) / 0.95), rgb(var(--color-success-rgb) / 0.95));
  transition: width 0.25s ease;
}

.candidatesTableWrap {
  margin-top: 12px;
  border: 1px solid rgb(var(--color-surface-rgb) / 0.1);
  border-radius: 12px;
  overflow: auto;
  background: rgb(var(--color-bg-rgb) / 0.98);
}

.candidatesMobileList {
  margin-top: 10px;
  display: none;
  gap: 8px;
}

.candidateMobileCard {
  border: 1px solid rgb(var(--color-surface-rgb) / 0.1);
  border-radius: 10px;
  background: rgb(var(--color-bg-rgb) / 0.96);
  padding: 9px;
  display: grid;
  gap: 7px;
}

.candidateMobileCard__head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
}

.candidateMobileCard__id {
  font-size: 12px;
  opacity: 0.8;
}

.candidateMobileCard__title {
  font-weight: 700;
  line-height: 1.25;
}

.candidateMobileCard__meta {
  display: flex;
  align-items: center;
  gap: 6px;
  flex-wrap: wrap;
}

.candidatesTable {
  width: 100%;
  min-width: 860px;
  border-collapse: collapse;
}

.candidatesTable thead {
  background: rgb(var(--color-surface-rgb) / 0.05);
}

.candidatesTable th {
  text-align: left;
  padding: 7px 6px;
  font-size: 12px;
  opacity: 0.85;
  background: rgb(var(--color-surface-rgb) / 0.04);
}

.candidatesTable td {
  padding: 7px 6px;
  border-top: 1px solid rgb(var(--color-surface-rgb) / 0.06);
  vertical-align: top;
}

.candidatesRow:hover {
  background: rgb(var(--color-surface-rgb) / 0.02);
}

.manualTableWrap {
  margin-top: 12px;
  border: 1px solid rgb(var(--color-surface-rgb) / 0.1);
  border-radius: 12px;
  overflow: auto;
  background: rgb(var(--color-bg-rgb) / 0.98);
}

.manualTable {
  width: 100%;
  min-width: 760px;
  border-collapse: collapse;
}

.manualTable thead {
  background: rgb(var(--color-surface-rgb) / 0.05);
}

.manualTable th {
  text-align: left;
  padding: 7px 6px;
  font-size: 12px;
  opacity: 0.85;
  background: rgb(var(--color-surface-rgb) / 0.04);
}

.manualTable td {
  padding: 7px 6px;
  border-top: 1px solid rgb(var(--color-surface-rgb) / 0.06);
  vertical-align: top;
}

.manualStatusTag {
  display: inline-flex;
  align-items: center;
  padding: 2px 8px;
  border-radius: 999px;
  border: 1px solid rgb(var(--color-surface-rgb) / 0.2);
  background: rgb(var(--color-surface-rgb) / 0.08);
  font-size: 12px;
}

.manualStatusTag--published {
  border-color: rgb(var(--color-success-rgb) / 0.28);
  background: rgb(var(--color-success-rgb) / 0.08);
}

.manualMobileList {
  margin-top: 10px;
  display: none;
  gap: 8px;
}

.manualMobileCard {
  border: 1px solid rgb(var(--color-surface-rgb) / 0.1);
  border-radius: 10px;
  background: rgb(var(--color-bg-rgb) / 0.96);
  padding: 9px;
  display: grid;
  gap: 7px;
}

.manualMobileCard__head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
}

.manualMobileCard__title {
  font-weight: 700;
  line-height: 1.25;
}

.manualMobileCard__meta {
  display: flex;
  align-items: center;
  gap: 6px;
  flex-wrap: wrap;
}

.candidateTitle {
  font-weight: 600;
}

.candidateShort {
  opacity: 0.75;
  font-size: 12px;
  margin-top: 4px;
  line-height: 1.3;
}

.cellMono {
  white-space: nowrap;
  font-variant-numeric: tabular-nums;
}

.cellStack {
  display: grid;
  gap: 4px;
}

.typeTag {
  display: inline-flex;
  align-items: center;
  padding: 2px 7px;
  border-radius: 999px;
  border: 1px solid rgb(var(--color-surface-rgb) / 0.2);
  background: rgb(var(--color-surface-rgb) / 0.08);
  font-size: 11px;
  width: fit-content;
}

.matchedSources {
  margin-top: 6px;
  display: flex;
  gap: 4px;
  flex-wrap: wrap;
}

.matchedSourceTag {
  display: inline-flex;
  align-items: center;
  padding: 1px 7px;
  border-radius: 999px;
  border: 1px solid rgb(var(--color-surface-rgb) / 0.14);
  background: transparent;
  font-size: 11px;
}

.cellMuted {
  opacity: 0.7;
}

.rowActions {
  display: flex;
  gap: 6px;
  flex-wrap: wrap;
}

.rowActions--right {
  justify-content: flex-end;
}

.rowActionButton {
  padding: 5px 8px;
  border-radius: 8px;
  border: 1px solid rgb(var(--color-surface-rgb) / 0.16);
  background: transparent;
  color: inherit;
  font-size: 12px;
  cursor: pointer;
}

.rowActionButton--success {
  border-color: rgb(var(--color-success-rgb) / 0.24);
  background: rgb(var(--color-success-rgb) / 0.05);
}

.rowActionButton--primary {
  border-color: rgb(var(--color-primary-rgb) / 0.24);
  background: rgb(var(--color-primary-rgb) / 0.06);
}

.rowActionButton--ghost {
  background: rgb(var(--color-surface-rgb) / 0.08);
}

.candidateDetailModalState {
  font-size: 13px;
  opacity: 0.85;
}

.candidateDetailModalState--error {
  color: var(--color-danger);
  opacity: 1;
}

.candidateDetailModal {
  display: grid;
  gap: 10px;
}

.candidateDetailModalGrid {
  display: grid;
  gap: 8px;
  grid-template-columns: repeat(2, minmax(0, 1fr));
}

.candidateDetailModalItem {
  display: grid;
  gap: 2px;
  padding: 8px;
  border: 1px solid rgb(var(--color-surface-rgb) / 0.12);
  border-radius: 10px;
  background: rgb(var(--color-bg-rgb) / 0.94);
}

.candidateDetailModalItem span {
  font-size: 11px;
  opacity: 0.76;
}

.candidateDetailModalItem strong {
  font-size: 13px;
  line-height: 1.35;
}

.candidateDetailModalText {
  margin: 0;
  font-size: 13px;
  line-height: 1.5;
}

.candidateDetailModalSources {
  display: flex;
  align-items: center;
  gap: 6px;
  flex-wrap: wrap;
}

.candidateDetailModalActions {
  display: flex;
  justify-content: flex-end;
  gap: 8px;
}

.tableEmpty {
  padding: 20px 10px;
  opacity: 0.75;
}

.inlineError {
  margin-top: 12px;
  color: var(--color-danger);
  font-size: 13px;
}

.inlineLoading {
  margin-top: 12px;
  opacity: 0.85;
  font-size: 13px;
}

.pagerRow {
  margin-top: 14px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  flex-wrap: wrap;
}

.pagerMeta {
  opacity: 0.85;
  font-size: 14px;
}

.pagerActions {
  display: flex;
  gap: 10px;
}

@media (max-width: 900px) {
  .candidatesPage {
    padding: 8px 6px;
  }

  .pageHeader {
    align-items: stretch;
  }

  .headerActions {
    width: 100%;
    gap: 8px;
  }

  .tabSwitch {
    width: 100%;
  }

  .tabSwitch .toolbarButton {
    flex: 1 1 auto;
    text-align: center;
  }

  .headerActions > .toolbarButton {
    flex: 1 1 auto;
    text-align: center;
  }

  .uxOverview {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }

  .timeContextBar {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }

  .uxActionBar {
    position: static;
  }

  .uxActionBar__group,
  .duplicatesPanel__actions {
    width: 100%;
  }

  .uxActionBar__group .toolbarButton,
  .duplicatesPanel__actions .toolbarButton {
    flex: 1 1 auto;
  }

  .duplicatesPanel__actions label {
    width: calc(50% - 3px);
  }

  .duplicatesPanel__actions .filterInput {
    width: 100%;
  }

  .filterField,
  .filterField--wide,
  .filterActions {
    grid-column: span 12;
  }

  .filterActions {
    justify-content: flex-start;
  }

  .candidatesTableWrap {
    display: none;
  }

  .candidatesMobileList {
    display: grid;
  }

  .manualToolbar .toolbarButton {
    flex: 1 1 auto;
  }

  .manualField,
  .manualField--wide,
  .manualField--full {
    grid-column: span 12;
  }

  .manualQuickActions {
    width: 100%;
  }

  .quickChip {
    flex: 1 1 auto;
    text-align: center;
  }

  .manualFormActions {
    width: 100%;
  }

  .manualFormActions .toolbarButton {
    flex: 1 1 auto;
  }

  .manualTableWrap {
    display: none;
  }

  .manualMobileList {
    display: grid;
  }

  .candidateDetailModalGrid {
    grid-template-columns: 1fr;
  }

  .pagerRow {
    gap: 8px;
  }

  .pagerMeta {
    width: 100%;
    font-size: 13px;
  }

  .pagerActions {
    width: 100%;
  }

  .pagerActions .toolbarButton {
    flex: 1 1 auto;
  }
}
</style>
