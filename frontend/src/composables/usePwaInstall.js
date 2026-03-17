import { ref } from 'vue'

// Module-level singleton so state is shared across all components
const deferredInstallPrompt = ref(null)
const canInstall = ref(false)

export function usePwaInstall() {
  const handleBeforeInstallPrompt = (event) => {
    event.preventDefault()
    deferredInstallPrompt.value = event
    canInstall.value = true
  }

  const handleInstalled = () => {
    deferredInstallPrompt.value = null
    canInstall.value = false
  }

  const installApp = async () => {
    const promptEvent = deferredInstallPrompt.value
    if (!promptEvent) return

    try {
      await promptEvent.prompt()
      await promptEvent.userChoice
    } catch (error) {
      console.warn('Install prompt failed:', error)
    } finally {
      deferredInstallPrompt.value = null
      canInstall.value = false
    }
  }

  return {
    canInstall,
    installApp,
    handleBeforeInstallPrompt,
    handleInstalled,
  }
}
