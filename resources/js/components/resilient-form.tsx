import { Form } from '@inertiajs/react';
import { RefreshCw } from 'lucide-react';
import { useState, type ReactNode } from 'react';
import { createIdempotencyKey } from '../lib/network-recovery';

type Props = {
    action: string;
    method?: 'get' | 'post' | 'put' | 'patch' | 'delete';
    className?: string;
    children: ReactNode | ((props: any) => ReactNode);
    retryLabel?: string;
    headers?: Record<string, string>;
    onNetworkError?: (error: Error) => void;
    onSuccess?: (...args: any[]) => void;
    onError?: (...args: any[]) => void;
    [key: string]: any;
};

export default function ResilientForm({ children, retryLabel = 'Try again', headers, onNetworkError, onSuccess, onError, ...props }: Props) {
    const [key, setKey] = useState(createIdempotencyKey);
    const [networkError, setNetworkError] = useState(false);
    const rotate = () => { setKey(createIdempotencyKey()); setNetworkError(false); };

    return <Form
        {...props}
        headers={{ ...headers, 'X-Idempotency-Key': key }}
        onNetworkError={(error) => { setNetworkError(true); onNetworkError?.(error); }}
        onSuccess={(...args) => { rotate(); onSuccess?.(...args); }}
        onError={(...args) => { rotate(); onError?.(...args); }}
    >
        {(slot: any) => <>
            {typeof children === 'function' ? children(slot) : children}
            {networkError && <div className="mutation-recovery" role="alert">
                <span>The request did not receive a response. Your input is preserved.</span>
                <button type="button" onClick={() => slot.submit()} disabled={!navigator.onLine}><RefreshCw aria-hidden="true"/>{retryLabel}</button>
            </div>}
        </>}
    </Form>;
}
