export const BAND_LABELS = {critical:'Crítica',high:'Alta',attention:'Atenção',unknown:'Sem histórico',future:'Data futura',recent:'Recente'};
export const BAND_ORDER = ['critical','high','attention','unknown','future','recent'];
const DAY=86400000;
const text=value=>String(value??'');
export const normalized=value=>text(value).normalize('NFD').replace(/[\u0300-\u036f]/g,'').toLowerCase();

export function googleMapsUrl(value) {
  if (typeof value!=='string') return null;
  const match=value.trim().match(/^([+-]?\d+(?:\.\d+)?)\s*,\s*([+-]?\d+(?:\.\d+)?)$/);
  if (!match) return null;
  const latitude=Number(match[1]),longitude=Number(match[2]);
  if (!Number.isFinite(latitude)||!Number.isFinite(longitude)||Math.abs(latitude)>90||Math.abs(longitude)>180) return null;
  return 'https://www.google.com/maps/search/?api=1&query='+encodeURIComponent(latitude+','+longitude);
}

export function validateThresholds(values) {
  const limits=values.map(Number);
  if (limits.length!==3 || limits.some(v=>!Number.isInteger(v)||v<1||v>3650) || !(limits[0]<limits[1]&&limits[1]<limits[2])) throw new Error('Use três números inteiros crescentes, entre 1 e 3650 dias.');
  return limits;
}

export function classify(value, now=Date.now(), thresholds=[7,15,30]) {
  const limits=validateThresholds(thresholds);
  const date=value?Date.parse(value):NaN;
  if (!Number.isFinite(date)) return {band:'unknown',days:null};
  const elapsed=Number(now)-date;
  if (elapsed<0) return {band:'future',days:null};
  const days=Math.floor(elapsed/DAY);
  return {band:elapsed>=limits[2]*DAY?'critical':elapsed>=limits[1]*DAY?'high':elapsed>=limits[0]*DAY?'attention':'recent',days};
}

export function selectRows(rows, filters={}, now=Date.now(), thresholds=[7,15,30]) {
  const query=normalized(filters.search).trim();
  const user=normalized(filters.user).trim();
  const selected=rows.map(row=>({...row,...classify(row.last_inventory_at,now,thresholds)})).filter(row=>{
    for (const key of ['category','model','location','status','band']) if (filters[key]&&filters[key]!=='all'&&text(row[key])!==filters[key]) return false;
    if (query&&!normalized([row.tag,row.name,row.serial,row.model,row.location,row.default_location,row.assigned_user?.name,row.assigned_user?.username,row.current?.reported_user,row.current?.ip].join(' ')).includes(query)) return false;
    if (user) {
      const candidate=filters.userScope==='reported'?row.current?.reported_user:filters.userScope==='historical'?(row.historical_users??[]).join(' '):[row.assigned_user?.name,row.assigned_user?.username].join(' ');
      if (!normalized(candidate).includes(user)) return false;
    }
    return true;
  });
  const order=filters.sort??'priority';
  selected.sort((a,b)=>{
    if (order==='tag') return text(a.tag).localeCompare(text(b.tag),'pt-BR',{numeric:true});
    if (order==='oldest'||order==='newest') {
      if (a.days===null&&b.days!==null) return 1;
      if (b.days===null&&a.days!==null) return -1;
      if (a.days!==null&&b.days!==null) {
        const delta=Date.parse(a.last_inventory_at)-Date.parse(b.last_inventory_at);
        if (delta) return order==='newest'?-delta:delta;
      }
    } else {
      const rank=BAND_ORDER.indexOf(a.band)-BAND_ORDER.indexOf(b.band);
      if (rank) return rank;
      const delta=(b.days??0)-(a.days??0);
      if (delta) return delta;
    }
    return text(a.tag).localeCompare(text(b.tag),'pt-BR',{numeric:true});
  });
  return selected;
}

export function summarize(rows) {
  const bands=Object.fromEntries(BAND_ORDER.map(b=>[b,0]));
  const locations=new Map();
  let withEvidence=0,withIp=0,withUser=0;
  for (const row of rows) {
    bands[row.band]=(bands[row.band]??0)+1;
    if (row.last_inventory_at) withEvidence++;
    if (row.current?.ip) withIp++;
    if (row.current?.reported_user) withUser++;
    const name=row.location||'Sem local cadastrado';
    const item=locations.get(name)??{name,total:0,critical:0,attention:0,unknown:0};
    item.total++;
    if (row.band==='critical') item.critical++;
    if (row.band==='high'||row.band==='attention') item.attention++;
    if (row.band==='unknown') item.unknown++;
    locations.set(name,item);
  }
  return {total:rows.length,bands,withEvidence,withIp,withUser,locations:[...locations.values()].sort((a,b)=>(b.critical+b.attention)-(a.critical+a.attention)||b.total-a.total||a.name.localeCompare(b.name,'pt-BR'))};
}

function csvCell(value) {
  let cell=text(value);
  if (/^[\s]*[=+\-@]/.test(cell)) cell="'"+cell;
  return '"'+cell.replaceAll('"','""')+'"';
}

export function toCsv(rows) {
  const header=['Patrimônio','Nome','Série','Categoria','Modelo','Status','Local cadastrado','Responsável cadastrado','Login cadastrado','Usuário reportado','IP reportado','Coordenadas reportadas','Última alteração de inventário','Dias sem evidência','Faixa','Atualização do cadastro'];
  const lines=rows.map(row=>[row.tag,row.name,row.serial,row.category,row.model,row.status,row.location,row.assigned_user?.name,row.assigned_user?.username,row.current?.reported_user,row.current?.ip,row.current?.coordinates,row.last_inventory_at,row.days,BAND_LABELS[row.band],row.updated_at]);
  return '\uFEFF'+[header,...lines].map(line=>line.map(csvCell).join(';')).join('\r\n');
}
