import type {Locale,Theme} from '../types';
export function resolveLocale(saved:string|null,server:Locale|null|undefined,browserLanguage:string):Locale{return saved==='id'||saved==='en'?saved:server||(browserLanguage.toLowerCase().startsWith('id')?'id':'en')}
export function resolveTheme(saved:string|null,server:Theme|null|undefined):Theme{return saved==='light'||saved==='dark'?saved:server||'dark'}
