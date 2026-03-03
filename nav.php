<?php
$desde = "2026-01-31";
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
  border: 1px solid #11f1f1f;
  cursor: pointer;
  z-index: 1002;
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
  border: 1px solid #11f1f1f;
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
  min-width: 180px;
}

.liquidacion-btn:hover .submenu {
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
}

.submenu-item:last-child {
  margin-bottom: 0;
}

.submenu-item:hover {
  background: #1a1a1a;
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
      <a class="submenu-item" href="https://asociacion.asociaciondetransportistaszonanorte.io/tele/liquidacion.php?desde=<?= $desde ?>&hasta=<?= $hasta ?>&empresas%5B%5D=Hospital&empresas%5B%5D=P.campa%C3%B1a-maicao">
        🏥 Hospital Maicao
      </a>
      
      <a class="submenu-item" href="https://asociacion.asociaciondetransportistaszonanorte.io/tele/liquidacion.php?desde=<?= $desde ?>&hasta=<?= $hasta ?>&empresas%5B%5D=Puestos%20de%20Salud">
        💊 Puestos de Salud
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