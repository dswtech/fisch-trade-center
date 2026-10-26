import React from 'react';
import {renderToStaticMarkup} from 'react-dom/server';
import {describe,expect,it,vi} from 'vitest';

vi.mock('@inertiajs/react',()=>({
 Head:()=>null,
 Link:({href,children,...props}:any)=><a href={href} {...props}>{children}</a>,
 Form:({children,action,method,className}:any)=><form action={action} method={method} className={className}>{typeof children==='function'?children({processing:false,errors:{},submit:vi.fn()}):children}</form>,
}));

import OperatorAudit from '../pages/operator-audit';

describe('operator trust operations',()=>{
 it('renders real report actions, suspension controls, and redacted audit identifiers',()=>{
  const html=renderToStaticMarkup(<OperatorAudit reports={[{id:'report-1',targetType:'user',targetId:'12345678-0000-4000-8000-000000000000',reasonCode:'suspected_scam',status:'open',createdAt:'2026-10-26T00:00:00Z',targets:[{id:'user-1',name:'Trader review'}]}]} suspensions={[]} events={[{public_id:'event-1',correlation_id:'87654321-0000-4000-8000-000000000000',event_type:'report.created',subject_type:'report',subject_public_id:'report-1',reason_code:'suspected_scam',created_at:'2026-10-26T00:00:00Z'}]}/>);

  expect(html).toContain('1 open reports need review.');
  expect(html).toContain('action="/operator/reports/report-1"');
  expect(html).toContain('action="/operator/suspensions"');
  expect(html).toContain('report.created');
  expect(html).not.toContain('email');
 });
});
