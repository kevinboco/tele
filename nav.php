<?php
$desde = "2026-01-31";
$hasta = date("Y-m-d");
$empresa = "Hospital";
?>

<style>
:root {
  --bg: #0a0a0a;
  --br: #11f1f1f;
  --btn: #111;
  --z-rail: 1000;
  --z-overlay: 999;
  --z-submenu: 1001;
}

/* === BOTÓN HAMBURGUESA === */
.menu-toggle {
  position: fixed;
  left: 14px;
  top: 14px;
  width: 56px;
  height: 56px;
  border-radius: 50%;
  background: var(--btn);
  color: #fff;
  display: grid;
  place-items: center;
  cursor: pointer;
  border: 1px solid var(--br);
  box-shadow: 0 8px 18px rgba(0, 0, 0, 0.35);
  z-index: calc(var(--z-rail) + 2);
  transition: transform 0.18s ease, background 0.25s ease;
}
.menu-toggle:hover { transform: translateY(-1px); }
.menu-toggle .bars {
  position: relative;
  width: 26px;
  height: 2px;
  background: #fff;
  border-radius: 2px;
}
.menu-toggle .bars::before,
.menu-toggle .bars::after {
  content: "";
  position: absolute;
  left: 0;
  width: 26px;
  height: 2px;
  background: #fff;
  border-radius: 2px;
  transition: transform 0.22s ease, top 0.22s ease;
}
.menu-toggle .bars::before { top: -8px; }
.menu-toggle .bars::after  { top:  8px; }
.menu-toggle.is-open .bars { background: transparent; }
.menu-toggle.is-open .bars::before {
  top: 0;
  transform: rotate(45deg);
}
.menu-toggle.is-open .bars::after {
  top: 0;
  transform: rotate(-45deg);
}

/* === OVERLAY === */
.nav-overlay {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.15);
  opacity: 0;
  pointer-events: none;
  transition: opacity 0.2s ease;
  z-index: var(--z-overlay);
}
.nav-overlay.is-visible { 
  opacity: 1; 
  pointer-events: auto; 
}

/* === RIEL LATERAL === */
.mini-rail {
  position: fixed;
  left: 10px;
  top: 84px;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 12px;
  padding: 10px 8px;
  background: var(--bg);
  border: 1px solid var(--br);
  border-radius: 16px;
  box-shadow: 0 12px 28px rgba(0, 0, 0, 0.35);
  max-height: calc(100vh - 110px);
  overflow-y: auto;
  overflow-x: hidden;
  -webkit-overflow-scrolling: touch;
  transform: translateX(-120%);
  transition: transform 0.26s ease;
  z-index: var(--z-rail);
}
.mini-rail.is-open { 
  transform: translateX(0); 
}

.mini-rail::-webkit-scrollbar {
  width: 6px;
}
.mini-rail::-webkit-scrollbar-track {
  background: #050505;
  border-radius: 10px;
}
.mini-rail::-webkit-scrollbar-thumb {
  background: #333;
  border-radius: 10px;
}
.mini-rail::-webkit-scrollbar-thumb:hover {
  background: #555;
}

/* === BOTONES === */
.rail-item {
  position: relative;
  width: 70px;
  height: 70px;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  background: #0e0e0e;
  border: 1px solid #202020;
  border-radius: 12px;
  box-shadow: inset 0 0 0 2px #0f0f0f;
  text-decoration: none;
  color: #eaeaea;
  font-size: 12px;
  transition: transform 0.15s ease, border-color 0.2s ease;
  flex-shrink: 0;
}
.rail-item img {
  width: 30px;
  height: 30px;
  margin-bottom: 5px;
  object-fit: contain;
  transition: filter 0.15s ease, transform 0.12s ease;
  filter: saturate(1.1);
}
.rail-item:hover {
  border-color: #2b2b2b;
  box-shadow: inset 0 0 0 2px #1a1a1a;
  transform: scale(1.07);
}
.rail-item:hover img { 
  filter: saturate(1.6); 
  transform: translateY(-2px); 
}
.rail-item:hover span { 
  color: #00e0a0; 
}

/* ===== SUBMENÚ DE LIQUIDACIÓN ===== */
.liquidacion-btn {
  position: relative;
}

.submenu {
  position: absolute;
  top: 0;
  left: 100%;
  margin-left: 10px;
  display: none;
  background: #0a0a0a;
  border: 1px solid #11f1f1f;
  border-radius: 12px;
  padding: 8px;
  min-width: 200px;
  z-index: var(--z-submenu);
}

/* Mostrar submenú al hacer hover */
.liquidacion-btn:hover .submenu {
  display: block !important;
}

/* PUENTE INVISIBLE */
.liquidacion-btn::after {
  content: '';
  position: absolute;
  top: -5px;
  right: -20px;
  width: 30px;
  height: 80px;
  background: transparent;
  z-index: var(--z-submenu);
}

/* El submenú se mantiene visible */
.submenu:hover {
  display: block !important;
}

.submenu-item {
  display: block;
  padding: 12px 15px;
  background: #0e0e0e;
  border: 1px solid #202020;
  border-radius: 8px;
  text-decoration: none;
  color: #eaeaea;
  margin-bottom: 5px;
  white-space: nowrap;
  font-size: 13px;
}

.submenu-item:last-child {
  margin-bottom: 0;
}

.submenu-item:hover {
  background: #1a1a1a;
  border-color: #00e0a0;
  color: #00e0a0;
}

@media (max-width: 480px) {
  .mini-rail {
    left: 8px;
    top: 72px;
    max-height: calc(100vh - 90px);
  }
}
</style>

<!-- === BOTÓN HAMBURGUESA === -->
<button id="menuToggle" class="menu-toggle" aria-label="Abrir menú">
  <span class="bars" aria-hidden="true"></span>
</button>

<!-- === OVERLAY === -->
<div id="navOverlay" class="nav-overlay" hidden></div>

<!-- === MENÚ COMPLETO === -->
<nav id="miniRail" class="mini-rail">

  <!-- INICIO -->
  <a class="rail-item" href="index2.php" title="Inicio">
    <img src="https://img.icons8.com/color/48/home--v5.png" alt="Inicio">
    <span>Inicio</span>
  </a>

  <!-- INFORME -->
  <a class="rail-item" href="informe.php" title="Informe de viajes">
    <img src="https://img.icons8.com/color/48/combo-chart--v1.png" alt="Informe">
    <span>Informe</span>
  </a>

  <!-- LIQUIDACIÓN CON SUBMENÚ -->
  <div class="rail-item liquidacion-btn" title="Liquidación">
    <img src="https://img.icons8.com/color/48/bill.png" alt="Liquidación">
    <span>Liquidación</span>
    
    <div class="submenu">
      <!-- Hospital Maicao -->
      <a class="submenu-item" href="https://asociacion.asociaciondetransportistaszonanorte.io/tele/liquidacion.php?desde=<?= $desde ?>&hasta=<?= $hasta ?>&empresas%5B%5D=Hospital&empresas%5B%5D=P.campa%C3%B1a-maicao">
        🏥 Hospital Maicao
      </a>
      
      <!-- Puestos de Salud (todos) -->
      <a class="submenu-item" href="https://asociacion.asociaciondetransportistaszonanorte.io/tele/liquidacion.php?desde=<?= $desde ?>&hasta=<?= $hasta ?>&empresas%5B%5D=P.flor+de+la+guajira&empresas%5B%5D=p.nazareth&empresas%5B%5D=P.paraiso&empresas%5B%5D=P.puerto+estrella&empresas%5B%5D=p.siapana&empresas%5B%5D=P.villa+F%C3%A1tima">
        💊 Puestos de Salud
      </a>
    </div>
  </div>

  <!-- MAPA -->
  <a class="rail-item" href="https://asociacion.asociaciondetransportistaszonanorte.io/tele/prueba.php?view=graph" title="Mapa préstamos">
    <img src="https://img.icons8.com/color/48/share-3.png" alt="Mapa">
    <span>Mapa</span>
  </a>

  <!-- PAGO -->
  <a class="rail-item" href="https://asociacion.asociaciondetransportistaszonanorte.io/tele/pago.php?desde=<?= $desde ?>&hasta=<?= $hasta ?>&empresa=<?= $empresa ?>" title="Pago">
    <img src="https://img.icons8.com/color/48/paid.png" alt="Pago">
    <span>Pago</span>
  </a>

  <!-- EDITAR PRÉSTAMOS -->
  <a class="rail-item" href="https://asociacion.asociaciondetransportistaszonanorte.io/tele/admin_prestamos.php?view=cards" title="Editar préstamos">
    <img src="https://img.icons8.com/color/48/edit-file.png" alt="Editar">
    <span>Editar</span>
  </a>

  <!-- LIQUIDACIÓN PRESTAMISTAS -->
  <a class="rail-item" href="https://asociacion.asociaciondetransportistaszonanorte.io/tele/urgente.php" title="Liquidación prestamistas">
    <img src="https://img.icons8.com/color/64/loan.png" alt="Liquidación prestamistas">
    <span>Liquidación<br>prestamistas</span>
  </a>

  <!-- CUENTAS GUARDADAS -->
  <a class="rail-item" href="https://asociacion.asociaciondetransportistaszonanorte.io/tele/ver_foto_cuenta.php" title="Cuentas guardadas">
    <img src="https://img.icons8.com/color/48/picture.png" alt="Foto">
    <span>Cuentas de<br>cobro guardadas</span>
  </a>

  <!-- DÍAS -->
  <a class="rail-item" href="https://asociacion.asociaciondetransportistaszonanorte.io/tele/tatiana.php" title="Días">
    <img src="https://img.icons8.com/color/48/planner.png" alt="Días">
    <span>Días</span>
  </a>

</nav>

<script>
(function(){
  const btn = document.getElementById('menuToggle');
  const rail = document.getElementById('miniRail');
  const overlay = document.getElementById('navOverlay');

  function openRail() {
    rail.classList.add('is-open');
    btn.classList.add('is-open');
    overlay.hidden = false;
    overlay.classList.add('is-visible');
    document.body.style.overflow = 'hidden';
  }
  
  function closeRail() {
    rail.classList.remove('is-open');
    btn.classList.remove('is-open');
    overlay.classList.remove('is-visible');
    document.body.style.overflow = '';
    setTimeout(() => overlay.hidden = true, 180);
  }

  btn.addEventListener('click', () =>
    rail.classList.contains('is-open') ? closeRail() : openRail()
  );
  
  overlay.addEventListener('click', closeRail);
  
  window.addEventListener('keydown', e => {
    if (e.key === 'Escape' && rail.classList.contains('is-open')) closeRail();
  });

  // === EFECTO DE MAGNIFICACIÓN ===
  const isTouch = (
    'ontouchstart' in window ||
    navigator.maxTouchPoints > 0 ||
    window.matchMedia('(pointer: coarse)').matches
  );

  const items = [...rail.querySelectorAll('.rail-item, .liquidacion-btn')];

  if (!isTouch) {
    rail.addEventListener('mousemove', e => {
      const max = 140;
      const base = 70;
      const y = e.clientY;
      
      items.forEach(it => {
        const r = it.getBoundingClientRect();
        const d = Math.abs(y - (r.top + r.height/2));
        
        if (d < max) {
          const scale = 1 + (1 - d/max) * 0.3;
          const s = base * scale;
          it.style.width = s + 'px';
          it.style.height = s + 'px';
          it.style.zIndex = 1000 - d;
        } else {
          it.style.width = base + 'px';
          it.style.height = base + 'px';
          it.style.zIndex = 'auto';
        }
      });
    });
    
    rail.addEventListener('mouseleave', () =>
      items.forEach(i => {
        i.style.width = '70px';
        i.style.height = '70px';
        i.style.zIndex = 'auto';
      })
    );
  } else {
    items.forEach(i => {
      i.style.width = '70px';
      i.style.height = '70px';
      i.style.zIndex = 'auto';
    });
  }
})();
</script>