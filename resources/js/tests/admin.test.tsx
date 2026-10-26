import React from 'react';
import { renderToStaticMarkup } from 'react-dom/server';
import { describe, expect, it, vi } from 'vitest';

vi.mock('@inertiajs/react', () => ({
    Head: () => null,
    Link: ({ href, children, ...props }: any) => <a href={href} {...props}>{children}</a>,
    Form: ({ children, action, method, className }: any) => <form action={action} method={method} className={className}>{typeof children === 'function' ? children({ processing: false, errors: {}, submit: vi.fn() }) : children}</form>,
}));

import Admin from '../pages/admin';

describe('admin catalog operations', () => {
    it('renders CSV preview, Rod fallback, and manual thumbnail controls', () => {
        const html = renderToStaticMarkup(<Admin
            manualTimeControlsEnabled={true}
            imports={[{ id: 'import-1', status: 'valid', checksum: 'abcdef1234567890', importedAt: 'now', errors: [], warnings: [{ row: 2, code: 'value_change_over_25_percent' }], preview: { created: 1, value_changed: 1 }, published: false }]}
            rods={[{ id: 'rod-1', name: 'Aurora Rod', isActive: true }]}
            catalogItems={[{ id: 'item-1', name: 'Aurora Skin', category: 'Rod Skin', categoryKey: 'rod_skin', aliases: ['Aurora'], isActive: true, rodId: null, rodName: 'Undefined Rod', thumbnailUrl: '/images/categories/rod_skin.svg', hasThumbnail: false }]}
        />);

        expect(html).toContain('CSV upload');
        expect(html).toContain('created 1');
        expect(html).toContain('warning(s) require confirmation');
        expect(html).toContain('Undefined Rod');
        expect(html).toContain('/images/categories/rod_skin.svg');
        expect(html).toContain('accept="image/jpeg,image/png,image/webp"');
        expect(html).toContain('/admin/rods/rod-1');
        expect(html).toContain('value="Aurora"');
        expect(html).toContain('/admin/catalog-items/item-1');
        expect(html).toContain('/admin/time-controls');
    });
});
