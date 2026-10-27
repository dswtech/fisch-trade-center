import React from 'react';
import {renderToStaticMarkup} from 'react-dom/server';
import {describe,expect,it,vi} from 'vitest';

vi.mock('@inertiajs/react',()=>({
 Head:()=>null,
 Link:({href,children,...props}:any)=><a href={href} {...props}>{children}</a>,
 Form:({children,action,method,className,...props}:any)=><form action={action} method={method} className={className} {...props}>{typeof children==='function'?children({processing:false,errors:{}}):children}</form>,
 router:{get:vi.fn()},
}));

import {ListingWizard,listingBuilderOptions} from '../pages/workspace';

const t={offerBuilder:'Choose items for your offer',offerBuilderHint:'Use inventory and catalog.',selected:'selected',offer:'Items you give',want:'Items you want',searchItems:'Search name or alias',filter:'Category',allCategories:'All categories',valueMissing:'N/A',noMatch:'No match',closeDetail:'Close detail',itemDetail:'Item detail',currentValue:'Current value',offerRange:'Offer range',available:'Available',quantity:'Quantity',updateItem:'Update quantity',addToOffer:'Add to offer',selectItemHint:'Choose a card.',draft:'Offer draft',estimatedValue:'Estimated value',removeItem:'Remove item',save:'Save',publish:'Publish for 14 days'};
const items=[
 {id:'catalog-a',name:'Abyssal Current',category:'Rod Skin',thumbnailUrl:'/a.svg',value:100,offerRange:'95-105'},
 {id:'catalog-b',name:'Tidebreaker',category:'Boats',thumbnailUrl:'/b.svg',value:200,offerRange:'190-210'},
];
const entries=[{id:'entry-a',catalogItemId:'catalog-a',name:'Abyssal Current',quantity:3,thumbnailUrl:'/a.svg'}];

describe('listing offer builder',()=>{
 it('renders dynamic Give versus Want sides with compatible form payload',()=>{
  const html=renderToStaticMarkup(<ListingWizard entries={entries} items={items} t={t} listing={{id:'listing-a',status:'active',version:1,expiresAt:null,selected:{offered:[{id:'entry-a',quantity:2}],wanted:[{id:'catalog-b',quantity:1}]}}}/>);

  expect(html).toContain('class="listing-composer-board"');
  expect(html).toContain('class="listing-composer-vs"');
  expect(html).toContain('Items you give');
  expect(html).toContain('Items you want');
  expect(html).toContain('src="/a.svg"');
  expect(html).toContain('Abyssal Current');
  expect(html).toContain('Tidebreaker');
  expect(html).toContain('name="offered[0][inventoryEntryId]" value="entry-a"');
  expect(html).toContain('name="offered[0][quantity]" value="2"');
  expect(html).toContain('name="wanted[0][catalogItemId]" value="catalog-b"');
  expect(html).toContain('200 → 200 PTS');
 });

 it('only offers owned inventory on the Give side while Want uses the active catalog',()=>{
  expect(listingBuilderOptions('offered',entries,items).map(item=>item.name)).toEqual(['Abyssal Current']);
  expect(listingBuilderOptions('offered',[...entries,{id:'empty',catalogItemId:'catalog-b',name:'Tidebreaker',quantity:0}],items).map(item=>item.name)).toEqual(['Abyssal Current']);
  expect(listingBuilderOptions('wanted',entries,items).map(item=>item.name)).toEqual(['Abyssal Current','Tidebreaker']);
 });
});
