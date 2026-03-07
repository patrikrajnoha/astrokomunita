import { expect, test, type Page } from '@playwright/test'

const USER_EMAIL = process.env.E2E_USER_EMAIL || 'admin@admin.sk'
const USER_PASSWORD = process.env.E2E_USER_PASSWORD || 'admin'
const API_ORIGIN = process.env.E2E_API_ORIGIN || 'http://127.0.0.1:8001'

async function loginViaUi(page: Page, email: string, password: string): Promise<void> {
  await page.goto('/login')
  await page.locator('input[type="email"]').fill(email)
  await page.locator('input[type="password"]').fill(password)
  await page.locator('button[type="submit"]').click()
  await expect(page).not.toHaveURL(/\/login(?:\?|$)/)
}

async function authenticatedUserId(page: Page): Promise<number> {
  return page.evaluate(async () => {
    const response = await fetch('/api/auth/me', {
      method: 'GET',
      credentials: 'include',
      headers: {
        Accept: 'application/json',
      },
    })

    if (!response.ok) {
      return 0
    }

    const payload = await response.json()
    return Number(payload?.id || 0)
  })
}

async function ensureRealtimeReady(page: Page): Promise<void> {
  await expect.poll(async () => page.evaluate(() => Boolean((window as any).Echo))).toBe(true)

  const userId = await authenticatedUserId(page)
  expect(userId).toBeGreaterThan(0)

  await expect.poll(
    () =>
      page.evaluate((id) => {
        const channels = (window as any).Echo?.connector?.pusher?.channels?.channels
        if (!channels || typeof channels !== 'object') return false
        return Boolean(channels[`private-users.${id}`]?.subscribed)
      }, userId),
    { timeout: 30_000 },
  ).toBe(true)
}

async function triggerDevNotification(
  page: Page,
  contestName: string,
): Promise<{ ok: boolean; status: number; body: string }> {
  const result = await page.evaluate(
    async ({ apiOrigin, contestNameValue }) => {
      const cookie = (name: string): string => {
        const row = document.cookie
          .split('; ')
          .find((item) => item.startsWith(`${name}=`))
        return row ? decodeURIComponent(row.slice(name.length + 1)) : ''
      }

      await fetch(`${apiOrigin}/sanctum/csrf-cookie`, {
        method: 'GET',
        credentials: 'include',
      })

      const xsrfToken = cookie('XSRF-TOKEN')
      const response = await fetch(`${apiOrigin}/api/notifications/dev-test`, {
        method: 'POST',
        credentials: 'include',
        headers: {
          Accept: 'application/json',
          'Content-Type': 'application/json',
          'X-Requested-With': 'XMLHttpRequest',
          ...(xsrfToken ? { 'X-XSRF-TOKEN': xsrfToken } : {}),
        },
        body: JSON.stringify({
          type: 'contest_winner',
          contest_id: Date.now(),
          contest_name: contestNameValue,
          post_id: 1,
        }),
      })

      return {
        ok: response.ok,
        status: response.status,
        body: await response.text(),
      }
    },
    { apiOrigin: API_ORIGIN, contestNameValue: contestName },
  )

  return result
}

async function notificationBadgeCount(page: Page): Promise<number> {
  return page.evaluate(() => {
    const badges = Array.from(document.querySelectorAll('a[href="/notifications"] .notificationBadge'))
    if (!badges.length) return 0

    const visibleBadge =
      badges.find((element) => {
        const htmlElement = element as HTMLElement
        return htmlElement.offsetParent !== null
      }) || badges[0]

    const text = (visibleBadge.textContent || '').trim()
    const match = text.match(/\d+/)
    return match ? Number.parseInt(match[0], 10) : 0
  })
}

test.describe.configure({ retries: 1 })

test('realtime notification appears in another context without refresh', async ({ browser, page }) => {
  await loginViaUi(page, USER_EMAIL, USER_PASSWORD)

  const secondContext = await browser.newContext()
  const secondPage = await secondContext.newPage()

  try {
    await loginViaUi(secondPage, USER_EMAIL, USER_PASSWORD)
    await secondPage.waitForLoadState('networkidle')
    await ensureRealtimeReady(secondPage)

    const beforeUrl = secondPage.url()
    const beforeBadge = await notificationBadgeCount(secondPage)
    expect(beforeBadge).toBeLessThan(99)

    const triggerResult = await triggerDevNotification(page, `Playwright Smoke ${Date.now()}`)
    if (!triggerResult.ok && triggerResult.status === 404) {
      test.skip(true, `Dev notification endpoint unavailable (${triggerResult.status}).`)
    }
    expect(
      triggerResult.ok,
      `Dev notification trigger failed (${triggerResult.status}): ${triggerResult.body}`,
    ).toBeTruthy()

    await expect.poll(() => notificationBadgeCount(secondPage), { timeout: 30_000 }).toBeGreaterThan(beforeBadge)
    await expect.poll(() => secondPage.url(), { timeout: 30_000 }).toBe(beforeUrl)
  } finally {
    await secondContext.close()
  }
})
