import { beforeEach, describe, expect, it, vi } from 'vitest';

const { visit } = vi.hoisted(() => ({ visit: vi.fn() }));
vi.mock('@inertiajs/react', () => ({ router: { visit } }));

import { createIdempotencyKey, retryableMutation } from './network-recovery';

describe('retryable mutation', () => {
    beforeEach(() => {
        visit.mockReset();
        vi.stubGlobal('window', { dispatchEvent: vi.fn() });
    });

    it('reuses the same idempotency key for a manual network retry', () => {
        retryableMutation('post', '/inventory', { quantity: 1 });
        const firstOptions = visit.mock.calls[0][1];
        firstOptions.onNetworkError();
        const failureEvent = vi.mocked(window.dispatchEvent).mock.calls[0][0] as CustomEvent<{ retry: () => void }>;
        failureEvent.detail.retry();

        expect(visit).toHaveBeenCalledTimes(2);
        expect(visit.mock.calls[1][1].headers['X-Idempotency-Key']).toBe(firstOptions.headers['X-Idempotency-Key']);
    });

    it('creates an RFC 4122 version 4 key without randomUUID', () => {
        expect(createIdempotencyKey()).toMatch(/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/);
    });
});
