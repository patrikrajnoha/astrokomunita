import { expect, test, type Page } from '@playwright/test'

const ADMIN_EMAIL = process.env.E2E_ADMIN_EMAIL || process.env.E2E_USER_EMAIL || 'admin@admin.sk'
const ADMIN_PASSWORD = process.env.E2E_ADMIN_PASSWORD || process.env.E2E_USER_PASSWORD || 'admin'
const REGULAR_EMAIL = process.env.E2E_REGULAR_EMAIL || 'patrik@patrik.sk'
const REGULAR_PASSWORD = process.env.E2E_REGULAR_PASSWORD || 'patrik'

type ApiResult = {
  ok: boolean
  status: number
  body: string
  data: Record<string, unknown> | null
}

async function loginViaUi(page: Page, email: string, password: string): Promise<void> {
  await page.goto('/login')
  await page.locator('input[type="email"]').fill(email)
  await page.locator('input[type="password"]').fill(password)
  await page.locator('button[type="submit"]').click()
  await expect(page).not.toHaveURL(/\/login(?:\?|$)/)
}

async function apiRequest(
  page: Page,
  method: 'GET' | 'POST' | 'PATCH' | 'DELETE',
  path: string,
  payload: Record<string, unknown> | null = null,
): Promise<ApiResult> {
  return page.evaluate(
    async ({ requestMethod, requestPath, requestPayload }) => {
      const readCookie = (name: string): string => {
        const cookieRow = document.cookie
          .split('; ')
          .find((item) => item.startsWith(`${name}=`))
        return cookieRow ? decodeURIComponent(cookieRow.slice(name.length + 1)) : ''
      }

      const headers: Record<string, string> = {
        Accept: 'application/json',
      }
      const normalizedMethod = String(requestMethod || 'GET').toUpperCase()

      if (normalizedMethod !== 'GET' && normalizedMethod !== 'HEAD') {
        await fetch('/sanctum/csrf-cookie', {
          method: 'GET',
          credentials: 'include',
        })

        const xsrfToken = readCookie('XSRF-TOKEN')
        headers['X-Requested-With'] = 'XMLHttpRequest'
        headers['Content-Type'] = 'application/json'
        if (xsrfToken) {
          headers['X-XSRF-TOKEN'] = xsrfToken
        }
      }

      const response = await fetch(requestPath, {
        method: normalizedMethod,
        credentials: 'include',
        headers,
        body: requestPayload ? JSON.stringify(requestPayload) : undefined,
      })

      const body = await response.text()
      let data: Record<string, unknown> | null = null
      try {
        data = body ? (JSON.parse(body) as Record<string, unknown>) : null
      } catch {
        data = null
      }

      return {
        ok: response.ok,
        status: response.status,
        body,
        data,
      }
    },
    {
      requestMethod: method,
      requestPath: path,
      requestPayload: payload,
    },
  )
}

async function createPost(page: Page, content: string): Promise<number> {
  const result = await apiRequest(page, 'POST', '/api/posts', { content })
  expect(result.ok, `Create post failed (${result.status}): ${result.body}`).toBeTruthy()
  expect(result.status).toBe(201)

  const id = Number(result.data?.id || 0)
  expect(id).toBeGreaterThan(0)
  return id
}

async function safeDeletePost(page: Page, id: number): Promise<void> {
  if (!Number.isInteger(id) || id <= 0) return
  await apiRequest(page, 'DELETE', `/api/posts/${id}`)
}

async function safeUnpinGlobalPost(page: Page, id: number): Promise<void> {
  if (!Number.isInteger(id) || id <= 0) return
  await apiRequest(page, 'PATCH', `/api/admin/posts/${id}/unpin`)
}

async function openCommunityFeed(page: Page): Promise<void> {
  await page.goto('/')
  const forYouTab = page.locator('#feed-tab-for-you')
  if (await forYouTab.count()) {
    if ((await forYouTab.getAttribute('aria-selected')) !== 'true') {
      await forYouTab.click()
    }
  }
}

async function waitForFeedCard(page: Page, marker: string): Promise<void> {
  const card = page.locator('article.post-card').filter({ hasText: marker }).first()
  await expect(card).toBeVisible()
}

async function waitForProfileCard(page: Page, marker: string): Promise<void> {
  const card = page.locator('.postList article.postItem').filter({ hasText: marker }).first()
  await expect(card).toBeVisible()
}

async function feedCardIndex(page: Page, marker: string): Promise<number> {
  return page.evaluate((needle) => {
    const cards = Array.from(document.querySelectorAll('article.post-card'))
    return cards.findIndex((card) => (card.textContent || '').includes(needle))
  }, marker)
}

async function profileCardIndex(page: Page, marker: string): Promise<number> {
  return page.evaluate((needle) => {
    const cards = Array.from(document.querySelectorAll('.postList article.postItem'))
    return cards.findIndex((card) => (card.textContent || '').includes(needle))
  }, marker)
}

async function pinFeedPostAsAdmin(page: Page, marker: string): Promise<void> {
  const card = page.locator('article.post-card').filter({ hasText: marker }).first()
  await expect(card).toBeVisible()
  await card.locator('.dropdownTrigger').click()
  await page.getByRole('menuitem', { name: 'Pripnut' }).click()
}

async function pinProfilePost(page: Page, marker: string): Promise<void> {
  const card = page.locator('.postList article.postItem').filter({ hasText: marker }).first()
  await expect(card).toBeVisible()
  await card.locator('.dropdownTrigger').click()
  await page.getByRole('menuitem', { name: 'Pripnut' }).click()
}

test.describe.configure({ retries: 1 })

test('admin can globally pin any post in feed and it moves above newer unpinned posts', async ({ browser, page }) => {
  const markerBase = Date.now()
  const regularPostMarker = `[e2e-global-pin-target-${markerBase}]`
  const adminPostMarker = `[e2e-global-pin-control-${markerBase}]`
  let regularPostId = 0
  let adminPostId = 0

  const regularContext = await browser.newContext()
  const regularPage = await regularContext.newPage()

  try {
    await loginViaUi(regularPage, REGULAR_EMAIL, REGULAR_PASSWORD)
    regularPostId = await createPost(regularPage, regularPostMarker)
  } finally {
    await regularContext.close()
  }

  await loginViaUi(page, ADMIN_EMAIL, ADMIN_PASSWORD)

  try {
    adminPostId = await createPost(page, adminPostMarker)

    await openCommunityFeed(page)
    await waitForFeedCard(page, regularPostMarker)
    await waitForFeedCard(page, adminPostMarker)

    await expect.poll(async () => {
      const targetIndex = await feedCardIndex(page, regularPostMarker)
      const controlIndex = await feedCardIndex(page, adminPostMarker)
      if (targetIndex < 0 || controlIndex < 0) return null
      return targetIndex - controlIndex
    }).toBeGreaterThan(0)

    await pinFeedPostAsAdmin(page, regularPostMarker)

    await expect.poll(async () => {
      const targetIndex = await feedCardIndex(page, regularPostMarker)
      const controlIndex = await feedCardIndex(page, adminPostMarker)
      if (targetIndex < 0 || controlIndex < 0) return null
      return targetIndex - controlIndex
    }).toBeLessThan(0)

    await expect(
      page.locator('article.post-card').filter({ hasText: regularPostMarker }).first().locator('.pinned-badge'),
    ).toBeVisible()
  } finally {
    await safeUnpinGlobalPost(page, regularPostId)
    await safeDeletePost(page, adminPostId)
    await safeDeletePost(page, regularPostId)
  }
})

test('user profile pin appears first on /profile but does not affect global feed order', async ({ page }) => {
  const markerBase = Date.now()
  const olderMarker = `[e2e-user-profile-pin-older-${markerBase}]`
  const newerMarker = `[e2e-user-profile-pin-newer-${markerBase}]`
  let olderPostId = 0
  let newerPostId = 0

  await loginViaUi(page, REGULAR_EMAIL, REGULAR_PASSWORD)

  try {
    olderPostId = await createPost(page, olderMarker)
    await page.waitForTimeout(1100)
    newerPostId = await createPost(page, newerMarker)

    await page.goto('/profile')
    await waitForProfileCard(page, olderMarker)
    await waitForProfileCard(page, newerMarker)

    await expect.poll(async () => {
      const olderIndex = await profileCardIndex(page, olderMarker)
      const newerIndex = await profileCardIndex(page, newerMarker)
      if (olderIndex < 0 || newerIndex < 0) return null
      return olderIndex - newerIndex
    }).toBeGreaterThan(0)

    await pinProfilePost(page, olderMarker)

    await expect(page.locator('.pinCard').filter({ hasText: olderMarker })).toBeVisible()
    await expect.poll(async () => {
      const olderIndex = await profileCardIndex(page, olderMarker)
      const newerIndex = await profileCardIndex(page, newerMarker)
      if (olderIndex < 0 || newerIndex < 0) return null
      return olderIndex - newerIndex
    }).toBeLessThan(0)

    await openCommunityFeed(page)
    await waitForFeedCard(page, olderMarker)
    await waitForFeedCard(page, newerMarker)
    await expect.poll(async () => {
      const olderIndex = await feedCardIndex(page, olderMarker)
      const newerIndex = await feedCardIndex(page, newerMarker)
      if (olderIndex < 0 || newerIndex < 0) return null
      return olderIndex - newerIndex
    }).toBeGreaterThan(0)
  } finally {
    await safeDeletePost(page, newerPostId)
    await safeDeletePost(page, olderPostId)
  }
})

test('admin profile pin appears first on /profile but does not affect global feed order', async ({ page }) => {
  const markerBase = Date.now()
  const olderMarker = `[e2e-admin-profile-pin-older-${markerBase}]`
  const newerMarker = `[e2e-admin-profile-pin-newer-${markerBase}]`
  let olderPostId = 0
  let newerPostId = 0

  await loginViaUi(page, ADMIN_EMAIL, ADMIN_PASSWORD)

  try {
    olderPostId = await createPost(page, olderMarker)
    await page.waitForTimeout(1100)
    newerPostId = await createPost(page, newerMarker)

    await page.goto('/profile')
    await waitForProfileCard(page, olderMarker)
    await waitForProfileCard(page, newerMarker)

    await expect.poll(async () => {
      const olderIndex = await profileCardIndex(page, olderMarker)
      const newerIndex = await profileCardIndex(page, newerMarker)
      if (olderIndex < 0 || newerIndex < 0) return null
      return olderIndex - newerIndex
    }).toBeGreaterThan(0)

    await pinProfilePost(page, olderMarker)

    await expect(page.locator('.pinCard').filter({ hasText: olderMarker })).toBeVisible()
    await expect.poll(async () => {
      const olderIndex = await profileCardIndex(page, olderMarker)
      const newerIndex = await profileCardIndex(page, newerMarker)
      if (olderIndex < 0 || newerIndex < 0) return null
      return olderIndex - newerIndex
    }).toBeLessThan(0)

    await openCommunityFeed(page)
    await waitForFeedCard(page, olderMarker)
    await waitForFeedCard(page, newerMarker)
    await expect.poll(async () => {
      const olderIndex = await feedCardIndex(page, olderMarker)
      const newerIndex = await feedCardIndex(page, newerMarker)
      if (olderIndex < 0 || newerIndex < 0) return null
      return olderIndex - newerIndex
    }).toBeGreaterThan(0)
  } finally {
    await safeDeletePost(page, newerPostId)
    await safeDeletePost(page, olderPostId)
  }
})
