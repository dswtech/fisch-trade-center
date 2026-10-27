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
  await page.locator('.listing-composer-side').filter({hasText:/You Give|Kamu Berikan/}).getByRole('button',{name:/Add item|Tambah item/}).click();
  await page.locator('.listing-item-picker').getByRole('button',{name:/Abyssal Current/}).click();
  await page.locator('.listing-composer-side').filter({hasText:/You Want|Kamu Cari/}).getByRole('button',{name:/Add item|Tambah item/}).click();
  await page.locator('.listing-item-picker').getByRole('searchbox').fill('Tidebreaker');
  await page.locator('.listing-item-picker').getByRole('button',{name:/Tidebreaker/}).click();
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
  const resultBox=await page.locator('.simulator-balance .sim-result').boundingBox();
  expect(resultBox).not.toBeNull();
  expect(resultBox!.width).toBeLessThanOrEqual(96);
  expect(Math.abs(resultBox!.width-resultBox!.height)).toBeLessThanOrEqual(2);
  const overflow=await page.evaluate(()=>document.documentElement.scrollWidth-document.documentElement.clientWidth);
  expect(overflow).toBeLessThanOrEqual(1);
  await page.getByRole('button',{name:'Add item You give'}).click();
  await page.getByRole('dialog').getByRole('button',{name:/Abyssal Current/}).click();
  await page.getByRole('button',{name:'Add item You receive'}).click();
  await page.getByRole('dialog').getByRole('button',{name:/Abyssal Current/}).click();
  await page.getByRole('button',{name:/Calculate with active snapshot/}).click();
  await expect(page.locator('.simulator-balance .sim-result')).toContainText('fair');
  await expect(page.locator('.simulator-balance .sim-result')).toContainText('0.00%');
});

test('player menu pages retain the primary application shell',async({page})=>{
  for(const path of ['/catalog','/inventory','/my-listings','/simulator','/profile','/negotiations']){
    await page.goto(path);
    await expect(page.locator('.topbar')).toHaveCount(1);
    await expect(page.locator('.sidebar')).toHaveCount(1);
    await expect(page.locator('.module-header')).toHaveCount(0);
    await expect(page.locator('.module-nav')).toHaveCount(0);
  }
});

test('player requests more items and owner replies with a compact counteroffer',async({page,browser})=>{
  await page.goto('/');
  await page.getByRole('link',{name:/View match|Lihat kecocokan|Find a match|Cari kombinasi/}).first().click();
  await expect(page.getByRole('heading',{name:/Inventory recommendations|Rekomendasi inventory/})).toBeVisible();
  const firstRecommendation=page.locator('.recommendation-card').first();
  await expect(firstRecommendation.getByText(/W\/F\/L result|Hasil W\/F\/L/)).toBeVisible();
  await expect(firstRecommendation.locator('.status')).toContainText(/fair/i);
  await expect(firstRecommendation.locator('.recommendation-editor-items img')).toBeVisible();
  const cardBox=await firstRecommendation.boundingBox();
  expect(cardBox).not.toBeNull();
  expect(cardBox!.height).toBeLessThan(440);
  const quantityInput=firstRecommendation.locator('.recommendation-editor-items input[type="number"]').first();
  const initialQuantity=Number(await quantityInput.inputValue());
  await firstRecommendation.getByRole('button',{name:/Add item|Tambah item/}).click();
  await page.locator('.recommendation-picker>div>button').first().click();
  await expect(quantityInput).toHaveValue(String(initialQuantity+1));
  await expect(firstRecommendation.locator('.status')).toContainText(/lose/i);
  await firstRecommendation.getByRole('button',{name:/View summary|Lihat ringkasan/}).click();
  await expect(page.getByRole('heading',{name:/Proposal summary|Ringkasan proposal/})).toBeVisible();
  await expect(page.locator('.recommendation-review-items section').nth(1).locator('img')).toBeVisible();
  await page.getByRole('button',{name:/Ask other party to add items|Minta lawan tambah item/}).click();
  await expect(page).toHaveURL(/\/negotiations\//);
  const negotiationUrl=page.url();
  await expect(page.getByText(/Status: proposed|Status: proposed/i)).toBeVisible();
  await expect(page.getByText(/Waiting for the other party to add items|Menunggu lawan menambah item/)).toBeVisible();
  expect(await page.content()).not.toContain('SeedSecretUSN');
  expect(await page.content()).not.toContain('SeedSecretIGN');

  const ownerContext=await browser.newContext({baseURL:'http://127.0.0.1:8011'});
  const owner=await ownerContext.newPage();
  await owner.goto('/login');
  await owner.getByLabel('Email').fill('owner-fixture@example.test');
  await owner.getByLabel('Password').fill('E2ePassword123!');
  await owner.getByRole('button',{name:'Login'}).click();
  await expect(owner).toHaveURL('/');
  const beforeBellUrl=owner.url();
  const notificationTrigger=owner.locator('button[aria-controls="trade-action-popover"]');
  await notificationTrigger.click();
  await expect(owner.getByRole('dialog',{name:/Trade actions|Tindakan trade/})).toBeVisible();
  await expect(owner.locator('.notification-list>a')).toHaveCount(1);
  await expect(owner.locator('.notification-list>a')).toHaveAttribute('href',new URL(negotiationUrl).pathname);
  await expect(owner).toHaveURL(beforeBellUrl);
  await owner.keyboard.press('Escape');
  await expect(notificationTrigger).toBeFocused();
  await owner.goto(negotiationUrl);
  await expect(owner.getByRole('heading',{name:/The other party requests more items|Lawan meminta tambahan item/})).toBeVisible();
  await expect(owner.getByRole('button',{name:/Accept this version|Terima versi ini/})).toHaveCount(0);
  await owner.getByRole('button',{name:/Add items & reply|Tambah item & balas/}).click();
  await expect(owner.locator('.counter-composer')).toBeVisible();
  await expect(owner.locator('.counter-receive-side img')).toBeVisible();
  await owner.locator('.counter-composer').getByRole('button',{name:/Quantity \+|Jumlah \+/}).first().click();
  await owner.locator('.counter-composer').getByRole('button',{name:/View summary|Lihat ringkasan/}).click();
  await expect(owner.getByRole('heading',{name:/Counteroffer summary|Ringkasan counteroffer/})).toBeVisible();
  await expect(owner.locator('.recommendation-review-items section').nth(1).locator('img')).toBeVisible();
  await owner.getByRole('button',{name:/Send counteroffer|Kirim counteroffer/}).click();
  await expect(owner.locator('summary').filter({hasText:/Version 2/i})).toBeVisible();
  await ownerContext.close();
});

test('two players finalize a zero-fee deal before identity reveal',async({page,browser})=>{
  await page.goto('/profile');
  await page.getByLabel(/Roblox username/).fill('DealPrivateUSN');
  await page.getByLabel(/In-game name/).fill('DealPrivateIGN');
  await page.getByRole('button',{name:/^Save$/}).click();
  await page.goto('/');
  await page.getByRole('link',{name:/View match|Lihat kecocokan|Find a match|Cari kombinasi/}).first().click();
  await page.getByRole('button',{name:/View summary|Lihat ringkasan/}).first().click();
  await page.getByRole('button',{name:/Send proposal|Kirim proposal/}).click();
  await expect(page).toHaveURL(/\/negotiations\//);
  const negotiationUrl=page.url();
  await page.getByRole('button',{name:/Accept this version|Terima versi ini/}).click();

  const ownerContext=await browser.newContext({baseURL:'http://127.0.0.1:8011'});
  const owner=await ownerContext.newPage();
  await owner.goto('/login');
  await owner.getByLabel('Email').fill('owner-fixture@example.test');
  await owner.getByLabel('Password').fill('E2ePassword123!');
  await owner.getByRole('button',{name:'Login'}).click();
  await expect(owner).toHaveURL('/');
  await owner.goto(negotiationUrl);
  const ownerAcceptance=owner.getByRole('button',{name:/Accept this version|Terima versi ini/});
  await expect(ownerAcceptance).toBeEnabled();
  await ownerAcceptance.click();

  await page.reload();
  await page.getByRole('button',{name:/Propose server choice|Ajukan pilihan server/}).click();
  await owner.reload();
  await owner.getByRole('button',{name:/Accept this server|Setujui server ini/}).click();
  await expect(owner).toHaveURL(/\/deals\//);
  const dealUrl=owner.url();
  expect(await owner.content()).not.toContain('DealPrivateUSN');

  await page.goto(negotiationUrl);
  await page.getByRole('checkbox').check();
  await page.getByRole('button',{name:/Accept and reveal identities|Setujui dan buka identitas/}).click();
  await expect(page).toHaveURL(/\/deals\//);
  expect(await page.content()).not.toContain('SeedSecretUSN');

  await owner.goto(dealUrl);
  await owner.getByRole('checkbox').check();
  await owner.getByRole('button',{name:/Accept and reveal identities|Setujui dan buka identitas/}).click();
  await expect(owner.getByText('DealPrivateUSN')).toBeVisible();
  await page.reload();
  await expect(page.getByText('SeedSecretUSN')).toBeVisible();
  await ownerContext.close();
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
  await expect(page.locator('.topbar')).toHaveCount(1);
  await expect(page.locator('.sidebar')).toHaveCount(1);
  await expect(page.getByRole('heading',{name:'Trust operations'})).toBeVisible();
  await expect(page.getByText('No structured reports have been submitted.')).toBeVisible();
  await page.goto('/admin');
  await page.getByRole('link',{name:'Time controls'}).click();
  await expect(page).toHaveURL('/admin/time-controls');
  await expect(page.getByRole('heading',{name:'Non-production time controls'})).toBeVisible();
});
