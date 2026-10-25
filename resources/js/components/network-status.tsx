import { RefreshCw, WifiOff } from 'lucide-react';
import { useEffect, useState } from 'react';
import { NETWORK_FAILURE_EVENT, NETWORK_RECOVERED_EVENT } from '../lib/network-recovery';

export default function NetworkStatus() {
    const [offline, setOffline] = useState(() => !navigator.onLine);
    const [failed, setFailed] = useState(false);
    const [retry, setRetry] = useState<null | (() => void)>(null);

    useEffect(() => {
        const onOffline = () => setOffline(true);
        const onOnline = () => setOffline(false);
        const onFailure = (event: Event) => {
            setFailed(true);
            const nextRetry = (event as CustomEvent<{ retry?: () => void }>).detail?.retry;
            setRetry(() => nextRetry ?? null);
        };
        const onRecovered = () => { setFailed(false); setRetry(null); };
        window.addEventListener('offline', onOffline);
        window.addEventListener('online', onOnline);
        window.addEventListener(NETWORK_FAILURE_EVENT, onFailure);
        window.addEventListener(NETWORK_RECOVERED_EVENT, onRecovered);
        document.addEventListener('inertia:networkError', onFailure);
        document.addEventListener('inertia:success', onRecovered);
        return () => {
            window.removeEventListener('offline', onOffline);
            window.removeEventListener('online', onOnline);
            window.removeEventListener(NETWORK_FAILURE_EVENT, onFailure);
            window.removeEventListener(NETWORK_RECOVERED_EVENT, onRecovered);
            document.removeEventListener('inertia:networkError', onFailure);
            document.removeEventListener('inertia:success', onRecovered);
        };
    }, []);

    if (! offline && ! failed) return null;

    return <div className="network-status" role={failed ? 'alert' : 'status'} aria-live="assertive">
        <WifiOff aria-hidden="true"/>
        <span>{offline ? 'You are offline. Your input stays on this device.' : 'The connection was interrupted. Review the current state before retrying.'}</span>
        {retry && ! offline && <button type="button" onClick={retry}><RefreshCw aria-hidden="true"/>Try again</button>}
    </div>;
}
