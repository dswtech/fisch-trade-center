import {Head,Link} from '@inertiajs/react';
import {ArrowLeft,Clock3,EyeOff,TimerOff} from 'lucide-react';
import ResilientForm from '../components/resilient-form';

type PreDeal={id:string;negotiationId:string;serverType:string;status:string;expiresAt:string};
type Reveal={id:string;negotiationId:string;expiresAt:string};

export default function AdminTimeControls({preDeals=[],reveals=[]}:{preDeals?:PreDeal[];reveals?:Reveal[]}){
 return <main className="module-page"><Head title="Non-production time controls"/><header className="module-header"><Link href="/admin" className="module-brand" aria-label="ADMIN CONSOLE home"><Clock3/><span>TIME <b>CONTROLS</b></span></Link><Link href="/admin" className="back-link"><ArrowLeft/>Admin operations</Link></header><div className="time-control-shell">
  <header><h1>Non-production time controls</h1><p>Force a real expiry path without waiting 30 minutes or 30 days. Every trigger is admin-only and audited.</p></header>
  <section><header><div><h2>Pre-deal deadlines</h2><p>{preDeals.length} active deadlines</p></div></header>{preDeals.length===0?<p className="operator-empty">No pre-deal deadline is available.</p>:preDeals.map(item=><article key={item.id}><div><strong>{item.serverType.replaceAll('_',' ')}</strong><small>Negotiation {item.negotiationId.slice(0,8)}… · {item.status} · expires {new Date(item.expiresAt).toLocaleString()}</small></div><ResilientForm action={`/admin/time-controls/pre-deals/${item.id}/expire`} method="post"><button className="filter-button"><TimerOff/>Expire now</button></ResilientForm></article>)}</section>
  <section><header><div><h2>Reveal access</h2><p>{reveals.length} deals with active access</p></div></header>{reveals.length===0?<p className="operator-empty">No reveal access is active.</p>:reveals.map(item=><article key={item.id}><div><strong>Deal {item.id.slice(0,8)}…</strong><small>Negotiation {item.negotiationId.slice(0,8)}… · access ends {new Date(item.expiresAt).toLocaleString()}</small></div><ResilientForm action={`/admin/time-controls/deals/${item.id}/expire-reveal`} method="post"><button className="filter-button"><EyeOff/>Expire access</button></ResilientForm></article>)}</section>
 </div></main>;
}
