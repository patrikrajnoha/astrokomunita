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
  [BOT_FAILURE_REASONS.TRANSLATION_TIMEOUT]: 'Preklad timeoutol, run pokračoval bez prekladu pre časť položiek.',
  [BOT_FAILURE_REASONS.PROVIDER_UNAVAILABLE]: 'Prekladový provider je nedostupný, run pokračoval s pôvodným textom.',
  [BOT_FAILURE_REASONS.STALE_RUN_RECOVERED]: 'Našiel sa starý nedokončený run, bol automaticky označený ako failed.',
  [BOT_FAILURE_REASONS.UNHANDLED_EXCEPTION]: 'Run zlyhal na nečakanej chybe.',
  [BOT_FAILURE_REASONS.LOCK_CONFLICT]: 'Run sa nespustil, pretože je už aktívny iný run pre tento source.',
  [BOT_FAILURE_REASONS.RATE_LIMITED]: 'Source je rate limited. Skús to neskôr alebo nastav API key.',
  [BOT_FAILURE_REASONS.COOLDOWN_RATE_LIMITED]: 'Source je v cooldown okne po rate limite.',
  [BOT_FAILURE_REASONS.NEEDS_API_KEY]: 'Source vyžaduje API key.',
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
