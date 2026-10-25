import { router } from '@inertiajs/react';

export const NETWORK_FAILURE_EVENT = 'fisch:network-failure';
export const NETWORK_RECOVERED_EVENT = 'fisch:network-recovered';

export function reportNetworkFailure(retry?: () => void) {
    window.dispatchEvent(new CustomEvent(NETWORK_FAILURE_EVENT, { detail: { retry } }));
}

export function clearNetworkFailure() {
    window.dispatchEvent(new Event(NETWORK_RECOVERED_EVENT));
}

export function createIdempotencyKey() {
    const bytes = crypto.getRandomValues(new Uint8Array(16));
    bytes[6] = (bytes[6] & 0x0f) | 0x40;
    bytes[8] = (bytes[8] & 0x3f) | 0x80;
    const hex = Array.from(bytes, (value) => value.toString(16).padStart(2, '0')).join('');

    return `${hex.slice(0, 8)}-${hex.slice(8, 12)}-${hex.slice(12, 16)}-${hex.slice(16, 20)}-${hex.slice(20)}`;
}

export function retryableMutation(method: 'post' | 'patch' | 'put' | 'delete', url: string, data: Record<string, unknown>) {
    const key = createIdempotencyKey();
    const submit = () => router.visit(url, {
        method,
        data: data as any,
        preserveScroll: true,
        headers: { 'X-Idempotency-Key': key },
        onNetworkError: () => reportNetworkFailure(submit),
        onSuccess: clearNetworkFailure,
        onError: clearNetworkFailure,
    });
    submit();
}
