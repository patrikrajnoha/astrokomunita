import { inject } from 'vue'

export const settingsContextKey = Symbol('settings-context')

export function useSettingsContext() {
  const context = inject(settingsContextKey, null)

  if (!context) {
    throw new Error('Kontext nastavení nie je dostupný. Pripojte toto zobrazenie pod /settings.')
  }

  return context
}
