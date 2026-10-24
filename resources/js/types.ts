export type Locale='id'|'en'; export type Theme='dark'|'light'; export type WflStatus='win'|'fair'|'lose'|'unavailable';
export interface SharedProps{auth:{user:null|{id:string;name:string;emailVerified:boolean;role:string}};preferences:{locale:Locale|null;theme:Theme|null};flash:{success?:string;error?:string};errors:Record<string,string>}
export interface WflResult{giveTotal:number|null;receiveTotal:number|null;delta:number|null;deltaPercent:number|null;snapshotVersion:string|null;perspective:string;status:WflStatus;reasons:Array<{code:string;item_id?:string}>}
export interface ListingSummary{id:string;alias:string;offered:string;wanted:string;value:number|null;age:string;status:WflStatus}
