import {usePage} from '@inertiajs/react';
import {useEffect,useMemo,useState} from 'react';
import type {Locale,SharedProps} from '../types';
import {retryableMutation} from './network-recovery';
import {resolveLocale} from './preferences';

export function useProductLocale(){
    const {props}=usePage<SharedProps>();
    const initial=useMemo(()=>resolveLocale(localStorage.getItem('ftc-locale'),props.preferences?.locale,navigator.language),[]);
    const [locale,setLocale]=useState<Locale>(initial);
    useEffect(()=>localStorage.setItem('ftc-locale',locale),[locale]);
    const toggle=()=>{const next=locale==='id'?'en':'id';setLocale(next);if(props.auth?.user)retryableMutation('patch','/preferences',{locale:next});};
    return {locale,toggle,user:props.auth?.user};
}
