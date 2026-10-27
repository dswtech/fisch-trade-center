import {expect,test,type Page} from '@playwright/test';
import axe from 'axe-core';

const wcagTags=['wcag2a','wcag2aa','wcag21a','wcag21aa','wcag22aa'];

async function expectAccessible(page:Page):Promise<void>{
  await page.addScriptTag({content:axe.source});
  const violations=await page.evaluate(async(tags)=>{
    const axeApi=(window as typeof window&{axe:typeof axe}).axe;
    const result=await axeApi.run(document,{runOnly:{type:'tag',values:tags}});
    return result.violations.map(({id,impact,help,nodes})=>({id,impact,help,nodes:nodes.map(({target,failureSummary})=>({target,failureSummary}))}));
  },wcagTags);
  expect(violations,JSON.stringify(violations,null,2)).toEqual([]);
}

test('public and authenticated surfaces meet WCAG A/AA',async({page})=>{
  for(const path of ['/','/login','/search','/catalog']){
    await page.goto(path);
    await expectAccessible(page);
  }

  await page.goto('/login');
  await page.getByLabel('Email').fill('sysadmin@ftc.test');
  await page.getByLabel('Password').fill('Sysadmin123!');
  await page.getByRole('button',{name:'Login'}).click();
  await expect(page).toHaveURL('/');

  for(const path of ['/profile','/simulator','/admin']){
    await page.goto(path);
    await expectAccessible(page);
  }

  await page.goto('/simulator');
  await page.getByRole('button',{name:'Add item You give'}).click();
  await expectAccessible(page);
});
