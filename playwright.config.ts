import {defineConfig,devices} from '@playwright/test';

export default defineConfig({
  testDir:'./tests/e2e',
  fullyParallel:false,
  workers:1,
  retries:0,
  reporter:[['list'],['html',{open:'never'}]],
  use:{baseURL:'http://127.0.0.1:8011',trace:'retain-on-failure',screenshot:'only-on-failure',channel:process.env.PLAYWRIGHT_CHANNEL||'msedge'},
  webServer:{command:'powershell -NoProfile -ExecutionPolicy Bypass -File tests/e2e/start-server.ps1',url:'http://127.0.0.1:8011/up',reuseExistingServer:false,timeout:120000},
  projects:[
    {name:'desktop',use:{...devices['Desktop Chrome'],viewport:{width:1440,height:1000}}},
    {name:'mobile',use:{...devices['Pixel 7'],channel:process.env.PLAYWRIGHT_CHANNEL||'msedge'}},
  ],
});
