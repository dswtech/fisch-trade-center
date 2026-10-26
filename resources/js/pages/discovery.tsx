import { Head, Link, router, usePage } from '@inertiajs/react';
import { Anchor, ArrowRight, Filter, Fish, Search, SlidersHorizontal, Sparkles, UserRound, X } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import ProductShell from '../components/product-shell';
import {useProductLocale} from '../lib/product-locale';
import { hasInvertedValueRange } from '../lib/search';
import type { ListingSummary, SharedProps, WflResult } from '../types';

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
    id: { discover: 'Temukan Trade', search: 'Pilih filter trade', filters: 'Filter', searchAction: 'Cari', give: 'Kamu Berikan', receive: 'Kamu Terima', snapshot: 'Snapshot Value', latest: 'Listing Trade Terbaru', viewAll: 'Lihat semua', recommended: 'Rekomendasi', synthetic: 'DATA DEMO SYNTHETIC', dashboard: 'Dashboard', catalog: 'Katalog', inventory: 'Inventory', listings: 'Listing', simulator: 'Simulator W/F/L', profile: 'Profil', trade: 'Trade ini', fair: 'FAIR untuk kamu', fresh: 'Data terbaru', found: 'listing ditemukan', searching: 'Mencari trade aktif…', errorTitle: 'Pencarian belum dapat diperbarui', networkError: 'Koneksi terputus. Hasil dan filter sebelumnya tetap tersedia.', retry: 'Coba lagi', emptyTitle: 'Belum ada trade yang cocok', emptyBody: 'Ubah satu atau beberapa filter untuk menemukan listing aktif lain.', changeFilters: 'Ubah filter', loadNext: 'Halaman berikutnya', offeredItem: 'Item ditawarkan', anyOffered: 'Semua item tawaran', wantedItem: 'Item dicari', anyWanted: 'Semua item dicari', category: 'Kategori', allCategories: 'Semua kategori', minimumValue: 'Value minimum', maximumValue: 'Value maksimum', freshness: 'Kesegaran', anyTime: 'Kapan saja', lastHour: 'Satu jam terakhir', today: 'Hari ini', lastWeek: 'Tujuh hari terakhir', applyFilters: 'Terapkan filter', rangeError: 'Value maksimum harus sama dengan atau lebih besar dari value minimum.', buildOffer: 'Susun item yang kamu berikan', chooseTarget: 'Pilih item yang ingin diterima', openSimulator: 'Buka simulator', recommendationHint: 'Pilih listing untuk melihat kombinasi inventory yang sesuai.', match: 'Cari kombinasi' },
    en: { discover: 'Discover Trades', search: 'Choose trade filters', filters: 'Filters', searchAction: 'Search', give: 'You Give', receive: 'You Receive', snapshot: 'Value Snapshot', latest: 'Latest Trade Listings', viewAll: 'View all', recommended: 'Recommendations', synthetic: 'SYNTHETIC DEMO DATA', dashboard: 'Dashboard', catalog: 'Catalog', inventory: 'Inventory', listings: 'Listings', simulator: 'W/F/L Simulator', profile: 'Profile', trade: 'This trade', fair: 'FAIR for you', fresh: 'Latest data', found: 'listings found', searching: 'Searching active trades…', errorTitle: 'Search could not be updated', networkError: 'The connection was interrupted. Previous results and filters remain available.', retry: 'Try again', emptyTitle: 'No matching currents', emptyBody: 'Adjust one or more filters to discover other active trades.', changeFilters: 'Change filters', loadNext: 'Next page', offeredItem: 'Offered item', anyOffered: 'Any offered item', wantedItem: 'Wanted item', anyWanted: 'Any wanted item', category: 'Category', allCategories: 'All categories', minimumValue: 'Minimum value', maximumValue: 'Maximum value', freshness: 'Freshness', anyTime: 'Any time', lastHour: 'Last hour', today: 'Today', lastWeek: 'Last week', applyFilters: 'Apply filters', rangeError: 'Maximum value must be equal to or greater than minimum value.', buildOffer: 'Build the items you will give', chooseTarget: 'Choose the items you want', openSimulator: 'Open simulator', recommendationHint: 'Choose a listing to see matching inventory combinations.', match: 'Find a match' },
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
    synthetic = false,
}: {
    listings?: ListingSummary[];
    snapshot?: { version: string; ageLabel: string };
    wfl?: WflResult;
    nextCursor?: string | null;
    filters?: Partial<SearchFilters>;
    filterOptions?: { items: Option[]; categories: Option[] };
    synthetic?: boolean;
}) {
    const { props } = usePage<SharedProps>();
    const {locale,toggle,user}=useProductLocale();
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
    const t = copy[locale];
    const deltaLabel = wfl?.deltaPercent == null ? '—' : `${wfl.deltaPercent >= 0 ? '+' : ''}${wfl.deltaPercent.toFixed(1)}%`;
    const statusLabel = wfl?.status ? wfl.status.toUpperCase() : 'W/F/L';

    useEffect(() => {
        const stopStart = router.on('start', () => setLoading(true));
        const stopFinish = router.on('finish', () => setLoading(false));
        return () => { stopStart(); stopFinish(); };
    }, []);

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

    return <ProductShell active="discovery" locale={locale} onLocaleToggle={toggle} user={user} initialTheme={props.preferences?.theme} syntheticLabel={synthetic?t.synthetic:undefined}><Head title={t.discover}/>
            <section className="command-bar" aria-label="Trade search">
                <button className="search-field" onClick={() => setFiltersOpen(true)}><Search/><span>{t.search}</span></button>
                <button className="filter-button" onClick={() => setFiltersOpen(true)}><SlidersHorizontal/>{t.filters}</button>
                <button className="primary-button" onClick={() => setFiltersOpen(true)}><Search/>{t.searchAction}</button>
            </section>
            <section className="trade-zone" data-ui="trade-comparison">
                <div className="trade-panel give-panel"><PanelTitle n="01" over={t.trade} title={t.give}/><ItemCard tone="cyan" name={t.buildOffer} category={t.simulator} value="—" icon={<Sparkles/>}/><Link className="add-item" href="/simulator">{t.openSimulator}</Link></div> {/* taste-skill-ignore: paired trade-side marker from the approved composition */}
                <div className="balance-orb" aria-label={wfl?.status || 'unavailable'}><span>{deltaLabel}</span><strong>{statusLabel}</strong></div>
                <div className="trade-panel receive-panel"><PanelTitle n="02" over={t.trade} title={t.receive}/><ItemCard tone="violet" name={t.chooseTarget} category={t.simulator} value="—" icon={<Anchor/>}/><Link className="add-item" href="/simulator">{t.openSimulator}</Link></div> {/* taste-skill-ignore: paired trade-side marker from the approved composition */}
                <aside className="insight-column"><div className="snapshot-card"><div><p>{t.snapshot}</p><strong>{snapshot.version}</strong></div><span className="live-dot">{t.fresh}</span><small>{snapshot.ageLabel}</small></div><div className="recommendation-card"><div className="recommendation-head"><div><p>{t.recommended}</p><h3>{t.openSimulator}</h3></div><Filter/></div><p>{t.recommendationHint}</p><Link className="recommendation-link" href="/simulator">{t.openSimulator}<ArrowRight/></Link></div></aside>
            </section>
            <section className="listing-section" data-ui="listing-results" aria-busy={loading}><div className="section-head"><div><p className="eyebrow">{listings.length} {t.found}</p><h2>{t.latest}</h2></div><Link href="/listings">{t.viewAll}<ArrowRight/></Link></div>{loading && <div className="search-feedback loading" role="status" aria-live="polite"><span aria-hidden="true"/>{t.searching}</div>}{serverError && <div className="search-feedback error" role="alert"><div><strong>{t.errorTitle}</strong><span>{serverError}</span></div><button onClick={() => retrySearch.current()}>{t.retry}</button></div>}{listings.length === 0 ? <div className="empty-state"><Fish/><h3>{t.emptyTitle}</h3><p>{t.emptyBody}</p><button className="filter-button" onClick={() => setFiltersOpen(true)}>{t.changeFilters}</button></div> : <div className="listing-grid" tabIndex={0} aria-label={t.latest}>{listings.map((listing) => <article className="listing-card" data-listing-id={listing.id} key={listing.id}><div className="listing-top"><span className="anonymous"><UserRound/>{listing.alias}</span><span>{listing.age}</span></div><div className="listing-flow"><img className="mini-glyph" src={listing.offeredThumbnailUrl} alt=""/><div><small>OFFERS</small><strong>{listing.offered}</strong></div><ArrowRight/><div className="wanted-item"><img src={listing.wantedThumbnailUrl} alt=""/><span><small>WANTS</small><strong>{listing.wanted}</strong></span></div></div><div className="listing-foot"><strong>{listing.value?.toLocaleString() ?? '—'} pts</strong><span className={`status ${listing.status}`}>{listing.status.toUpperCase()}</span><Link href={'/recommendations/'+listing.id}>{t.match}<ArrowRight/></Link></div></article>)}</div>}{nextHref && <button className="primary-button pagination-button" onClick={() => runSearch(nextHref)} disabled={loading}>{t.loadNext}</button>}</section> {/* taste-skill-ignore: compact result count is functional metadata */}
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
    </ProductShell>;
}

function PanelTitle({ n, over, title }: { n: string; over: string; title: string }) { return <div className="panel-title"><span className="number">{n}</span><div><p>{over}</p><h1>{title}</h1></div></div>; }
function ItemCard({ tone, name, category, value, icon }: { tone: string; name: string; category: string; value: string; icon: React.ReactNode }) { return <div className={`item-card ${tone}`}><div className="item-glyph">{icon}</div><div><strong>{name}</strong><small>{category}</small></div><span>{value}<small>PTS</small></span></div>; }
