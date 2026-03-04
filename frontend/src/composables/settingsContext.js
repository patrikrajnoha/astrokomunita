import { inject } from 'vue'

export const settingsContextKey = Symbol('settings-context')

export function useSettingsContext() {
  const context = inject(settingsContextKey, null)

  if (!context) {
    throw new Error('Settings context is unavailable. Mount this view under /settings.')
  }

  return context
}
