import React from 'react';
import { renderToStaticMarkup } from 'react-dom/server';
import { describe, expect, it, vi } from 'vitest';

vi.mock('@inertiajs/react', () => ({
    Head: () => null,
    Link: ({ href, children, ...props }: any) => <a href={href} {...props}>{children}</a>,
    Form: ({ children, action, method, className }: any) => <form action={action} method={method} className={className}>{typeof children === 'function' ? children({ processing: false, errors: {}, submit: vi.fn() }) : children}</form>,
    router: { post: vi.fn() },
    usePage: () => ({ props: { tradeActions: { pendingCount: 0, items: [] } } }),
}));

vi.mock('../lib/product-locale', () => ({
    useProductLocale: () => ({ locale: 'en', toggle: vi.fn() }),
}));

import Trade,{calculateRecommendationDraft} from '../pages/trade';

describe('phase three trade screens', () => {
    it('renders an actionable recommendation with perspective and stable inventory keys', () => {
        const html = renderToStaticMarkup(<Trade
            section="recommendations"
            listing={{ id: 'listing-1', alias: 'Anonymous trader', offered: 'Tidebreaker', wanted: 'Storm Skiff', snapshotVersion: 'v1' }}
            missingItems={['Unknown Relic']}
            fairThresholdPercent={10}
            recommendationInventory={[{id:'entry-1',inventoryEntryId:'entry-1',name:'Storm Skiff',thumbnailUrl:'/images/categories/boat.svg',quantity:1,available:3,unitValue:100,offerRange:'95-105'}]}
            recommendations={[{
                id: 'candidate-1',
                items: [{ id: 'entry-1', inventoryEntryId: 'entry-1', name: 'Storm Skiff', thumbnailUrl: '/images/categories/boat.svg', quantity: 1, unitValue: 100, offerRange: '95-105' }],
                giveTotal: 100, receiveTotal: 100, delta: 0, deltaPercent: 0, status: 'fair', perspective: 'viewer', reasons: [],
            }]}
        />);

        expect(html).toContain('W/F/L result');
        expect(html).toContain('Delta: 0 PTS');
        expect(html).toContain('src="/images/categories/boat.svg"');
        expect(html).toContain('Storm Skiff');
        expect(html).toContain('Available ×3');
        expect(html).toContain('Missing value: Unknown Relic');
        expect(html).toContain('View summary');
        expect(html).not.toContain('Send proposal');
    });

    it('recalculates W/F/L after item and quantity changes',()=>{
        const fair=calculateRecommendationDraft([{id:'entry-1',inventoryEntryId:'entry-1',name:'Item',quantity:2,available:3,unitValue:50}],100,10);
        expect(fair).toMatchObject({giveTotal:100,receiveTotal:100,delta:0,deltaPercent:0,status:'fair'});
        const win=calculateRecommendationDraft([{id:'entry-1',inventoryEntryId:'entry-1',name:'Item',quantity:1,available:3,unitValue:50}],100,10);
        expect(win.status).toBe('win');
        expect(calculateRecommendationDraft([{id:'entry-2',inventoryEntryId:'entry-2',name:'Unknown',quantity:1,available:1,unitValue:null}],100,10).status).toBe('unavailable');
    });

    it('posts a structured report through the phase four route', () => {
        const html = renderToStaticMarkup(<Trade
            section="negotiation"
            reportingEnabled={true}
            entries={[]}
            proposals={[]}
            negotiation={{ id: 'negotiation-1', alias: 'Trader-ABCD', status: 'proposed', expiresAt: '2026-10-27T00:00:00Z', activeProposal: null }}
        />);

        expect(html).not.toContain('/negotiations/negotiation-1/counter');
        expect(html).toContain('action="/reports"');
        expect(html).toContain('name="targetId" value="negotiation-1"');
        expect(html).toContain('name="reasonCode"');
    });

    it('renders a non-binding add-items request without an accept action', () => {
        const html = renderToStaticMarkup(<Trade
            section="negotiation"
            fairThresholdPercent={10}
            entries={[{id:'owner-entry',inventoryEntryId:'owner-entry',name:'Tidebreaker',thumbnailUrl:'/images/categories/boat.svg',quantity:1,available:2,unitValue:100}]}
            proposals={[]}
            negotiation={{id:'negotiation-1',alias:'Trader-ABCD',status:'proposed',expiresAt:'2026-10-27T00:00:00Z',activeProposal:{
                id:'proposal-1',version:1,status:'active',intent:'request_more_items',proposerIsViewer:false,snapshotVersion:'v1',
                giveTotal:100,receiveTotal:200,deltaPercent:100,perspectiveStatus:'win',expiresAt:'2026-10-27T00:00:00Z',canAccept:false,
                items:[
                    {id:'mine-1',inventoryEntryId:'owner-entry',name:'Tidebreaker',thumbnailUrl:'/images/categories/boat.svg',quantity:1,unitValue:100,side:'mine'},
                    {id:'theirs-1',inventoryEntryId:'initiator-entry',name:'Storm Skiff',thumbnailUrl:'/images/categories/boat.svg',quantity:2,unitValue:100,side:'theirs'},
                ],
            }}}
        />);

        expect(html).toContain('The other party requests more items');
        expect(html).toContain('Add items &amp; reply');
        expect(html).toContain('Reject request');
        expect(html).not.toContain('Accept this version');
        expect(html).toContain('src="/images/categories/boat.svg"');
    });

    it('keeps identity hidden until reveal and renders zero-fee final acceptance', () => {
        const waiting = renderToStaticMarkup(<Trade section="deal" deal={{
            id: 'deal-1', negotiationId: 'negotiation-1', status: 'awaiting_final_acceptance', serverType: 'public_server',
            snapshotVersion: 2, expiresAt: '2026-10-26T00:30:00Z', feeQuote: 0, finalAcceptedByMe: false, identity: null,
        }}/>);
        expect(waiting).toContain('0 — wallet disabled');
        expect(waiting).toContain('action="/negotiations/negotiation-1/final-accept"');
        expect(waiting).toContain('Identity remains hidden');
        expect(waiting).not.toContain('USN:');

        const revealed = renderToStaticMarkup(<Trade section="deal" deal={{
            id: 'deal-1', negotiationId: 'negotiation-1', status: 'revealed', serverType: 'public_server', snapshotVersion: 2,
            expiresAt: '2026-10-26T00:30:00Z', revealExpiresAt: '2026-11-25T00:00:00Z', feeQuote: 0,
            finalAcceptedByMe: true, identity: {usn: 'SafeUser', ign: 'SafeIGN'},
        }}/>);
        expect(revealed).toContain('USN:');
        expect(revealed).toContain('SafeUser');
        expect(revealed).toContain('SafeIGN');
    });
});
