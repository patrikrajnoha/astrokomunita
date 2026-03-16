export function normalizeTranslationStatus(value) {
  const statusValue = String(value || "").trim().toLowerCase();
  if (statusValue === "done" || statusValue === "translated") return "Prelozene";
  if (statusValue === "failed" || statusValue === "error") return "Zlyhalo";
  return "Caka";
}

export function translationStatusStyle(value) {
  const normalized = normalizeTranslationStatus(value);
  if (normalized === "Prelozene") {
    return "display:inline-flex; align-items:center; padding:2px 7px; border-radius:999px; border:1px solid rgba(22,163,74,.25); background:rgba(22,163,74,.05); font-size:12px;";
  }
  if (normalized === "Zlyhalo") {
    return "display:inline-flex; align-items:center; padding:2px 7px; border-radius:999px; border:1px solid rgba(239,68,68,.25); background:rgba(239,68,68,.05); font-size:12px;";
  }
  return "display:inline-flex; align-items:center; padding:2px 7px; border-radius:999px; border:1px solid rgba(245,158,11,.25); background:rgba(245,158,11,.05); font-size:12px;";
}

export function formatDate(value, timeZone) {
  if (!value) return "-";
  const d = new Date(value);
  if (isNaN(d.getTime())) return String(value);

  try {
    return d.toLocaleString("sk-SK", {
      dateStyle: "medium",
      timeStyle: "short",
      ...(timeZone ? { timeZone } : {}),
    });
  } catch {
    return d.toLocaleString("sk-SK", {
      dateStyle: "medium",
      timeStyle: "short",
    });
  }
}

export function isPendingTranslation(candidate) {
  const statusValue = String(candidate?.translation_status || "").trim().toLowerCase();
  return statusValue === "pending";
}

export function candidatePreviewShort(candidate, fallbackFormatter) {
  if (!isPendingTranslation(candidate)) {
    return typeof fallbackFormatter === "function" ? fallbackFormatter(candidate) : "-";
  }

  const translated = String(candidate?.translated_description || candidate?.translated_title || "").trim();
  if (translated !== "") {
    return translated;
  }

  return "Preklad prebieha...";
}

export function formatAstronomyTime(value, timeZone) {
  if (typeof value !== "string" || value.trim() === "") return "-";
  const parsed = new Date(value);
  if (Number.isNaN(parsed.getTime())) return "-";

  try {
    return new Intl.DateTimeFormat("sk-SK", {
      hour: "2-digit",
      minute: "2-digit",
      hour12: false,
      ...(timeZone ? { timeZone } : {}),
    }).format(parsed);
  } catch {
    return new Intl.DateTimeFormat("sk-SK", {
      hour: "2-digit",
      minute: "2-digit",
      hour12: false,
    }).format(parsed);
  }
}

export function moonPhaseLabel(value) {
  const normalized = String(value || "").trim().toLowerCase();
  if (normalized === "new_moon") return "Nov";
  if (normalized === "waxing_crescent") return "Dorastajúci kosáčik";
  if (normalized === "first_quarter") return "Prvá štvrt";
  if (normalized === "waxing_gibbous") return "Dorastajúci Mesiac";
  if (normalized === "full_moon") return "Spln";
  if (normalized === "waning_gibbous") return "Ubúdajúci Mesiac";
  if (normalized === "last_quarter") return "Posledná štvrt";
  if (normalized === "waning_crescent") return "Ubúdajúci kosáčik";
  return "-";
}

export function formatConfidence(value) {
  if (value === null || value === undefined || value === "") return "-";
  const numeric = Number(value);
  if (Number.isNaN(numeric)) return "-";
  return numeric.toFixed(2);
}

export function clampInteger(value, min, max, fallback) {
  const numeric = Number(value);
  if (!Number.isFinite(numeric)) return fallback;
  return Math.min(max, Math.max(min, Math.trunc(numeric)));
}

export function toIsoDate(value) {
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return "";
  const year = String(date.getFullYear());
  const month = String(date.getMonth() + 1).padStart(2, "0");
  const day = String(date.getDate()).padStart(2, "0");
  return `${year}-${month}-${day}`;
}

export function startOfDay(value) {
  const date = new Date(value);
  date.setHours(0, 0, 0, 0);
  return date;
}

export function endOfDay(value) {
  const date = new Date(value);
  date.setHours(23, 59, 59, 999);
  return date;
}

export function getIsoWeek(value) {
  const date = new Date(value);
  date.setHours(0, 0, 0, 0);
  date.setDate(date.getDate() + 3 - ((date.getDay() + 6) % 7));
  const weekOne = new Date(date.getFullYear(), 0, 4);
  weekOne.setHours(0, 0, 0, 0);
  const diffDays = (date.getTime() - weekOne.getTime()) / 86400000;
  return 1 + Math.round((diffDays - 3 + ((weekOne.getDay() + 6) % 7)) / 7);
}

export function resolveIsoWeekRange(year, week) {
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

export function resolveTimeFilterParams({ preset, filterYear, filterMonth, filterWeek, now = new Date() }) {
  const safePreset = String(preset || "none");
  const safeYear = clampInteger(filterYear, 2000, 2100, now.getFullYear());
  const safeMonth = clampInteger(filterMonth, 1, 12, now.getMonth() + 1);
  const safeWeek = clampInteger(filterWeek, 1, 53, getIsoWeek(now));

  if (safePreset === "month") {
    return {
      year: safeYear,
      month: safeMonth,
      week: undefined,
      date_from: undefined,
      date_to: undefined,
    };
  }

  if (safePreset === "year") {
    return {
      year: safeYear,
      month: undefined,
      week: undefined,
      date_from: toIsoDate(startOfDay(new Date(safeYear, 0, 1))),
      date_to: toIsoDate(endOfDay(new Date(safeYear, 11, 31))),
    };
  }

  if (safePreset === "week") {
    const range = resolveIsoWeekRange(safeYear, safeWeek);
    return {
      year: range.year,
      month: undefined,
      week: range.week,
      date_from: range.from,
      date_to: range.to,
    };
  }

  if (safePreset === "next_7_days") {
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

  if (safePreset === "next_30_days") {
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

export function normalizeSources(values) {
  if (!Array.isArray(values)) return [];
  return values
    .map((item) => String(item || "").trim().toLowerCase())
    .filter((item) => item.length > 0);
}

export function sourceLabel(source) {
  const key = String(source || "").toLowerCase();
  if (key === "astropixels") return "AstroPixels";
  if (key === "imo") return "IMO";
  if (key === "nasa_watch_the_skies" || key === "nasa_wts") return "NASA WTS";
  if (key === "nasa") return "NASA";
  return key || "-";
}

export function sourceBadgeStyle(source) {
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

export function statusBadgeStyle(value) {
  const key = String(value || "").toLowerCase();
  if (key === "approved") {
    return "display:inline-flex; align-items:center; padding:2px 7px; border-radius:999px; border:1px solid rgba(22,163,74,.24); background:rgba(22,163,74,.05); font-size:12px;";
  }
  if (key === "rejected" || key === "duplicate") {
    return "display:inline-flex; align-items:center; padding:2px 7px; border-radius:999px; border:1px solid rgba(239,68,68,.24); background:rgba(239,68,68,.05); font-size:12px;";
  }
  return "display:inline-flex; align-items:center; padding:2px 7px; border-radius:999px; border:1px solid rgba(245,158,11,.24); background:rgba(245,158,11,.05); font-size:12px;";
}

export function toLocalInput(value) {
  if (!value) return "";
  const d = new Date(value);
  if (isNaN(d.getTime())) return "";
  const pad = (n) => String(n).padStart(2, "0");
  return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`;
}

export function nowLocalInput() {
  return toLocalInput(new Date().toISOString());
}

export function addHoursToLocalInput(value, hours) {
  const base = value ? new Date(value) : new Date();
  if (Number.isNaN(base.getTime())) return "";
  base.setHours(base.getHours() + hours);
  return toLocalInput(base.toISOString());
}
