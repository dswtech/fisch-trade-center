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
  await page.getByRole('button',{name:/Save profile/}).click();

  await page.goto('/inventory');
  await page.getByLabel('Catalog item').selectOption({label:'Abyssal Current'});
  await page.getByLabel('Quantity').fill('2');
  await page.getByRole('button',{name:/Add item/}).click();
  await expect(page.getByRole('heading',{name:'Abyssal Current'})).toBeVisible();

  await page.goto('/listings/create');
  await page.getByLabel('You offer').selectOption({index:1});
  await page.getByLabel('You want').selectOption({label:'Aurora Rod'});
  await page.getByRole('button',{name:/Publish for 14 days/}).click();
  await page.goto('/my-listings');
  await expect(page.getByText('active').first()).toBeVisible();

  await page.context().clearCookies();
  await page.goto('/');
  await page.getByRole('button',{name:/Filters|Filter/}).click();
  await page.getByLabel('Offered item').selectOption({label:'Abyssal Current'});
  await page.getByLabel('Wanted item').selectOption({label:'Aurora Rod'});
  await page.getByRole('button',{name:'Apply filters'}).click();
  await expect(page).toHaveURL(/\/search\?.*offeredItem=.*wantedItem=/);
  await expect(page.getByText('Abyssal Current').first()).toBeVisible();
  await expect(page.getByText('Aurora Rod').first()).toBeVisible();
  const html=await page.content();
  expect(html).not.toContain('PrivateUSN');
  expect(html).not.toContain('PrivateIGN');
  expect(html).not.toContain('e2e@example.test');
});

test('simulator reports fair at the positive boundary',async({page})=>{
  await page.goto('/simulator');
  await page.getByLabel('Give total').fill('1000');
  await page.getByLabel('Receive total').fill('1100');
  await expect(page.getByText('Fair for viewer')).toBeVisible();
  await expect(page.getByText('10.0%')).toBeVisible();
});
