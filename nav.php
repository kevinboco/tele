<?php
// nav_minirail_png_labels.php — Riel auto-alto + labels en hover + magnificación
?>
<style>
:root{ --rail-w:64px; --bg:#0a0a0a; --br:#1f1f1f; --btn:#111; --z-rail:1000; --z-ov:999; }

.menu-toggle{
  position:fixed; left:14px; top:14px; width:56px; height:56px; border-radius:50%;
  background:var(--btn); color:#fff; display:grid; place-items:center; cursor:pointer;
  border:1px solid var(--br); box-shadow:0 8px 18px rgba(0,0,0,.35); z-index:calc(var(--z-rail)+2);
  transition:transform .18s ease, background .25s ease;
}
.menu-toggle:hover{ transform:translateY(-1px); }
.menu-toggle .bars{ position:relative; width:26px; height:2px; background:#fff; border-radius:2px; }
.menu-toggle .bars::before,.menu-toggle .bars::after{
  content:""; position:absolute; left:0; width:26px; height:2px; background:#fff; border-radius:2px;
  transition:transform .22s ease, top .22s ease, opacity .2s ease;
}
.menu-toggle .bars::before{ top:-8px; } .menu-toggle .bars::after{ top:8px; }
.menu-toggle.is-open .bars{ background:transparent; }
.menu-toggle.is-open .bars::before{ top:0; transform:rotate(45deg); }
.menu-toggle.is-open .bars::after{ top:0; transform:rotate(-45deg); }

.nav-overlay{
  position:fixed; inset:0; background:rgba(0,0,0,.15);
  opacity:0; pointer-events:none; transition:opacity .2s ease; z-index:var(--z-ov);
}
.nav-overlay.is-visible{ opacity:1; pointer-events:auto; }

/* 🔹 Riel: altura automática (no llega al fondo) */
.mini-rail{
  position:fixed; left:10px; top:84px;
  display:inline-flex; flex-direction:column; align-items:center; gap:10px;
  padding:10px 8px;
  background:var(--bg); border:1px solid var(--br); border-radius:16px;
  box-shadow:0 12px 28px rgba(0,0,0,.35);
  height:auto; max-height:calc(100vh - 140px);  /* por si hay muchos ítems */
  overflow:visible;                              /* labels pueden salir */
  transform:translateX(-90px); transition:transform .26s ease; z-index:var(--z-rail);
}
.mini-rail.is-open{ transform:translateX(0); }

/* Botones */
.rail-item{
  position:relative; width:48px; height:48px; display:grid; place-items:center;
  background:#0e0e0e; border:1px solid #202020; border-radius:10px;
  box-shadow: inset 0 0 0 2px #0f0f0f;
  text-decoration:none; transition:transform .12s ease, border-color .2s ease, box-shadow .2s ease;
  will-change: width, height, transform;
}
.rail-item:hover{ border-color:#2b2b2b; box-shadow: inset 0 0 0 2px #1a1a1a; }
.rail-item img{ width:28px; height:28px; object-fit:contain; display:block; filter: drop-shadow(0 0 2px rgba(0,255,180,.35)); }

/* 🔹 Label textual del destino (aparece al pasar el mouse) */
.rail-label{
  position:absolute; left:calc(100% + 10px); top:50%; transform:translateY(-50%);
  background:#0b0b0b; color:#eaeaea; border:1px solid #202020; border-radius:10px;
  padding:6px 10px; font-size:12px; white-space:nowrap;
  box-shadow:0 8px 18px rgba(0,0,0,.35);
  opacity:0; pointer-events:none; transition:opacity .15s ease, transform .15s ease;
}
.rail-item:hover .rail-label{ opacity:1; transform:translateY(-50%) translateX(2px); }

@media (max-width:480px){ .mini-rail{ left:8px; } }
</style>

<button id="menuToggle" class="menu-toggle" aria-label="Abrir menú" aria-expanded="false" aria-controls="miniRail">
  <span class="bars" aria-hidden="true"></span>
</button>
<div id="navOverlay" class="nav-overlay" hidden></div>

<nav id="miniRail" class="mini-rail" aria-label="Navegación rápida">
  <!-- INICIO -->
  <a class="rail-item" href="index2.php" title="Inicio">
    <img src="https://img.icons8.com/ios-filled/48/ffffff/home.png" alt="Inicio"
         onerror="this.onerror=null;this.src='https://cdn-icons-png.flaticon.com/512/1946/1946488.png';">
    <span class="rail-label">Inicio</span>
  </a>

  <!-- INFORME DE VIAJES -->
  <a class="rail-item" href="informe.php" title="Informe de viajes">
    <img src="https://img.icons8.com/ios-filled/48/00e0a0/combo-chart.png" alt="Informe de viajes"
         onerror="this.onerror=null;this.src='https://cdn-icons-png.flaticon.com/512/1828/1828911.png';">
    <span class="rail-label">Informe de viajes</span>
  </a>

  <!-- LIQUIDACIÓN -->
  <a class="rail-item" href="https://asociacion.asociaciondetransportistaszonanorte.io/tele/liquidacion.php?desde=2025-09-29&hasta=2025-10-12&empresa=Hospital" title="Liquidación">
    <img src="https://img.icons8.com/ios-filled/48/00e0a0/bill.png" alt="Liquidación"
         onerror="this.onerror=null;this.src='https://cdn-icons-png.flaticon.com/512/1250/1250700.png';">
    <span class="rail-label">Liquidación</span>
  </a>

  <!-- MAPA PRÉSTAMOS -->
  <a class="rail-item" href="https://asociacion.asociaciondetransportistaszonanorte.io/tele/prueba.php?view=graph" title="Mapa préstamos (D3)">
    <img src="https://img.icons8.com/ios-filled/50/00e0a0/network.png" alt="Mapa préstamos"
         onerror="this.onerror=null;this.src='https://cdn-icons-png.flaticon.com/512/971/971112.png';">
    <span class="rail-label">Mapa préstamos</span>
  </a>

  <!-- EDITAR PRÉSTAMOS -->
  <a class="rail-item" href="https://asociacion.asociaciondetransportistaszonanorte.io/tele/admin_prestamos.php?view=cards" title="Editar préstamos">
    <img src="https://img.icons8.com/ios-filled/48/ffffff/edit-file.png" alt="Editar préstamos"
         onerror="this.onerror=null;this.src='https://cdn-icons-png.flaticon.com/512/1159/1159633.png';">
    <span class="rail-label">Editar préstamos</span>
  </a>
</nav>

<script>
(function(){
  const btn = document.getElementById('menuToggle');
  const rail = document.getElementById('miniRail');
  const ov = document.getElementById('navOverlay');

  function openR(){ rail.classList.add('is-open'); btn.classList.add('is-open');
    ov.hidden=false; ov.classList.add('is-visible'); btn.setAttribute('aria-expanded','true');
    document.body.style.overflow='hidden'; }
  function closeR(){ rail.classList.remove('is-open'); btn.classList.remove('is-open');
    ov.classList.remove('is-visible'); btn.setAttribute('aria-expanded','false');
    document.body.style.overflow=''; setTimeout(()=>ov.hidden=true,180); }

  btn.addEventListener('click', ()=> rail.classList.contains('is-open') ? closeR() : openR());
  ov.addEventListener('click', closeR);
  window.addEventListener('keydown', e => { if(e.key==='Escape' && rail.classList.contains('is-open')) closeR(); });

  // Magnificación vertical (como tu dock)
  const items = [...rail.querySelectorAll('.rail-item')];
  rail.addEventListener('mousemove', (e) => {
    const R = 140, base=48, y = e.clientY;
    items.forEach(it=>{
      const r=it.getBoundingClientRect(), d=Math.abs(y-(r.top+r.height/2));
      if(d<R){ const s=1+(1-d/R)*0.35, size=base*s; it.style.width=size+'px'; it.style.height=size+'px'; it.style.zIndex=1000-d; }
      else { it.style.width=base+'px'; it.style.height=base+'px'; it.style.zIndex='auto'; }
    });
  });
  rail.addEventListener('mouseleave', ()=> items.forEach(i=>{ i.style.width='48px'; i.style.height='48px'; i.style.zIndex='auto'; }));
})();
</script>
