<?php
// nav_color.php — Menú lateral con submenú en Liquidación (SIN magnificación)
$desde   = "2026-01-31";
$hasta   = date("Y-m-d");
$empresa = "Hospital";
?>
<style>
:root {
  --bg: #0a0a0a;
  --br: #11f1f1f;
  --btn: #111;
  --z-hamburger: 1002;
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
  z-index: var(--z-hamburger);
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

/* === BOTONES PRINCIPALES (SIN EFECTO DE MAGNIFICACIÓN) === */
.rail-item {
  width: 70px;
  height: 70px;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  background: #0e0e0e;
  border: 1px solid #202020;
  border-radius: 12px;
  text-decoration: none;
  color: #eaeaea;
  font-size: 12px;
  transition: border-color 0.2s ease;
  flex-shrink: 0;
}
.rail-item img {
  width: 30px;
  height: 30px;
  margin-bottom: 5px;
  object-fit: contain;
}
.rail-item:hover {
  border-color: #2b2b2b;
  background: #1a1a1a;
}
.rail-item:hover span { color: #00e0a0; }

/* === CONTENEDOR DEL SUBMENÚ === */
.menu-item-with-submenu {
  position: relative;
  width: 70px;
  height: 70px;
  flex-shrink: 0;
}

.menu-item-with-submenu .main-button {
  width: 100%;
  height: 100%;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  background: #0e0e0e;
  border: 1px solid #202020;
  border-radius: 12px;
  text-decoration: none;
  color: #eaeaea;
  font-size: 12px;
  cursor: pointer;
  box-sizing: border-box;
}
.menu-item-with-submenu .main-button img {
  width: 30px;
  height: 30px;
  margin-bottom: 5px;
  object-fit: contain;
}
.menu-item-with-submenu .main-button:hover {
  border-color: #2b2b2b;
  background: #1a1a1a;
}
.menu-item-with-submenu .main-button:hover span { color: #00e0a0; }

/* === SUBMENÚ CON DISEÑO DE "BOLITAS" === */
.submenu {
  position: absolute;
  top: 0;
  left: 100%;
  margin-left: 15px;
  display: none;
  flex-direction: column;
  gap: 12px;
  z-index: var(--z-submenu);
}

/* Mostrar submenú al hacer hover */
.menu-item-with-submenu:hover .submenu {
  display: flex;
}

/* Estilo de "bolitas" para cada opción */
.submenu-item {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  width: 70px;
  height: 70px;
  background: #0e0e0e;
  border: 1px solid #202020;
  border-radius: 50%; /* Esto hace las "bolitas" circulares */
  text-decoration: none;
  color: #eaeaea;
  font-size: 11px;
  text-align: center;
  transition: all 0.2s ease;
  box-shadow: 0 4px 8px rgba(0, 0, 0, 0.3);
}

.submenu-item img {
  width: 30px;
  height: 30px;
  margin-bottom: 5px;
  object-fit: contain;
}

.submenu-item:hover {
  border-color: #00e0a0;
  transform: scale(1.1);
  background: #1a1a1a;
}

.submenu-item:hover span {
  color: #00e0a0;
}

/* Puente invisible para hover */
.menu-item-with-submenu::after {
  content: '';
  position: absolute;
  top: 0;
  right: -15px;
  width: 15px;
  height: 100%;
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
<button id="menuToggle" class="menu-toggle" aria-label="Abrir menú" aria-expanded="false">
  <span class="bars" aria-hidden="true"></span>
</button>

<!-- === OVERLAY === -->
<div id="navOverlay" class="nav-overlay" hidden></div>

<!-- === MENÚ LATERAL === -->
<nav id="miniRail" class="mini-rail" aria-label="Menú lateral">

  <a class="rail-item" href="index2.php" title="Inicio">
    <img src="https://img.icons8.com/color/48/home--v5.png" alt="Inicio">
    <span>Inicio</span>
  </a>

  <a class="rail-item" href="informe.php" title="Informe de viajes">
    <img src="https://img.icons8.com/color/48/combo-chart--v1.png" alt="Informe">
    <span>Informe</span>
  </a>

  <!-- === BOTÓN DE LIQUIDACIÓN CON SUBMENÚ DE "BOLITAS" === -->
  <div class="menu-item-with-submenu">
    <div class="main-button" title="Liquidación">
      <img src="https://img.icons8.com/color/48/bill.png" alt="Liquidación">
      <span>Liquidación</span>
    </div>

    <div class="submenu">
      <!-- Primera "bolita": Hospital Maicao -->
      <a class="submenu-item" href="https://asociacion.asociaciondetransportistaszonanorte.io/tele/liquidacion.php?desde=<?= $desde ?>&hasta=<?= $hasta ?>&empresas%5B%5D=Hospital&empresas%5B%5D=P.campa%C3%B1a-maicao" title="Hospital Maicao">
        <img src="https://img.icons8.com/color/48/hospital.png" alt="Hospital">
        <span>Hospital<br>Maicao</span>
      </a>
      
      <!-- Segunda "bolita": Puestos de Salud -->
      <a class="submenu-item" href="https://asociacion.asociaciondetransportistaszonanorte.io/tele/liquidacion.php?desde=<?= $desde ?>&hasta=<?= $hasta ?>&empresas%5B%5D=Puestos%20de%20Salud" title="Puestos de Salud">
        <img src="https://img.icons8.com/color/48/health-checkup.png" alt="Puestos de Salud">
        <span>Puestos de<br>Salud</span>
      </a>
    </div>
  </div>

  <a class="rail-item" href="https://asociacion.asociaciondetransportistaszonanorte.io/tele/prueba.php?view=graph" title="Mapa préstamos">
    <img src="https://img.icons8.com/color/48/share-3.png" alt="Mapa">
    <span>Mapa</span>
  </a>

  <a class="rail-item" href="https://asociacion.asociaciondetransportistaszonanorte.io/tele/pago.php?desde=<?= $desde ?>&hasta=<?= $hasta ?>&empresa=<?= $empresa ?>" title="Pago">
    <img src="https://img.icons8.com/color/48/paid.png" alt="Pago">
    <span>Pago</span>
  </a>

  <a class="rail-item" href="https://asociacion.asociaciondetransportistaszonanorte.io/tele/admin_prestamos.php?view=cards" title="Editar préstamos">
    <img src="https://img.icons8.com/color/48/edit-file.png" alt="Editar">
    <span>Editar</span>
  </a>

  <a class="rail-item" href="https://asociacion.asociaciondetransportistaszonanorte.io/tele/urgente.php" title="Liquidación prestamistas">
    <img src="https://img.icons8.com/color/64/loan.png" alt="Liquidación prestamistas">
    <span>Liquidación<br>prestamistas</span>
  </a>
  
  <a class="rail-item" href="https://asociacion.asociaciondetransportistaszonanorte.io/tele/ver_foto_cuenta.php" title="Cuentas guardadas">
    <img src="https://img.icons8.com/color/48/picture.png" alt="Foto">
    <span>Cuentas de<br>cobro guardadas</span>
  </a>

  <a class="rail-item" href="https://asociacion.asociaciondetransportistaszonanorte.io/tele/tatiana.php" title="Días">
    <img src="https://img.icons8.com/color/48/planner.png" alt="Días">
    <span>Días</span>
  </a>

</nav>

<script>
(function(){
  const btn     = document.getElementById('menuToggle');
  const rail    = document.getElementById('miniRail');
  const overlay = document.getElementById('navOverlay');

  function openRail() {
    rail.classList.add('is-open');
    btn.classList.add('is-open');
    overlay.hidden = false;
    setTimeout(() => overlay.classList.add('is-visible'), 10);
    document.body.style.overflow = 'hidden';
  }
  
  function closeRail() {
    rail.classList.remove('is-open');
    btn.classList.remove('is-open');
    overlay.classList.remove('is-visible');
    document.body.style.overflow = '';
    setTimeout(() => overlay.hidden = true, 260);
  }

  btn.addEventListener('click', (e) => {
    e.stopPropagation();
    rail.classList.contains('is-open') ? closeRail() : openRail();
  });
  
  overlay.addEventListener('click', closeRail);
  
  window.addEventListener('keydown', e => {
    if (e.key === 'Escape' && rail.classList.contains('is-open')) closeRail();
  });

  // === SOLO MANEJO TÁCTIL PARA MÓVILES ===
  const isTouch = (
    'ontouchstart' in window ||
    navigator.maxTouchPoints > 0 ||
    window.matchMedia('(pointer: coarse)').matches
  );

  if (isTouch) {
    const submenuParent = document.querySelector('.menu-item-with-submenu');
    if (submenuParent) {
      const mainButton = submenuParent.querySelector('.main-button');
      const submenu = submenuParent.querySelector('.submenu');
      
      mainButton.addEventListener('click', (e) => {
        e.preventDefault();
        e.stopPropagation();
        
        // Toggle del submenú
        if (submenu.style.display === 'flex') {
          submenu.style.display = 'none';
        } else {
          submenu.style.display = 'flex';
        }
      });

      // Cerrar al hacer clic fuera
      document.addEventListener('click', (e) => {
        if (!submenuParent.contains(e.target)) {
          submenu.style.display = 'none';
        }
      });
    }
  }
})();
</script>