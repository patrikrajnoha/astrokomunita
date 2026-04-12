import { nextTick, onBeforeUnmount, onMounted, ref } from 'vue'

// Login rocket animation inspired by:
// https://codepen.io/stivaliserna/pen/rNMwpaG
// Adapted for this app (Vue composable flow, Three.js runtime loading, GLTF/fallback model).
const LOGIN_SUCCESS_ANIMATION_MS = 2800
const MOBILE_BREAKPOINT_PX = 768
const ROCKET_MODEL_SCALE = 0.6
const ROCKET_BASE_Y = 24
const ROCKET_IDLE_LOOP_MS = 2000
const ROCKET_LAUNCH_STAGE_MS = 700
const SUCCESS_OVERLAY_BASE_OPACITY = 0.86
const SUCCESS_OVERLAY_MIN_OPACITY = 0.06
const THREE_SCRIPT_SRC = 'https://unpkg.com/three@0.123.0/build/three.min.js'
const GLTF_LOADER_SCRIPT_SRC = 'https://unpkg.com/three@0.123.0/examples/js/loaders/GLTFLoader.js'
const SCRIPT_READY_TIMEOUT_MS = 7000

const appBaseUrl = String(import.meta.env.BASE_URL || '/')
const normalizedBaseUrl = appBaseUrl.endsWith('/') ? appBaseUrl : `${appBaseUrl}/`
const ROCKET_MODEL_SRC = `${normalizedBaseUrl}animations/rocket/rocket.gltf`

// Module-level: shared across instances for deduplication
const externalScriptPromises = new Map()
let rocketTemplateScene = null
let rocketTemplatePromise = null

function findExistingScript(src) {
  return Array.from(document.querySelectorAll('script[src]')).find((el) => el.src === src) || null
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

function getRocketViewportConfig(viewportWidth) {
  if (viewportWidth <= MOBILE_BREAKPOINT_PX) {
    return {
      modelScale: 0.74,
      cameraZ: 430,
      cameraY: -4,
      floatAmplitude: 28,
      rotationSpeed: 0.07,
      launchDistance: 330,
    }
  }

  return {
    modelScale: ROCKET_MODEL_SCALE,
    cameraZ: 500,
    cameraY: -10,
    floatAmplitude: 40,
    rotationSpeed: 0.1,
    launchDistance: 480,
  }
}

function createFallbackRocket(THREE) {
  const group = new THREE.Group()
  const bodyMaterial = new THREE.MeshStandardMaterial({
    color: 0xf2f7ff,
    metalness: 0.2,
    roughness: 0.45,
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

export function useLoginAnimation() {
  const rocketCanvasRef = ref(null)
  const showSuccessAnimation = ref(false)
  const isAnimating = ref(false)
  const overlayOpacity = ref(SUCCESS_OVERLAY_BASE_OPACITY)

  let animationTimerId = null
  let rocketScene = null
  let rocketCamera = null
  let rocketRenderer = null
  let rocketModel = null
  let rocketThrusterLight = null
  let rocketThrusterFlame = null
  let rocketFrameId = null
  let rocketResizeHandler = null
  let rocketViewportConfig = null

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
    model.position.y = ROCKET_BASE_Y
    model.userData.baseY = ROCKET_BASE_Y
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
    rocketThrusterLight = null

    if (rocketThrusterFlame) {
      rocketThrusterFlame.geometry?.dispose?.()
      const flameMaterials = Array.isArray(rocketThrusterFlame.material)
        ? rocketThrusterFlame.material
        : [rocketThrusterFlame.material]
      flameMaterials.forEach((material) => material?.dispose?.())
    }

    rocketThrusterFlame = null
    rocketViewportConfig = null
    overlayOpacity.value = SUCCESS_OVERLAY_BASE_OPACITY
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
    rocketThrusterLight = new THREE.PointLight(0xff8e47, 0, 460, 2.2)
    rocketThrusterLight.position.set(0, ROCKET_BASE_Y - 70, 0)

    const thrusterFlameMaterial = new THREE.MeshStandardMaterial({
      color: 0xff994d,
      emissive: 0xff5e26,
      emissiveIntensity: 0.9,
      transparent: true,
      opacity: 0.12,
      roughness: 0.45,
      metalness: 0.05,
      depthWrite: false,
    })
    rocketThrusterFlame = new THREE.Mesh(new THREE.ConeGeometry(9, 34, 18), thrusterFlameMaterial)
    rocketThrusterFlame.position.set(0, ROCKET_BASE_Y - 78, 0)
    rocketThrusterFlame.rotation.x = Math.PI
    rocketThrusterFlame.renderOrder = 2

    rocketScene.add(
      ambientLight,
      directionalLight,
      pointLight,
      rocketThrusterLight,
      rocketThrusterFlame,
    )

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

    const animationStartTs = performance.now()
    const launchStartMs = Math.max(0, LOGIN_SUCCESS_ANIMATION_MS - ROCKET_LAUNCH_STAGE_MS)
    const loop = () => {
      if (!rocketRenderer || !rocketScene || !rocketCamera) {
        return
      }

      const elapsedMs = performance.now() - animationStartTs
      const t = (elapsedMs % ROCKET_IDLE_LOOP_MS) / ROCKET_IDLE_LOOP_MS
      rocketRenderer.render(rocketScene, rocketCamera)

      const targetRocketPosition = rocketViewportConfig?.floatAmplitude || 40
      const rotationSpeed = rocketViewportConfig?.rotationSpeed || 0.1
      const launchDistance = rocketViewportConfig?.launchDistance || 480
      const baseScale = rocketViewportConfig?.modelScale || ROCKET_MODEL_SCALE
      const idleLift = targetRocketPosition * Math.sin(Math.PI * 2 * t)
      const launchProgressRaw = elapsedMs <= launchStartMs
        ? 0
        : Math.min((elapsedMs - launchStartMs) / ROCKET_LAUNCH_STAGE_MS, 1)
      const launchProgress = launchProgressRaw ** 3
      const thrusterIdlePulse = 0.86 + (Math.sin(elapsedMs * 0.032) * 0.14)
      const thrusterBoost = launchProgressRaw > 0 ? launchProgress : 0
      const thrusterStrength = (0.08 + (thrusterBoost * 1.35)) * thrusterIdlePulse
      const overlayLaunchProgress = launchProgressRaw > 0 ? launchProgressRaw ** 1.25 : 0

      overlayOpacity.value = SUCCESS_OVERLAY_BASE_OPACITY
        - ((SUCCESS_OVERLAY_BASE_OPACITY - SUCCESS_OVERLAY_MIN_OPACITY) * overlayLaunchProgress)

      if (rocketModel) {
        const baseY = rocketModel.userData?.baseY ?? ROCKET_BASE_Y
        const rotationMultiplier = 1 - (launchProgress * 0.72)
        rocketModel.rotation.y += rotationSpeed * rotationMultiplier
        rocketModel.rotation.z = (idleLift / Math.max(targetRocketPosition, 1)) * 0.05 * (1 - launchProgress)
        rocketModel.position.y = baseY + idleLift - (launchDistance * launchProgress)
        rocketModel.scale.setScalar(baseScale * (1 + (launchProgress * 0.12)))
      }

      const thrusterY = (rocketModel?.position?.y ?? ROCKET_BASE_Y) - 72
      if (rocketThrusterLight) {
        rocketThrusterLight.position.set(0, thrusterY, 8)
        rocketThrusterLight.intensity = 0.12 + (thrusterStrength * 2.9)
      }

      if (rocketThrusterFlame) {
        rocketThrusterFlame.position.set(0, thrusterY - 9, 0)
        const flameScaleY = 0.65 + (thrusterStrength * 2.6)
        rocketThrusterFlame.scale.set(1, flameScaleY, 1)

        const flameMaterial = rocketThrusterFlame.material
        if (flameMaterial && !Array.isArray(flameMaterial)) {
          flameMaterial.opacity = 0.1 + (thrusterStrength * 0.75)
          flameMaterial.emissiveIntensity = 0.9 + (thrusterStrength * 2.1)
        }
      }

      rocketFrameId = requestAnimationFrame(loop)
    }

    loop()
  }

  async function waitForSuccessAnimation() {
    showSuccessAnimation.value = true
    isAnimating.value = true
    overlayOpacity.value = SUCCESS_OVERLAY_BASE_OPACITY

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

  function cancelAnimation() {
    if (animationTimerId !== null) {
      clearTimeout(animationTimerId)
      animationTimerId = null
    }

    stopRocketAnimation()
    showSuccessAnimation.value = false
    isAnimating.value = false
    overlayOpacity.value = SUCCESS_OVERLAY_BASE_OPACITY
  }

  onMounted(() => {
    void ensureRocketTemplateLoaded()
  })

  onBeforeUnmount(() => {
    cancelAnimation()
  })

  return {
    rocketCanvasRef,
    showSuccessAnimation,
    isAnimating,
    overlayOpacity,
    waitForSuccessAnimation,
    cancelAnimation,
  }
}
