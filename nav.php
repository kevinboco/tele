<?php
// nav_minirail_png.php — Riel vertical con PNG de internet + magnificación
?>
<style>
:root{ --rail-w:64px; --bg:#0a0a0a; --br:#1f1f1f; --btn:#111; --z-rail:1000; --z-ov:999; }
.menu-toggle{ position:fixed; left:14px; top:14px; width:56px; height:56px; border-radius:50%;
  background:var(--btn); color:#fff; display:grid; place-items:center; cursor:pointer;
  border:1px solid var(--br); box-shadow:0 8px 18px rgba(0,0,0,.35); z-index:calc(var(--z-rail)+2);
  transition:transform .18s ease, background .25s ease; }
.menu-toggle:hover{ transform:translateY(-1px); }
.menu-toggle .bars{ position:relative; width:26px; height:2px; background:#fff; border-radius:2px; }
.menu-toggle .bars::before,.menu-toggle .bars::after{ content:""; position:absolute; left:0; width:26px; height:2px; background:#fff; border-radius:2px;
  transition:transform .22s ease, top .22s ease, opacity .2s ease; }
.menu-toggle .bars::before{ top:-8px; } .menu-toggle .bars::after{ top:8px; }
.menu-toggle.is-open .bars{ background:transparent; }
.menu-toggle.is-open .bars::before{ top:0; transform:rotate(45deg); }
.menu-toggle.is-open .bars::after{ top:0; transform:rotate(-45deg); }

.nav-overlay{ position:fixed; inset:0; background:rgba(0,0,0,.15); opacity:0; pointer-events:none;
  transition:opacity .2s ease; z-index:var(--z-ov); }
.nav-overlay.is-visible{ opacity:1; pointer-events:auto; }

.mini-rail{ position:fixed; left:10px; top:84px; height:calc(100vh - 100px); width:var(--rail-w);
  display:flex; flex-direction:column; align-items:center; gap:10px; padding:10px 8px;
  background:var(--bg); border:1px solid var(--br); border-radius:16px; box-shadow:0 12px 28px rgba(0,0,0,.35);
  transform:translateX(-90px); transition:transform .26s ease; z-index:var(--z-rail); }
.mini-rail.is-open{ transform:translateX(0); }

.rail-item{ position:relative; width:48px; height:48px; display:grid; place-items:center;
  background:#0e0e0e; border:1px solid #202020; border-radius:10px; box-shadow: inset 0 0 0 2px #0f0f0f;
  text-decoration:none; transition:transform .12s ease, border-color .2s ease, box-shadow .2s ease; will-change:width,height,transform; }
.rail-item:hover{ border-color:#2b2b2b; box-shadow: inset 0 0 0 2px #1a1a1a; }
.rail-item img{ width:28px; height:28px; object-fit:contain; display:block; filter: drop-shadow(0 0 2px rgba(0,255,180,.35)); }
@media (max-width:480px){ .mini-rail{ left:8px; } }
</style>

<button id="menuToggle" class="menu-toggle" aria-label="Abrir menú" aria-expanded="false" aria-controls="miniRail">
  <span class="bars" aria-hidden="true"></span>
</button>
<div id="navOverlay" class="nav-overlay" hidden></div>

<nav id="miniRail" class="mini-rail" aria-label="Navegación rápida">
  <!-- INICIO -->
  <a class="rail-item" href="index2.php" title="Inicio">
    <img
      src="https://img.icons8.com/ios-filled/48/ffffff/home.png"
      alt="Inicio"
      onerror="this.onerror=null;this.src='https://cdn-icons-png.flaticon.com/512/1946/1946488.png';">
  </a>

  <!-- INFORME DE VIAJES (gráfica) -->
  <a class="rail-item" href="informe.php" title="Informe de viajes">
    <img
      src="https://img.icons8.com/ios-filled/48/00e0a0/combo-chart.png"
      alt="Informe de viajes"
      onerror="this.onerror=null;this.src='https://cdn-icons-png.flaticon.com/512/1828/1828911.png';">
  </a>

  <!-- LIQUIDACIÓN (recibo/lista) -->
  <a class="rail-item" href="https://asociacion.asociaciondetransportistaszonanorte.io/tele/liquidacion.php?desde=2025-09-29&hasta=2025-10-12&empresa=Hospital" title="Liquidación">
    <img
      src="https://img.icons8.com/ios-filled/48/00e0a0/bill.png"
      alt="Liquidación"
      onerror="this.onerror=null;this.src='https://cdn-icons-png.flaticon.com/512/1250/1250700.png';">
  </a>

  <!-- MAPA PRÉSTAMOS (grafo/red) -->
  <a class="rail-item" href="https://asociacion.asociaciondetransportistaszonanorte.io/tele/prueba.php?view=graph" title="Mapa préstamos (D3)">
    <img
      src="https://img.icons8.com/ios-filled/50/00e0a0/network.png"
      alt="Mapa préstamos"
      onerror="this.onerror=null;this.src='https://cdn-icons-png.flaticon.com/512/971/971112.png';">
  </a>

  <!-- EDITAR PRÉSTAMOS (archivo + lápiz) -->
  <a class="rail-item" href="https://asociacion.asociaciondetransportistaszonanorte.io/tele/admin_prestamos.php?view=cards" title="Editar préstamos">
    <img
      src="https://img.icons8.com/ios-filled/48/ffffff/edit-file.png"
      alt="Editar préstamos"
      onerror="this.onerror=null;this.src='https://cdn-icons-png.flaticon.com/512/1159/1159633.png';">
  </a>
</nav>

<script>
(function(){
  const toggleBtn = document.getElementById('menuToggle');
  const rail = document.getElementById('miniRail');
  const overlay = document.getElementById('navOverlay');

  function openRail(){ rail.classList.add('is-open'); toggleBtn.classList.add('is-open');
    overlay.hidden=false; overlay.classList.add('is-visible'); toggleBtn.setAttribute('aria-expanded','true');
    document.body.style.overflow='hidden'; }
  function closeRail(){ rail.classList.remove('is-open'); toggleBtn.classList.remove('is-open');
    overlay.classList.remove('is-visible'); toggleBtn.setAttribute('aria-expanded','false');
    document.body.style.overflow=''; setTimeout(()=>overlay.hidden=true,180); }

  toggleBtn.addEventListener('click', ()=> rail.classList.contains('is-open') ? closeRail() : openRail());
  overlay.addEventListener('click', closeRail);
  window.addEventListener('keydown', e => { if(e.key==='Escape' && rail.classList.contains('is-open')) closeRail(); });

  // Magnificación vertical (hover cerca)
  const items = [...rail.querySelectorAll('.rail-item')];
  rail.addEventListener('mousemove', (e) => {
    const maxDistance = 140, base = 48, mouseY = e.clientY;
    items.forEach(item => {
      const r = item.getBoundingClientRect();
      const d = Math.abs(mouseY - (r.top + r.height/2));
      if (d < maxDistance){
        const scale = 1 + (1 - d/maxDistance)*0.35;
        const size = base * scale;
        item.style.width = size+'px';
        item.style.height = size+'px';
        item.style.zIndex = 1000 - d;
      } else {
        item.style.width = base+'px';
        item.style.height = base+'px';
        item.style.zIndex = 'auto';
      }
    });
  });
  rail.addEventListener('mouseleave', () => {
    items.forEach(i => { i.style.width='48px'; i.style.height='48px'; i.style.zIndex='auto'; });
  });
})();
</script>
