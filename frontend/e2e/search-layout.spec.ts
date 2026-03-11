import { expect, test } from '@playwright/test'

test.describe('search layout smoke', () => {
  test('search page keeps visible input, stable columns and no horizontal overflow', async ({ page }) => {
    await page.goto('/search')

    const searchInput = page.getByRole('searchbox', { name: /search|hľadať|hladat/i }).first()
    await expect(searchInput).toBeVisible()

    const mainColumn = page.locator('main').first()
    await expect(mainColumn).toBeVisible()
    await expect(page.getByRole('navigation', { name: /search tabs|karty hľadania|karty hladania/i })).toBeVisible()
    await expect(page.getByRole('heading', { level: 2, name: /top|správy|spravy|udalosti/i }).first()).toBeVisible()

    const hasHorizontalOverflow = await page.evaluate(() => {
      const tolerance = 2
      return document.documentElement.scrollWidth > window.innerWidth + tolerance
    })
    expect(hasHorizontalOverflow).toBe(false)

    const center = page.locator('main').first()
    const rightRail = page.locator('aside[aria-label="Right sidebar"]').first()

    if (await center.isVisible() && await rightRail.isVisible()) {
      const centerBox = await center.boundingBox()
      const rightRailBox = await rightRail.boundingBox()
      expect(centerBox).not.toBeNull()
      expect(rightRailBox).not.toBeNull()

      if (centerBox && rightRailBox) {
        expect(centerBox.x + centerBox.width).toBeLessThanOrEqual(rightRailBox.x + 2)
      }
    }
  })
})
