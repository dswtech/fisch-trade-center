import {Link,router,usePage} from '@inertiajs/react';
import {Anchor,ArrowRight,Bell,BookOpen,Boxes,ChevronDown,ChevronRight,Compass,Fish,LayoutDashboard,LogOut,Menu,Moon,ShieldCheck,ShipWheel,Sun,UserRound,X} from 'lucide-react';
import {useEffect,useRef,useState,type ReactNode} from 'react';
import {retryableMutation} from '../lib/network-recovery';
import {resolveTheme} from '../lib/preferences';
import type {Locale,SharedProps,Theme} from '../types';
import ResilientForm from './resilient-form';

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
 pendingTradeActions?:number;
};

const labels={
 id:{dashboard:'Dashboard',discovery:'Temukan Trade',catalog:'Katalog',inventory:'Inventory',listings:'Penawaran Saya',negotiations:'Negosiasi',simulator:'Simulator W/F/L',profile:'Profil',trust:'Trust operations',player:'Player',guest:'Guest angler',signIn:'Login',openMenu:'Buka menu',closeMenu:'Tutup menu',toggleTheme:'Ganti tema',notifications:'Tindakan trade',notificationEmpty:'Tidak ada tindakan trade yang menunggu.',allNegotiations:'Lihat semua negosiasi',proposalAction:'Proposal baru menunggu respons',requestItemsAction:'Lawan meminta tambahan item',serverChoiceAction:'Pilih server trade',preDealAction:'Setujui pilihan server',finalAction:'Final acceptance menunggu',account:'Menu akun',logout:'Logout',loginTitle:'Masuk ke akun',loginHint:'Lanjutkan trade tanpa meninggalkan halaman ini.',email:'Email',password:'Password',newAngler:'Angler baru?',createAccount:'Buat akun',closeLogin:'Tutup form login'},
 en:{dashboard:'Dashboard',discovery:'Discover Trades',catalog:'Catalog',inventory:'Inventory',listings:'My Offers',negotiations:'Negotiations',simulator:'W/F/L Simulator',profile:'Profile',trust:'Trust operations',player:'Player',guest:'Guest angler',signIn:'Login',openMenu:'Open menu',closeMenu:'Close menu',toggleTheme:'Toggle theme',notifications:'Trade actions',notificationEmpty:'No trade actions are waiting.',allNegotiations:'View all negotiations',proposalAction:'New proposal needs a response',requestItemsAction:'The other party requests more items',serverChoiceAction:'Choose a trade server',preDealAction:'Accept the server choice',finalAction:'Final acceptance is waiting',account:'Account menu',logout:'Logout',loginTitle:'Sign in to your account',loginHint:'Continue trading without leaving this page.',email:'Email',password:'Password',newAngler:'New angler?',createAccount:'Create account',closeLogin:'Close login form'},
};

const loginRequestEvent='ftc:request-login';
export function requestProductLogin(returnTo:string):void{
 window.dispatchEvent(new CustomEvent(loginRequestEvent,{detail:{returnTo}}));
}

export default function ProductShell({active,children,locale,onLocaleToggle,user,initialTheme,syntheticLabel,pendingTradeActions}:Props){
 const {props,url}=usePage<SharedProps>();
 const pendingCount=pendingTradeActions??props.tradeActions?.pendingCount??0;
 const [drawer,setDrawer]=useState(false);
 const [accountOpen,setAccountOpen]=useState(false);
 const [notificationOpen,setNotificationOpen]=useState(false);
 const [loginOpen,setLoginOpen]=useState(false);
 const [loginReturnTo,setLoginReturnTo]=useState(url);
 const accountRef=useRef<HTMLDivElement>(null);
 const accountTriggerRef=useRef<HTMLButtonElement>(null);
 const notificationRef=useRef<HTMLDivElement>(null);
 const notificationTriggerRef=useRef<HTMLButtonElement>(null);
 const loginTriggerRef=useRef<HTMLButtonElement>(null);
 const loginEmailRef=useRef<HTMLInputElement>(null);
 const [theme,setTheme]=useState<Theme>(()=>resolveTheme(typeof localStorage==='undefined'?null:localStorage.getItem('ftc-theme'),initialTheme));
 const t=labels[locale];
 const nav=[
  {key:'dashboard',label:t.dashboard,href:'/',icon:LayoutDashboard},
  {key:'discovery',label:t.discovery,href:'/',icon:Compass},
  {key:'catalog',label:t.catalog,href:'/catalog',icon:BookOpen},
  {key:'inventory',label:t.inventory,href:'/inventory',icon:Boxes},
  ...(user?[{key:'listings',label:t.listings,href:'/my-listings',icon:Fish}]:[]),
  ...(user?[{key:'negotiations',label:t.negotiations,href:'/negotiations',icon:Anchor}]:[]),
  {key:'simulator',label:t.simulator,href:'/simulator',icon:ShipWheel},
  ...((user&&['operator','admin'].includes(user.role))?[{key:'trust',label:t.trust,href:'/operator/audit',icon:ShieldCheck}]:[]),
 ] as Array<{key:ProductShellSection;label:string;href:string;icon:typeof LayoutDashboard}>;

 useEffect(()=>{
  const open=(event:Event)=>{const target=(event as CustomEvent<{returnTo?:string}>).detail?.returnTo;setLoginReturnTo(target?.startsWith('/')&&!target.startsWith('//')?target:url);setLoginOpen(true)};
  window.addEventListener(loginRequestEvent,open);
  return()=>window.removeEventListener(loginRequestEvent,open);
 },[url]);
 useEffect(()=>{
  document.documentElement.dataset.theme=theme;
  document.documentElement.classList.toggle('dark',theme==='dark');
  localStorage.setItem('ftc-theme',theme);
 },[theme]);
 useEffect(()=>{
  if(!accountOpen)return;
  const close=(event:MouseEvent)=>{if(!accountRef.current?.contains(event.target as Node))setAccountOpen(false)};
  const escape=(event:KeyboardEvent)=>{if(event.key==='Escape'){setAccountOpen(false);accountTriggerRef.current?.focus()}};
  document.addEventListener('mousedown',close);document.addEventListener('keydown',escape);
  return()=>{document.removeEventListener('mousedown',close);document.removeEventListener('keydown',escape)};
 },[accountOpen]);
 useEffect(()=>{
  if(!notificationOpen)return;
  const close=(event:MouseEvent)=>{if(!notificationRef.current?.contains(event.target as Node))setNotificationOpen(false)};
  const escape=(event:KeyboardEvent)=>{if(event.key==='Escape'){setNotificationOpen(false);notificationTriggerRef.current?.focus()}};
  document.addEventListener('mousedown',close);document.addEventListener('keydown',escape);
  return()=>{document.removeEventListener('mousedown',close);document.removeEventListener('keydown',escape)};
 },[notificationOpen]);
 useEffect(()=>{
  if(!loginOpen)return;
  loginEmailRef.current?.focus();
  const escape=(event:KeyboardEvent)=>{if(event.key==='Escape'){setLoginOpen(false);loginTriggerRef.current?.focus()}};
  document.addEventListener('keydown',escape);
  return()=>document.removeEventListener('keydown',escape);
 },[loginOpen]);

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
    {user&&<div className="notification-menu" ref={notificationRef}><button ref={notificationTriggerRef} className="icon-button notification-button" type="button" aria-label={`${t.notifications}: ${pendingCount}`} aria-expanded={notificationOpen} aria-controls="trade-action-popover" onClick={()=>{setNotificationOpen(open=>!open);setAccountOpen(false)}}><Bell/>{pendingCount>0&&<span>{pendingCount>99?'99+':pendingCount}</span>}</button>{notificationOpen&&<div className="notification-popover" id="trade-action-popover" role="dialog" aria-labelledby="trade-action-title"><header><div><h2 id="trade-action-title">{t.notifications}</h2><span>{pendingCount}</span></div></header>{(props.tradeActions?.items??[]).length?<div className="notification-list">{props.tradeActions.items.slice(0,5).map(item=><Link href={item.href} key={`${item.negotiationId}-${item.stage}`} onClick={()=>setNotificationOpen(false)}><span><strong>{item.alias}</strong><small>{tradeActionLabel(item.stage,t)}</small><time dateTime={item.actionAt}>{relativeTime(item.actionAt,locale)}</time></span><ChevronRight/></Link>)}</div>:<p className="notification-empty">{t.notificationEmpty}</p>}<Link className="notification-footer" href="/negotiations" onClick={()=>setNotificationOpen(false)}>{t.allNegotiations}<ArrowRight/></Link></div>}</div>}
    <button className="locale-button" onClick={onLocaleToggle}>{locale.toUpperCase()}</button>
    <button className="icon-button" aria-label={t.toggleTheme} onClick={toggleTheme}>{theme==='dark'?<Sun/>:<Moon/>}</button>
    {user?<div className="account-menu" ref={accountRef}><button ref={accountTriggerRef} className="account-trigger" aria-label={`${t.account}: ${user.name}`} aria-expanded={accountOpen} aria-controls="account-popover" onClick={()=>{setAccountOpen(open=>!open);setNotificationOpen(false)}}><UserRound/><span>{user.name}</span><ChevronDown/></button>{accountOpen&&<div className="account-popover" id="account-popover"><Link href="/profile" onClick={()=>setAccountOpen(false)}><UserRound/>{t.profile}</Link><button type="button" onClick={()=>router.post('/logout')}><LogOut/>{t.logout}</button></div>}</div>:<button ref={loginTriggerRef} className="login-button" type="button" aria-expanded={loginOpen} aria-controls="login-popover" onClick={()=>setLoginOpen(true)}><UserRound/>{t.signIn}</button>}
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
  {loginOpen&&<div className="login-layer" onMouseDown={()=>setLoginOpen(false)}><section className="login-popover" id="login-popover" role="dialog" aria-labelledby="login-title" onMouseDown={event=>event.stopPropagation()}><header><div><h2 id="login-title">{t.loginTitle}</h2><p>{t.loginHint}</p></div><button className="icon-button" type="button" aria-label={t.closeLogin} onClick={()=>{setLoginOpen(false);loginTriggerRef.current?.focus()}}><X/></button></header><ResilientForm action="/login" method="post" className="login-form">{({errors,processing}:any)=><><input type="hidden" name="return_to" value={loginReturnTo}/><label>{t.email}<input ref={loginEmailRef} name="email" type="email" autoComplete="email" required aria-invalid={!!errors.email}/><small role="alert">{errors.email}</small></label><label>{t.password}<input name="password" type="password" autoComplete="current-password" required aria-invalid={!!errors.password}/><small role="alert">{errors.password}</small></label><button className="primary-button" disabled={processing}>{t.signIn}<ArrowRight/></button></>}</ResilientForm><p className="login-register">{t.newAngler} <Link href="/register">{t.createAccount}</Link></p></section></div>}
 </div>;
}

function tradeActionLabel(stage:string,t:Record<string,string>):string{
 return stage==='request_more_items'?t.requestItemsAction:stage==='server_choice'?t.serverChoiceAction:stage==='pre_deal_acceptance'?t.preDealAction:stage==='final_acceptance'?t.finalAction:t.proposalAction;
}

function relativeTime(value:string,locale:Locale):string{
 const elapsed=Math.max(0,Date.now()-new Date(value).getTime());
 const formatter=new Intl.RelativeTimeFormat(locale==='id'?'id':'en',{numeric:'auto'});
 if(elapsed<60_000)return formatter.format(0,'second');
 if(elapsed<3_600_000)return formatter.format(-Math.floor(elapsed/60_000),'minute');
 if(elapsed<86_400_000)return formatter.format(-Math.floor(elapsed/3_600_000),'hour');
 return formatter.format(-Math.floor(elapsed/86_400_000),'day');
}
