import {expect,test} from '@playwright/test';

test('guest searches, filters, sorts, opens detail, and uses browser history without overflow',async({page})=>{
  await page.goto('/catalog');
  await expect(page.getByRole('button',{name:/Login/}).first()).toBeVisible();
  await expect(page.locator('.catalog-item-card')).toHaveCount(4);
  await expect(page.locator('.catalog-item-card img').first()).toBeVisible();
  const artworkInset=await page.locator('.catalog-item-card').first().evaluate(card=>{
    const frame=card.querySelector('.catalog-art')!.getBoundingClientRect();
    const image=card.querySelector('img')!.getBoundingClientRect();
    return {horizontal:frame.width-image.width,vertical:frame.height-image.height};
  });
  expect(artworkInset.horizontal).toBeGreaterThanOrEqual(19);
  expect(artworkInset.vertical).toBeGreaterThanOrEqual(19);
  const dropdownPalette=await page.getByLabel(/Category|Kategori/).locator('option').first().evaluate(option=>{
    const style=getComputedStyle(option);
    return {background:style.backgroundColor,color:style.color};
  });
  expect(dropdownPalette.background).not.toBe('rgb(255, 255, 255)');
  expect(dropdownPalette.color).not.toBe(dropdownPalette.background);

  const search=page.getByRole('searchbox',{name:/Search name or alias|Cari nama atau alias/});
  await search.fill('Abyssal');
  await search.press('Enter');
  await expect(page).toHaveURL(/q=Abyssal/);
  await expect(page.locator('.catalog-item-card')).toHaveCount(1);
  await page.locator('.catalog-item-card').first().click();
  await expect(page).toHaveURL(/\/catalog\/[0-9a-f-]+$/);
  await expect(page.getByText(/2[,.]400 PTS/)).toBeVisible();
  await expect(page.getByRole('link',{name:/Find trades for this item|Cari trade item ini/})).toBeVisible();

  await page.goBack();
  await expect(page.getByRole('searchbox')).toHaveValue('Abyssal');
  await search.fill('');
  await search.press('Enter');
  await page.getByLabel(/Category|Kategori/).selectOption('boat');
  await expect(page).toHaveURL(/category=boat/);
  await expect(page.getByRole('heading',{name:'Tidebreaker'})).toBeVisible();
  await page.getByLabel(/Sort|Urutkan/).selectOption('name_desc');
  await expect(page).toHaveURL(/sort=name_desc/);

  const overflow=await page.evaluate(()=>document.documentElement.scrollWidth-document.documentElement.clientWidth);
  expect(overflow).toBeLessThanOrEqual(1);
});

test('guest login opens over the catalog and preserves the current URL',async({page})=>{
  await page.goto('/catalog?category=boat&sort=name_desc');
  await page.getByRole('button',{name:/Login/}).click();
  await expect(page.getByRole('dialog')).toBeVisible();
  await expect(page.getByLabel('Email')).toBeFocused();
  await expect(page).toHaveURL(/\/catalog\?category=boat&sort=name_desc/);
  await page.keyboard.press('Escape');
  await expect(page.getByRole('dialog')).toHaveCount(0);
  await expect(page.getByRole('button',{name:/Login/})).toBeFocused();
});

test('authenticated header exposes account menu and trade action shortcut',async({page})=>{
  await page.goto('/login');
  await page.getByLabel('Email').fill('e2e@example.test');
  await page.getByLabel('Password').fill('E2ePassword123!');
  await page.getByRole('button',{name:'Login'}).click();
  await expect(page).toHaveURL('/');
  await page.goto('/catalog');

  const catalogUrl=page.url();
  const tradeActionButton=page.locator('button[aria-controls="trade-action-popover"]');
  await tradeActionButton.click();
  await expect(page.getByRole('dialog',{name:/Trade actions|Tindakan trade/})).toBeVisible();
  await expect(page).toHaveURL(catalogUrl);
  await expect(page.locator('.notification-footer')).toHaveAttribute('href','/negotiations');
  await page.keyboard.press('Escape');
  await expect(tradeActionButton).toBeFocused();
  await page.getByRole('button',{name:/Account menu|Menu akun/}).click();
  await expect(page.getByRole('link',{name:/Profile|Profil/}).last()).toHaveAttribute('href','/profile');
  await expect(page.getByRole('button',{name:'Logout'})).toBeVisible();
  await page.keyboard.press('Escape');
  await expect(page.getByRole('button',{name:'Logout'})).toHaveCount(0);
  await expect(page.getByRole('button',{name:/Account menu|Menu akun/})).toBeFocused();
  await page.getByRole('button',{name:/Account menu|Menu akun/}).click();
  await page.getByRole('heading',{name:/Catalog|Katalog/}).click();
  await expect(page.getByRole('button',{name:'Logout'})).toHaveCount(0);
});
