import React from 'react';
import {renderToStaticMarkup} from 'react-dom/server';
import {describe,expect,it,vi} from 'vitest';

vi.mock('@inertiajs/react',()=>({
 Link:({href,children,...props}:any)=><a href={href} {...props}>{children}</a>,
 router:{get:vi.fn()},
}));

import {Catalog,CatalogDetail} from '../pages/workspace';

const t={searchItems:'Cari nama atau alias',filter:'Kategori',sort:'Urutkan',allCategories:'Semua kategori',valueHigh:'Value tertinggi',valueLow:'Value terendah',nameAsc:'Nama A–Z',nameDesc:'Nama Z–A',results:'item ditemukan',valueMissing:'N/A',empty:'Belum ada data',noMatch:'Tidak ada item yang cocok',catalogHint:'Katalog belum tersedia',previous:'Sebelumnya',next:'Berikutnya',page:'Halaman',of:'dari',backCatalog:'Kembali ke katalog',currentValue:'Value saat ini',offerRange:'Offer range',rod:'Rod',publishedSnapshot:'Snapshot aktif',findTrades:'Cari trade item ini'};

describe('public catalog',()=>{
 it('renders image-led cards and preserves filters in pagination links',()=>{
  const html=renderToStaticMarkup(<Catalog items={[{id:'item-a',name:'Abyssal Current',category:'Rod Skin',rodName:'Undefined Rod',thumbnailUrl:'/fallback.svg',value:2400,offerRange:'2300-2500'}]} filters={{q:'abyssal',category:'rod_skin',sort:'name_asc'}} pagination={{currentPage:2,lastPage:4,perPage:40,total:121,from:41,to:80}} t={t}/>);
  expect(html).toContain('src="/fallback.svg"');
  expect(html).toContain('Abyssal Current');
  expect(html).toContain('2.400 PTS');
  expect(html).toContain('2300-2500');
  expect(html).toContain('href="/catalog/item-a"');
  expect(html).toContain('q=abyssal&amp;category=rod_skin&amp;sort=name_asc&amp;page=3');
  expect(html).toContain('aria-current="page"');
 });

 it('renders stable item detail and listing CTA',()=>{
  const html=renderToStaticMarkup(<CatalogDetail item={{id:'item-a',name:'Abyssal Current',category:'Rod Skin',rodName:'Undefined Rod',thumbnailUrl:'/fallback.svg',value:2400,offerRange:'2300-2500'}} snapshot={{version:'snapshot-v1',publishedAt:'2026-10-26'}} t={t}/>);
  expect(html).toContain('Undefined Rod');
  expect(html).toContain('snapshot-v1');
  expect(html).toContain('/search?q=Abyssal%20Current');
 });
});
