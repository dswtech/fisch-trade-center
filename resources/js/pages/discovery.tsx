import { Head, Link, router, usePage } from '@inertiajs/react';
import { Anchor, ArrowRight, Bell, BookOpen, Boxes, Compass, Filter, Fish, LayoutDashboard, Menu, Moon, Search, ShipWheel, SlidersHorizontal, Sparkles, Sun, UserRound, X } from 'lucide-react';
import { useEffect, useMemo, useRef, useState } from 'react';
import { retryableMutation } from '../lib/network-recovery';
import { hasInvertedValueRange } from '../lib/search';
import type { ListingSummary, Locale, SharedProps, Theme, WflResult } from '../types';

type SearchFilters = {
    offeredItem: string;
    wantedItem: string;
    category: string;
    minValue: string;
    maxValue: string;
    freshness: string;
};

type Option = { id: string; name: string };

const copy = {
    id: { discover: 'Temukan Trade', search: 'Pilih filter trade', filters: 'Filter', searchAction: 'Cari', give: 'Kamu Berikan', receive: 'Kamu Terima', snapshot: 'Snapshot Value', latest: 'Listing Trade Terbaru', viewAll: 'Lihat semua', recommended: 'Rekomendasi', synthetic: 'DATA DEMO SYNTHETIC', dashboard: 'Dashboard', catalog: 'Katalog', inventory: 'Inventory', listings: 'Listing', simulator: 'Simulator W/F/L', profile: 'Profil', trade: 'Trade ini', fair: 'FAIR untuk kamu', fresh: 'Data terbaru', found: 'listing ditemukan', searching: 'Mencari trade aktif…', errorTitle: 'Pencarian belum dapat diperbarui', networkError: 'Koneksi terputus. Hasil dan filter sebelumnya tetap tersedia.', retry: 'Coba lagi', emptyTitle: 'Belum ada trade yang cocok', emptyBody: 'Ubah satu atau beberapa filter untuk menemukan listing aktif lain.', changeFilters: 'Ubah filter', loadNext: 'Halaman berikutnya', offeredItem: 'Item ditawarkan', anyOffered: 'Semua item tawaran', wantedItem: 'Item dicari', anyWanted: 'Semua item dicari', category: 'Kategori', allCategories: 'Semua kategori', minimumValue: 'Value minimum', maximumValue: 'Value maksimum', freshness: 'Kesegaran', anyTime: 'Kapan saja', lastHour: 'Satu jam terakhir', today: 'Hari ini', lastWeek: 'Tujuh hari terakhir', applyFilters: 'Terapkan filter', rangeError: 'Value maksimum harus sama dengan atau lebih besar dari value minimum.' },
    en: { discover: 'Discover Trades', search: 'Choose trade filters', filters: 'Filters', searchAction: 'Search', give: 'You Give', receive: 'You Receive', snapshot: 'Value Snapshot', latest: 'Latest Trade Listings', viewAll: 'View all', recommended: 'Recommendations', synthetic: 'SYNTHETIC DEMO DATA', dashboard: 'Dashboard', catalog: 'Catalog', inventory: 'Inventory', listings: 'Listings', simulator: 'W/F/L Simulator', profile: 'Profile', trade: 'This trade', fair: 'FAIR for you', fresh: 'Latest data', found: 'listings found', searching: 'Searching active trades…', errorTitle: 'Search could not be updated', networkError: 'The connection was interrupted. Previous results and filters remain available.', retry: 'Try again', emptyTitle: 'No matching currents', emptyBody: 'Adjust one or more filters to discover other active trades.', changeFilters: 'Change filters', loadNext: 'Next page', offeredItem: 'Offered item', anyOffered: 'Any offered item', wantedItem: 'Wanted item', anyWanted: 'Any wanted item', category: 'Category', allCategories: 'All categories', minimumValue: 'Minimum value', maximumValue: 'Maximum value', freshness: 'Freshness', anyTime: 'Any time', lastHour: 'Last hour', today: 'Today', lastWeek: 'Last week', applyFilters: 'Apply filters', rangeError: 'Maximum value must be equal to or greater than minimum value.' },
};

const fallbackListings: ListingSummary[] = [
    { id: 'lst_demo_01', alias: 'Angler 28', offered: 'Abyssal Current', wanted: 'Ancient Relic Set', offeredThumbnailUrl: '/images/categories/rod_skin.svg', wantedThumbnailUrl: '/images/categories/relic_set.svg', value: 2400, age: '4m', status: 'fair' },
    { id: 'lst_demo_02', alias: 'Angler 07', offered: 'Tidebreaker', wanted: 'Celestial Skin', offeredThumbnailUrl: '/images/categories/boat.svg', wantedThumbnailUrl: '/images/categories/rod_skin.svg', value: 4100, age: '11m', status: 'win' },
    { id: 'lst_demo_03', alias: 'Angler 91', offered: 'Golden Bobber', wanted: 'Abyssal Current + add', offeredThumbnailUrl: '/images/categories/other.svg', wantedThumbnailUrl: '/images/categories/rod_skin.svg', value: 3200, age: '18m', status: 'fair' },
    { id: 'lst_demo_04', alias: 'Angler 44', offered: 'Sunken Crown', wanted: 'Tidebreaker', offeredThumbnailUrl: '/images/categories/relic_set.svg', wantedThumbnailUrl: '/images/categories/boat.svg', value: 3800, age: '24m', status: 'lose' },
];

export default function Discovery({
    listings = fallbackListings,
    snapshot = { version: 'fixture-2026.10.24', ageLabel: 'Synthetic fixture · 24 Oct 2026' },
    wfl,
    nextCursor,
    filters: initialFilters = {},
    filterOptions = { items: [], categories: [] },
}: {
    listings?: ListingSummary[];
    snapshot?: { version: string; ageLabel: string };
    wfl?: WflResult;
    nextCursor?: string | null;
    filters?: Partial<SearchFilters>;
    filterOptions?: { items: Option[]; categories: Option[] };
}) {
    const { props } = usePage<SharedProps>();
    const [drawer, setDrawer] = useState(false);
    const [filtersOpen, setFiltersOpen] = useState(false);
    const [loading, setLoading] = useState(false);
    const [clientError, setClientError] = useState<string | null>(null);
    const [searchError, setSearchError] = useState<string | null>(null);
    const retrySearch = useRef<() => void>(() => router.reload());
    const [searchFilters, setSearchFilters] = useState<SearchFilters>({
        offeredItem: initialFilters.offeredItem ?? '',
        wantedItem: initialFilters.wantedItem ?? '',
        category: initialFilters.category ?? '',
        minValue: initialFilters.minValue ?? '',
        maxValue: initialFilters.maxValue ?? '',
        freshness: initialFilters.freshness ?? '',
    });
    const initialLocale = useMemo<Locale>(() => props.preferences?.locale || (navigator.language.toLowerCase().startsWith('id') ? 'id' : 'en'), []);
    const [locale, setLocale] = useState<Locale>(() => (localStorage.getItem('ftc-locale') as Locale) || initialLocale);
    const [theme, setTheme] = useState<Theme>(() => (localStorage.getItem('ftc-theme') as Theme) || props.preferences?.theme || 'dark');
    const t = copy[locale];

    useEffect(() => {
        document.documentElement.dataset.theme = theme;
        document.documentElement.classList.toggle('dark', theme === 'dark');
        localStorage.setItem('ftc-theme', theme);
    }, [theme]);
    useEffect(() => localStorage.setItem('ftc-locale', locale), [locale]);
    useEffect(() => {
        const stopStart = router.on('start', () => setLoading(true));
        const stopFinish = router.on('finish', () => setLoading(false));
        return () => { stopStart(); stopFinish(); };
    }, []);

    const persist = (next: { theme?: Theme; locale?: Locale }) => {
        if (props.auth?.user) retryableMutation('patch', '/preferences', next);
    };
    const updateFilter = (key: keyof SearchFilters, value: string) => {
        setClientError(null);
        setSearchFilters((current) => ({ ...current, [key]: value }));
    };
    const runSearch = (url: string, data: Record<string, string> = {}) => {
        const attempt = () => router.get(url, data, {
            preserveState: true,
            preserveScroll: true,
            onStart: () => setSearchError(null),
            onNetworkError: () => setSearchError(t.networkError),
            onError: () => setFiltersOpen(true),
        });
        retrySearch.current = attempt;
        attempt();
    };
    const applyFilters = () => {
        if (hasInvertedValueRange(searchFilters.minValue, searchFilters.maxValue)) {
            setClientError(t.rangeError);
            return;
        }
        runSearch('/search', Object.fromEntries(Object.entries(searchFilters).filter(([, value]) => value !== '')));
        setFiltersOpen(false);
    };
    const nextHref = nextCursor ? `/search?${new URLSearchParams({ ...Object.fromEntries(Object.entries(searchFilters).filter(([, value]) => value !== '')), cursor: nextCursor }).toString()}` : null;
    const serverError = searchError || props.flash?.error || Object.values(props.errors || {})[0];

    return <><Head title={t.discover}/><div className="app-shell">
        <header className="topbar">
            <button className="icon-button mobile-only" aria-label="Open menu" onClick={() => setDrawer(true)}><Menu/></button>
            <Link href="/" className="brand" aria-label="FISCHTRADE home"><img src="/images/brand-mark.png" alt=""/><span>FISCH<span>TRADE</span></span></Link>
            <div className="top-actions">
                <span className="demo-badge">{t.synthetic}</span><button className="icon-button" aria-label="Notifications"><Bell/></button>
                <button className="locale-button" onClick={() => { const next = locale === 'id' ? 'en' : 'id'; setLocale(next); persist({ locale: next }); }}>{locale.toUpperCase()}</button>
                <button className="icon-button" aria-label="Toggle theme" onClick={() => { const next = theme === 'dark' ? 'light' : 'dark'; setTheme(next); persist({ theme: next }); }}>{theme === 'dark' ? <Sun/> : <Moon/>}</button>
                <Link className="avatar" aria-label={props.auth?.user ? 'Open profile' : 'Sign in'} href={props.auth?.user ? '/profile' : '/login'}><UserRound/></Link>
            </div>
        </header>
        <aside className={`sidebar ${drawer ? 'open' : ''}`}>
            <button className="icon-button close-drawer" onClick={() => setDrawer(false)} aria-label="Close menu"><X/></button>
            <nav>{[[LayoutDashboard, t.dashboard, '/'], [Compass, t.discover, '/'], [BookOpen, t.catalog, '/catalog'], [Boxes, t.inventory, '/inventory'], [Fish, t.listings, '/listings'], [ShipWheel, t.simulator, '/simulator']].map(([Icon, label, href]: any) => <Link key={label} href={href} className={label === t.discover ? 'active' : ''}><Icon/><span>{label}</span></Link>)}</nav>
            <img className="sidebar-art" src="/images/sidebar-ocean-art.png" alt=""/>
            <Link className="sidebar-profile" href={props.auth?.user ? '/profile' : '/login'}><span className="avatar"><UserRound/></span><span>{props.auth?.user?.name || 'Guest angler'}<small>{props.auth?.user ? 'Player' : 'Sign in to trade'}</small></span></Link>
        </aside>
        <main className="workspace">
            <section className="command-bar" aria-label="Trade search">
                <button className="search-field" onClick={() => setFiltersOpen(true)}><Search/><span>{t.search}</span></button>
                <button className="filter-button" onClick={() => setFiltersOpen(true)}><SlidersHorizontal/>{t.filters}</button>
                <button className="primary-button" onClick={() => setFiltersOpen(true)}><Search/>{t.searchAction}</button>
            </section>
            <section className="trade-zone">
                <div className="trade-panel give-panel"><PanelTitle n="01" over={t.trade} title={t.give}/><ItemCard tone="cyan" name="Abyssal Current" category="Rod Skin · Undefined Rod" value="2,400" icon={<Sparkles/>}/><button className="add-item">+ Add item</button></div>
                <div className="balance-orb" aria-label={wfl?.status || 'fair'}><span>±8.3%</span><strong>{t.fair}</strong></div>
                <div className="trade-panel receive-panel"><PanelTitle n="02" over={t.trade} title={t.receive}/><ItemCard tone="violet" name="Tidebreaker" category="Boats" value="2,600" icon={<Anchor/>}/><button className="add-item">+ Add item</button></div>
                <aside className="insight-column"><div className="snapshot-card"><div><p>{t.snapshot}</p><strong>{snapshot.version}</strong></div><span className="live-dot">{t.fresh}</span><small>{snapshot.ageLabel}</small></div><div className="recommendation-card"><div className="recommendation-head"><div><p>{t.recommended}</p><h3>Closest currents</h3></div><Filter/></div>{['Ancient Relic Set + Pearl', 'Tidebreaker', 'Celestial Skin'].map((name, index) => <button key={name}><span>{index + 1}</span><div>{name}<small>{['+2.1% · Fair', '+7.8% · Fair', '+13.4% · Win'][index]}</small></div><ArrowRight/></button>)}</div></aside>
            </section>
            <section className="listing-section" aria-busy={loading}><div className="section-head"><div><p className="eyebrow">{listings.length} {t.found}</p><h2>{t.latest}</h2></div><Link href="/listings">{t.viewAll}<ArrowRight/></Link></div>{loading && <div className="search-feedback loading" role="status" aria-live="polite"><span aria-hidden="true"/>{t.searching}</div>}{serverError && <div className="search-feedback error" role="alert"><div><strong>{t.errorTitle}</strong><span>{serverError}</span></div><button onClick={() => retrySearch.current()}>{t.retry}</button></div>}{listings.length === 0 ? <div className="empty-state"><Fish/><h3>{t.emptyTitle}</h3><p>{t.emptyBody}</p><button className="filter-button" onClick={() => setFiltersOpen(true)}>{t.changeFilters}</button></div> : <div className="listing-grid" tabIndex={0} aria-label={t.latest}>{listings.map((listing) => <article className="listing-card" data-listing-id={listing.id} key={listing.id}><div className="listing-top"><span className="anonymous"><UserRound/>{listing.alias}</span><span>{listing.age}</span></div><div className="listing-flow"><img className="mini-glyph" src={listing.offeredThumbnailUrl} alt=""/><div><small>OFFERS</small><strong>{listing.offered}</strong></div><ArrowRight/><div className="wanted-item"><img src={listing.wantedThumbnailUrl} alt=""/><span><small>WANTS</small><strong>{listing.wanted}</strong></span></div></div><div className="listing-foot"><strong>{listing.value?.toLocaleString() ?? '—'} pts</strong><span className={`status ${listing.status}`}>{listing.status.toUpperCase()}</span></div></article>)}</div>}{nextHref && <button className="primary-button pagination-button" onClick={() => runSearch(nextHref)} disabled={loading}>{t.loadNext}</button>}</section>
        </main>
        <nav className="bottom-nav">{[[Compass, t.discover, '/'], [BookOpen, t.catalog, '/catalog'], [Fish, t.listings, '/listings'], [ShipWheel, 'W/F/L', '/simulator'], [UserRound, t.profile, '/login']].map(([Icon, label, href]: any) => <Link href={href} key={label}><Icon/><span>{label}</span></Link>)}</nav>
        {filtersOpen && <div className="sheet-backdrop" onMouseDown={() => setFiltersOpen(false)}><section className="filter-sheet" role="dialog" aria-modal="true" aria-label={t.filters} onMouseDown={(event) => event.stopPropagation()}>
            <header><h2>{t.filters}</h2><button className="icon-button" onClick={() => setFiltersOpen(false)} aria-label="Close filters"><X/></button></header>
            <label>{t.offeredItem}<select value={searchFilters.offeredItem} onChange={(event) => updateFilter('offeredItem', event.target.value)}><option value="">{t.anyOffered}</option>{filterOptions.items.map((option) => <option value={option.id} key={option.id}>{option.name}</option>)}</select></label>
            <label>{t.wantedItem}<select value={searchFilters.wantedItem} onChange={(event) => updateFilter('wantedItem', event.target.value)}><option value="">{t.anyWanted}</option>{filterOptions.items.map((option) => <option value={option.id} key={option.id}>{option.name}</option>)}</select></label>
            <label>{t.category}<select value={searchFilters.category} onChange={(event) => updateFilter('category', event.target.value)}><option value="">{t.allCategories}</option>{filterOptions.categories.map((option) => <option value={option.id} key={option.id}>{option.name}</option>)}</select></label>
            <label>{t.minimumValue}<input type="number" min="0" value={searchFilters.minValue} aria-invalid={!!clientError || !!props.errors?.minValue} onChange={(event) => updateFilter('minValue', event.target.value)}/></label>
            <label>{t.maximumValue}<input type="number" min="0" value={searchFilters.maxValue} aria-invalid={!!clientError || !!props.errors?.maxValue} onChange={(event) => updateFilter('maxValue', event.target.value)}/></label>
            {(clientError || props.errors?.minValue || props.errors?.maxValue) && <p className="form-error" role="alert">{clientError || props.errors?.minValue || props.errors?.maxValue}</p>}
            <label>{t.freshness}<select value={searchFilters.freshness} onChange={(event) => updateFilter('freshness', event.target.value)}><option value="">{t.anyTime}</option><option value="hour">{t.lastHour}</option><option value="day">{t.today}</option><option value="week">{t.lastWeek}</option></select></label>
            <button className="primary-button" onClick={applyFilters} disabled={loading}>{loading ? t.searching : t.applyFilters}</button>
        </section></div>}
    </div></>;
}

function PanelTitle({ n, over, title }: { n: string; over: string; title: string }) { return <div className="panel-title"><span className="number">{n}</span><div><p>{over}</p><h1>{title}</h1></div></div>; }
function ItemCard({ tone, name, category, value, icon }: { tone: string; name: string; category: string; value: string; icon: React.ReactNode }) { return <div className={`item-card ${tone}`}><div className="item-glyph">{icon}</div><div><strong>{name}</strong><small>{category}</small></div><span>{value}<small>PTS</small></span></div>; }
