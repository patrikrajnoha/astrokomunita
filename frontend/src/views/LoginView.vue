<template>
  <div class="loginView" :class="{ isAnimationOnly: showSuccessAnimation }">
    <div class="loginStars" aria-hidden="true">
      <span
        v-for="star in stars"
        :key="star.id"
        class="loginStar"
        :style="star.style"
      ></span>
    </div>

    <AuthSplitLayout>
      <template #hero>
        <AuthHeroPanel
          eyebrow="Prihlasovanie"
          title="Prihlásenie"
          subtitle="Pokračujte do svojho Astrokomunita účtu bezpečným prihlásením."
        />
      </template>

      <AuthFormSection
        kicker="Účet"
        title="Vitajte späť"
        description="Použite e-mail a heslo pre prístup k profilu a komunitnému feedu."
      >
        <form class="authForm" @submit.prevent="submit" novalidate>
          <AuthField
            v-model="email"
            label="E-mail"
            type="email"
            autocomplete="email"
            placeholder="you@example.com"
            :error="emailError"
            required
          >
            <template #icon>
              <svg viewBox="0 0 24 24" fill="none">
                <path d="M3.5 7.5 12 13l8.5-5.5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" />
                <rect x="3.5" y="5.5" width="17" height="13" rx="2.8" stroke="currentColor" stroke-width="1.8" />
              </svg>
            </template>
          </AuthField>

          <AuthField
            v-model="password"
            label="Heslo"
            type="password"
            autocomplete="current-password"
            placeholder="Zadajte heslo"
            :error="passwordError"
            required
          >
            <template #icon>
              <svg viewBox="0 0 24 24" fill="none">
                <path d="M7.5 11V9.2A4.5 4.5 0 0 1 12 4.7a4.5 4.5 0 0 1 4.5 4.5V11" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" />
                <rect x="5" y="11" width="14" height="9.5" rx="2.7" stroke="currentColor" stroke-width="1.8" />
              </svg>
            </template>
            <template #labelAction>
              <RouterLink :to="forgotPasswordLink" class="authInlineLink">Zabudli ste heslo?</RouterLink>
            </template>
          </AuthField>

          <AuthAlert
            v-if="error"
            title="Nepodarilo sa prihlásiť"
            :message="error"
          />

          <AuthAlert
            v-if="isBannedState"
            title="Účet je blokovaný"
            :message="bannedDetails"
          />

          <p v-if="resetSuccessMessage" class="authField__meta">{{ resetSuccessMessage }}</p>

          <AuthActions
            :back-to="{ name: 'home' }"
            back-label="Späť"
            submit-label="Prihlásiť sa"
            loading-label="Prihlasuje sa..."
            :loading="authBusy"
          />

          <p class="authFootnote">
            Potrebujete účet?
            <RouterLink class="authInlineLink" :to="registerLink">Vytvoriť účet</RouterLink>
          </p>
        </form>
      </AuthFormSection>
    </AuthSplitLayout>

    <Transition name="login-success-fade">
      <div
        v-if="showSuccessAnimation"
        class="loginSuccessOverlay"
        role="status"
        aria-live="polite"
        aria-label="Prihlásenie úspešné, pripravujem presmerovanie"
      >
        <div class="loginSuccessOverlay__scene" aria-hidden="true">
          <div class="rain rain1"></div>
          <div class="rain rain2">
            <div class="drop drop2"></div>
          </div>
          <div class="rain rain3"></div>
          <div class="rain rain4"></div>
          <div class="rain rain5">
            <div class="drop drop5"></div>
          </div>
          <div class="rain rain6"></div>
          <div class="rain rain7"></div>
          <div class="rain rain8">
            <div class="drop drop8"></div>
          </div>
          <div class="rain rain9"></div>
          <div class="rain rain10"></div>
          <div class="drop drop11"></div>
          <div class="drop drop12"></div>
          <div ref="rocketCanvasRef" class="loginSuccessOverlay__canvas"></div>
        </div>
      </div>
    </Transition>
  </div>
</template>

<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import AuthActions from '@/components/auth/AuthActions.vue'
import AuthAlert from '@/components/auth/AuthAlert.vue'
import AuthField from '@/components/auth/AuthField.vue'
import AuthFormSection from '@/components/auth/AuthFormSection.vue'
import AuthHeroPanel from '@/components/auth/AuthHeroPanel.vue'
import AuthSplitLayout from '@/components/auth/AuthSplitLayout.vue'
import api from '@/services/api'
import { prefetchHomeFeed } from '@/services/feedPrefetch'
import { useAuthStore } from '@/stores/auth'

const LOGIN_SUCCESS_ANIMATION_MS = 2800
const MOBILE_BREAKPOINT_PX = 768
const ROCKET_MODEL_SCALE = 0.6
const THREE_SCRIPT_SRC = 'https://unpkg.com/three@0.123.0/build/three.min.js'
const GLTF_LOADER_SCRIPT_SRC = 'https://unpkg.com/three@0.123.0/examples/js/loaders/GLTFLoader.js'
const SCRIPT_READY_TIMEOUT_MS = 7000
const appBaseUrl = String(import.meta.env.BASE_URL || '/')
const normalizedBaseUrl = appBaseUrl.endsWith('/') ? appBaseUrl : `${appBaseUrl}/`
const ROCKET_MODEL_SRC = `${normalizedBaseUrl}animations/rocket/rocket.gltf`

const externalScriptPromises = new Map()

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()

const email = ref(typeof route.query.email === 'string' ? route.query.email : '')
const password = ref('')
const error = ref('')
const attempted = ref(false)
const showSuccessAnimation = ref(false)
const isAnimating = ref(false)
const rocketCanvasRef = ref(null)
const stars = createStars(80)
let animationTimerId = null
let rocketScene = null
let rocketCamera = null
let rocketRenderer = null
let rocketModel = null
let rocketFrameId = null
let rocketResizeHandler = null
let rocketViewportConfig = null
let rocketTemplateScene = null
let rocketTemplatePromise = null

const redirect = computed(() => {
  const candidate = route.query.redirect
  return typeof candidate === 'string' && candidate.startsWith('/') ? candidate : '/'
})

const registerLink = computed(() => ({
  name: 'register',
  query: { redirect: redirect.value },
}))

const forgotPasswordLink = computed(() => ({
  name: 'forgot-password',
  query: email.value ? { email: email.value } : undefined,
}))

const emailError = computed(() => (attempted.value && !email.value.trim() ? 'E-mail je povinný.' : ''))
const passwordError = computed(() => (attempted.value && !password.value ? 'Heslo je povinné.' : ''))
const resetSuccessMessage = computed(() => (
  route.query.reset === '1' ? 'Heslo bolo zmenené. Môžete sa prihlásiť novým heslom.' : ''
))
const authBusy = computed(() => auth.loading || isAnimating.value)

const isBannedState = computed(() => auth.error?.type === 'banned')
const bannedDetails = computed(() => {
  if (!isBannedState.value) return ''

  const reason = String(auth.error?.reason || '').trim()
  const bannedAtRaw = auth.error?.bannedAt
  let bannedAt = ''

  if (bannedAtRaw) {
    const parsed = new Date(bannedAtRaw)
    bannedAt = Number.isNaN(parsed.getTime()) ? String(bannedAtRaw) : parsed.toLocaleString()
  }

  if (reason && bannedAt) return `Dovod: ${reason}. Blokovane: ${bannedAt}.`
  if (reason) return `Dovod: ${reason}.`
  if (bannedAt) return `Blokovane: ${bannedAt}.`
  return 'Tento ucet je blokovany.'
})

onBeforeUnmount(() => {
  if (animationTimerId !== null) {
    clearTimeout(animationTimerId)
    animationTimerId = null
  }

  stopRocketAnimation()
})

onMounted(() => {
  void ensureRocketTemplateLoaded()
})

function findExistingScript(src) {
  return Array.from(document.querySelectorAll('script[src]')).find((scriptElement) => scriptElement.src === src) || null
}

function loadExternalScript(src, readyCheck) {
  if (readyCheck()) {
    return Promise.resolve()
  }

  const cachedPromise = externalScriptPromises.get(src)
  if (cachedPromise) {
    return cachedPromise
  }

  const scriptPromise = new Promise((resolve, reject) => {
    const existingScript = findExistingScript(src)

    if (existingScript) {
      let settled = false
      let pollIntervalId = null
      let timeoutId = null

      const cleanup = () => {
        existingScript.removeEventListener('load', onLoad)
        existingScript.removeEventListener('error', onError)
        if (pollIntervalId !== null) {
          clearInterval(pollIntervalId)
          pollIntervalId = null
        }
        if (timeoutId !== null) {
          clearTimeout(timeoutId)
          timeoutId = null
        }
      }

      const finishResolve = () => {
        if (settled) return
        settled = true
        cleanup()
        resolve()
      }

      const finishReject = () => {
        if (settled) return
        settled = true
        cleanup()
        reject(new Error(`Script load failed: ${src}`))
      }

      const onLoad = () => {
        if (readyCheck()) {
          finishResolve()
          return
        }

        finishReject()
      }

      const onError = () => {
        finishReject()
      }

      if (readyCheck()) {
        finishResolve()
        return
      }

      existingScript.addEventListener('load', onLoad)
      existingScript.addEventListener('error', onError)

      pollIntervalId = window.setInterval(() => {
        if (readyCheck()) {
          finishResolve()
        }
      }, 50)

      timeoutId = window.setTimeout(() => {
        finishReject()
      }, SCRIPT_READY_TIMEOUT_MS)
      return
    }

    const scriptElement = document.createElement('script')
    scriptElement.src = src
    scriptElement.async = true
    scriptElement.defer = true
    scriptElement.dataset.loginRocket = src
    scriptElement.addEventListener('load', () => resolve(), { once: true })
    scriptElement.addEventListener('error', () => reject(new Error(`Script load failed: ${src}`)), { once: true })
    document.head.appendChild(scriptElement)
  })

  externalScriptPromises.set(src, scriptPromise)
  return scriptPromise
}

async function ensureRocketDependencies() {
  try {
    await loadExternalScript(THREE_SCRIPT_SRC, () => Boolean(window.THREE))
    await loadExternalScript(GLTF_LOADER_SCRIPT_SRC, () => Boolean(window.THREE?.GLTFLoader))
    return Boolean(window.THREE?.GLTFLoader)
  } catch {
    return false
  }
}

function handleRocketResize() {
  if (!rocketRenderer || !rocketCamera || !rocketCanvasRef.value) {
    return
  }

  const width = rocketCanvasRef.value.clientWidth || window.innerWidth
  const height = rocketCanvasRef.value.clientHeight || window.innerHeight

  rocketViewportConfig = getRocketViewportConfig(width)
  applyRocketViewportConfig(rocketViewportConfig)

  rocketRenderer.setSize(width, height)
  rocketCamera.aspect = width / height
  rocketCamera.updateProjectionMatrix()
}

function getRocketViewportConfig(viewportWidth) {
  if (viewportWidth <= MOBILE_BREAKPOINT_PX) {
    return {
      modelScale: 0.74,
      cameraZ: 430,
      cameraY: -4,
      floatAmplitude: 28,
      rotationSpeed: 0.07,
    }
  }

  return {
    modelScale: ROCKET_MODEL_SCALE,
    cameraZ: 500,
    cameraY: -10,
    floatAmplitude: 40,
    rotationSpeed: 0.1,
  }
}

function applyRocketViewportConfig(config) {
  if (!config || !rocketCamera) {
    return
  }

  rocketCamera.position.z = config.cameraZ
  rocketCamera.position.y = config.cameraY

  if (rocketModel) {
    rocketModel.scale.setScalar(config.modelScale)
  }
}

function applyRocketModelTransforms(model) {
  if (!model) {
    return
  }

  model.scale.setScalar(rocketViewportConfig?.modelScale || ROCKET_MODEL_SCALE)
  model.position.y = 50
}

function createFallbackRocket(THREE) {
  const group = new THREE.Group()
  const bodyMaterial = new THREE.MeshStandardMaterial({
    color: 0xf2f7ff,
    metalness: 0.18,
    roughness: 0.36,
  })
  const accentMaterial = new THREE.MeshStandardMaterial({
    color: 0xff3a79,
    metalness: 0.1,
    roughness: 0.48,
  })
  const finMaterial = new THREE.MeshStandardMaterial({
    color: 0x42246f,
    metalness: 0.16,
    roughness: 0.52,
  })
  const windowFrameMaterial = new THREE.MeshStandardMaterial({
    color: 0x22183e,
    metalness: 0.25,
    roughness: 0.4,
  })
  const windowGlassMaterial = new THREE.MeshStandardMaterial({
    color: 0x5ecbff,
    emissive: 0x1a5a88,
    emissiveIntensity: 0.4,
    metalness: 0.08,
    roughness: 0.24,
  })

  const body = new THREE.Mesh(new THREE.CylinderGeometry(18, 22, 88, 20), bodyMaterial)
  body.position.y = 18

  const nose = new THREE.Mesh(new THREE.ConeGeometry(18, 34, 20), accentMaterial)
  nose.position.y = 79

  const base = new THREE.Mesh(new THREE.CylinderGeometry(20, 22, 20, 20), accentMaterial)
  base.position.y = -34

  const wingLeft = new THREE.Mesh(new THREE.BoxGeometry(8, 30, 22), finMaterial)
  wingLeft.position.set(-22, -42, 0)
  wingLeft.rotation.z = 0.22

  const wingRight = wingLeft.clone()
  wingRight.position.x = 22
  wingRight.rotation.z = -0.22

  const windowFrame = new THREE.Mesh(new THREE.CylinderGeometry(7, 7, 2, 20), windowFrameMaterial)
  windowFrame.rotation.x = Math.PI / 2
  windowFrame.position.set(0, 36, 20)

  const windowGlass = new THREE.Mesh(new THREE.CircleGeometry(5, 24), windowGlassMaterial)
  windowGlass.position.set(0, 36, 21.2)

  group.add(body, nose, base, wingLeft, wingRight, windowFrame, windowGlass)
  group.rotation.y = Math.PI

  group.traverse((node) => {
    if (node.isMesh) {
      node.castShadow = true
      node.receiveShadow = true
    }
  })

  return group
}

async function ensureRocketTemplateLoaded() {
  const depsReady = await ensureRocketDependencies()
  if (!depsReady) {
    return null
  }

  if (rocketTemplateScene) {
    return rocketTemplateScene
  }

  if (rocketTemplatePromise) {
    return rocketTemplatePromise
  }

  const THREE = window.THREE
  rocketTemplatePromise = new Promise((resolve, reject) => {
    const loader = new THREE.GLTFLoader()
    loader.load(
      ROCKET_MODEL_SRC,
      (gltf) => resolve(gltf?.scene || null),
      undefined,
      reject,
    )
  })
    .then((scene) => {
      if (!scene) {
        return null
      }

      rocketTemplateScene = scene
      return rocketTemplateScene
    })
    .catch(() => null)
    .finally(() => {
      if (!rocketTemplateScene) {
        rocketTemplatePromise = null
      }
    })

  return rocketTemplatePromise
}

function stopRocketAnimation() {
  if (rocketFrameId !== null) {
    cancelAnimationFrame(rocketFrameId)
    rocketFrameId = null
  }

  if (rocketResizeHandler) {
    window.removeEventListener('resize', rocketResizeHandler, false)
    rocketResizeHandler = null
  }

  if (rocketRenderer) {
    rocketRenderer.dispose()

    if (typeof rocketRenderer.forceContextLoss === 'function') {
      rocketRenderer.forceContextLoss()
    }

    if (rocketRenderer.domElement?.parentNode) {
      rocketRenderer.domElement.parentNode.removeChild(rocketRenderer.domElement)
    }
  }

  if (rocketCanvasRef.value) {
    rocketCanvasRef.value.replaceChildren()
  }

  rocketScene = null
  rocketCamera = null
  rocketRenderer = null
  rocketModel = null
  rocketViewportConfig = null
}

async function startRocketAnimation() {
  if (!showSuccessAnimation.value || !rocketCanvasRef.value) {
    return
  }

  stopRocketAnimation()

  const depsReady = await ensureRocketDependencies()
  if (!depsReady || !showSuccessAnimation.value || !rocketCanvasRef.value) {
    return
  }

  const THREE = window.THREE
  const width = rocketCanvasRef.value.clientWidth || window.innerWidth
  const height = rocketCanvasRef.value.clientHeight || window.innerHeight
  rocketViewportConfig = getRocketViewportConfig(width)

  rocketScene = new THREE.Scene()
  rocketScene.fog = new THREE.Fog(0x5d0361, 10, 1500)

  rocketCamera = new THREE.PerspectiveCamera(60, width / height, 1, 10000)
  rocketCamera.position.x = 0
  rocketCamera.position.z = rocketViewportConfig.cameraZ
  rocketCamera.position.y = rocketViewportConfig.cameraY

  rocketRenderer = new THREE.WebGLRenderer({
    alpha: true,
    antialias: true,
  })
  rocketRenderer.outputEncoding = THREE.sRGBEncoding
  rocketRenderer.setPixelRatio(Math.min(window.devicePixelRatio || 1, 2))
  rocketRenderer.setSize(width, height)
  rocketRenderer.shadowMap.enabled = true
  rocketCanvasRef.value.replaceChildren(rocketRenderer.domElement)

  const ambientLight = new THREE.HemisphereLight(0x404040, 0x404040, 1)
  const directionalLight = new THREE.DirectionalLight(0xdfebff, 1)
  directionalLight.position.set(-300, 0, 600)

  const pointLight = new THREE.PointLight(0xa11148, 2, 1000, 2)
  pointLight.position.set(200, -100, 50)
  rocketScene.add(ambientLight, directionalLight, pointLight)

  rocketModel = createFallbackRocket(THREE)
  applyRocketModelTransforms(rocketModel)
  rocketScene.add(rocketModel)

  void ensureRocketTemplateLoaded().then((templateScene) => {
    if (!templateScene || !rocketScene || !showSuccessAnimation.value) {
      return
    }

    const detailedRocketModel = templateScene.clone(true)
    applyRocketModelTransforms(detailedRocketModel)

    if (rocketModel && rocketScene) {
      rocketScene.remove(rocketModel)
    }

    rocketModel = detailedRocketModel
    rocketScene.add(rocketModel)
  })

  rocketResizeHandler = handleRocketResize
  window.addEventListener('resize', rocketResizeHandler, false)

  const animationDuration = 2000
  const loop = () => {
    if (!rocketRenderer || !rocketScene || !rocketCamera) {
      return
    }

    const t = (Date.now() % animationDuration) / animationDuration
    rocketRenderer.render(rocketScene, rocketCamera)

    const targetRocketPosition = rocketViewportConfig?.floatAmplitude || 40
    const rotationSpeed = rocketViewportConfig?.rotationSpeed || 0.1
    const delta = targetRocketPosition * Math.sin(Math.PI * 2 * t)
    if (rocketModel) {
      rocketModel.rotation.y += rotationSpeed
      rocketModel.position.y = delta
    }

    rocketFrameId = requestAnimationFrame(loop)
  }

  loop()
}

async function waitForSuccessAnimation() {
  showSuccessAnimation.value = true
  isAnimating.value = true

  await nextTick()
  void startRocketAnimation()

  return new Promise((resolve) => {
    animationTimerId = setTimeout(() => {
      stopRocketAnimation()
      showSuccessAnimation.value = false
      isAnimating.value = false
      animationTimerId = null
      resolve()
    }, LOGIN_SUCCESS_ANIMATION_MS)
  })
}

function shouldPrefetchHomeFeed(destination) {
  if (typeof destination !== 'string') {
    return false
  }

  const [path] = destination.split('?')
  return path === '/'
}

async function submit() {
  if (authBusy.value) {
    return
  }

  attempted.value = true
  error.value = ''

  if (!email.value.trim() || !password.value) {
    return
  }

  try {
    await auth.login({
      email: email.value.trim(),
      password: password.value,
      remember: true,
    })

    let destination = redirect.value

    if (
      !auth.isAdmin &&
      auth.user?.email &&
      !auth.user?.email_verified_at
    ) {
      destination = { name: 'settings.email', query: { redirect: redirect.value } }
    }

    if (shouldPrefetchHomeFeed(destination)) {
      void prefetchHomeFeed(api)
    }

    await waitForSuccessAnimation()
    await router.push(destination)
  } catch (e) {
    if (animationTimerId !== null) {
      clearTimeout(animationTimerId)
      animationTimerId = null
    }

    stopRocketAnimation()
    showSuccessAnimation.value = false
    isAnimating.value = false
    error.value = e?.response?.data?.message || e?.authError?.message || e?.message || 'Prihlásenie zlyhalo.'
  }
}

function seededRandom(seed) {
  const value = Math.sin(seed * 9999.91) * 10000
  return value - Math.floor(value)
}

function createStars(count) {
  const generatedStars = []

  for (let i = 1; i <= count; i += 1) {
    const x = seededRandom(i * 1.37)
    const y = seededRandom(i * 2.17)
    const size = [1, 2, 3, 4][Math.floor(seededRandom(i * 3.31) * 4)]
    const delay = -(seededRandom(i * 4.13) * 4)

    generatedStars.push({
      id: i,
      style: {
        left: `${(x * 100).toFixed(2)}%`,
        top: `${(y * 100).toFixed(2)}%`,
        '--star-size': `${size}px`,
        '--blink-delay': `${delay.toFixed(2)}s`,
      },
    })
  }

  return generatedStars
}
</script>

<style scoped>
.loginView {
  position: relative;
  min-height: 100dvh;
  overflow: hidden;
  background:
    linear-gradient(164deg, rgb(18 24 34 / 1) 0%, rgb(21 29 40 / 1) 56%, rgb(17 23 33 / 1) 100%);
}

.loginView :deep(.authSplit) {
  position: relative;
  z-index: 1;
  background: transparent;
  transition: opacity 140ms ease, visibility 140ms ease;
}

.loginView.isAnimationOnly :deep(.authSplit) {
  opacity: 0;
  visibility: hidden;
  pointer-events: none;
}

.loginStars {
  position: absolute;
  inset: 0;
  pointer-events: none;
}

.loginStar {
  position: absolute;
  z-index: 0;
}

.loginStar::before,
.loginStar::after {
  position: absolute;
  content: '';
  background-color: #fff;
  border-radius: 10px;
  animation: loginStarBlink 1.5s infinite;
  animation-delay: var(--blink-delay);
}

.loginStar::before {
  top: calc(var(--star-size) / 2);
  left: calc(var(--star-size) / -2);
  width: calc(3 * var(--star-size));
  height: var(--star-size);
}

.loginStar::after {
  top: calc(var(--star-size) / -2);
  left: calc(var(--star-size) / 2);
  width: var(--star-size);
  height: calc(3 * var(--star-size));
}

.login-success-fade-enter-active,
.login-success-fade-leave-active {
  transition: opacity 240ms ease;
}

.login-success-fade-enter-from,
.login-success-fade-leave-to {
  opacity: 0;
}

.loginSuccessOverlay {
  position: fixed;
  inset: 0;
  z-index: 90;
  overflow: hidden;
  pointer-events: none;
  background: transparent;
}

.loginSuccessOverlay__scene {
  position: absolute;
  inset: 0;
  overflow: hidden;
  perspective: 10rem;
  isolation: isolate;
}

.loginSuccessOverlay__canvas {
  position: absolute;
  inset: 0;
  z-index: 1;
}

.loginSuccessOverlay__canvas :deep(canvas) {
  width: 100% !important;
  height: 100% !important;
  display: block;
}

.rain {
  position: absolute;
  width: 16px;
  height: 160px;
  border-radius: 999px;
  background: rgb(198 200 215 / 0.38);
  z-index: 0;
}

.rain1 {
  left: 3rem;
  top: 20rem;
  animation: raining 4s linear infinite both -2s;
}

.rain2 {
  left: 14rem;
  top: 8rem;
  animation: raining 4s linear infinite both -4s;
}

.rain3 {
  right: 17rem;
  top: 5rem;
  animation: raining 4s linear infinite both -4s;
}

.rain4 {
  left: 50rem;
  top: 1rem;
  animation: raining 4s linear infinite both -4.5s;
}

.rain5 {
  right: 35rem;
  top: 25rem;
  animation: raining 4s linear infinite both -1s;
}

.rain6 {
  left: 45rem;
  top: 40rem;
  animation: raining 4s linear infinite both -2.5s;
}

.rain7 {
  right: 15rem;
  top: 50rem;
  animation: raining 4s linear infinite both -1s;
}

.rain8 {
  left: 22rem;
  top: 35rem;
  animation: raining 4s linear infinite both -1s;
}

.rain9 {
  right: 45rem;
  top: 50rem;
  animation: raining 4s linear infinite both -1.5s;
}

.rain10 {
  right: 15rem;
  top: 50rem;
  animation: raining 4s linear infinite both -1s;
}

.drop {
  position: absolute;
  width: 14px;
  height: 50px;
  border-radius: 999px;
  background: rgb(198 200 215 / 0.38);
  z-index: 0;
}

.drop2 {
  left: 45rem;
  top: 32rem;
  animation: raining 4s linear infinite both -1s;
}

.drop5 {
  left: 70rem;
  top: 30rem;
  animation: raining 4s linear infinite both -3.4s;
}

.drop8 {
  left: 15rem;
  top: 38rem;
  animation: raining 4s linear infinite both -2.4s;
}

.drop11 {
  left: 45rem;
  top: 50rem;
  animation: raining 4s linear infinite both -1.4s;
}

.drop12 {
  left: 30rem;
  top: 55rem;
  animation: raining 4s linear infinite both -3.4s;
}

@keyframes raining {
  from {
    transform: translateY(-60rem);
  }
  to {
    transform: translateY(6rem);
  }
}

@keyframes loginStarBlink {
  0%,
  100% {
    transform: scale(1);
    opacity: 1;
  }

  50% {
    transform: scale(0.4);
    opacity: 0.5;
  }
}

@media (max-width: 900px) {
  .rain,
  .drop {
    opacity: 0.52;
  }
}

@media (max-width: 768px) {
  .rain {
    width: 10px;
    height: 110px;
    opacity: 0.28;
  }

  .drop {
    width: 9px;
    height: 34px;
    opacity: 0.24;
  }

  .rain4,
  .rain5,
  .rain6,
  .rain7,
  .rain9,
  .rain10,
  .drop5,
  .drop11,
  .drop12 {
    display: none;
  }

  .rain1 {
    left: 1.2rem;
    top: 8.4rem;
  }

  .rain2 {
    left: 6.8rem;
    top: 2.2rem;
  }

  .rain3 {
    right: 2.4rem;
    top: 1rem;
  }

  .rain8 {
    left: 12rem;
    top: 10.5rem;
  }

  .drop2 {
    left: 9.2rem;
    top: 13.5rem;
  }

  .drop8 {
    left: 5.8rem;
    top: 17rem;
  }
}
</style>
