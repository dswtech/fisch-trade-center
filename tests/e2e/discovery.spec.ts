import {expect,test} from '@playwright/test';

test('public discovery supports filters, theme persistence, redaction, and responsive width',async({page})=>{
  await page.goto('/');
  await expect(page).toHaveTitle(/Discover Trades|Temukan Trade/);
  await expect(page.getByText('SYNTHETIC DEMO DATA')).toHaveCount(1);

  const filterButton=page.getByRole('button',{name:/Filters|Filter/});
  await filterButton.click();
  await expect(page.getByRole('dialog')).toBeVisible();
  await page.keyboard.press('Escape');
  await expect(page.getByRole('dialog')).toBeHidden();
  await expect(filterButton).toBeFocused();

  await page.getByRole('button',{name:'Toggle theme'}).click();
  await expect.poll(()=>page.evaluate(()=>localStorage.getItem('ftc-theme'))).toBe('light');
  await page.reload();
  await expect(page.locator('html')).toHaveAttribute('data-theme','light');

  const html=await page.content();
  expect(html).not.toContain('e2e@example.test');
  expect(html).not.toContain('PrivateUSN');
  expect(await page.evaluate(()=>document.documentElement.scrollWidth-document.documentElement.clientWidth)).toBe(0);
});

test('keyboard reaches a visible interactive control',async({page})=>{
  await page.goto('/');
  await page.keyboard.press('Tab');
  await expect(page.locator(':focus')).toBeVisible();
  await expect(page.locator(':focus')).not.toHaveCSS('outline-style','none');
});

test('combined filters expose loading state and continue with a deterministic cursor',async({page})=>{
  await page.goto('/search');
  await page.getByRole('button',{name:/Filters|Filter/}).click();
  await page.getByLabel('Offered item').selectOption({label:'Abyssal Current'});
  await page.getByLabel('Wanted item').selectOption({label:'Aurora Rod'});
  await page.getByLabel('Category').selectOption({label:'Skins'});
  await page.getByLabel('Minimum value').fill('2400');
  await page.getByLabel('Maximum value').fill('2400');
  await page.getByLabel('Freshness').selectOption('hour');
  await page.route('**/search?**',async route=>{await new Promise(resolve=>setTimeout(resolve,350));await route.continue()},{times:1});
  await page.getByRole('button',{name:'Apply filters'}).click();
  await expect(page.getByRole('status')).toContainText('Searching active trades');
  await expect.poll(()=>new URL(page.url()).searchParams.get('freshness')).toBe('hour');
  const params=new URL(page.url()).searchParams;
  expect(params.get('offeredItem')).toBeTruthy();
  expect(params.get('wantedItem')).toBeTruthy();
  expect(params.get('category')).toBeTruthy();
  expect(params.get('minValue')).toBe('2400');
  expect(params.get('maxValue')).toBe('2400');

  const cards=page.locator('[data-listing-id]');
  await expect(cards).toHaveCount(24);
  const firstPageIds=await cards.evaluateAll(elements=>elements.map(element=>element.getAttribute('data-listing-id')));
  await page.getByRole('link',{name:'Next page'}).click();
  await expect(page).toHaveURL(/cursor=/);
  const secondPageIds=await cards.evaluateAll(elements=>elements.map(element=>element.getAttribute('data-listing-id')));
  expect(secondPageIds.length).toBeGreaterThan(0);
  expect(secondPageIds.length).toBeLessThan(24);
  expect(firstPageIds.filter(id=>secondPageIds.includes(id))).toEqual([]);
});
