<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>Ajuste de Pago (con gestor de cuentas de cobro)</title>
<script src="https://cdn.tailwindcss.com"></script>
<style>
  .num { font-variant-numeric: tabular-nums; }
  .table-sticky thead tr { position: sticky; top: 0; z-index: 30; }
  .table-sticky thead th { position: sticky; top: 0; z-index: 31; background-color: #2563eb !important; color: #fff !important; }
  .table-sticky thead { box-shadow: 0 2px 0 rgba(0,0,0,0.06); }

  /* Modal Viajes */
  .viajes-backdrop{ position:fixed; inset:0; background:rgba(0,0,0,.45); display:none; align-items:center; justify-content:center; z-index:10000; }
  .viajes-backdrop.show{ display:flex; }
  .viajes-card{ width:min(720px,94vw); max-height:90vh; overflow:hidden; border-radius:16px; background:#fff;
                box-shadow:0 20px 60px rgba(0,0,0,.25); border:1px solid #e5e7eb; }
  .viajes-header{padding:14px 16px;border-bottom:1px solid #eef2f7}
  .viajes-body{padding:14px 16px;overflow:auto; max-height:70vh}
  .viajes-close{padding:6px 10px; border-radius:10px; cursor:pointer;}
  .viajes-close:hover{background:#f3f4f6}

  .conductor-link{cursor:pointer; color:#0d6efd; text-decoration:underline;}

  /* Optimizaciones para la tabla principal - QUITAR SCROLL VERTICAL */
  .table-responsive {
    font-size: 0.875rem; /* text-sm */
  }
  .table-responsive th,
  .table-responsive td {
    padding: 0.5rem 0.75rem; /* px-3 py-2 */
    white-space: nowrap;
  }
</style>
</head>
<body class="bg-slate-100 text-slate-800 min-h-screen">
  <header class="max-w-[1600px] mx-auto px-3 md:px-4 pt-6">
    <div class="bg-white border border-slate-200 rounded-2xl shadow-sm px-5 py-4">
      <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3">
        <h2 class="text-xl md:text-2xl font-bold">🧾 Ajuste de Pago</h2>
        <div class="flex items-center gap-2">
          <button id="btnShowSaveCuenta" class="rounded-lg border border-amber-300 px-3 py-2 text-sm bg-amber-50 hover:bg-amber-100">⭐ Guardar como cuenta</button>
          <button id="btnShowGestorCuentas" class="rounded-lg border border-blue-300 px-3 py-2 text-sm bg-blue-50 hover:bg-blue-100">📚 Cuentas guardadas</button>
        </div>
      </div>

      <!-- filtros -->
      <form id="formFiltros" class="mt-3 grid grid-cols-1 md:grid-cols-6 gap-3" method="get">
        <label class="block md:col-span-1">
          <span class="block text-xs font-medium mb-1">Desde</span>
          <input id="inp_desde" type="date" name="desde" value="<?= htmlspecialchars($desde) ?>" required class="w-full rounded-xl border border-slate-300 px-3 py-2">
        </label>
        <label class="block md:col-span-1">
          <span class="block text-xs font-medium mb-1">Hasta</span>
          <input id="inp_hasta" type="date" name="hasta" value="<?= htmlspecialchars($hasta) ?>" required class="w-full rounded-xl border border-slate-300 px-3 py-2">
        </label>
        <label class="block md:col-span-2">
          <span class="block text-xs font-medium mb-1">Empresa</span>
          <select id="sel_empresa" name="empresa" class="w-full rounded-xl border border-slate-300 px-3 py-2">
            <option value="">-- Todas --</option>
            <?php
              $resEmp2 = $conn->query("SELECT DISTINCT empresa FROM viajes WHERE empresa IS NOT NULL AND empresa<>'' ORDER BY empresa ASC");
              if ($resEmp2) while ($e = $resEmp2->fetch_assoc()) {
                $sel = ($empresaFiltro==$e['empresa'])?'selected':''; ?>
                <option value="<?= htmlspecialchars($e['empresa']) ?>" <?= $sel ?>><?= htmlspecialchars($e['empresa']) ?></option>
            <?php } ?>
          </select>
        </label>
        <div class="md:col-span-2 flex md:items-end">
          <button class="w-full rounded-xl bg-blue-600 text-white py-2.5 font-semibold shadow">Aplicar</button>
        </div>
      </form>
    </div>
  </header>

  <main class="max-w-[1600px] mx-auto px-3 md:px-4 py-6 space-y-5">
    <!-- Panel montos -->
    <section class="bg-white border border-slate-200 rounded-2xl shadow-sm p-5">
      <div class="grid grid-cols-1 md:grid-cols-6 gap-4">
        <div>
          <div class="text-xs text-slate-500 mb-1">Conductores en rango</div>
          <div class="text-lg font-semibold"><?= count($filas) ?></div>
        </div>
        <label class="block md:col-span-2">
          <span class="block text-xs font-medium mb-1">Cuenta de cobro (facturado)</span>
          <input id="inp_facturado" type="text" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-right num"
                 value="<?= number_format($total_facturado,0,',','.') ?>">
        </label>
        <label class="block md:col-span-2">
          <span class="block text-xs font-medium mb-1">Valor recibido (llegó)</span>
          <input id="inp_recibido" type="text" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-right num"
                 value="<?= number_format($total_facturado,0,',','.') ?>">
        </label>
        <div>
          <div class="text-xs text-slate-500 mb-1">Diferencia a repartir</div>
          <div id="lbl_diferencia" class="text-lg font-semibold text-amber-600 num">0</div>
        </div>
      </div>
    </section>

    <!-- Tabla principal - MODIFICADA: sin max-height -->
    <section class="bg-white border border-slate-200 rounded-2xl shadow-sm p-5">
      <div class="overflow-auto rounded-xl border border-slate-200 table-sticky">
        <table class="w-full text-sm table-responsive">
          <thead class="bg-blue-600 text-white">
            <tr>
              <th class="px-3 py-2 text-left">Conductor</th>
              <th class="px-3 py-2 text-right">Total viajes (base)</th>
              <th class="px-3 py-2 text-right">Ajuste por diferencia</th>
              <th class="px-3 py-2 text-right">Valor que llegó</th>
              <th class="px-3 py-2 text-right">Retención 3.5%</th>
              <th class="px-3 py-2 text-right">4×1000</th>
              <th class="px-3 py-2 text-right">Aporte 10%</th>
              <th class="px-3 py-2 text-right">Seg. social</th>
              <th class="px-3 py-2 text-right">Préstamos (pend.)</th>
              <th class="px-3 py-2 text-left">N° Cuenta</th>
              <th class="px-3 py-2 text-right">A pagar</th>
            </tr>
          </thead>
          <tbody id="tbody" class="divide-y divide-slate-100 bg-white">
            <?php foreach ($filas as $f): ?>
            <tr>
              <td class="px-3 py-2">
                <button type="button" class="conductor-link" title="Ver viajes"><?= htmlspecialchars($f['nombre']) ?></button>
              </td>
              <td class="px-3 py-2 text-right num base"><?= number_format($f['total_bruto'],0,',','.') ?></td>
              <td class="px-3 py-2 text-right num ajuste">0</td>
              <td class="px-3 py-2 text-right num llego">0</td>
              <td class="px-3 py-2 text-right num ret">0</td>
              <td class="px-3 py-2 text-right num mil4">0</td>
              <td class="px-3 py-2 text-right num apor">0</td>
              <td class="px-3 py-2 text-right">
                <input type="text" class="ss w-full max-w-[120px] rounded-lg border border-slate-300 px-2 py-1 text-right num" value="">
              </td>
              <td class="px-3 py-2 text-right">
                <div class="flex items-center justify-end gap-2">
                  <span class="num prest">0</span>
                  <button type="button" class="btn-prest text-xs px-2 py-1 rounded border border-slate-300 bg-slate-50 hover:bg-slate-100">
                    Seleccionar
                  </button>
                </div>
                <div class="text-[11px] text-slate-500 text-right selected-deudor"></div>
              </td>
              <td class="px-3 py-2">
                <input type="text" class="cta w-full max-w-[180px] rounded-lg border border-slate-300 px-2 py-1" value="" placeholder="N° cuenta">
              </td>
              <td class="px-3 py-2 text-right num pagar">0</td>
            </tr>
            <?php endforeach; ?>
          </tbody>
          <tfoot class="bg-slate-50 font-semibold">
            <tr>
              <td class="px-3 py-2" colspan="3">Totales</td>
              <td class="px-3 py-2 text-right num" id="tot_valor_llego">0</td>
              <td class="px-3 py-2 text-right num" id="tot_retencion">0</td>
              <td class="px-3 py-2 text-right num" id="tot_4x1000">0</td>
              <td class="px-3 py-2 text-right num" id="tot_aporte">0</td>
              <td class="px-3 py-2 text-right num" id="tot_ss">0</td>
              <td class="px-3 py-2 text-right num" id="tot_prestamos">0</td>
              <td class="px-3 py-2"></td>
              <td class="px-3 py-2 text-right num" id="tot_pagar">0</td>
            </tr>
          </tfoot>
        </table>
      </div>
    </section>
  </main>

  <!-- El resto del código permanece igual -->
  <!-- ===== Modal PRÉSTAMOS (multi) ===== -->
  <div id="prestModal" class="hidden fixed inset-0 z-50">
    <!-- ... contenido del modal préstamos ... -->
  </div>

  <!-- ===== Modal VIAJES ===== -->
  <div id="viajesModal" class="viajes-backdrop">
    <!-- ... contenido del modal viajes ... -->
  </div>

  <!-- ===== Modal GUARDAR CUENTA ===== -->
  <div id="saveCuentaModal" class="hidden fixed inset-0 z-50">
    <!-- ... contenido del modal guardar cuenta ... -->
  </div>

  <!-- ===== Modal GESTOR DE CUENTAS ===== -->
  <div id="gestorCuentasModal" class="hidden fixed inset-0 z-50">
    <!-- ... contenido del modal gestor de cuentas ... -->
  </div>

<script>
  // El código JavaScript permanece igual
  // ===== Claves de persistencia =====
  const COMPANY_SCOPE = <?= json_encode(($empresaFiltro ?: '__todas__')) ?>;
  const ACC_KEY   = 'cuentas:'+COMPANY_SCOPE;
  const SS_KEY    = 'seg_social:'+COMPANY_SCOPE;
  const PREST_SEL_KEY = 'prestamo_sel_multi:v2:'+COMPANY_SCOPE;
  const PERIODOS_KEY  = 'cuentas_cobro_periodos:v1';

  const PRESTAMOS_LIST = <?php echo json_encode($prestamosList, JSON_UNESCAPED_UNICODE|JSON_NUMERIC_CHECK); ?>;

  const toInt = (s)=>{ if(typeof s==='number') return Math.round(s); s=(s||'').toString().replace(/\./g,'').replace(/,/g,'').replace(/[^\d\-]/g,''); return parseInt(s||'0',10)||0; };
  const fmt = (n)=> (n||0).toLocaleString('es-CO');
  const getLS=(k)=>{try{return JSON.parse(localStorage.getItem(k)||'{}')}catch{return{}}}
  const setLS=(k,v)=> localStorage.setItem(k, JSON.stringify(v));

  let accMap = getLS(ACC_KEY);
  let ssMap  = getLS(SS_KEY);
  let prestSel = getLS(PREST_SEL_KEY); if(!prestSel || typeof prestSel!=='object') prestSel = {};

  const tbody = document.getElementById('tbody');

  function summarizeNames(arr){ if(!arr||arr.length===0)return''; const n=arr.map(x=>x.name); return n.length<=2?n.join(', '): n.slice(0,2).join(', ')+' +'+(n.length-2)+' más'; }
  function sumTotals(arr){ return (arr||[]).reduce((a,b)=> a+(toInt(b.total)||0),0); }

  [...tbody.querySelectorAll('tr')].forEach(tr=>{
    const cta = tr.querySelector('input.cta');
    const ss  = tr.querySelector('input.ss');
    const baseName = tr.children[0].innerText.trim();
    const prestSpan = tr.querySelector('.prest');
    const selLabel  = tr.querySelector('.selected-deudor');

    if (accMap[baseName]) cta.value = accMap[baseName];
    if (ssMap[baseName])  ss.value  = fmt(toInt(ssMap[baseName]));

    const chosen = prestSel[baseName] || [];
    prestSpan.textContent = fmt(sumTotals(chosen));
    selLabel.textContent  = summarizeNames(chosen);

    cta.addEventListener('change', ()=>{ accMap[baseName] = cta.value.trim(); setLS(ACC_KEY, accMap); });
    ss.addEventListener('input', ()=>{ ssMap[baseName] = toInt(ss.value); setLS(SS_KEY, ssMap); recalc(); });
  });

  // ===== Modal préstamos =====
  const prestModal   = document.getElementById('prestModal');
  const btnAssign    = document.getElementById('btnAssign');
  const btnCancel    = document.getElementById('btnCancel');
  const btnClose     = document.getElementById('btnCloseModal');
  const btnSelectAll = document.getElementById('btnSelectAll');
  const btnUnselectAll = document.getElementById('btnUnselectAll');
  const btnClearSel  = document.getElementById('btnClearSel');
  const prestSearch  = document.getElementById('prestSearch');
  const prestList    = document.getElementById('prestList');
  const selCount     = document.getElementById('selCount');
  const selTotal     = document.getElementById('selTotal');

  let currentRow=null, selectedIds=new Set(), filteredIdx=[];

  function renderPrestList(filter=''){
    prestList.innerHTML='';
    const nf=(filter||'').normalize('NFD').replace(/[\u0300-\u036f]/g,'').toLowerCase();
    filteredIdx=[];
    const frag=document.createDocumentFragment();
    PRESTAMOS_LIST.forEach((item,idx)=>{
      if(nf && !item.key.includes(nf)) return;
      filteredIdx.push(idx);

      const row=document.createElement('label');
      row.className='flex items-center justify-between gap-3 px-3 py-2 border-b border-slate-200';
      const left=document.createElement('div'); left.className='flex items-center gap-3';
      const cb=document.createElement('input'); cb.type='checkbox'; cb.checked=selectedIds.has(item.id); cb.dataset.id=item.id;
      const nm=document.createElement('span'); nm.className='truncate max-w-[360px]'; nm.textContent=item.name;
      left.append(cb,nm);
      const val=document.createElement('span'); val.className='num font-semibold'; val.textContent=(item.total||0).toLocaleString('es-CO');
      row.append(left,val);
      cb.addEventListener('change',()=>{ if(cb.checked)selectedIds.add(item.id); else selectedIds.delete(item.id); updateSelSummary(); });
      frag.append(row);
    });
    prestList.append(frag);
    updateSelSummary();
  }
  function updateSelSummary(){
    const arr=PRESTAMOS_LIST.filter(it=>selectedIds.has(it.id));
    selCount.textContent=arr.length;
    selTotal.textContent=arr.reduce((a,b)=>a+(b.total||0),0).toLocaleString('es-CO');
  }
  function openPrestModalForRow(tr){
    currentRow=tr; selectedIds=new Set();
    const baseName=tr.children[0].innerText.trim();
    (prestSel[baseName]||[]).forEach(x=> selectedIds.add(Number(x.id)));
    prestSearch.value=''; renderPrestList('');
    prestModal.classList.remove('hidden');
    requestAnimationFrame(()=>{ prestSearch.focus(); prestSearch.select(); });
  }
  function closePrest(){ prestModal.classList.add('hidden'); currentRow=null; selectedIds=new Set(); filteredIdx=[]; }
  btnCancel.addEventListener('click',closePrest); btnClose.addEventListener('click',closePrest);
  btnSelectAll.addEventListener('click',()=>{ filteredIdx.forEach(i=>selectedIds.add(PRESTAMOS_LIST[i].id)); renderPrestList(prestSearch.value); });
  btnUnselectAll.addEventListener('click',()=>{ filteredIdx.forEach(i=>selectedIds.delete(PRESTAMOS_LIST[i].id)); renderPrestList(prestSearch.value); });
  btnClearSel.addEventListener('click',()=>{
    if(!currentRow) return;
    const baseName=currentRow.children[0].innerText.trim();
    currentRow.querySelector('.prest').textContent='0';
    currentRow.querySelector('.selected-deudor').textContent='';
    delete prestSel[baseName]; setLS(PREST_SEL_KEY, prestSel); recalc();
    selectedIds.clear(); renderPrestList(prestSearch.value);
  });
  btnAssign.addEventListener('click',()=>{
    if(!currentRow) return;
    const baseName=currentRow.children[0].innerText.trim();
    const chosen=PRESTAMOS_LIST.filter(it=>selectedIds.has(it.id)).map(it=>({id:it.id,name:it.name,total:it.total}));
    prestSel[baseName]=chosen; setLS(PREST_SEL_KEY, prestSel);
    currentRow.querySelector('.prest').textContent = sumTotals(chosen).toLocaleString('es-CO');
    currentRow.querySelector('.selected-deudor').textContent = summarizeNames(chosen);
    recalc(); closePrest();
  });
  prestSearch.addEventListener('input',()=>renderPrestList(prestSearch.value));
  tbody.querySelectorAll('.btn-prest').forEach(btn=> btn.addEventListener('click',()=>openPrestModalForRow(btn.closest('tr'))));

  // ===== Datos para el modal de viajes =====
  const RANGO_DESDE = <?= json_encode($desde) ?>;
  const RANGO_HASTA = <?= json_encode($hasta) ?>;
  const RANGO_EMP   = <?= json_encode($empresaFiltro) ?>;
  const CONDUCTORES_LIST = <?= json_encode(array_map(fn($f)=>$f['nombre'],$filas), JSON_UNESCAPED_UNICODE); ?>;

  const viajesModal            = document.getElementById('viajesModal');
  const viajesContent          = document.getElementById('viajesContent');
  const viajesTitle            = document.getElementById('viajesTitle');
  const viajesClose            = document.getElementById('viajesCloseBtn');
  const viajesSelectConductor  = document.getElementById('viajesSelectConductor');
  const viajesRango            = document.getElementById('viajesRango');
  const viajesEmpresa          = document.getElementById('viajesEmpresa');

  let viajesConductorActual = null;

  function initViajesSelect(selectedName) {
    viajesSelectConductor.innerHTML = "";
    CONDUCTORES_LIST.forEach(nombre => {
      const opt = document.createElement('option');
      opt.value = nombre;
      opt.textContent = nombre;
      if (nombre === selectedName) opt.selected = true;
      viajesSelectConductor.appendChild(opt);
    });
  }

  // Esta función SE LLAMA cada vez que cargamos viajes via fetch
  function attachFiltroViajes(){
    const pills = viajesContent.querySelectorAll('#legendFilterBar .legend-pill');
    const rows  = viajesContent.querySelectorAll('#viajesTableBody .row-viaje');
    if (!pills.length || !rows.length) return;

    let activeCat = null;

    function applyFilter(cat){
      if (cat === activeCat) {
        activeCat = null;
      } else {
        activeCat = cat;
      }

      pills.forEach(p => {
        const pcat = p.getAttribute('data-tipo');
        if (activeCat && pcat === activeCat) {
          p.classList.add('ring-2','ring-blue-500','ring-offset-1','ring-offset-white');
        } else {
          p.classList.remove('ring-2','ring-blue-500','ring-offset-1','ring-offset-white');
        }
      });

      rows.forEach(r => {
        if (!activeCat) {
          r.style.display = '';
        } else {
          if (r.classList.contains('cat-' + activeCat)) {
            r.style.display = '';
          } else {
            r.style.display = 'none';
          }
        }
      });
    }

    pills.forEach(p => {
      p.addEventListener('click', ()=>{
        const cat = p.getAttribute('data-tipo');
        applyFilter(cat);
      });
    });
  }

  function loadViajes(nombre) {
    viajesContent.innerHTML = '<p class="text-center m-0 animate-pulse">Cargando…</p>';
    viajesConductorActual = nombre;
    viajesTitle.textContent = nombre;

    const qs = new URLSearchParams({
      viajes_conductor: nombre,
      desde: RANGO_DESDE,
      hasta: RANGO_HASTA,
      empresa: RANGO_EMP
    });

    fetch('<?= basename(__FILE__) ?>?' + qs.toString())
      .then(r => r.text())
      .then(html => {
        viajesContent.innerHTML = html;
        attachFiltroViajes(); // <-- importante
      })
      .catch(() => {
        viajesContent.innerHTML = '<p class="text-center text-rose-600">Error cargando viajes.</p>';
      });
  }

  function abrirModalViajes(nombreInicial){
    viajesRango.textContent   = RANGO_DESDE + " → " + RANGO_HASTA;
    viajesEmpresa.textContent = (RANGO_EMP && RANGO_EMP !== "") ? RANGO_EMP : "Todas las empresas";

    initViajesSelect(nombreInicial);

    viajesModal.classList.add('show');

    loadViajes(nombreInicial);
  }

  function cerrarModalViajes(){
    viajesModal.classList.remove('show');
    viajesContent.innerHTML = '';
    viajesConductorActual = null;
  }

  viajesClose.addEventListener('click', cerrarModalViajes);
  viajesModal.addEventListener('click', (e)=>{
    if(e.target===viajesModal) cerrarModalViajes();
  });

  viajesSelectConductor.addEventListener('change', ()=>{
    const nuevo = viajesSelectConductor.value;
    loadViajes(nuevo);
  });

  document.querySelectorAll('#tbody .conductor-link').forEach(btn=>{
    btn.addEventListener('click', ()=>{
      abrirModalViajes(btn.textContent.trim());
    });
  });

  // ===== Cálculos =====
  function distribIgual(diff,n){
    const arr=new Array(n).fill(0);
    if(n<=0||diff===0)return arr;
    const s=diff>=0?1:-1;
    let a=Math.abs(diff);
    const base=Math.floor(a/n);
    let resto=a%n;
    for(let i=0;i<n;i++){
      arr[i]=s*base+(resto>0?s:0);
      if(resto>0)resto--;
    }
    return arr;
  }

  function recalc(){
    const fact=toInt(document.getElementById('inp_facturado').value);
    const rec =toInt(document.getElementById('inp_recibido').value);
    const diff=fact-rec;
    document.getElementById('lbl_diferencia').textContent=fmt(diff);

    const rows=[...tbody.querySelectorAll('tr')];
    const ajustes=distribIgual(diff, rows.length);

    let sumLleg=0,sumRet=0,sumMil4=0,sumAp=0,sumSS=0,sumPrest=0,sumPagar=0;
    rows.forEach((tr,i)=>{
      const base=toInt(tr.querySelector('.base').textContent);
      const prest=toInt(tr.querySelector('.prest').textContent);
      const aj=ajustes[i]||0;
      const llego=base-aj;
      const ret=Math.round(llego*0.035);
      const mil4=Math.round(llego*0.004);
      const ap=Math.round(llego*0.10);
      const ss=toInt(tr.querySelector('input.ss').value);
      const pagar = llego - ret - mil4 - ap - ss - prest;

      tr.querySelector('.ajuste').textContent=(aj===0?'0':(aj>0?'-'+fmt(aj):'+'+fmt(Math.abs(aj))));
      tr.querySelector('.llego').textContent=fmt(llego);
      tr.querySelector('.ret').textContent=fmt(ret);
      tr.querySelector('.mil4').textContent=fmt(mil4);
      tr.querySelector('.apor').textContent=fmt(ap);
      tr.querySelector('.pagar').textContent=fmt(pagar);

      sumLleg+=llego; sumRet+=ret; sumMil4+=mil4; sumAp+=ap; sumSS+=ss; sumPrest+=prest; sumPagar+=pagar;
    });

    document.getElementById('tot_valor_llego').textContent=fmt(sumLleg);
    document.getElementById('tot_retencion').textContent=fmt(sumRet);
    document.getElementById('tot_4x1000').textContent=fmt(sumMil4);
    document.getElementById('tot_aporte').textContent=fmt(sumAp);
    document.getElementById('tot_ss').textContent=fmt(sumSS);
    document.getElementById('tot_prestamos').textContent=fmt(sumPrest);
    document.getElementById('tot_pagar').textContent=fmt(sumPagar);
  }

  const fmtInput=(el)=> el.addEventListener('input',()=>{ const raw=toInt(el.value); el.value=fmt(raw); recalc(); });
  fmtInput(document.getElementById('inp_facturado'));
  fmtInput(document.getElementById('inp_recibido'));
  recalc();

  // ===== Gestor de cuentas =====
  const formFiltros = document.getElementById('formFiltros');
  const inpDesde = document.getElementById('inp_desde');
  const inpHasta = document.getElementById('inp_hasta');
  const selEmpresa = document.getElementById('sel_empresa');
  const inpFact = document.getElementById('inp_facturado');
  const inpRec = document.getElementById('inp_recibido');

  const saveCuentaModal = document.getElementById('saveCuentaModal');
  const btnShowSaveCuenta = document.getElementById('btnShowSaveCuenta');
  const btnCloseSaveCuenta = document.getElementById('btnCloseSaveCuenta');
  const btnCancelSaveCuenta = document.getElementById('btnCancelSaveCuenta');
  const btnDoSaveCuenta = document.getElementById('btnDoSaveCuenta');

  const iNombre = document.getElementById('cuenta_nombre');
  const iEmpresa = document.getElementById('cuenta_empresa');
  const iRango = document.getElementById('cuenta_rango');
  const iCFact = document.getElementById('cuenta_facturado');
  const iCRec  = document.getElementById('cuenta_recibido');

  const PERIODOS = getLS(PERIODOS_KEY);

  function openSaveCuenta(){
    const emp = selEmpresa.value.trim();
    if(!emp){ alert('Selecciona una EMPRESA antes de guardar la cuenta.'); return; }
    const d = inpDesde.value; const h = inpHasta.value;

    iEmpresa.value = emp;
    iRango.value = `${d} → ${h}`;
    iNombre.value = `${emp} ${d} a ${h}`;
    iCFact.value = fmt(toInt(inpFact.value));
    iCRec.value  = fmt(toInt(inpRec.value));

    saveCuentaModal.classList.remove('hidden');
    setTimeout(()=> iNombre.focus(), 0);
  }
  function closeSaveCuenta(){ saveCuentaModal.classList.add('hidden'); }

  btnShowSaveCuenta.addEventListener('click', openSaveCuenta);
  btnCloseSaveCuenta.addEventListener('click', closeSaveCuenta);
  btnCancelSaveCuenta.addEventListener('click', closeSaveCuenta);

  btnDoSaveCuenta.addEventListener('click', ()=>{
    const emp = iEmpresa.value.trim();
    const [d1, d2raw] = iRango.value.split('→');
    const desde = (d1||'').trim();
    const hasta = (d2raw||'').trim();
    const nombre = iNombre.value.trim() || `${emp} ${desde} a ${hasta}`;
    const facturado = toInt(iCFact.value);
    const recibido  = toInt(iCRec.value);

    const item = { id: Date.now(), nombre, desde, hasta, facturado, recibido };
    if(!PERIODOS[emp]) PERIODOS[emp] = [];
    PERIODOS[emp].push(item);
    setLS(PERIODOS_KEY, PERIODOS);
    closeSaveCuenta();
    alert('Cuenta guardada ✔');
  });

  const gestorModal = document.getElementById('gestorCuentasModal');
  const btnShowGestor = document.getElementById('btnShowGestorCuentas');
  const btnCloseGestor = document.getElementById('btnCloseGestor');
  const btnAddDesdeFiltro = document.getElementById('btnAddDesdeFiltro');
  const lblEmpresaActual = document.getElementById('lblEmpresaActual');
  const buscaCuenta = document.getElementById('buscaCuenta');
  const tbodyCuentas = document.getElementById('tbodyCuentas');

  function renderCuentas(){
    const emp = selEmpresa.value.trim();
    const filtro = (buscaCuenta.value||'').toLowerCase();
    lblEmpresaActual.textContent = emp || '(todas)';

    const arr = (PERIODOS[emp]||[]).slice().sort((a,b)=> (a.desde>b.desde? -1:1));
    tbodyCuentas.innerHTML = '';
    if(arr.length===0){
      tbodyCuentas.innerHTML = "<tr><td colspan='5' class='px-3 py-4 text-center text-slate-500'>No hay cuentas guardadas para esta empresa.</td></tr>";
      return;
    }
    const frag = document.createDocumentFragment();
    arr.forEach(item=>{
      if(filtro && !item.nombre.toLowerCase().includes(filtro)) return;
      const tr = document.createElement('tr');
      tr.innerHTML = `
        <td class="px-3 py-2">${item.nombre}</td>
        <td class="px-3 py-2">${item.desde} &rarr; ${item.hasta}</td>
        <td class="px-3 py-2 text-right num">${fmt(item.facturado||0)}</td>
        <td class="px-3 py-2 text-right num">${fmt(item.recibido||0)}</td>
        <td class="px-3 py-2 text-right">
          <div class="inline-flex gap-2">
            <button class="btnUsar border px-2 py-1 rounded bg-slate-50 hover:bg-slate-100 text-xs">Usar</button>
            <button class="btnUsarAplicar border px-2 py-1 rounded bg-blue-50 hover:bg-blue-100 text-xs">Usar y aplicar</button>
            <button class="btnEditar border px-2 py-1 rounded bg-amber-50 hover:bg-amber-100 text-xs">Editar</button>
            <button class="btnEliminar border px-2 py-1 rounded bg-rose-50 hover:bg-rose-100 text-xs text-rose-700">Eliminar</button>
          </div>
        </td>`;
      tr.querySelector('.btnUsar').addEventListener('click', ()=> usarCuenta(item,false));
      tr.querySelector('.btnUsarAplicar').addEventListener('click', ()=> usarCuenta(item,true));
      tr.querySelector('.btnEditar').addEventListener('click', ()=> editarCuenta(item));
      tr.querySelector('.btnEliminar').addEventListener('click', ()=> eliminarCuenta(item));
      frag.appendChild(tr);
    });
    tbodyCuentas.appendChild(frag);
  }

  function usarCuenta(item, aplicar){
    selEmpresa.value = selEmpresa.value;
    inpDesde.value = item.desde;
    inpHasta.value = item.hasta;
    if(item.facturado) document.getElementById('inp_facturado').value = fmt(item.facturado);
    if(item.recibido)  document.getElementById('inp_recibido').value  = fmt(item.recibido);
    recalc();
    if(aplicar) formFiltros.submit();
  }

  function editarCuenta(item){
    saveCuentaModal.classList.remove('hidden');
    iEmpresa.value = selEmpresa.value;
    iRango.value = `${item.desde} → ${item.hasta}`;
    iNombre.value = item.nombre;
    iCFact.value = fmt(item.facturado||0);
    iCRec.value  = fmt(item.recibido||0);
    btnDoSaveCuenta.onclick = ()=>{
      const [d,h] = iRango.value.split('→').map(s=>s.trim());
      item.nombre = iNombre.value.trim() || item.nombre;
      item.desde  = d || item.desde;
      item.hasta  = h || item.hasta;
      item.facturado = toInt(iCFact.value);
      item.recibido  = toInt(iCRec.value);
      setLS(PERIODOS_KEY, PERIODOS);
      closeSaveCuenta(); renderCuentas();
    };
  }

  function eliminarCuenta(item){
    const emp = selEmpresa.value.trim();
    if(!confirm('¿Eliminar esta cuenta?')) return;
    PERIODOS[emp] = (PERIODOS[emp]||[]).filter(x=> x.id!==item.id);
    setLS(PERIODOS_KEY, PERIODOS);
    renderCuentas();
  }

  function openGestor(){
    renderCuentas();
    gestorModal.classList.remove('hidden');
    setTimeout(()=> buscaCuenta.focus(), 0);
  }
  function closeGestor(){ gestorModal.classList.add('hidden'); }

  btnShowGestor.addEventListener('click', openGestor);
  btnCloseGestor.addEventListener('click', closeGestor);
  buscaCuenta.addEventListener('input', renderCuentas);
  btnAddDesdeFiltro.addEventListener('click', ()=>{ closeGestor(); openSaveCuenta(); });

  const nf1 = el => el.addEventListener('input', ()=>{ el.value = fmt(toInt(el.value)); });
  nf1(iCFact); nf1(iCRec);
</script>

</body>
</html>