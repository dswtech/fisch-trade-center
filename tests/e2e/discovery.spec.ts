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
