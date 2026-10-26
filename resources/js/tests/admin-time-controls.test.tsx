import React from 'react';
import {renderToStaticMarkup} from 'react-dom/server';
import {describe,expect,it,vi} from 'vitest';

vi.mock('@inertiajs/react',()=>({
 Head:()=>null,
 Link:({href,children,...props}:any)=><a href={href} {...props}>{children}</a>,
 Form:({children,action,method,className}:any)=><form action={action} method={method} className={className}>{typeof children==='function'?children({processing:false,errors:{},submit:vi.fn()}):children}</form>,
}));

import AdminTimeControls from '../pages/admin-time-controls';

describe('admin non-production time controls',()=>{
 it('renders explicit audited expiry actions for both phase four limits',()=>{
  const html=renderToStaticMarkup(<AdminTimeControls
   preDeals={[{id:'predeal-1',negotiationId:'negotiation-1',serverType:'private_server',status:'accepted',expiresAt:'2026-10-26T00:30:00Z'}]}
   reveals={[{id:'deal-1',negotiationId:'negotiation-1',expiresAt:'2026-11-25T00:00:00Z'}]}
  />);

  expect(html).toContain('Non-production time controls');
  expect(html).toContain('action="/admin/time-controls/pre-deals/predeal-1/expire"');
  expect(html).toContain('action="/admin/time-controls/deals/deal-1/expire-reveal"');
  expect(html).toContain('Every trigger is admin-only and audited.');
 });
});
