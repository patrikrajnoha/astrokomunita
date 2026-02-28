from __future__ import annotations

import json
import logging
import os
import re
import time
from datetime import date as date_cls
from datetime import datetime, time as time_cls, timedelta, timezone
from pathlib import Path
from urllib import request as urllib_request
from zoneinfo import ZoneInfo

import numpy as np
from fastapi import Depends, FastAPI, Header, HTTPException, Query
from pydantic import BaseModel, Field
from skyfield import almanac
from skyfield.api import EarthSatellite, Loader, wgs84

try:
    from argostranslate import translate as argos_translate
except Exception as exc:  # pragma: no cover
    argos_translate = None
    ARGOS_IMPORT_ERROR = str(exc)
else:
    ARGOS_IMPORT_ERROR = None

APP_ROOT = Path(__file__).resolve().parent.parent
DATA_DIR = APP_ROOT / "data"
DATA_DIR.mkdir(parents=True, exist_ok=True)
ISS_TLE_CACHE_PATH = DATA_DIR / "iss_tle.json"
ISS_TLE_URL = os.getenv("ISS_TLE_URL", "https://celestrak.org/NORAD/elements/stations.txt")
ISS_TLE_REFRESH_HOURS = max(1, int(os.getenv("ISS_TLE_REFRESH_HOURS", "12")))

loader = Loader(str(DATA_DIR))
ts = loader.timescale()
eph = loader("de421.bsp")

EARTH = eph["earth"]
SUN = eph["sun"]
MOON = eph["moon"]

PLANETS = [
    ("mercury", "Mercury", eph["mercury"]),
    ("venus", "Venus", eph["venus"]),
    ("mars", "Mars", eph["mars"]),
    ("jupiter", "Jupiter", eph["jupiter barycenter"]),
    ("saturn", "Saturn", eph["saturn barycenter"]),
]

DIRECTIONS = ["N", "NE", "E", "SE", "S", "SW", "W", "NW"]

SERVICE_VERSION = "1.1.0"
INTERNAL_TOKEN = os.getenv("INTERNAL_TOKEN", "")
MAX_TRANSLATE_CHARS = int(os.getenv("TRANSLATION_CHUNK_MAX_CHARS", "4000"))

ASTRONOMY_TERMS = {
    "meteor shower": "meteorick\u00FD roj",
    "lunar eclipse": "zatmenie Mesiaca",
    "solar eclipse": "zatmenie Slnka",
    "International Space Station": "Medzin\u00E1rodn\u00E1 vesm\u00EDrna stanica",
    "Milky Way": "Mlie\u010Dna cesta",
    "black hole": "\u010Dierna diera",
    "supernova": "supernova",
    "exoplanet": "exoplan\u00E9ta",
    "deep space": "hlbok\u00FD vesm\u00EDr",
    "space telescope": "vesm\u00EDrny teleskop",
    "nebula": "hmlovina",
    "rocket launch": "\u0161tart rakety",
}

app = FastAPI(title="Sky Summary Service", version=SERVICE_VERSION)
logger = logging.getLogger("uvicorn.error")

translation_state: dict[str, object] = {
    "error": None,
    "installed_languages": [],
    "has_en": False,
    "has_sk": False,
    "has_en_sk_pair": False,
    "translator": None,
}
iss_state: dict[str, object] = {
    "satellite": None,
    "source": None,
    "fetched_at": None,
    "error": None,
}


class TranslateRequest(BaseModel):
    text: str = ""
    from_lang: str = Field(default="en", alias="from")
    to_lang: str = Field(default="sk", alias="to")
    domain: str | None = "astronomy"

    model_config = {
        "populate_by_name": True,
    }


def ensure_internal_token(x_internal_token: str | None = Header(default=None, alias="X-Internal-Token")) -> None:
    if not INTERNAL_TOKEN:
        raise HTTPException(status_code=500, detail="INTERNAL_TOKEN is not configured.")

    if not x_internal_token or x_internal_token != INTERNAL_TOKEN:
        raise HTTPException(status_code=401, detail="Unauthorized internal token.")


@app.on_event("startup")
def startup_check() -> None:
    refresh_translation_state()
    refresh_iss_tle_cache()


def refresh_translation_state() -> None:
    state = {
        "error": None,
        "installed_languages": [],
        "has_en": False,
        "has_sk": False,
        "has_en_sk_pair": False,
        "translator": None,
    }

    if ARGOS_IMPORT_ERROR:
        state["error"] = f"argostranslate import failed: {ARGOS_IMPORT_ERROR}"
        translation_state.update(state)
        return

    if argos_translate is None:
        state["error"] = "argostranslate is unavailable."
        translation_state.update(state)
        return

    try:
        installed = argos_translate.get_installed_languages()
    except Exception as exc:  # pragma: no cover
        state["error"] = f"failed_to_list_languages:{exc}"
        translation_state.update(state)
        return

    codes = sorted({lang.code for lang in installed if getattr(lang, "code", None)})
    state["installed_languages"] = codes
    state["has_en"] = "en" in codes
    state["has_sk"] = "sk" in codes

    if not (state["has_en"] and state["has_sk"]):
        state["error"] = "Missing installed language packages for en and/or sk."
        translation_state.update(state)
        return

    from_lang = next((lang for lang in installed if lang.code == "en"), None)
    to_lang = next((lang for lang in installed if lang.code == "sk"), None)

    if from_lang is None or to_lang is None:
        state["error"] = "Missing en or sk language object."
        translation_state.update(state)
        return

    try:
        translator = from_lang.get_translation(to_lang)
    except Exception as exc:
        state["error"] = f"Missing en->sk translation model: {exc}"
        translation_state.update(state)
        return

    state["has_en_sk_pair"] = True
    state["translator"] = translator
    translation_state.update(state)


@app.get("/health")
def health() -> dict[str, object]:
    return {
        "ok": bool(translation_state.get("has_en_sk_pair")),
        "version": SERVICE_VERSION,
        "iss_tle_ready": iss_state.get("satellite") is not None,
    }


@app.get("/diagnostics")
def diagnostics(_: None = Depends(ensure_internal_token)) -> dict[str, object]:
    refresh_translation_state()
    return {
        "version": SERVICE_VERSION,
        "engine": "argos",
        "has_en": bool(translation_state.get("has_en")),
        "has_sk": bool(translation_state.get("has_sk")),
        "has_en_sk_pair": bool(translation_state.get("has_en_sk_pair")),
        "installed_languages": translation_state.get("installed_languages", []),
        "error": translation_state.get("error"),
    }


@app.post("/translate")
def translate(payload: TranslateRequest, _: None = Depends(ensure_internal_token)) -> dict[str, object]:
    text = payload.text or ""
    if text.strip() == "":
        return {
            "translated": text,
            "meta": {
                "engine": "argos",
                "from": payload.from_lang,
                "to": payload.to_lang,
                "took_ms": 0,
            },
        }

    started = time.perf_counter()

    if payload.from_lang != "en" or payload.to_lang != "sk":
        raise HTTPException(status_code=422, detail="Only en->sk translation is supported.")

    if not bool(translation_state.get("has_en_sk_pair")):
        refresh_translation_state()

    translator = translation_state.get("translator")
    if translator is None:
        raise HTTPException(
            status_code=503,
            detail=str(translation_state.get("error") or "en->sk translation model is unavailable."),
        )

    chunks = split_text_preserving_format(text, MAX_TRANSLATE_CHARS)
    translated_chunks: list[str] = []

    for chunk in chunks:
        if chunk == "" or chunk.isspace():
            translated_chunks.append(chunk)
            continue
        translated_chunks.append(translator.translate(chunk))

    translated = "".join(translated_chunks)

    if (payload.domain or "").strip().lower() == "astronomy":
        translated = apply_astronomy_terminology(translated)

    took_ms = int((time.perf_counter() - started) * 1000)

    return {
        "translated": translated,
        "meta": {
            "engine": "argos",
            "from": payload.from_lang,
            "to": payload.to_lang,
            "took_ms": took_ms,
        },
    }


def split_text_preserving_format(text: str, max_chars: int) -> list[str]:
    if len(text) <= max_chars:
        return [text]

    parts = re.split(r"(\n+)", text)
    chunks: list[str] = []

    for part in parts:
        if part == "":
            continue
        if part.startswith("\n"):
            chunks.append(part)
            continue

        chunks.extend(split_long_segment(part, max_chars))

    return chunks


def split_long_segment(segment: str, max_chars: int) -> list[str]:
    if len(segment) <= max_chars:
        return [segment]

    units = re.split(r"(?<=[.!?])(\s+)", segment)
    chunks: list[str] = []
    current = ""

    for unit in units:
        if unit == "":
            continue

        if len(unit) > max_chars:
            if current:
                chunks.append(current)
                current = ""
            chunks.extend([unit[i : i + max_chars] for i in range(0, len(unit), max_chars)])
            continue

        if len(current) + len(unit) <= max_chars:
            current += unit
            continue

        if current:
            chunks.append(current)
        current = unit

    if current:
        chunks.append(current)

    return chunks


def apply_astronomy_terminology(text: str) -> str:
    translated = text
    for source, target in ASTRONOMY_TERMS.items():
        translated = re.sub(re.escape(source), target, translated, flags=re.IGNORECASE)
    return translated


@app.get("/sky-summary")
def sky_summary(
    lat: float = Query(..., ge=-90.0, le=90.0),
    lon: float = Query(..., ge=-180.0, le=180.0),
    tz: str = Query(..., min_length=1),
    date: str = Query(..., pattern=r"^\d{4}-\d{2}-\d{2}$"),
) -> dict:
    try:
        local_tz = ZoneInfo(tz)
    except Exception as exc:  # pragma: no cover
        raise HTTPException(status_code=422, detail=f"Invalid timezone: {tz}") from exc

    try:
        local_date = date_cls.fromisoformat(date)
    except ValueError as exc:
        raise HTTPException(status_code=422, detail="Invalid date format. Expected YYYY-MM-DD") from exc

    location = wgs84.latlon(latitude_degrees=lat, longitude_degrees=lon)
    observer = EARTH + location
    sample_local = datetime.now(local_tz)
    sample_t = ts.from_datetime(sample_local.astimezone(timezone.utc))
    sample_sun_alt, _, _ = observer.at(sample_t).observe(SUN).apparent().altaz()

    moon_payload = build_moon_payload(observer=observer, location=location, local_date=local_date, local_tz=local_tz)
    planets_payload = build_planets_payload(observer=observer, local_date=local_date, local_tz=local_tz)

    return {
        "moon": moon_payload,
        "sample_at": isoformat_with_timezone(sample_local),
        "sun_altitude_deg": round(float(sample_sun_alt.degrees), 1),
        "planets": planets_payload,
    }


@app.get("/iss-preview")
def iss_preview(
    lat: float = Query(..., ge=-90.0, le=90.0),
    lon: float = Query(..., ge=-180.0, le=180.0),
    tz: str = Query(..., min_length=1),
) -> dict[str, object]:
    try:
        local_tz = ZoneInfo(tz)
    except Exception as exc:  # pragma: no cover
        raise HTTPException(status_code=422, detail=f"Invalid timezone: {tz}") from exc

    satellite = ensure_iss_satellite()
    if satellite is None:
        logger.warning("ISS preview unavailable: no TLE data ready.", extra={"iss_error": iss_state.get("error")})
        return {"available": False}

    local_now = datetime.now(local_tz)
    local_start = datetime.combine(local_now.date(), time_cls(0, 0), tzinfo=local_tz)
    local_end = local_start + timedelta(days=1)

    location = wgs84.latlon(latitude_degrees=lat, longitude_degrees=lon)
    observer = EARTH + location

    t0 = ts.from_datetime(local_start.astimezone(timezone.utc))
    t1 = ts.from_datetime(local_end.astimezone(timezone.utc))

    try:
        events_t, events = satellite.find_events(location, t0, t1, altitude_degrees=10.0)
    except Exception as exc:  # pragma: no cover
        logger.exception("ISS preview event calculation failed.", extra={"lat": lat, "lon": lon, "tz": tz})
        iss_state["error"] = f"event_calc_failed:{exc}"
        return {"available": False}

    passes = build_iss_passes(
        satellite=satellite,
        observer=observer,
        location=location,
        event_times=events_t,
        event_codes=events,
        local_tz=local_tz,
    )

    next_visible = next((item for item in passes if item["is_visible"] and item["set_at"] >= local_now), None)
    if next_visible is None:
        return {"available": False}

    duration_sec = max(0, int(round((next_visible["set_at"] - next_visible["rise_at"]).total_seconds())))

    return {
        "available": True,
        "next_pass_at": next_visible["rise_at"].isoformat(),
        "duration_sec": duration_sec,
        "duration": duration_sec,
        "max_altitude_deg": round(float(next_visible["max_altitude_deg"]), 1),
        "max_altitude": round(float(next_visible["max_altitude_deg"]), 1),
        "direction_start": next_visible["direction_start"],
        "direction_end": next_visible["direction_end"],
    }


def build_moon_payload(observer, location, local_date: date_cls, local_tz: ZoneInfo) -> dict:
    local_noon = datetime.combine(local_date, time_cls(12, 0), tzinfo=local_tz)
    noon_utc = local_noon.astimezone(timezone.utc)
    t_noon = ts.from_datetime(noon_utc)

    phase_deg = float(almanac.moon_phase(eph, t_noon).degrees % 360.0)
    illumination = float(almanac.fraction_illuminated(eph, "moon", t_noon) * 100.0)

    rise_local, set_local = moon_rise_set_times(location=location, local_date=local_date, local_tz=local_tz)
    altitude_hourly = moon_altitude_hourly(observer=observer, local_date=local_date, local_tz=local_tz)

    return {
        "phase_deg": round(phase_deg, 1),
        "phase_name": phase_name(phase_deg),
        "illumination": round(illumination, 1),
        "rise_local": rise_local,
        "set_local": set_local,
        "altitude_hourly": altitude_hourly,
    }


def moon_rise_set_times(location, local_date: date_cls, local_tz: ZoneInfo) -> tuple[str | None, str | None]:
    start_local = datetime.combine(local_date, time_cls(0, 0), tzinfo=local_tz)
    end_local = start_local + timedelta(days=1)

    t0 = ts.from_datetime(start_local.astimezone(timezone.utc))
    t1 = ts.from_datetime(end_local.astimezone(timezone.utc))

    f = almanac.risings_and_settings(eph, MOON, location)
    times, events = almanac.find_discrete(t0, t1, f)

    moonrise = None
    moonset = None

    for event_time, event_value in zip(times, events):
        local_dt = event_time.utc_datetime().replace(tzinfo=timezone.utc).astimezone(local_tz)
        hhmm = local_dt.strftime("%H:%M")
        if bool(event_value) and moonrise is None:
            moonrise = hhmm
        if (not bool(event_value)) and moonset is None:
            moonset = hhmm

    return moonrise, moonset


def moon_altitude_hourly(observer, local_date: date_cls, local_tz: ZoneInfo) -> list[dict]:
    start_local = datetime.combine(local_date, time_cls(0, 0), tzinfo=local_tz)
    local_times = [start_local + timedelta(hours=offset) for offset in range(24)]
    utc_times = [dt.astimezone(timezone.utc) for dt in local_times]
    t = ts.from_datetimes(utc_times)

    alt, _, _ = observer.at(t).observe(MOON).apparent().altaz()
    alt_deg = np.asarray(alt.degrees)

    return [
        {
            "local_time": local_times[idx].strftime("%H:%M"),
            "altitude_deg": round(float(alt_deg[idx]), 1),
        }
        for idx in range(len(local_times))
    ]


def build_planets_payload(observer, local_date: date_cls, local_tz: ZoneInfo) -> list[dict]:
    start_local = datetime.combine(local_date, time_cls(18, 0), tzinfo=local_tz)
    end_local = datetime.combine(local_date + timedelta(days=1), time_cls(3, 0), tzinfo=local_tz)

    local_times = []
    current = start_local
    while current <= end_local:
        local_times.append(current)
        current += timedelta(minutes=10)

    utc_times = [dt.astimezone(timezone.utc) for dt in local_times]
    t = ts.from_datetimes(utc_times)

    sun_apparent = observer.at(t).observe(SUN).apparent()
    sun_alt, _, _ = sun_apparent.altaz()
    sun_alt_deg = np.asarray(sun_alt.degrees)

    visible = []

    for key, name, body in PLANETS:
        planet_apparent = observer.at(t).observe(body).apparent()
        alt, az, _ = planet_apparent.altaz()
        alt_deg = np.asarray(alt.degrees)
        az_deg = np.asarray(az.degrees)
        elongation_deg = np.asarray(planet_apparent.separation_from(sun_apparent).degrees)

        dark_mask = (alt_deg >= 10.0) & (sun_alt_deg < -6.0)
        fallback_mask = alt_deg >= 10.0
        mask = dark_mask if np.any(dark_mask) else fallback_mask

        if not np.any(mask):
            continue

        indices = np.where(mask)[0]
        max_idx = int(indices[np.argmax(alt_deg[indices])])
        segment = segment_containing_index(indices, max_idx)

        start_idx = int(segment[0])
        end_idx = int(segment[-1])

        alt_max = float(np.max(alt_deg[segment]))
        az_at_best = float(az_deg[max_idx] % 360.0)
        elongation_at_best = clamp_elongation_deg(float(elongation_deg[max_idx]))

        visible.append(
            {
                "key": key,
                "name": name,
                "best_from": local_times[start_idx].strftime("%H:%M"),
                "best_to": local_times[end_idx].strftime("%H:%M"),
                "direction": az_to_direction(az_at_best),
                "alt_max_deg": round(alt_max, 1),
                "az_at_best_deg": round(az_at_best, 1),
                "elongation_deg": round(elongation_at_best, 1),
                "is_low": alt_max < 15.0,
            }
        )

    visible.sort(key=lambda item: item["alt_max_deg"], reverse=True)
    return visible[:3]


def ensure_iss_satellite() -> EarthSatellite | None:
    satellite = iss_state.get("satellite")
    fetched_at = iss_state.get("fetched_at")

    if isinstance(satellite, EarthSatellite):
        if isinstance(fetched_at, str):
            try:
                fetched_dt = datetime.fromisoformat(fetched_at)
            except ValueError:
                fetched_dt = None
            if fetched_dt is not None and datetime.now(timezone.utc) - fetched_dt < timedelta(hours=ISS_TLE_REFRESH_HOURS):
                return satellite
        else:
            return satellite

    refresh_iss_tle_cache()
    satellite = iss_state.get("satellite")
    return satellite if isinstance(satellite, EarthSatellite) else None


def refresh_iss_tle_cache() -> None:
    cached_payload = load_cached_iss_tle()

    try:
        remote_payload = fetch_remote_iss_tle()
    except Exception as exc:  # pragma: no cover
        logger.warning("ISS TLE refresh failed; falling back to cache if available.", exc_info=exc)
        if cached_payload is not None:
            set_iss_satellite(cached_payload, source="cache", error=f"remote_fetch_failed:{exc}")
            logger.info("ISS TLE loaded from local cache.")
            return

        iss_state.update({
            "satellite": None,
            "source": None,
            "fetched_at": None,
            "error": f"remote_fetch_failed:{exc}",
        })
        return

    save_cached_iss_tle(remote_payload)
    set_iss_satellite(remote_payload, source="remote", error=None)
    logger.info("ISS TLE refreshed from remote source.")


def fetch_remote_iss_tle() -> dict[str, str]:
    request = urllib_request.Request(
        ISS_TLE_URL,
        headers={"User-Agent": "astrokomunita-sky/1.1"},
        method="GET",
    )

    with urllib_request.urlopen(request, timeout=10) as response:
        body = response.read().decode("utf-8", errors="replace")

    payload = parse_iss_tle_payload(body)
    if payload is None:
        raise RuntimeError("ISS TLE entry not found in remote feed.")

    payload["fetched_at"] = datetime.now(timezone.utc).isoformat()
    return payload


def parse_iss_tle_payload(raw_text: str) -> dict[str, str] | None:
    lines = [line.strip() for line in raw_text.splitlines() if line.strip()]
    for index, line in enumerate(lines):
        if "ISS" not in line.upper():
            continue
        if index + 2 >= len(lines):
            continue

        line1 = lines[index + 1]
        line2 = lines[index + 2]
        if line1.startswith("1 ") and line2.startswith("2 "):
            return {
                "name": line,
                "line1": line1,
                "line2": line2,
            }

    return None


def save_cached_iss_tle(payload: dict[str, str]) -> None:
    ISS_TLE_CACHE_PATH.write_text(json.dumps(payload), encoding="utf-8")


def load_cached_iss_tle() -> dict[str, str] | None:
    if not ISS_TLE_CACHE_PATH.exists():
        return None

    try:
        payload = json.loads(ISS_TLE_CACHE_PATH.read_text(encoding="utf-8"))
    except Exception as exc:  # pragma: no cover
        logger.warning("Failed to read cached ISS TLE.", exc_info=exc)
        return None

    if not isinstance(payload, dict):
        return None

    name = str(payload.get("name") or "").strip()
    line1 = str(payload.get("line1") or "").strip()
    line2 = str(payload.get("line2") or "").strip()
    fetched_at = str(payload.get("fetched_at") or "").strip()
    if not name or not line1.startswith("1 ") or not line2.startswith("2 "):
        return None

    return {
        "name": name,
        "line1": line1,
        "line2": line2,
        "fetched_at": fetched_at or datetime.now(timezone.utc).isoformat(),
    }


def set_iss_satellite(payload: dict[str, str], source: str, error: str | None) -> None:
    satellite = EarthSatellite(payload["line1"], payload["line2"], payload["name"], ts)
    iss_state.update({
        "satellite": satellite,
        "source": source,
        "fetched_at": payload.get("fetched_at"),
        "error": error,
    })


def build_iss_passes(
    satellite: EarthSatellite,
    observer,
    location,
    event_times,
    event_codes,
    local_tz: ZoneInfo,
) -> list[dict[str, object]]:
    passes: list[dict[str, object]] = []
    current: dict[str, object] = {}

    for event_time, event_code in zip(event_times, event_codes):
        dt_utc = event_time.utc_datetime().replace(tzinfo=timezone.utc)
        dt_local = dt_utc.astimezone(local_tz)

        if int(event_code) == 0:
            current = {"rise_at": dt_local, "rise_time": event_time}
            continue

        if int(event_code) == 1 and current:
            current["culmination_at"] = dt_local
            current["culmination_time"] = event_time
            continue

        if int(event_code) != 2 or not current:
            continue

        rise_at = current.get("rise_at")
        culmination_at = current.get("culmination_at")
        rise_time = current.get("rise_time")
        culmination_time = current.get("culmination_time")
        if not isinstance(rise_at, datetime) or not isinstance(culmination_at, datetime):
            current = {}
            continue
        if rise_time is None or culmination_time is None:
            current = {}
            continue

        set_at = dt_local
        topocentric_rise = (satellite - location).at(rise_time)
        topocentric_culm = (satellite - location).at(culmination_time)
        topocentric_set = (satellite - location).at(event_time)
        rise_alt, rise_az, _ = topocentric_rise.altaz()
        culm_alt, _, _ = topocentric_culm.altaz()
        _, set_az, _ = topocentric_set.altaz()
        sun_alt, _, _ = observer.at(culmination_time).observe(SUN).apparent().altaz()
        sunlit = bool(satellite.at(culmination_time).is_sunlit(eph))

        passes.append({
            "rise_at": rise_at,
            "set_at": set_at,
            "max_altitude_deg": float(culm_alt.degrees),
            "direction_start": az_to_direction(float(rise_az.degrees)),
            "direction_end": az_to_direction(float(set_az.degrees)),
            "is_visible": sunlit and float(sun_alt.degrees) < -4.0 and float(culm_alt.degrees) >= 10.0,
        })
        current = {}

    return passes


def segment_containing_index(indices: np.ndarray, needle: int) -> np.ndarray:
    splits = np.where(np.diff(indices) != 1)[0] + 1
    for segment in np.split(indices, splits):
        if needle in segment:
            return segment
    return indices


def az_to_direction(azimuth_deg: float) -> str:
    idx = int(((azimuth_deg % 360.0) + 22.5) // 45.0) % 8
    return DIRECTIONS[idx]


def clamp_elongation_deg(value: float) -> float:
    return max(0.0, min(180.0, value))


def isoformat_with_timezone(value: datetime) -> str:
    if value.tzinfo is None or value.utcoffset() is None:
        raise ValueError("sample_at must include timezone information.")
    return value.isoformat()


def phase_name(phase_deg: float) -> str:
    boundaries = [
        (22.5, "New moon"),
        (67.5, "Waxing crescent"),
        (112.5, "First quarter"),
        (157.5, "Waxing gibbous"),
        (202.5, "Full moon"),
        (247.5, "Waning gibbous"),
        (292.5, "Last quarter"),
        (337.5, "Waning crescent"),
        (360.0, "New moon"),
    ]

    normalized = phase_deg % 360.0
    for limit, label in boundaries:
        if normalized < limit:
            return label

    return "New moon"
