<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Liquidación de Conductores</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<script src="https://cdn.tailwindcss.com"></script>
<style>
  body {
    font-family: 'Inter', 'Segoe UI', sans-serif;
    background: #f1f5f9;
    color: #0f172a;
    padding: 22px;
  }
  .layout {
    display: grid;
    grid-template-columns: 1fr 2fr 1.2fr;
    gap: 20px;
  }
  @media (max-width: 1200px) {
    .layout { grid-template-columns: 1fr; }
  }
  .box {
    border-radius: 16px;
    background: #fff;
    border: 1px solid #e5e7eb;
  }
  .total-chip {
    display: inline-block;
    padding: 6px 12px;
    border-radius: 999px;
    background: #eef2ff;
    color: #1d4ed8;
    font-weight: 700;
    border: 1px solid #dbe7ff;
    margin-bottom: 8px;
    float: right;
  }
</style>
</head>
<body>

<!-- Header -->
<div class="box p-4 mb-4 shadow-sm">
  <h2 class="text-center m-0 font-extrabold text-slate-900">🪙 Liquidación de Conductores</h2>
  <p class="text-center text-slate-600 mt-1">
    Periodo: <strong><?= htmlspecialchars($_GET['desde'] ?? '') ?></strong> — <strong><?= htmlspecialchars($_GET['hasta'] ?? '') ?></strong>
    <?php if (!empty($_GET['empresa'])): ?> • Empresa: <strong><?= htmlspecialchars($_GET['empresa']) ?></strong><?php endif; ?>
  </p>
</div>

<div class="layout">

  <!-- 🔹 TARIFAS -->
  <section class="box p-5 shadow-md">
    <div class="flex items-center gap-2 mb-4">
      <span class="text-2xl">🚐</span>
      <h3 class="text-xl md:text-2xl font-extrabold text-slate-900 m-0">Tarifas por vehículo</h3>
    </div>

    <div id="tarifas_cards" class="flex flex-col gap-6">
      <!-- 🔸 Ejemplo de tarjeta de vehículo -->
      <?php foreach ($vehiculos as $veh):
        $t = $tarifas_guardadas[$veh] ?? ["completo"=>0,"medio"=>0,"extra"=>0,"carrotanque"=>0];
      ?>
      <div class="rounded-2xl ring-1 ring-slate-200 bg-white shadow-md p-6 hover:shadow-lg transition" data-vehiculo="<?= htmlspecialchars($veh) ?>">
        <span class="inline-flex items-center rounded-xl bg-slate-100 ring-1 ring-slate-200 text-slate-900 font-bold px-4 py-2 mb-3">
          <?= htmlspecialchars($veh) ?>
        </span>

        <?php if ($veh === "Carrotanque"): ?>
          <div class="flex flex-col gap-2">
            <label class="text-sm font-semibold text-slate-600">Carrotanque</label>
            <input type="number" step="1000" data-campo="carrotanque"
              class="tw-tarifa w-full rounded-xl border border-slate-300 px-4 py-2 text-lg font-semibold text-slate-800 bg-white focus:outline-none focus:ring-4 focus:ring-violet-500/20 focus:border-violet-500 transition"
              value="<?= (int)$t['carrotanque'] ?>">
          </div>
        <?php else: ?>
          <div class="flex flex-col gap-4">
            <div class="flex flex-col">
              <label class="text-sm font-semibold text-slate-600">Completo</label>
              <input type="number" step="1000" data-campo="completo"
                class="tw-tarifa w-full rounded-xl border border-slate-300 px-4 py-2 text-lg font-semibold text-slate-800 bg-white focus:outline-none focus:ring-4 focus:ring-violet-500/20 focus:border-violet-500 transition"
                value="<?= (int)$t['completo'] ?>">
            </div>
            <div class="flex flex-col">
              <label class="text-sm font-semibold text-slate-600">Medio</label>
              <input type="number" step="1000" data-campo="medio"
                class="tw-tarifa w-full rounded-xl border border-slate-300 px-4 py-2 text-lg font-semibold text-slate-800 bg-white focus:outline-none focus:ring-4 focus:ring-violet-500/20 focus:border-violet-500 transition"
                value="<?= (int)$t['medio'] ?>">
            </div>
            <div class="flex flex-col">
              <label class="text-sm font-semibold text-slate-600">Extra</label>
              <input type="number" step="1000" data-campo="extra"
                class="tw-tarifa w-full rounded-xl border border-slate-300 px-4 py-2 text-lg font-semibold text-slate-800 bg-white focus:outline-none focus:ring-4 focus:ring-violet-500/20 focus:border-violet-500 transition"
                value="<?= (int)$t['extra'] ?>">
            </div>
          </div>
        <?php endif; ?>
      </div>
      <?php endforeach; ?>
    </div>

    <!-- 🔸 Filtro -->
    <div class="rounded-2xl ring-1 ring-slate-200 bg-white shadow-md p-5 mt-6">
      <div class="flex items-center gap-2 mb-3">
        <span>🗓️</span><h5 class="m-0 text-lg font-extrabold text-slate-900">Filtro de Liquidación</h5>
      </div>
      <form class="row g-3 justify-content-center" method="get">
        <div class="col-md-3">
          <label class="form-label mb-1">Desde:</label>
          <input type="date" name="desde" class="form-control" required>
        </div>
        <div class="col-md-3">
          <label class="form-label mb-1">Hasta:</label>
          <input type="date" name="hasta" class="form-control" required>
        </div>
        <div class="col-md-3">
          <label class="form-label mb-1">Empresa:</label>
          <select name="empresa" class="form-select">
            <option value="">-- Todas --</option>
            <?php foreach($empresas as $e): ?>
              <option value="<?= htmlspecialchars($e) ?>" <?= ($_GET['empresa'] ?? '')==$e?'selected':'' ?>>
                <?= htmlspecialchars($e) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-2 align-self-end">
          <button class="btn btn-primary w-100" type="submit">Filtrar</button>
        </div>
      </form>
    </div>
  </section>

  <!-- 🔹 TABLA DE CONDUCTORES -->
  <section class="box p-4 shadow-sm">
    <h3 class="text-center font-extrabold text-slate-900">🧑‍✈️ Resumen por Conductor
      <span id="total_chip_container" class="total-chip">🔢 Total General: <span id="total_general">0</span></span>
    </h3>

    <table id="tabla_conductores" class="table mt-3">
      <thead>
        <tr>
          <th>Conductor</th><th>Vehículo</th><th>Completos</th><th>Medios</th><th>Extras</th><th>Carrotanques</th><th>Total</th>
        </tr>
      </thead>
      <tbody>
        <!-- Aquí van tus filas dinámicas -->
      </tbody>
    </table>
  </section>

  <!-- 🔹 PANEL DE VIAJES -->
  <aside class="box p-4 shadow-sm" id="panelViajes">
    <h4 class="font-extrabold text-slate-900">🧳 Viajes</h4>
    <div id="contenidoPanel" class="mt-2 text-slate-500">Selecciona un conductor para ver sus viajes aquí.</div>
  </aside>
</div>

<script>
function getTarifas(){
  const tarifas = {};
  document.querySelectorAll('#tarifas_cards [data-vehiculo]').forEach(card=>{
    const veh = card.getAttribute('data-vehiculo').trim();
    tarifas[veh] = {completo:0, medio:0, extra:0, carrotanque:0};
    card.querySelectorAll('input.tw-tarifa').forEach(inp=>{
      tarifas[veh][inp.dataset.campo] = parseFloat(inp.value)||0;
    });
  });
  return tarifas;
}
function formatNumber(num){return (num||0).toLocaleString('es-CO');}
function recalcular(){
  const tarifas = getTarifas();
  let totalGeneral = 0;
  document.querySelectorAll('#tabla_conductores tbody tr').forEach(f=>{
    const veh=f.dataset.vehiculo;
    const c=parseInt(f.cells[2].innerText)||0;
    const m=parseInt(f.cells[3].innerText)||0;
    const e=parseInt(f.cells[4].innerText)||0;
    const ca=parseInt(f.cells[5].innerText)||0;
    const t=tarifas[veh]||{completo:0,medio:0,extra:0,carrotanque:0};
    const totalFila=c*t.completo+m*t.medio+e*t.extra+ca*t.carrotanque;
    f.querySelector('input.totales').value=formatNumber(totalFila);
    totalGeneral+=totalFila;
  });
  document.getElementById('total_general').innerText=formatNumber(totalGeneral);
}
document.querySelectorAll('#tarifas_cards input.tw-tarifa').forEach(input=>{
  input.addEventListener('change',()=>{
    const card = input.closest('[data-vehiculo]');
    const tipoVehiculo = card.getAttribute('data-vehiculo').trim();
    const empresa = "<?= htmlspecialchars($_GET['empresa'] ?? '') ?>";
    const campo = input.dataset.campo;
    const valor = parseInt(input.value)||0;
    fetch(`<?= basename(__FILE__) ?>`,{
      method:'POST',
      headers:{'Content-Type':'application/x-www-form-urlencoded'},
      body:new URLSearchParams({guardar_tarifa:1,empresa,tipo_vehiculo:tipoVehiculo,campo,valor})
    }).then(r=>r.text()).then(()=>recalcular());
  });
});
recalcular();
</script>
</body>
</html>
