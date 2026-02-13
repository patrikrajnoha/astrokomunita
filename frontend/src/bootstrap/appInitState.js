import { reactive } from 'vue'

export const appInitState = reactive({
  initializing: true,
  initError: null,
  mounted: false,
})

export function setInitError(error) {
  appInitState.initError = error
}

export function setInitializing(value) {
  appInitState.initializing = Boolean(value)
}

export function setMounted(value) {
  appInitState.mounted = Boolean(value)
}
