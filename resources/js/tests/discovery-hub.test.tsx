import React from 'react';
import {renderToStaticMarkup} from 'react-dom/server';
import {beforeEach,describe,expect,it,vi} from 'vitest';

let currentUser:any=null;
vi.mock('@inertiajs/react',()=>({
 Head:()=>null,
 Link:({href,children,...props}:any)=><a href={href} {...props}>{children}</a>,
 Form:({children,action,method,className}:any)=><form action={action} method={method} className={className}>{typeof children==='function'?children({processing:false,errors:{}}):children}</form>,
 router:{reload:vi.fn(),get:vi.fn(),post:vi.fn(),on:vi.fn(()=>vi.fn())},
 usePage:()=>({url:'/',props:{auth:{user:currentUser},tradeActions:{pendingCount:0,items:[]},preferences:{locale:'id',theme:'dark'},flash:{},errors:{}}}),
}));
vi.mock('../lib/product-locale',()=>({useProductLocale:()=>({locale:'id',toggle:vi.fn(),user:currentUser})}));

import DiscoveryHub from '../pages/discovery-hub';

const items=Array.from({length:4},(_,index)=>({id:`item-${index}`,name:`Item ${index}`,quantity:index+1,thumbnailUrl:'/images/categories/other.svg',unitValue:100}));
const listing={id:'listing-1',alias:'Angler 42',offered:'Four items',wanted:'Target Boat',offeredItems:items,wantedItems:[items[0]],offeredThumbnailUrl:items[0].thumbnailUrl,wantedThumbnailUrl:items[0].thumbnailUrl,value:1000,wantedValue:950,age:'2m',status:'fair' as const};
const marketInsights={activeListingCount:12,snapshot:{version:'v-market',ageLabel:'Published now'},mostOffered:[{id:'item-1',name:'Abyssal Current',thumbnailUrl:items[0].thumbnailUrl,listingCount:8}],mostWanted:[{id:'item-2',name:'Tidebreaker',thumbnailUrl:items[0].thumbnailUrl,listingCount:6}]};

describe('two-column discovery hub',()=>{
 beforeEach(()=>{currentUser=null});

 it('renders public market data, compact multi-item offers, and login actions',()=>{
  const html=renderToStaticMarkup(<DiscoveryHub listings={[listing]} marketInsights={marketInsights} personalRecommendations={{state:'guest',results:[]}}/>);
  expect(html).toContain('Trade yang Kamu Cari');
  expect(html).toContain('Kamu Berikan');
  expect(html).toContain('Kamu Terima');
  expect(html).toContain('Temukan Trade');
  expect(html.indexOf('id="trade-intent-title">Trade yang Kamu Cari')).toBeLessThan(html.indexOf('<h2>Temukan Trade'));
  expect(html).toContain('Insight Pasar');
  expect(html).toContain('aria-controls="market-panel-content"');
  expect(html).toContain('aria-controls="personal-panel-content"');
  expect(html).toContain('aria-expanded="true"');
  expect(html).toContain('12 Penawaran aktif');
  expect(html).toContain('Abyssal Current');
  expect(html).toContain('+1');
  expect(html).toContain('Login untuk menawarkan');
  expect(html).not.toContain('PrivateUSN');
 });

 it('renders renamed navigation and personal inventory matches for a player',()=>{
  currentUser={id:'user-1',name:'Player',emailVerified:true,role:'player'};
  const html=renderToStaticMarkup(<DiscoveryHub listings={[listing]} marketInsights={marketInsights} personalRecommendations={{state:'ready',results:[{listingId:'listing-1',items:[{name:'Abyssal Current',quantity:1}],giveTotal:1000,receiveTotal:950,deltaPercent:-5,status:'fair'}]}}/>);
  expect(html).toContain('Penawaran Saya');
  expect(html).toContain('Cocok untuk Inventory-mu');
  expect(html).toContain('Kombinasi kamu: Abyssal Current');
  expect(html).toContain('href="/recommendations/listing-1"');
 });

});
