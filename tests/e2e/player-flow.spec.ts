import {expect,test} from '@playwright/test';

test.beforeEach(async({page})=>{
  await page.goto('/login');
  await page.getByLabel('Email').fill('e2e@example.test');
  await page.getByLabel('Password').fill('E2ePassword123!');
  await page.getByRole('button',{name:'Login'}).click();
  await expect(page).toHaveURL('/');
});

test('verified player completes profile, inventory, and listing flow without identity leakage',async({page})=>{
  await page.goto('/profile');
  await page.getByLabel(/Roblox username/).fill('PrivateUSN');
  await page.getByLabel(/In-game name/).fill('PrivateIGN');
  await page.getByRole('button',{name:/^Save$/}).click();

  await page.goto('/inventory');
  await page.getByLabel('Catalog').selectOption({label:'Abyssal Current'});
  await page.getByLabel('Quantity',{exact:true}).fill('2');
  await page.getByRole('button',{name:/Add item/}).click();
  await expect(page.getByRole('heading',{name:'Abyssal Current'})).toBeVisible();

  await page.goto('/listings/create');
  await page.getByLabel('You offer').selectOption({index:1});
  await page.getByLabel('You want').selectOption({label:'Tidebreaker'});
  await page.getByRole('button',{name:/Publish for 14 days/}).click();
  await page.goto('/my-listings');
  await expect(page.getByText('active').first()).toBeVisible();

  await page.context().clearCookies();
  await page.goto('/');
  await page.getByRole('button',{name:/Filters|Filter/}).click();
  await page.getByLabel('Offered item').selectOption({label:'Abyssal Current'});
  await page.getByLabel('Wanted item').selectOption({label:'Tidebreaker'});
  await page.getByRole('button',{name:'Apply filters'}).click();
  await expect(page).toHaveURL(/\/search\?.*offeredItem=.*wantedItem=/);
  await expect(page.getByText('Abyssal Current').first()).toBeVisible();
  await expect(page.getByText('Tidebreaker').first()).toBeVisible();
  const html=await page.content();
  expect(html).not.toContain('PrivateUSN');
  expect(html).not.toContain('PrivateIGN');
  expect(html).not.toContain('e2e@example.test');
});

test('simulator reports fair for equal catalog items',async({page})=>{
  await page.goto('/simulator');
  await page.getByLabel('You give').selectOption({label:'Abyssal Current'});
  await page.getByLabel('You receive').selectOption({label:'Abyssal Current'});
  await page.getByRole('button',{name:/Calculate with active snapshot/}).click();
  await expect(page.locator('.sim-result')).toContainText('fair');
  await expect(page.locator('.sim-result')).toContainText('0.00%');
});

test('player starts an anonymous negotiation from a live recommendation',async({page})=>{
  await page.goto('/');
  await page.getByRole('link',{name:/Find a match|Cari kombinasi/}).first().click();
  await expect(page.getByRole('heading',{name:/Inventory recommendations|Rekomendasi inventory/})).toBeVisible();
  await expect(page.getByText(/FAIR for you|FAIR untuk kamu/).first()).toBeVisible();
  await page.getByRole('button',{name:/Send proposal|Kirim proposal/}).first().click();
  await expect(page).toHaveURL(/\/negotiations\//);
  await expect(page.getByText(/Status: proposed|Status: proposed/i)).toBeVisible();
  expect(await page.content()).not.toContain('SeedSecretUSN');
  expect(await page.content()).not.toContain('SeedSecretIGN');
});

test('failed profile mutation preserves private input and retries manually',async({page})=>{
  await page.goto('/profile');
  let attempts=0;
  await page.route('**/profile',async route=>{
    if(route.request().method()!=='PATCH')return route.continue();
    attempts+=1;
    if(attempts===1)await route.abort('failed'); else await route.continue();
  });
  await page.getByLabel(/Roblox username/).fill('RetryPrivateUSN');
  await page.getByLabel(/In-game name/).fill('RetryPrivateIGN');
  await page.getByRole('button',{name:/^Save$/}).click();
  const recovery=page.locator('.mutation-recovery');
  await expect(recovery).toContainText('input is preserved');
  await expect(page.getByLabel(/Roblox username/)).toHaveValue('RetryPrivateUSN');
  await recovery.getByRole('button',{name:'Try again'}).click();
  await expect(page.getByLabel(/Roblox username/)).toHaveValue('RetryPrivateUSN');
  expect(attempts).toBe(2);
});

test('administrator reaches the trust operations queue',async({page})=>{
  await page.context().clearCookies();
  await page.goto('/login');
  await page.getByLabel('Email').fill('sysadmin@ftc.test');
  await page.getByLabel('Password').fill('Sysadmin123!');
  await page.getByRole('button',{name:'Login'}).click();
  if ((page.viewportSize()?.width ?? 0) < 768) {
    await page.getByRole('button',{name:'Open menu'}).click();
  }
  await page.getByRole('link',{name:'Trust operations'}).click();
  await expect(page).toHaveURL('/operator/audit');
  await expect(page.getByRole('heading',{name:'Trust operations'})).toBeVisible();
  await expect(page.getByText('No structured reports have been submitted.')).toBeVisible();
});
