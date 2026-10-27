import {expect,test} from '@playwright/test';

test('public discovery supports filters, theme persistence, redaction, and responsive width',async({page})=>{
  await page.goto('/');
  await expect(page).toHaveTitle(/Discover Trades|Temukan Trade/);
  await expect(page.locator('.demo-badge')).toHaveCount(0);
  await expect(page.getByRole('heading',{name:/The Trade You Want|Trade yang Kamu Cari/})).toBeVisible();
  await expect(page.getByRole('heading',{name:/You Give|Kamu Berikan/})).toBeVisible();
  await expect(page.getByRole('heading',{name:/You Receive|Kamu Terima/})).toBeVisible();
  await expect(page.getByRole('heading',{name:/Market Insights|Insight Pasar/})).toBeVisible();
  await expect(page.getByRole('heading',{name:/Matches for Your Inventory|Cocok untuk Inventory-mu/})).toBeVisible();
  await expect(page.getByRole('button',{name:/Login to make an offer|Login untuk menawarkan/}).first()).toBeVisible();

  const filterButton=page.getByRole('button',{name:/Filters|Filter/});
  await filterButton.click();
  const filterDialog=page.getByRole('dialog');
  await expect(filterDialog).toBeVisible();
  const dialogBox=await filterDialog.boundingBox();
  const viewport=page.viewportSize()!;
  expect(dialogBox).not.toBeNull();
  if(viewport.width<=700){
    expect(Math.abs(dialogBox!.y+dialogBox!.height-viewport.height)).toBeLessThanOrEqual(2);
  }else{
    expect(Math.abs(dialogBox!.x+dialogBox!.width/2-viewport.width/2)).toBeLessThanOrEqual(3);
  }
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

test('wide discovery uses a feed and sticky insight rail without overlap',async({page})=>{
  await page.setViewportSize({width:1800,height:1000});
  await page.goto('/');
  const feed=await page.locator('.discovery-feed').boundingBox();
  const rail=await page.locator('.discovery-rail').boundingBox();
  const quickTrade=await page.locator('.quick-trade-zone').boundingBox();
  const discoveryHeading=await page.getByRole('heading',{name:/Discover Trades|Temukan Trade/}).boundingBox();
  expect(feed).not.toBeNull();
  expect(rail).not.toBeNull();
  expect(quickTrade).not.toBeNull();
  expect(discoveryHeading).not.toBeNull();
  expect(quickTrade!.y+quickTrade!.height).toBeLessThan(discoveryHeading!.y);
  expect(rail!.x).toBeGreaterThan(feed!.x+feed!.width);
  await expect(page.locator('.discovery-rail')).toHaveCSS('position','sticky');

  const give=await page.locator('.quick-trade-zone .give-panel').boundingBox();
  const receive=await page.locator('.quick-trade-zone .receive-panel').boundingBox();
  const orb=await page.locator('.quick-trade-zone .balance-orb').boundingBox();
  expect(give).not.toBeNull();
  expect(receive).not.toBeNull();
  expect(orb).not.toBeNull();
  expect(Math.abs(give!.height-receive!.height)).toBeLessThanOrEqual(2);
  expect(orb!.x).toBeGreaterThanOrEqual(give!.x+give!.width);
  expect(orb!.x+orb!.width).toBeLessThanOrEqual(receive!.x);

  const firstCard=await page.locator('[data-listing-id]').first().boundingBox();
  const firstRail=await page.locator('.rail-panel').first().boundingBox();
  expect(firstCard).not.toBeNull();
  expect(firstRail).not.toBeNull();
  expect(Math.abs(firstCard!.y-firstRail!.y)).toBeLessThanOrEqual(2);

  const marketToggle=page.locator('[aria-controls="market-panel-content"]');
  const personalToggle=page.locator('[aria-controls="personal-panel-content"]');
  await marketToggle.click();
  await expect(marketToggle).toHaveAttribute('aria-expanded','false');
  await expect(personalToggle).toHaveAttribute('aria-expanded','true');
  await personalToggle.click();
  await expect(personalToggle).toHaveAttribute('aria-expanded','false');
  expect((await page.locator('.discovery-feed').boundingBox())!.width).toBe(feed!.width);
});

test('guest trade action opens login over discovery and preserves the intended match',async({page})=>{
  await page.goto('/');
  const card=page.locator('[data-listing-id]').first();
  const listingId=await card.getAttribute('data-listing-id');
  await card.getByRole('button',{name:/Login to make an offer|Login untuk menawarkan/}).click();
  await expect(page.getByRole('dialog')).toBeVisible();
  await expect(page.locator('input[name="return_to"]')).toHaveValue(`/recommendations/${listingId}`);
  await expect(page).toHaveURL('/');
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
  await page.getByRole('button',{name:/^Next$|^Berikutnya$/}).click();
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
