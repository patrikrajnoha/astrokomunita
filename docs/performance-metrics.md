# Performance Metrics

## Co sa meria

Admin benchmark panel uklada merania do `performance_logs` pre:

- `events_list_200`: response time endpointu `GET /api/events` (warm-up 5 requestov + meranych N requestov), vratane priemerneho poctu DB query.
- `canonical_publish_100`: cas approve/publish flow pre event candidates.
- `bot_run_<source>`: cas bot importu pre fixture vstup (bez externych API), vratane fetched/published/error breakdown.

Kazdy run uklada:

- `duration_ms`: celkovy cas vzorky.
- `avg_ms`: priemer.
- `p95_ms`: 95. percentil.
- `min_ms`, `max_ms`: rozsah.
- `db_queries_avg`, `db_queries_p95`: DB query profil.
- `payload`: detail benchmarku (mode, fixture, notes, counts).

## Ako spustit benchmark

1. Otvor admin panel `Performance Metrics` (`/admin/performance-metrics`).
2. Vyber typ benchmarku (`all`, `events_list`, `canonical`, `bot`).
3. Nastav `sample_size` (max 500).
4. Klikni `Run benchmark (200 requests)`.

Alternativne API:

- `GET /api/admin/performance-metrics`
- `POST /api/admin/performance-metrics/run`

Priklad requestu:

```json
{
  "run": "events_list",
  "sample_size": 200,
  "mode": "normal"
}
```

## Upozornenie na zataz

Benchmark je urceny pre staging/dev. Pri vacsich sample-size moze docasne zatazit DB a API.

## Interpretacia metrik

- `avg_ms` je vhodne na porovnanie beznych behov.
- `p95_ms` ukazuje tail latency (zhorene requesty).
- `db_queries_avg` pomaha odhalit N+1/regresie.
- `payload` obsahuje technicke poznamky (napr. fixture source alebo rollback rezim).

