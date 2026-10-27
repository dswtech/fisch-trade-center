import React from 'react';
import {renderToStaticMarkup} from 'react-dom/server';
import {describe,expect,it,vi} from 'vitest';

const tradeActions={pendingCount:3,items:[{negotiationId:'neg-1',alias:'Angler 42',stage:'proposal',actionAt:'2026-10-27T01:00:00+07:00',href:'/negotiations/neg-1'}]};

vi.mock('@inertiajs/react',()=>({
 Link:({href,children,...props}:any)=><a href={href} {...props}>{children}</a>,
 router:{post:vi.fn()},
 usePage:()=>({url:'/catalog?q=Abyssal',props:{tradeActions}}),
}));

import ProductShell from '../components/product-shell';

describe('product shell',()=>{
 it('keeps the primary header, sidebar, active section, and mobile navigation around workspace content',()=>{
  const html=renderToStaticMarkup(<ProductShell active="catalog" locale="id" onLocaleToggle={vi.fn()} user={{id:'user-1',name:'FTC Player',emailVerified:true,role:'player'}}><h1>Katalog</h1></ProductShell>);

  expect(html).toContain('class="topbar"');
  expect(html).toContain('class="sidebar ');
  expect(html).toContain('aria-current="page"');
  expect(html).toContain('href="/catalog"');
  expect(html).toContain('href="/negotiations"');
  expect(html).toContain('Tindakan trade: 3');
  expect(html).toContain('aria-controls="trade-action-popover"');
  expect(html).toContain('aria-expanded="false"');
  expect(html).toContain('aria-label="Menu akun: FTC Player"');
  expect(html).toContain('class="bottom-nav"');
  expect(html).toContain('<h1>Katalog</h1>');
  expect(html).not.toContain('module-header');
  expect(html).not.toContain('module-nav');
 });

 it('shows a clear login action to guests',()=>{
  const html=renderToStaticMarkup(<ProductShell active="catalog" locale="id" onLocaleToggle={vi.fn()}><h1>Katalog</h1></ProductShell>);
  expect(html).toContain('aria-controls="login-popover"');
  expect(html).toContain('login-button');
  expect(html).not.toContain('notification-button');
 });
});
