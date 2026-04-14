import { expect, test, type Page } from '@playwright/test'

async function openFiltersPanel(page: Page): Promise<void> {
  const toggle = page.getByRole('button', { name: /zobraziť filtre|zobrazit filtre|skryť filtre|skryt filtre/i }).first()
  await expect(toggle).toBeVisible()

  if ((await toggle.getAttribute('aria-expanded')) !== 'true') {
    await toggle.click()
  }

  await expect(page.locator('.filters-content')).toBeVisible()
}

test.describe('events filters ux', () => {
  test('quick period presets and active chips stay in sync', async ({ page }) => {
    await page.goto('/events')

    await expect(page.getByRole('heading', { level: 1, name: /astronomick.*udalost/i })).toBeVisible()

    await openFiltersPanel(page)

    await page.getByRole('button', { name: 'Tento rok' }).click()
    await expect(page).toHaveURL(/\/events\?.*period=year/i)

    await page
      .getByRole('tablist', { name: /Časový rozsah udalostí|Casovy rozsah udalosti/i })
      .getByRole('button', { name: /Minulé|Minule/i })
      .click()
    await expect(page).toHaveURL(/\/events\?.*scope=past/i)

    const searchInput = page.getByRole('searchbox', { name: /hľadaj|hladaj/i })
    await searchInput.fill('mars')

    const searchChip = page.locator('.active-filters .filter-chip').filter({ hasText: /mars/i }).first()
    await expect(searchChip).toBeVisible()

    await searchChip.click()
    await expect(searchInput).toHaveValue('')

    const regionSelect = page.getByLabel(/región|region/i)
    await regionSelect.selectOption('global')

    await expect(page.locator('.active-filters .filter-chip').filter({ hasText: /glob/i })).toBeVisible()

    await page.locator('.active-filters .filter-chip-clear').first().click()

    await expect(page).toHaveURL(/\/events\?.*scope=future/i)
    await expect(searchInput).toHaveValue('')
    await expect(page.locator('.active-filters')).toHaveCount(0)
  })
})
