<?php
// nav_color.php
$desde   = "2026-01-31";
$hasta   = date("Y-m-d");
?>
<style>
:root {
  --bg: #0a0a0a;
  --br: #11f1f1f;
  --z-rail: 1000;
  --z-overlay: 999;
  --z-submenu: 1001;
}

.menu-toggle {
  position: fixed;
  left: 14px;
  top: 14px;
  width: 56px;
  height: 56px;
  border-radius: 50%;
  background: #111;
  color: #fff;
  display: grid;
  place-items: center;
  cursor: pointer;
  border: 1px solid #11f1f1f;
  z-index: 1002;
}
.menu-toggle .bars {
  position: relative;
  width: 26px;
  height: 2px;
  background: #fff;
}
.menu-toggle .bars::before,
.menu-toggle .bars::after {
  content: "";
  position: absolute;
  width: 26px;
  height: 2px;
  background: #fff;
}
.menu-toggle .bars::before { top: -8px; }
.menu-toggle .bars::after { top: 8px; }
.menu-toggle.is-open .bars { background: transparent; }
.menu-toggle.is-open .bars::before {
  top: 0;
  transform: rotate(45deg);
}
.menu-toggle.is-open .bars::after {
  top: 0;
  transform: rotate(-45deg);
}

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
  max-height: calc(100vh - 110px);
  overflow-y: auto;
  transform: translateX(-120%);
  transition: transform 0.26s ease;
  z-index: var(--z-rail);
}
.mini-rail.is-open { 
  transform: translateX(0); 
}

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
  flex-shrink: 0;
  position: relative;
}
.rail-item img {
  width: 30px;
  height: 30px;
  margin-bottom: 5px;
}

/* ===== SUBMENÚ CORREGIDO ===== */
.has-submenu {
  position: relative;
}

/* El submenú se posiciona a la derecha */
.submenu {
  position: absolute;
  top: -10px; /* Ajustado para cubrir mejor el área */
  left: 100%;
  margin-left: 0;
  display: none;
  flex-direction: column;
  gap: 10px;
  z-index: var(--z-submenu);
  padding-left: 10px; /* Espacio para el puente */
}

/* Mostrar submenú cuando el mouse está sobre el botón O sobre el propio submenú */
.has-submenu:hover .submenu,
.submenu:hover {
  display: flex;
}

/* Estilo de las opciones del submenú */
.submenu-item {
  width: 200px;
  background: #0e0e0e;
  border: 1px solid #202020;
  border-radius: 8px;
  padding: 12px 15px;
  text-decoration: none;
  color: #eaeaea;
  display: flex;
  align-items: center;
  gap: 12px;
  font-size: 14px;
  white-space: nowrap;
  transition: all 0.2s ease;
}

.submenu-item:hover {
  background: #1a1a1a;
  border-color: #00e0a0;
  color: #00e0a0;
  transform: translateX(5px);
}

.submenu-item img {
  width: 24px;
  height: 24px;
}

/* PUENTE INVISIBLE - Esto es clave para mantener el menú abierto */
.has-submenu::after {
  content: '';
  position: absolute;
  top: -10px;
  right: -20px;
  width: 30px; /* Ancho del puente */
  height: 90px; /* Altura para cubrir botón y submenú */
  background: transparent;
  z-index: calc(var(--z-submenu) - 1);
}

/* Para que el submenú no se cierre cuando el mouse está en el borde */
.submenu::before {
  content: '';
  position: absolute;
  left: -10px;
  top: 0;
  width: 10px;
  height: 100%;
  background: transparent;
}
</style>

<button id="menuToggle" class="menu-toggle">
  <span class="bars"></span>
</button>

<div id="navOverlay" class="nav-overlay" hidden></div>

<nav id="miniRail" class="mini-rail">

  <a class="rail-item" href="index2.php">
    <img src="https://img.icons8.com/color/48/home--v5.png">
    <span>Inicio</span>
  </a>

  <a class="rail-item" href="informe.php">
    <img src="https://img.icons8.com/color/48/combo-chart--v1.png">
    <span>Informe</span>
  </a>

  <!-- === BOTÓN LIQUIDACIÓN CON SUBMENÚ CORREGIDO === -->
  <div class="rail-item has-submenu">
    <img src="https://img.icons8.com/color/48/bill.png">
    <span>Liquidación</span>
    
    <div class="submenu">
      <a class="submenu-item" href="https://asociacion.asociaciondetransportistaszonanorte.io/tele/liquidacion.php?desde=<?= $desde ?>&hasta=<?= $hasta ?>&empresas%5B%5D=Hospital&empresas%5B%5D=P.campa%C3%B1a-maicao">
        <img src="https://img.icons8.com/color/48/hospital.png">
        <span>Hospital Maicao</span>
      </a>
      
      <a class="submenu-item" href="https://asociacion.asociaciondetransportistaszonanorte.io/tele/liquidacion.php?desde=<?= $desde ?>&hasta=<?= $hasta ?>&empresas%5B%5D=Puestos%20de%20Salud">
        <img src="https://img.icons8.com/color/48/health-checkup.png">
        <span>Puestos de Salud</span>
      </a>
    </div>
  </div>

  <a class="rail-item" href="https://asociacion.asociaciondetransportistaszonanorte.io/tele/prueba.php?view=graph">
    <img src="https://img.icons8.com/color/48/share-3.png">
    <span>Mapa</span>
  </a>

  <a class="rail-item" href="https://asociacion.asociaciondetransportistaszonanorte.io/tele/pago.php?desde=<?= $desde ?>&hasta=<?= $hasta ?>&empresa=Hospital">
    <img src="https://img.icons8.com/color/48/paid.png">
    <span>Pago</span>
  </a>

  <a class="rail-item" href="https://asociacion.asociaciondetransportistaszonanorte.io/tele/admin_prestamos.php?view=cards">
    <img src="https://img.icons8.com/color/48/edit-file.png">
    <span>Editar</span>
  </a>

  <a class="rail-item" href="https://asociacion.asociaciondetransportistaszonanorte.io/tele/urgente.php">
    <img src="https://img.icons8.com/color/64/loan.png">
    <span>Liquidación prestamistas</span>
  </a>
  
  <a class="rail-item" href="https://asociacion.asociaciondetransportistaszonanorte.io/tele/ver_foto_cuenta.php">
    <img src="https://img.icons8.com/color/48/picture.png">
    <span>Cuentas de cobro guardadas</span>
  </a>

  <a class="rail-item" href="https://asociacion.asociaciondetransportistaszonanorte.io/tele/tatiana.php">
    <img src="https://img.icons8.com/color/48/planner.png">
    <span>Días</span>
  </a>

</nav>

<script>
const btn = document.getElementById('menuToggle');
const rail = document.getElementById('miniRail');
const overlay = document.getElementById('navOverlay');

btn.addEventListener('click', () => {
  rail.classList.toggle('is-open');
  btn.classList.toggle('is-open');
  if (rail.classList.contains('is-open')) {
    overlay.hidden = false;
    setTimeout(() => overlay.classList.add('is-visible'), 10);
  } else {
    overlay.classList.remove('is-visible');
    setTimeout(() => overlay.hidden = true, 260);
  }
});

overlay.addEventListener('click', () => {
  rail.classList.remove('is-open');
  btn.classList.remove('is-open');
  overlay.classList.remove('is-visible');
  setTimeout(() => overlay.hidden = true, 260);
});
</script>