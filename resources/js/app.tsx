import '../css/app.css';
import React from 'react';
import {createInertiaApp} from '@inertiajs/react';
import {createRoot} from 'react-dom/client';
import NetworkStatus from './components/network-status';
const pages=import.meta.glob('./pages/**/*.tsx',{eager:true});
createInertiaApp({
  resolve:(name)=>(pages[`./pages/${name}.tsx`] as {default:React.ComponentType}).default,
  setup({el,App,props}){
    createRoot(el).render(<><NetworkStatus/><App {...props}/></>);
    requestAnimationFrame(()=>{
      el.querySelector('.avatar')?.setAttribute('aria-label','Player profile');
      const grid=el.querySelector('.listing-grid');
      grid?.setAttribute('aria-label','Latest trade listings');
      grid?.setAttribute('tabindex','0');
    });
    document.addEventListener('keydown',(event)=>{
      if(event.key!=='Escape')return;
      const backdrop=document.querySelector('.sheet-backdrop');
      if(!backdrop)return;
      backdrop.dispatchEvent(new MouseEvent('mousedown',{bubbles:true}));
      requestAnimationFrame(()=>document.querySelector<HTMLElement>('.filter-button')?.focus());
    });
  },
  progress:{color:'#f0d000'},
});
