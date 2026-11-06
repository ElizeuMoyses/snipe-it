import { BAND_LABELS, BAND_ORDER, classify, validateThresholds, selectRows, summarize, toCsv, googleMapsUrl } from './computer-health-domain.mjs';

const root = document.getElementById('computer-health-app');
if (root) {
  const element = id => document.getElementById('ch-' + id);
  const jq = window.jQuery;
  const labels = JSON.parse(root.dataset.labels);
  const locale = labels.locale.replace('_', '-');
  const escape = value => String(value ?? '').replace(/[&<>"']/g, character => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[character]));
  const number = value => Number(value).toLocaleString(locale);
  const state = {payload:null, selected:[], thresholds:[7,15,30], now:Date.now(), detail:null, detailToken:0, events:[], returnHistoryId:null};
  const table = () => jq('#computerHealthTable');
  const historyTable = () => jq('#computerHealthHistoryTable');
  const bandClass = {critical:'danger', high:'warning', attention:'warning', unknown:'default', future:'info', recent:'success'};
  const barClass = {...bandClass, unknown:'primary'};
  try {
    const saved = JSON.parse(localStorage.getItem('snipeit-computer-health-thresholds'));
    if (saved) state.thresholds = validateThresholds(saved);
  } catch {}

  function date(value) {
    if (!value || !Number.isFinite(Date.parse(value))) return labels.none;
    return new Intl.DateTimeFormat(locale, {dateStyle:'short', timeStyle:'short', timeZone:state.payload?.source.timezone || 'America/Sao_Paulo'}).format(new Date(value));
  }
  function text(value) {
    return value === null || value === undefined || value === '' ? `<span class="text-muted"><em>${escape(labels.none)}</em></span>` : escape(value);
  }
  function coordinates(value) {
    const url = googleMapsUrl(value);
    return url ? `<a href="${escape(url)}" target="_blank" rel="noopener noreferrer" title="Google Maps">${escape(value)} <i class="fa fa-external-link" aria-hidden="true"></i><span class="sr-only"> (Google Maps)</span></a>` : text(value);
  }
  function badge(row) {
    return `<span class="label label-${bandClass[row.band]}">${BAND_LABELS[row.band]}</span>`;
  }
  function days(row) {
    if (row.days === null) return row.band === 'future' ? 'Conferir a data do evento' : 'Sem alteração registrada';
    return row.days === 0 ? 'Hoje' : `${number(row.days)} ${row.days === 1 ? 'dia' : 'dias'} sem alteração`;
  }
  function link(kind, label, id, row) {
    if (label === null || label === undefined || label === '') return text(label);
    if (!Number.isInteger(Number(id)) || Number(id) <= 0) return text(label);
    const allowed = kind === 'asset' ? row.asset_url : row.links?.[kind];
    const formatters = {asset:'hardwareLinkFormatter', model:'modelsLinkObjFormatter', category:'categoriesLinkObjFormatter', user:'usersLinkObjFormatter', location:'locationsLinkObjFormatter', default_location:'locationsLinkObjFormatter'};
    const formatter = window[formatters[kind]];
    if (!allowed || typeof formatter !== 'function') return text(label);
    // Native API transformers pre-escape these names; this snapshot preserves raw text.
    return kind === 'asset' ? formatter(escape(label), {id:Number(id)}) : formatter({id:Number(id), name:escape(label)});
  }
  function field(label, value, rendered = false) {
    return `<dt>${escape(label)}</dt><dd>${rendered ? value : text(value)}</dd>`;
  }
  function filters() {
    return {...Object.fromEntries(new FormData(element('filters'))), sort:element('sort').value};
  }
  function syncSelect(id) {
    jq(element(id)).trigger('change.select2');
  }
  function setValue(id, value) {
    element(id).value = value;
    syncSelect(id);
  }
  function options(id, values) {
    const select = element(id), previous = select.value;
    const names = [...new Set(values.filter(Boolean))].sort((a,b) => a.localeCompare(b, locale, {numeric:true}));
    select.innerHTML = `<option value="">${escape(labels.all)}</option>` + names.map(name => `<option value="${escape(name)}">${escape(name)}</option>`).join('');
    if (names.includes(previous)) select.value = previous;
    syncSelect(id);
  }
  function thresholdInputs() {
    ['attention','high','critical'].forEach((key,index) => element('thresholds').elements[key].value = state.thresholds[index]);
    element('criteria-summary').textContent = `Critérios: ${state.thresholds.join(' / ')} dias`;
  }
  function source() {
    if (!state.payload) return;
    const payload = state.payload, capture = payload.source.replica_captured_at;
    element('source-summary').innerHTML = field('Fonte','Snipe-IT · ativos e alterações de inventário') + field('Computadores na base',number(payload.source.asset_count)) + field('Histórico de inventário',number(payload.source.inventory_event_count)+' eventos') + field('Fuso das datas',payload.source.timezone) + field(capture ? 'Dados replicados em' : 'Consulta realizada em',date(capture || payload.generated_at)) + field('Eventos com metadados inválidos',number(payload.source.invalid_history_count));
    element('source-fields').textContent = `Campos reconhecidos: ${Object.values(payload.source.fields).map(item => item.label).join(', ') || 'nenhum'}.` + (payload.source.missing_fields.length ? ` Campos ausentes: ${payload.source.missing_fields.join(', ')}.` : '');
  }
  function render(keepPage = false) {
    if (!state.payload) return;
    source();
    state.selected = selectRows(state.payload.assets, filters(), state.now, state.thresholds);
    const stats = summarize(state.selected), [attention,high,critical] = state.thresholds;
    element('total').textContent = number(stats.total);
    const notebooks = state.selected.filter(row => row.category === 'Notebook').length;
    element('total-foot').textContent = `${number(notebooks)} notebooks · ${number(stats.total-notebooks)} desktops`;
    element('total-foot').title = `${number(stats.total)} de ${number(state.payload.assets.length)} computadores na base`;
    element('critical').textContent = number(stats.bands.critical);
    element('critical-foot').textContent = `A partir de ${critical} dias`;
    element('attention').textContent = number(stats.bands.high + stats.bands.attention);
    element('attention-foot').textContent = `${attention} a ${critical-1} dias`;
    element('unknown').textContent = number(stats.bands.unknown);
    element('coverage').textContent = `${number(stats.withEvidence)} de ${number(stats.total)} computadores com histórico no recorte`;
    const ranges = {recent:`menos de ${attention} dias`,attention:`${attention} a ${high-1} dias`,high:`${high} a ${critical-1} dias`,critical:`${critical} dias ou mais`,unknown:'sem registro',future:'conferir a data'};
    for (const band of BAND_ORDER) {
      const wrapper = element('dist-'+band), count = stats.bands[band], percent = stats.total ? count/stats.total*100 : 0;
      element('count-'+band).textContent = number(count)+' · ';
      wrapper.querySelector('.progress-number .text-muted').textContent = Math.round(percent)+'%';
      const bar = wrapper.querySelector('.progress-bar');
      bar.className = 'progress-bar progress-bar-'+barClass[band];
      bar.style.width = percent+'%';
      bar.setAttribute('aria-valuenow',String(Math.round(percent)));
      bar.setAttribute('aria-valuemin','0');bar.setAttribute('aria-valuemax','100');
      wrapper.title = `${BAND_LABELS[band]}: ${ranges[band]}`;
      if (element('band').value === band) wrapper.setAttribute('aria-current','true'); else wrapper.removeAttribute('aria-current');
    }
    const locations = stats.locations.slice(0,5), largest = Math.max(1,...locations.map(item => item.total));
    element('locations').innerHTML = locations.length ? locations.map(item => {
      const title = item.name === 'Sem local cadastrado' ? escape(item.name) : `<a href="#computerHealthTable" data-location="${escape(item.name)}">${escape(item.name)}</a>`;
      return `<div class="progress-group"><span class="progress-text">${title}</span><span class="progress-number">${number(item.critical)} críticas · <b>${number(item.total)}</b></span><div class="progress sm"><div class="progress-bar progress-bar-danger" style="width:${item.critical/largest*100}%"></div><div class="progress-bar progress-bar-warning" style="width:${item.attention/largest*100}%"></div><div class="progress-bar progress-bar-primary" style="width:${(item.total-item.critical-item.attention)/largest*100}%"></div></div></div>`;
    }).join('') : '<p class="text-muted">Nenhum local neste recorte.</p>';
    element('result-count').textContent = `${number(stats.total)} de ${number(state.payload.assets.length)} computadores`;
    const selected = filters(), scope = [selected.status,selected.category,selected.location,selected.model,selected.band ? BAND_LABELS[selected.band] : null].filter(Boolean);
    element('active-scope').textContent = scope.length ? `Filtros: ${scope.join(' · ')}` : 'Todos os computadores, situações e locais';
    element('export').disabled = stats.total === 0;
    const previous = keepPage ? table().bootstrapTable('getOptions').pageNumber : 1;
    table().bootstrapTable('refreshOptions',{sortName:'queue_order',sortOrder:'asc',pageNumber:previous});
    table().bootstrapTable('load',state.selected.map((row,index) => ({...row,queue_order:index})));
    const size = table().bootstrapTable('getOptions').pageSize;
    if (state.selected.length && previous > 1) table().bootstrapTable('selectPage',Math.min(previous,Math.ceil(state.selected.length/size)));
    thresholdInputs();
  }
  function clear() {
    element('filters').reset();element('sort').value = 'priority';
    jq('#ch-filters select').trigger('change.select2');
    render();
  }
  async function request(url) {
    const response = await fetch(url,{credentials:'same-origin',headers:{Accept:'application/json'},cache:'no-store'});
    if (!response.ok) throw new Error(response.status === 401 ? 'Sua sessão expirou. Entre novamente no Snipe-IT.' : response.status === 403 ? 'Seu usuário não tem permissão para estes ativos.' : 'Não foi possível consultar os dados. Tente novamente.');
    try { return await response.json(); } catch { throw new Error('Não foi possível interpretar a resposta. Atualize a visão e tente novamente.'); }
  }
  async function load() {
    element('refresh').disabled = true;
    element('refresh').innerHTML = '<i class="fas fa-sync-alt fa-spin" aria-hidden="true"></i> Atualizando…';
    element('error').hidden = true;
    try {
      const payload = await request(root.dataset.source);
      if (!Array.isArray(payload.assets)) throw new Error('Os dados recebidos não puderam ser interpretados.');
      const first = !state.payload;
      state.payload = payload;state.now = Date.parse(payload.generated_at);
      for (const key of ['category','model','location','status']) options(key,payload.assets.map(row => row[key]));
      if (first && payload.assets.some(row => row.status === 'Utilizando')) setValue('status','Utilizando');
      const capture = payload.source.replica_captured_at;
      element('updated').textContent = capture ? ` · Dados replicados em ${date(capture)}` : ` · Consultado em ${date(payload.generated_at)}`;
      element('loading').hidden = true;element('dashboard').hidden = false;
      render();
    } catch (error) {
      element('loading').hidden = true;element('error').hidden = false;
      element('error').textContent = error.message + (state.payload ? ' A última visão válida foi preservada.' : '');
    } finally {
      element('refresh').disabled = false;
      element('refresh').innerHTML = '<i class="fas fa-sync-alt" aria-hidden="true"></i> Atualizar visão';
    }
  }
  function changeValue(row,key) {
    if (!Object.hasOwn(row.change,key)) return '<span class="text-muted">Não informado neste evento</span>';
    const value = row.change[key];
    if (value === null || value === '') return '<span class="text-muted"><em>Vazio</em></span>';
    return row.change.field === 'coordinates' ? coordinates(value) : escape(value);
  }
  function historyCount() {
    const rows = historyTable().bootstrapTable('getData');
    element('history-count').textContent = `${number(new Set(rows.map(row => row.event_id)).size)} de ${number(state.events.length)} eventos · ${number(rows.length)} alterações de campos`;
  }
  async function openDetail(id) {
    state.detail = id;state.returnHistoryId = id;
    const token = ++state.detailToken;
    element('detail-title').textContent = state.payload.assets.find(row => row.id === id)?.tag || labels.details;
    element('detail-loading').hidden = false;element('detail-content').hidden = true;element('detail-error').hidden = true;
    jq('#ch-detail').modal('show');
    try {
      const payload = await request(`${root.dataset.history}/${id}/history`);
      if (state.detail !== id || state.detailToken !== token) return;
      const queriedAt = Date.parse(payload.generated_at);
      if (!Number.isFinite(queriedAt)) throw new Error('A consulta do histórico não informou um horário válido. Atualize a visão e tente novamente.');
      state.now = queriedAt;
      state.payload.assets = state.payload.assets.map(row => row.id === id ? payload.asset : row);
      state.payload.source.inventory_event_count = state.payload.assets.reduce((sum,row) => sum+row.inventory_event_count,0);
      state.payload.source.invalid_history_count = state.payload.assets.reduce((sum,row) => sum+row.invalid_history_count,0);
      const row = {...payload.asset,...classify(payload.asset.last_inventory_at,queriedAt,state.thresholds)};
      state.events = payload.events;
      render(true);
      element('detail-title').textContent = `${row.tag}${row.name ? ' · '+row.name : ''}`;
      element('detail-priority').innerHTML = `${badge(row)} ${escape(days(row))}`;
      element('detail-fields').innerHTML = field(labels.tag,link('asset',row.tag,row.id,row),true) + field(labels.name,link('asset',row.name,row.id,row),true) + field(labels.model,link('model',row.model,row.model_id,row),true) + field(labels.category,link('category',row.category,row.category_id,row),true) + field(labels.serial,row.serial) + field('Última alteração de inventário',row.last_inventory_at ? date(row.last_inventory_at) : null) + field('Atualização do cadastro',row.updated_at ? date(row.updated_at) : null) + field(labels.assigned,link('user',row.assigned_user?.name || row.assigned_user?.username,row.assigned_user?.id,row),true) + field('Login cadastrado',row.assigned_user?.username) + field('Usuário reportado atual',row.current.reported_user) + field('IP reportado atual',row.current.ip) + field(labels.location,link('location',row.location,row.location_id,row),true) + field(labels.defaultLocation,link('default_location',row.default_location,row.default_location_id,row),true) + field('Coordenadas reportadas',coordinates(row.current.coordinates),true) + field(labels.status,row.status);
      const changes = payload.events.flatMap(event => event.changes.map(change => ({event_id:event.id,created_at:event.created_at,action_source:event.action_source,action_type:event.action_type,change})));
      historyTable().bootstrapTable('resetSearch','');
      historyTable().bootstrapTable('load',changes.map((item,index) => ({...item,sequence:index})));
      element('detail-loading').hidden = true;element('detail-content').hidden = false;
      jq('#tab-ch-device-details').tab('show');
      historyCount();
    } catch (error) {
      if (state.detail === id && state.detailToken === token) {
        element('detail-loading').hidden = true;element('detail-error').textContent = error.message;element('detail-error').hidden = false;
      }
    }
  }
  function prepareTables() {
    // The bundled toolbar extension adds Advanced Search whenever any toolbar is
    // visible, even with advancedSearch:false. Keep one shared filter scope.
    table().bootstrapTable('refreshOptions',{cookie:false,addrbar:false,showAdvancedSearch:false,sortName:'queue_order',sortOrder:'asc',columns:[
      {field:'queue_order',visible:false,switchable:false,sortable:true},
      {field:'band',title:'Prioridade',sortable:true,sorter:(a,b) => BAND_ORDER.indexOf(a)-BAND_ORDER.indexOf(b),formatter:(_value,row) => badge(row)+'<br><small>'+escape(days(row))+'</small>'},
      {field:'tag',title:labels.tag,sortable:true,formatter:(value,row) => link('asset',value,row.id,row)},
      {field:'name',title:labels.name,sortable:true,formatter:(value,row) => link('asset',value,row.id,row)},
      {field:'serial',title:labels.serial,visible:false,sortable:true,formatter:text},
      {field:'category',title:labels.category,visible:false,sortable:true,formatter:(value,row) => link('category',value,row.category_id,row)},
      {field:'model',title:labels.model,sortable:true,formatter:(value,row) => link('model',value,row.model_id,row)},
      {field:'assigned_user.name',title:labels.assigned,sortable:true,formatter:(_value,row) => link('user',row.assigned_user?.name || row.assigned_user?.username,row.assigned_user?.id,row)},
      {field:'current.reported_user',title:'Usuário reportado',sortable:true,formatter:text},
      {field:'current.ip',title:'IP reportado',sortable:true,formatter:text},
      {field:'location',title:labels.location,sortable:true,formatter:(value,row) => link('location',value,row.location_id,row)},
      {field:'status',title:labels.status,visible:false,sortable:true,formatter:text},
      {field:'last_inventory_at',title:'Última alteração de inventário',sortable:true,formatter:value => value ? escape(date(value)) : text(null)},
      {field:'current.coordinates',title:'Coordenadas reportadas',visible:false,formatter:coordinates},
      {field:'actions',title:labels.actions,searchable:false,switchable:false,formatter:(_value,row) => `<button type="button" class="btn btn-default btn-sm" data-history-id="${row.id}" title="Histórico de inventário"><i class="fa fa-history" aria-hidden="true"></i><span class="sr-only"> Histórico de ${escape(row.tag)}</span></button>`}
    ]});
    historyTable().bootstrapTable('refreshOptions',{cookie:false,addrbar:false,showAdvancedSearch:false,stickyHeader:false,sortName:'sequence',sortOrder:'asc',columns:[
      {field:'sequence',visible:false,switchable:false,sortable:true},
      {field:'created_at',title:'Data',sortable:true,formatter:value => escape(date(value))},
      {field:'event_id',title:'Evento',sortable:true,formatter:escape},
      {field:'change.label',title:'Campo',sortable:true,formatter:escape},
      {field:'change.old',title:'Antes',formatter:(_value,row) => changeValue(row,'old')},
      {field:'change.new',title:'Depois',formatter:(_value,row) => changeValue(row,'new')},
      {field:'action_source',title:'Origem',formatter:text}
    ]});
    historyTable().on('post-body.bs.table search.bs.table',historyCount);
    jq('#ch-detail').on('hide.bs.modal',() => {state.detail = null;state.detailToken++;});
    jq('#ch-detail').on('hidden.bs.modal',() => {
      const trigger = root.querySelector(`[data-history-id="${state.returnHistoryId}"]`);
      (trigger || element('refresh')).focus();
    });
    jq('#ch-detail').on('shown.bs.modal',() => historyTable().bootstrapTable('resetView'));
    jq('a[href="#ch-device-history"]').on('shown.bs.tab',() => historyTable().bootstrapTable('resetView'));
    jq('#ch-detail [role="tab"]').on('shown.bs.tab',event => {
      jq('#ch-detail [role="tab"]').attr({'aria-selected':'false',tabindex:'-1'});
      jq(event.target).attr({'aria-selected':'true',tabindex:'0'});
    });
    // Same roving keyboard navigation used by the native contract detail tabs.
    element('detail').addEventListener('keydown',event => {
      const tabs = [...element('detail').querySelectorAll('[role="tab"]')];
      const current = tabs.indexOf(event.target);
      if (current < 0 || !['ArrowRight','ArrowLeft','Home','End'].includes(event.key)) return;
      event.preventDefault();
      const next = event.key === 'Home' ? 0 : event.key === 'End' ? tabs.length-1 : (current+(event.key === 'ArrowRight' ? 1 : -1)+tabs.length)%tabs.length;
      tabs[next].focus();tabs[next].click();
    });
  }

  jq(() => {
    prepareTables();
    let debounce;
    jq('#ch-filters').on('submit',event => event.preventDefault());
    jq('#ch-filters').on('input.computerHealth change.computerHealth','input,select',() => {clearTimeout(debounce);debounce = setTimeout(() => render(),120);});
    element('sort').addEventListener('change',() => render());
    element('refresh').addEventListener('click',load);
    element('clear').addEventListener('click',clear);
    element('source').addEventListener('click',() => {source();jq('#ch-source-dialog').modal('show');});
    root.addEventListener('click',event => {
      const history = event.target.closest('[data-history-id]');
      if (history) {event.preventDefault();openDetail(Number(history.dataset.historyId));}
      const band = event.target.closest('[data-band]');
      if (band) {event.preventDefault();setValue('band',element('band').value === band.dataset.band ? '' : band.dataset.band);render();}
      const location = event.target.closest('[data-location]');
      if (location) {event.preventDefault();setValue('location',location.dataset.location);render();}
    });
    element('thresholds').addEventListener('submit',event => {
      event.preventDefault();
      try {
        state.thresholds = validateThresholds(['attention','high','critical'].map(key => element('thresholds').elements[key].value));
        try {localStorage.setItem('snipeit-computer-health-thresholds',JSON.stringify(state.thresholds));} catch {}
        element('threshold-error').hidden = true;render();
      } catch (error) {element('threshold-error').textContent = error.message;element('threshold-error').hidden = false;}
    });
    element('threshold-reset').addEventListener('click',() => {state.thresholds = [7,15,30];try {localStorage.removeItem('snipeit-computer-health-thresholds');} catch {}element('threshold-error').hidden = true;render();});
    element('export').addEventListener('click',() => {
      const rows = table().bootstrapTable('getData');
      const url = URL.createObjectURL(new Blob([toCsv(rows)],{type:'text/csv;charset=utf-8;'}));
      const anchor = document.createElement('a');anchor.href = url;anchor.download = `computadores-${new Date(state.now).toISOString().slice(0,10)}.csv`;anchor.click();setTimeout(() => URL.revokeObjectURL(url),1000);
    });
    thresholdInputs();load();
  });
}
