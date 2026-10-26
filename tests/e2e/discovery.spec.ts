import {expect,test} from '@playwright/test';

test('public discovery supports filters, theme persistence, redaction, and responsive width',async({page})=>{
  await page.goto('/');
  await expect(page).toHaveTitle(/Discover Trades|Temukan Trade/);
  await expect(page.locator('.demo-badge')).toHaveCount(0);
  await expect(page.locator('.recommendation-card').getByRole('link',{name:/Open simulator|Buka simulator/})).toBeVisible();

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

test('wide discovery centers the balance orb on the give and receive boundary',async({page})=>{
  await page.setViewportSize({width:1800,height:1000});
  await page.goto('/');
  const give=await page.locator('.give-panel').boundingBox();
  const orb=await page.locator('.balance-orb').boundingBox();
  expect(give).not.toBeNull();
  expect(orb).not.toBeNull();
  expect(Math.abs((give!.x+give!.width)-(orb!.x+orb!.width/2))).toBeLessThanOrEqual(3);
});

test('combined filters expose loading state and continue with a deterministic cursor',async({page})=>{
  await page.goto('/search');
  await page.getByRole('button',{name:/Filters|Filter/}).click();
  await page.getByLabel('Offered item').selectOption({label:'Abyssal Current'});
  await page.getByLabel('Wanted item').selectOption({label:'Tidebreaker'});
  await page.getByLabel('Category').selectOption({label:'Rod Skin'});
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
  await page.getByRole('button',{name:'Next page'}).click();
  await expect(page).toHaveURL(/cursor=/);
  const secondPageIds=await cards.evaluateAll(elements=>elements.map(element=>element.getAttribute('data-listing-id')));
  expect(secondPageIds.length).toBeGreaterThan(0);
  expect(secondPageIds.length).toBeLessThan(24);
  expect(firstPageIds.filter(id=>secondPageIds.includes(id))).toEqual([]);
});

test('failed search preserves results and retries only after user action',async({page})=>{
  await page.goto('/search');
  const before=await page.locator('[data-listing-id]').count();
  let attempts=0;
  await page.route('**/search?**',async route=>{
    attempts+=1;
    if(attempts===1)await route.abort('failed'); else await route.continue();
  });
  await page.getByRole('button',{name:/Filters|Filter/}).click();
  await page.getByLabel('Freshness').selectOption('hour');
  await page.getByRole('button',{name:'Apply filters'}).click();
  const recovery=page.locator('.search-feedback.error');
  await expect(recovery).toContainText(/connection was interrupted|Koneksi terputus/i);
  await expect(page.locator('[data-listing-id]')).toHaveCount(before);
  await recovery.getByRole('button',{name:/Try again|Coba lagi/}).click();
  await expect(page).toHaveURL(/freshness=hour/);
  expect(attempts).toBe(2);
});
