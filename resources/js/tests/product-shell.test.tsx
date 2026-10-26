import React from 'react';
import {renderToStaticMarkup} from 'react-dom/server';
import {describe,expect,it,vi} from 'vitest';

vi.mock('@inertiajs/react',()=>({
 Link:({href,children,...props}:any)=><a href={href} {...props}>{children}</a>,
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
  expect(html).toContain('class="bottom-nav"');
  expect(html).toContain('<h1>Katalog</h1>');
  expect(html).not.toContain('module-header');
  expect(html).not.toContain('module-nav');
 });
});
