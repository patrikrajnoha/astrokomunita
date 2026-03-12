import { expect, test, type Page } from '@playwright/test'

async function openFiltersPanel(page: Page): Promise<void> {
  const toggle = page.getByRole('button', { name: /zobrazit filtre|skryt filtre/i }).first()
  await expect(toggle).toBeVisible()

  if ((await toggle.getAttribute('aria-expanded')) !== 'true') {
    await toggle.click()
  }

  await expect(page.locator('.filters-content')).toBeVisible()
}

test.describe('events filters ux', () => {
  test('quick period presets and active chips stay in sync', async ({ page }) => {
    await page.goto('/events')

    await expect(page.getByRole('heading', { level: 1, name: /astronomicke udalosti/i })).toBeVisible()

    await openFiltersPanel(page)

    await page.getByRole('button', { name: 'Tento rok' }).click()
    await expect(page).toHaveURL(/\/events\?.*period=year/i)

    await page.getByRole('button', { name: 'Minule' }).click()
    await expect(page).toHaveURL(/\/events\?.*scope=past/i)

    const searchInput = page.getByLabel('Hladaj')
    await searchInput.fill('mars')

    const searchChip = page.locator('.active-filters .filter-chip').filter({ hasText: 'Hladat: mars' }).first()
    await expect(searchChip).toBeVisible()

    await searchChip.click()
    await expect(searchInput).toHaveValue('')

    const regionSelect = page.getByLabel('Region')
    await regionSelect.selectOption('global')

    await expect(page.locator('.active-filters .filter-chip').filter({ hasText: 'Region: Globalne' })).toBeVisible()

    await page.locator('.active-filters .filter-chip-clear').first().click()

    await expect(page).toHaveURL(/\/events\?.*scope=future/i)
    await expect(searchInput).toHaveValue('')
    await expect(page.locator('.active-filters')).toHaveCount(0)
  })
})
