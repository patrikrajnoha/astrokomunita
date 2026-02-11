from __future__ import annotations

from datetime import date as date_cls
from datetime import datetime, time, timedelta, timezone
from pathlib import Path
from zoneinfo import ZoneInfo

import numpy as np
from fastapi import FastAPI, HTTPException, Query
from skyfield import almanac
from skyfield.api import Loader, wgs84

APP_ROOT = Path(__file__).resolve().parent.parent
DATA_DIR = APP_ROOT / "data"
DATA_DIR.mkdir(parents=True, exist_ok=True)

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

SERVICE_VERSION = "1.0.0"
app = FastAPI(title="Sky Summary Service", version=SERVICE_VERSION)


@app.get("/health")
def health() -> dict[str, object]:
    return {"ok": True, "version": SERVICE_VERSION}


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

    moon_payload = build_moon_payload(observer=observer, location=location, local_date=local_date, local_tz=local_tz)
    planets_payload = build_planets_payload(observer=observer, local_date=local_date, local_tz=local_tz)

    return {
        "moon": moon_payload,
        "planets": planets_payload,
    }


def build_moon_payload(observer, location, local_date: date_cls, local_tz: ZoneInfo) -> dict:
    local_noon = datetime.combine(local_date, time(12, 0), tzinfo=local_tz)
    noon_utc = local_noon.astimezone(timezone.utc)
    t_noon = ts.from_datetime(noon_utc)

    phase_deg = float(almanac.moon_phase(eph, t_noon).degrees % 360.0)
    illumination = float(almanac.fraction_illuminated(eph, "moon", t_noon) * 100.0)

    rise_local, set_local = moon_rise_set_times(location=location, local_date=local_date, local_tz=local_tz)

    return {
        "phase_deg": round(phase_deg, 1),
        "phase_name": phase_name(phase_deg),
        "illumination": round(illumination, 1),
        "rise_local": rise_local,
        "set_local": set_local,
    }


def moon_rise_set_times(location, local_date: date_cls, local_tz: ZoneInfo) -> tuple[str | None, str | None]:
    start_local = datetime.combine(local_date, time(0, 0), tzinfo=local_tz)
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


def build_planets_payload(observer, local_date: date_cls, local_tz: ZoneInfo) -> list[dict]:
    start_local = datetime.combine(local_date, time(18, 0), tzinfo=local_tz)
    end_local = datetime.combine(local_date + timedelta(days=1), time(3, 0), tzinfo=local_tz)

    local_times = []
    current = start_local
    while current <= end_local:
        local_times.append(current)
        current += timedelta(minutes=10)

    utc_times = [dt.astimezone(timezone.utc) for dt in local_times]
    t = ts.from_datetimes(utc_times)

    sun_alt, _, _ = observer.at(t).observe(SUN).apparent().altaz()
    sun_alt_deg = np.asarray(sun_alt.degrees)

    visible = []

    for key, name, body in PLANETS:
        alt, az, _ = observer.at(t).observe(body).apparent().altaz()
        alt_deg = np.asarray(alt.degrees)
        az_deg = np.asarray(az.degrees)

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

        visible.append(
            {
                "key": key,
                "name": name,
                "best_from": local_times[start_idx].strftime("%H:%M"),
                "best_to": local_times[end_idx].strftime("%H:%M"),
                "direction": az_to_direction(az_at_best),
                "alt_max_deg": round(alt_max, 1),
                "az_at_best_deg": round(az_at_best, 1),
                "is_low": alt_max < 15.0,
            }
        )

    visible.sort(key=lambda item: item["alt_max_deg"], reverse=True)
    return visible[:3]


def segment_containing_index(indices: np.ndarray, needle: int) -> np.ndarray:
    splits = np.where(np.diff(indices) != 1)[0] + 1
    for segment in np.split(indices, splits):
        if needle in segment:
            return segment
    return indices


def az_to_direction(azimuth_deg: float) -> str:
    idx = int(((azimuth_deg % 360.0) + 22.5) // 45.0) % 8
    return DIRECTIONS[idx]


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
