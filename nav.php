<?php
// ===== FECHAS PARA HOSPITAL (tú las modificas manualmente) =====
$desde_hospital = "2026-02-24";

// ===== FECHAS PARA PUESTOS DE SALUD (tú las modificas manualmente) =====
$desde_puestos = "2026-02-26";

// Fecha hasta (siempre el día actual para ambos)
$hasta = date("Y-m-d");
?>

<style>
/* SOLO LO ESENCIAL */
.menu-toggle {
  position: fixed;
  left: 14px;
  top: 14px;
  width: 56px;
  height: 56px;
  border-radius: 50%;
  background: #111;
  border: 1px solid #1f1f1f;
  cursor: pointer;
  z-index: 1002;
  color: white;
  font-size: 24px;
  display: flex;
  align-items: center;
  justify-content: center;
}

.mini-rail {
  position: fixed;
  left: 10px;
  top: 84px;
  display: flex;
  flex-direction: column;
  gap: 12px;
  padding: 10px;
  background: #0a0a0a;
  border: 1px solid #1f1f1f;
  border-radius: 16px;
  transform: translateX(-120%);
  transition: transform 0.26s ease;
  z-index: 1000;
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
  position: relative;
}

.rail-item img {
  width: 30px;
  height: 30px;
  margin-bottom: 5px;
}

/* ===== SUBMENÚ ===== */
.liquidacion-btn,
.pago-btn {
  position: relative;
}

.submenu {
  position: absolute;
  top: 0;
  left: 100%;
  margin-left: 10px;
  display: none;
  background: #0a0a0a;
  border: 1px solid #1f1f1f;
  border-radius: 12px;
  padding: 8px;
  min-width: 200px;
  z-index: 1001;
}

/* Mostrar submenú al hacer hover */
.liquidacion-btn:hover .submenu,
.pago-btn:hover .submenu {
  display: block !important;
}

/* PUENTE INVISIBLE para poder mover el mouse al submenú */
.liquidacion-btn::after,
.pago-btn::after {
  content: '';
  position: absolute;
  top: -5px;
  right: -20px;
  width: 30px;
  height: 80px;
  background: transparent;
  z-index: 1000;
}

/* El submenú también se mantiene visible si el mouse está sobre él */
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

.nav-overlay {
  position: fixed;
  inset: 0;
  background: rgba(0,0,0,0.15);
  display: none;
  z-index: 999;
}

.nav-overlay.is-visible {
  display: block;
}
</style>

<button id="menuToggle" class="menu-toggle">
  ☰
</button>

<div id="navOverlay" class="nav-overlay"></div>

<nav id="miniRail" class="mini-rail">
  <a class="rail-item" href="index2.php">
    <img src="https://img.icons8.com/color/48/home--v5.png">
    <span>Inicio</span>
  </a>

  <a class="rail-item" href="informe.php">
    <img src="https://img.icons8.com/color/48/combo-chart--v1.png">
    <span>Informe</span>
  </a>

  <!-- BOTÓN LIQUIDACIÓN CON SUBMENÚ -->
  <div class="rail-item liquidacion-btn">
    <img src="https://img.icons8.com/color/48/bill.png">
    <span>Liquidación</span>
    
    <div class="submenu">
      <!-- Hospital Maicao (usa fecha de hospital) -->
      <a class="submenu-item" href="https://asociacion.asociaciondetransportistaszonanorte.io/tele/liquidacion.php?desde=<?= $desde_hospital ?>&hasta=<?= $hasta ?>&empresas%5B%5D=Hospital&empresas%5B%5D=P.campa%C3%B1a-maicao">
        🏥 Hospital Maicao
      </a>
      
      <!-- Puestos de Salud (usa fecha de puestos) -->
      <a class="submenu-item" href="https://asociacion.asociaciondetransportistaszonanorte.io/tele/liquidacion.php?desde=<?= $desde_puestos ?>&hasta=<?= $hasta ?>&empresas%5B%5D=P.flor+de+la+guajira&empresas%5B%5D=p.nazareth&empresas%5B%5D=P.paraiso&empresas%5B%5D=P.puerto+estrella&empresas%5B%5D=p.siapana&empresas%5B%5D=P.villa+F%C3%A1tima">
        💊 Puestos de Salud
      </a>
    </div>
  </div>

  <!-- BOTÓN PAGO CON SUBMENÚ -->
  <div class="rail-item pago-btn">
    <img src="https://img.icons8.com/color/48/paid.png">
    <span>Pago</span>
    
    <div class="submenu">
      <!-- Hospital Maicao (usa fecha de hospital) -->
      <a class="submenu-item" href="https://asociacion.asociaciondetransportistaszonanorte.io/tele/pago.php?desde=<?= $desde_hospital ?>&hasta=<?= $hasta ?>&empresas%5B%5D=Hospital&empresas%5B%5D=P.campa%C3%B1a-maicao">
        🏥 Hospital Maicao
      </a>
      
      <!-- Puestos de Salud (usa fecha de puestos) -->
      <a class="submenu-item" href="https://asociacion.asociaciondetransportistaszonanorte.io/tele/pago.php?desde=<?= $desde_puestos ?>&hasta=<?= $hasta ?>&empresas%5B%5D=P.flor+de+la+guajira&empresas%5B%5D=p.nazareth&empresas%5B%5D=P.paraiso&empresas%5B%5D=P.puerto+estrella&empresas%5B%5D=p.siapana&empresas%5B%5D=P.villa+F%C3%A1tima">
        💊 Puestos de Salud
      </a>
    </div>
  </div>

  <a class="rail-item" href="https://asociacion.asociaciondetransportistaszonanorte.io/tele/prueba.php?view=graph">
    <img src="https://img.icons8.com/color/48/share-3.png">
    <span>Mapa</span>
  </a>

  <a class="rail-item" href="https://asociacion.asociaciondetransportistaszonanorte.io/tele/admin_prestamos.php?view=cards">
    <img src="https://img.icons8.com/color/48/edit-file.png">
    <span>Editar</span>
  </a>

  <a class="rail-item" href="https://asociacion.asociaciondetransportistaszonanorte.io/tele/urgente.php">
    <img src="https://img.icons8.com/color/64/loan.png">
    <span>liquidacion prestamistas</span>
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

btn.onclick = function() {
  rail.classList.toggle('is-open');
  overlay.classList.toggle('is-visible');
};

overlay.onclick = function() {
  rail.classList.remove('is-open');
  overlay.classList.remove('is-visible');
};
</script>