import { useEmailSettings } from './useEmailSettings'
import { useExportSettings } from './useExportSettings'
import { useAccountSettings } from './useAccountSettings'

export function useSettingsState() {
  const emailSettings = useEmailSettings()
  const exportSettings = useExportSettings()
  const accountSettings = useAccountSettings()

  return {
    ...emailSettings,
    ...exportSettings,
    ...accountSettings,
  }
}
