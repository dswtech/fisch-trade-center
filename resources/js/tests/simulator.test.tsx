import React from 'react';
import {renderToStaticMarkup} from 'react-dom/server';
import {describe,expect,it,vi} from 'vitest';

vi.mock('@inertiajs/react',()=>({
 Head:()=>null,
 Link:({href,children,...props}:any)=><a href={href} {...props}>{children}</a>,
}));

import {Simulator} from '../pages/workspace';

const t={
 tradeBuilder:'Build both sides of the trade',tradeBuilderHint:'Choose up to 9 items per side.',snapshot:'Snapshot',valueMissing:'N/A',
 give:'You give',receive:'You receive',yourShare:'You',theirShare:'Them',unavailable:'Cannot be valued',calculate:'Calculate with active snapshot',
 add:'Add item',quantity:'Quantity',removeItem:'Remove item',emptySlot:'Empty slot',searchItems:'Search items',addToSide:'Add to',allCategories:'All',empty:'No items',
};

describe('simulator workbench',()=>{
 it('renders selected item slots, totals, snapshot, and valid GET fields',()=>{
  const html=renderToStaticMarkup(<Simulator
   items={[
    {id:'item-a',name:'Abyssal Current',category:'Rod Skin',thumbnailUrl:'/a.png',value:100},
    {id:'item-b',name:'Tidebreaker',category:'Boats',thumbnailUrl:'/b.png',value:200},
   ]}
   selected={{give:[{catalogItemId:'item-a',quantity:2}],receive:[{catalogItemId:'item-b',quantity:1}]}}
   result={{status:'fair',delta:0,deltaPercent:0,giveTotal:200,receiveTotal:200,snapshotVersion:'snapshot-1',perspective:'viewer',reasons:[]}}
   t={t}
  />);

  expect(html).toContain('class="trade-calculator"');
  expect(html).toContain('Abyssal Current');
  expect(html).toContain('Tidebreaker');
  expect(html).toContain('snapshot-1');
  expect(html).toContain('name="give[0][catalogItemId]" value="item-a"');
  expect(html).toContain('name="receive[0][catalogItemId]" value="item-b"');
  expect(html).toContain('aria-label="You 50%, Them 50%"');
 });
});
