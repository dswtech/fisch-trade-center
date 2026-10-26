import {Link} from '@inertiajs/react';
import {Anchor,BookOpen,Boxes,Compass,Fish,LayoutDashboard,Menu,Moon,ShieldCheck,ShipWheel,Sun,UserRound,X} from 'lucide-react';
import {useEffect,useState,type ReactNode} from 'react';
import {retryableMutation} from '../lib/network-recovery';
import {resolveTheme} from '../lib/preferences';
import type {Locale,SharedProps,Theme} from '../types';

export type ProductShellSection='dashboard'|'discovery'|'catalog'|'inventory'|'listings'|'negotiations'|'simulator'|'profile'|'trust';

type User=SharedProps['auth']['user'];
type Props={
 active:ProductShellSection;
 children:ReactNode;
 locale:Locale;
 onLocaleToggle:()=>void;
 user?:User;
 initialTheme?:Theme|null;
 syntheticLabel?:string;
};

const labels={
 id:{dashboard:'Dashboard',discovery:'Temukan Trade',catalog:'Katalog',inventory:'Inventory',listings:'Listing',negotiations:'Negosiasi',simulator:'Simulator W/F/L',profile:'Profil',trust:'Trust operations',player:'Player',guest:'Guest angler',signIn:'Masuk untuk trade',openMenu:'Buka menu',closeMenu:'Tutup menu',toggleTheme:'Ganti tema'},
 en:{dashboard:'Dashboard',discovery:'Discover Trades',catalog:'Catalog',inventory:'Inventory',listings:'Listings',negotiations:'Negotiations',simulator:'W/F/L Simulator',profile:'Profile',trust:'Trust operations',player:'Player',guest:'Guest angler',signIn:'Sign in to trade',openMenu:'Open menu',closeMenu:'Close menu',toggleTheme:'Toggle theme'},
};

export default function ProductShell({active,children,locale,onLocaleToggle,user,initialTheme,syntheticLabel}:Props){
 const [drawer,setDrawer]=useState(false);
 const [theme,setTheme]=useState<Theme>(()=>resolveTheme(typeof localStorage==='undefined'?null:localStorage.getItem('ftc-theme'),initialTheme));
 const t=labels[locale];
 const nav=[
  {key:'dashboard',label:t.dashboard,href:'/',icon:LayoutDashboard},
  {key:'discovery',label:t.discovery,href:'/',icon:Compass},
  {key:'catalog',label:t.catalog,href:'/catalog',icon:BookOpen},
  {key:'inventory',label:t.inventory,href:'/inventory',icon:Boxes},
  {key:'listings',label:t.listings,href:user?'/my-listings':'/listings',icon:Fish},
  ...(user?[{key:'negotiations',label:t.negotiations,href:'/negotiations',icon:Anchor}]:[]),
  {key:'simulator',label:t.simulator,href:'/simulator',icon:ShipWheel},
  ...((user&&['operator','admin'].includes(user.role))?[{key:'trust',label:t.trust,href:'/operator/audit',icon:ShieldCheck}]:[]),
 ] as Array<{key:ProductShellSection;label:string;href:string;icon:typeof LayoutDashboard}>;

 useEffect(()=>{
  document.documentElement.dataset.theme=theme;
  document.documentElement.classList.toggle('dark',theme==='dark');
  localStorage.setItem('ftc-theme',theme);
 },[theme]);

 const toggleTheme=()=>{
  const next=theme==='dark'?'light':'dark';
  setTheme(next);
  if(user)retryableMutation('patch','/preferences',{theme:next});
 };

 return <div className="app-shell">
  <header className="topbar">
   <button className="icon-button mobile-only" aria-label={t.openMenu} onClick={()=>setDrawer(true)}><Menu/></button>
   <Link href="/" className="brand" aria-label="FISCHTRADE home"><img src="/images/brand-mark.png" alt=""/><span>FISCH<span>TRADE</span></span></Link>
   <div className="top-actions">
    {syntheticLabel&&<span className="demo-badge">{syntheticLabel}</span>}
    <button className="locale-button" onClick={onLocaleToggle}>{locale.toUpperCase()}</button>
    <button className="icon-button" aria-label={t.toggleTheme} onClick={toggleTheme}>{theme==='dark'?<Sun/>:<Moon/>}</button>
    <Link className="avatar" aria-label={user?t.profile:t.signIn} href={user?'/profile':'/login'}><UserRound/></Link>
   </div>
  </header>
  <aside className={`sidebar ${drawer?'open':''}`}>
   <button className="icon-button close-drawer" onClick={()=>setDrawer(false)} aria-label={t.closeMenu}><X/></button>
   <nav>{nav.map(({key,label,href,icon:Icon})=><Link key={key} href={href} className={key===active?'active':undefined} aria-current={key===active?'page':undefined} onClick={()=>setDrawer(false)}><Icon/><span>{label}</span></Link>)}</nav>
   <img className="sidebar-art" src="/images/sidebar-ocean-art.png" alt=""/>
   <Link className={`sidebar-profile ${active==='profile'?'active':''}`} href={user?'/profile':'/login'}><span className="avatar"><UserRound/></span><span>{user?.name||t.guest}<small>{user?t.player:t.signIn}</small></span></Link>
  </aside>
  <main className="workspace">{children}</main>
  <nav className="bottom-nav">{nav.filter(item=>['discovery','catalog','listings','simulator'].includes(item.key)).map(({key,label,href,icon:Icon})=><Link href={href} key={key} className={key===active?'active':undefined} aria-current={key===active?'page':undefined}><Icon/><span>{label}</span></Link>)}<Link href={user?'/profile':'/login'} className={active==='profile'?'active':undefined} aria-current={active==='profile'?'page':undefined}><UserRound/><span>{t.profile}</span></Link></nav>
 </div>;
}
