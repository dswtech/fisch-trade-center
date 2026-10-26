import React from 'react';
import { renderToStaticMarkup } from 'react-dom/server';
import { describe, expect, it, vi } from 'vitest';

vi.mock('@inertiajs/react', () => ({
    Head: () => null,
    Link: ({ href, children, ...props }: any) => <a href={href} {...props}>{children}</a>,
    Form: ({ children, action, method, className }: any) => <form action={action} method={method} className={className}>{typeof children === 'function' ? children({ processing: false, errors: {}, submit: vi.fn() }) : children}</form>,
}));

vi.mock('../lib/product-locale', () => ({
    useProductLocale: () => ({ locale: 'en', toggle: vi.fn() }),
}));

import Trade from '../pages/trade';

describe('phase three trade screens', () => {
    it('renders an actionable recommendation with perspective and stable inventory keys', () => {
        const html = renderToStaticMarkup(<Trade
            section="recommendations"
            listing={{ id: 'listing-1', alias: 'Anonymous trader', offered: 'Tidebreaker', wanted: 'Storm Skiff', snapshotVersion: 'v1' }}
            missingItems={['Unknown Relic']}
            recommendations={[{
                id: 'candidate-1',
                items: [{ id: 'entry-1', inventoryEntryId: 'entry-1', name: 'Storm Skiff', quantity: 1, unitValue: 100, offerRange: '95-105' }],
                giveTotal: 100, receiveTotal: 100, delta: 0, deltaPercent: 0, status: 'fair', perspective: 'viewer', reasons: [],
            }]}
        />);

        expect(html).toContain('FAIR for you');
        expect(html).toContain('Delta: 0 pts · 0.00%');
        expect(html).toContain('Storm Skiff: 95-105');
        expect(html).toContain('Missing value: Unknown Relic');
        expect(html).toContain('action="/negotiations"');
        expect(html).toContain('name="items[0][inventoryEntryId]" value="entry-1"');
    });

    it('posts a structured report through the phase four route', () => {
        const html = renderToStaticMarkup(<Trade
            section="negotiation"
            reportingEnabled={true}
            entries={[]}
            proposals={[]}
            negotiation={{ id: 'negotiation-1', alias: 'Trader-ABCD', status: 'proposed', expiresAt: '2026-10-27T00:00:00Z', activeProposal: null }}
        />);

        expect(html).toContain('/negotiations/negotiation-1/counter');
        expect(html).toContain('action="/reports"');
        expect(html).toContain('name="targetId" value="negotiation-1"');
        expect(html).toContain('name="reasonCode"');
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
