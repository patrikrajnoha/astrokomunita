export const BOT_FAILURE_REASONS = Object.freeze({
  TRANSLATION_TIMEOUT: 'translation_timeout',
  PROVIDER_UNAVAILABLE: 'provider_unavailable',
  STALE_RUN_RECOVERED: 'stale_run_recovered',
  UNHANDLED_EXCEPTION: 'unhandled_exception',
  LOCK_CONFLICT: 'lock_conflict',
  RATE_LIMITED: 'rate_limited',
  COOLDOWN_RATE_LIMITED: 'cooldown_rate_limited',
  NEEDS_API_KEY: 'needs_api_key',
  UNKNOWN: 'unknown',
})

export const BOT_FAILURE_REASON_MESSAGES = Object.freeze({
  [BOT_FAILURE_REASONS.TRANSLATION_TIMEOUT]: 'Preklad timeoutol, run pokracoval bez prekladu pre cast poloziek.',
  [BOT_FAILURE_REASONS.PROVIDER_UNAVAILABLE]: 'Prekladovy provider je nedostupny, run pokracoval s povodnym textom.',
  [BOT_FAILURE_REASONS.STALE_RUN_RECOVERED]: 'Nasiel sa stary nedokoneny run, bol automaticky oznaceny ako failed.',
  [BOT_FAILURE_REASONS.UNHANDLED_EXCEPTION]: 'Run zlyhal na necakanej chybe.',
  [BOT_FAILURE_REASONS.LOCK_CONFLICT]: 'Run sa nespustil, pretoze je uz aktivny iny run pre tento source.',
  [BOT_FAILURE_REASONS.RATE_LIMITED]: 'Source je rate limited. Skus to neskor alebo nastav API key.',
  [BOT_FAILURE_REASONS.COOLDOWN_RATE_LIMITED]: 'Source je v cooldown okne po rate limite.',
  [BOT_FAILURE_REASONS.NEEDS_API_KEY]: 'Source vyzaduje API key.',
  [BOT_FAILURE_REASONS.UNKNOWN]: 'Run zlyhal.',
})

export const BOT_FAILURE_REASONS_BACKEND = Object.freeze([
  BOT_FAILURE_REASONS.TRANSLATION_TIMEOUT,
  BOT_FAILURE_REASONS.PROVIDER_UNAVAILABLE,
  BOT_FAILURE_REASONS.STALE_RUN_RECOVERED,
  BOT_FAILURE_REASONS.UNHANDLED_EXCEPTION,
  BOT_FAILURE_REASONS.LOCK_CONFLICT,
  BOT_FAILURE_REASONS.RATE_LIMITED,
  BOT_FAILURE_REASONS.COOLDOWN_RATE_LIMITED,
  BOT_FAILURE_REASONS.NEEDS_API_KEY,
  BOT_FAILURE_REASONS.UNKNOWN,
])
