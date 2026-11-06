import test from 'node:test';
import assert from 'node:assert/strict';
import { classify, validateThresholds, selectRows, summarize, toCsv, googleMapsUrl } from '../../public/js/computer-health-domain.mjs';

const now = Date.parse('2026-11-06T12:00:00Z');
const age = days => new Date(now - days * 86400000).toISOString();
const limits = [7, 15, 30];
const row = (id, days, extra={}) => ({id, tag:'PC-'+id, name:'Estação '+id, category:'Notebook', model:'Latitude', location:'Palhoça', status:'Em uso', assigned_user:{name:'Pessoa A',username:'pessoa.a'}, current:{ip:'10.0.0.'+id,reported_user:'DOMAIN\\pessoa.b'}, historical_users:['DOMAIN\\antigo'], last_inventory_at:days===null?null:age(days), ...extra});

test('age boundaries are inclusive and null/future dates remain separate', () => {
  for (const [days, expected] of [[0,'recent'],[6.99,'recent'],[7,'attention'],[14.99,'attention'],[15,'high'],[29.99,'high'],[30,'critical'],[200,'critical'],[-0.01,'future']]) {
    assert.equal(classify(age(days),now,limits).band,expected);
  }
  assert.deepEqual(classify(null,now,limits),{band:'unknown',days:null});
  assert.deepEqual(classify('bad date',now,limits),{band:'unknown',days:null});
});

test('thresholds accept ordered integers and reject invalid ranges', () => {
  assert.deepEqual(validateThresholds([3,10,60]),[3,10,60]);
  for (const invalid of [[0,15,30],[7,7,30],[15,7,30],[7,15,5000],[7.5,15,30]]) assert.throws(()=>validateThresholds(invalid));
  assert.equal(classify(age(12),now,[3,10,60]).band,'high');
});

test('combined filters intersect categories, model, location and exact band', () => {
  const rows=[row(1,40),row(2,40,{category:'Desktop'}),row(3,2),row(4,40,{location:'Curitiba'})];
  assert.deepEqual(selectRows(rows,{category:'Notebook',location:'Palhoça',model:'Latitude',band:'critical'},now,limits).map(r=>r.id),[1]);
  assert.equal(selectRows(rows,{search:'inexistente'},now,limits).length,0);
  assert.equal(selectRows(rows,{search:'palhoca'},now,limits).length,3);
});

test('assigned, reported and historical user filters remain distinct', () => {
  const rows=[row(1,40)];
  assert.equal(selectRows(rows,{user:'pessoa.a',userScope:'assigned'},now,limits).length,1);
  assert.equal(selectRows(rows,{user:'pessoa.a',userScope:'reported'},now,limits).length,0);
  assert.equal(selectRows(rows,{user:'antigo',userScope:'historical'},now,limits).length,1);
});

test('priority ordering and filtered summaries use only selected records', () => {
  const selected=selectRows([row(1,2),row(2,40),row(3,17),row(4,null)],{},now,limits);
  assert.deepEqual(selected.map(r=>r.id),[2,3,4,1]);
  const totals=summarize(selected);
  assert.equal(totals.total,4);
  assert.equal(totals.bands.critical,1);
  assert.equal(totals.bands.unknown,1);
  assert.equal(totals.withEvidence,3);
  assert.equal(summarize(selectRows(selected,{band:'critical'},now,limits)).total,1);
});

test('CSV exports selected records and neutralizes spreadsheet formulas', () => {
  const csv=toCsv(selectRows([row(1,40,{name:'=HYPERLINK("bad")'}),row(2,2)],{band:'critical'},now,limits));
  assert.ok(csv.startsWith('\uFEFF'));
  assert.ok(csv.includes("'=HYPERLINK"));
  assert.ok(csv.includes('PC-1'));
  assert.ok(!csv.includes('PC-2'));
  assert.ok(csv.includes('DOMAIN\\pessoa.b'));
});

test('oldest and newest ordering compare full timestamps within the same day', () => {
  const rows=[row(1,0.1),row(2,0.9),row(3,null),row(4,-1)];
  assert.deepEqual(selectRows(rows,{sort:'oldest'},now,limits).map(r=>r.id),[2,1,3,4]);
  assert.deepEqual(selectRows(rows,{sort:'newest'},now,limits).map(r=>r.id),[1,2,3,4]);
});

test('coordinates link to Google Maps only for complete decimal latitude and longitude', () => {
  assert.equal(googleMapsUrl(' -23.5, -46.6 '),'https://www.google.com/maps/search/?api=1&query=-23.5%2C-46.6');
  assert.equal(googleMapsUrl('0,0'),'https://www.google.com/maps/search/?api=1&query=0%2C0');
  assert.equal(googleMapsUrl('+90, -180'),'https://www.google.com/maps/search/?api=1&query=90%2C-180');
  assert.equal(googleMapsUrl('-90.0000,180.0000'),'https://www.google.com/maps/search/?api=1&query=-90%2C180');
});

test('invalid or missing coordinates remain text instead of outbound links', () => {
  for (const value of [null,undefined,'','Sem informação','Rua 1, 2','91,0','0,180.01','-90.1,0','1,2,3','1,','Infinity,0','0x10,20','1e2,20','https://example.invalid','<img src=x onerror=alert(1)>','{"lat":1,"lng":2}',[1,2]]) {
    assert.equal(googleMapsUrl(value),null,String(value));
  }
});
