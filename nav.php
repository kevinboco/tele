<?php
// nav_color.php — Menú lateral con íconos a color, texto fijo y magnificación
// === CON SUBMENÚ PARA LIQUIDACIÓN (Hospital Maicao + Puestos de Salud) ===

// === Fechas dinámicas ===
$desde   = "2026-01-31"; // <-- SOLO MODIFICAS ESTA FECHA (la fecha de inicio de la quincena/mes)
$hasta   = date("Y-m-d"); // fecha actual automática (NO TOCAR)

// URLs específicas para cada empresa (usando la misma variable $desde)
$url_hospital = "https://asociacion.asociaciondetransportistaszonanorte.io/tele/liquidacion.php?desde=$desde&hasta=$hasta&empresas%5B%5D=Hospital";
$url_puestos = "https://asociacion.asociaciondetransportistaszonanorte.io/tele/liquidacion.php?desde=$desde&hasta=$hasta&empresas%5B%5D=P.campa%C3%B1a-maicao";
?>
<style>
:root {
  --bg: #0a0a0a;
  --br: #11f1f1f;
  --btn: #111;
  --z-rail: 1000;
  --z-overlay: 999;
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
.nav-overlay.is-visible { opacity: 1; pointer-events: auto; }

/* === RIEL LATERAL (RESPONSIVO + SCROLL) === */
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

  /* que nunca se salga de la pantalla y tenga scroll */
  max-height: calc(100vh - 110px);
  overflow-y: auto;
  overflow-x: hidden;

  /* scroll suave en iOS / móviles */
  -webkit-overflow-scrolling: touch;
  touch-action: pan-y;
  overscroll-behavior: contain;

  transform: translateX(-90px);
  transition: transform 0.26s ease;
  z-index: var(--z-rail);
}
.mini-rail.is-open { transform: translateX(0); }

/* Scrollbar para navegadores WebKit */
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

/* === BOTONES PRINCIPALES === */
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
  flex-shrink: 0; /* que no se aplasten con el scroll */
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
.rail-item:hover img { filter: saturate(1.6); transform: translateY(-2px); }
.rail-item:hover span { color: #00e0a0; }

/* === CONTENEDOR PARA EL SUBMENÚ === */
.rail-item-container {
  position: relative;
  width: 70px;
  height: 70px;
}

.rail-item-container:hover .submenu {
  opacity: 1;
  visibility: visible;
  transform: translateX(0);
  pointer-events: auto;
}

/* === SUBMENÚ (HORIZONTAL A LA DERECHA) === */
.submenu {
  position: absolute;
  left: 100%;
  top: 0;
  min-width: 200px;
  background: var(--bg);
  border: 1px solid var(--br);
  border-radius: 12px;
  padding: 8px;
  display: flex;
  flex-direction: column;
  gap: 6px;
  opacity: 0;
  visibility: hidden;
  transform: translateX(-10px);
  transition: all 0.2s ease;
  z-index: calc(var(--z-rail) + 1);
  box-shadow: 0 12px 28px rgba(0, 0, 0, 0.5);
  pointer-events: none;
  backdrop-filter: blur(5px);
  background: rgba(10, 10, 10, 0.95);
}

/* === ITEMS DEL SUBMENÚ === */
.submenu-item {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 10px 12px;
  text-decoration: none;
  color: #eaeaea;
  border-radius: 8px;
  transition: all 0.15s ease;
  border: 1px solid transparent;
  white-space: nowrap;
}

.submenu-item:hover {
  background: #1a1a1a;
  border-color: #2b2b2b;
  transform: translateX(5px);
}

.submenu-item:hover span {
  color: #00e0a0;
}

.submenu-item img {
  width: 28px;
  height: 28px;
  object-fit: contain;
  filter: saturate(1.2);
}

.submenu-item span {
  font-size: 13px;
  font-weight: 500;
}

/* === RESPONSIVE PARA MÓVILES === */
@media (max-width: 768px) {
  .mini-rail {
    left: 8px;
    top: 72px;
    max-height: calc(100vh - 90px);
  }
  
  .submenu {
    position: fixed;
    left: 90px;
    top: auto;
    min-width: 180px;
  }
  
  .submenu-item {
    white-space: normal;
    word-break: break-word;
  }
  
  .submenu-item span {
    font-size: 12px;
  }
}

/* Ajuste para cuando el menú está abierto */
.mini-rail.is-open .submenu {
  left: calc(100% + 5px);
}
</style>

<!-- === BOTÓN === -->
<button id="menuToggle" class="menu-toggle" aria-label="Abrir menú" aria-expanded="false">
  <span class="bars" aria-hidden="true"></span>
</button>

<!-- === OVERLAY === -->
<div id="navOverlay" class="nav-overlay" hidden></div>

<!-- === MENÚ === -->
<nav id="miniRail" class="mini-rail" aria-label="Menú lateral">

  <a class="rail-item" href="index2.php" title="Inicio">
    <img src="https://img.icons8.com/color/48/home--v5.png" alt="Inicio">
    <span>Inicio</span>
  </a>

  <a class="rail-item" href="informe.php" title="Informe de viajes">
    <img src="https://img.icons8.com/color/48/combo-chart--v1.png" alt="Informe">
    <span>Informe</span>
  </a>

  <!-- BOTÓN LIQUIDACIÓN CON SUBMENÚ (HOSPITAL + PUESTOS) -->
  <div class="rail-item-container">
    <a class="rail-item" href="#" title="Liquidación" id="liquidacionBtn" onclick="return false;">
      <img src="https://img.icons8.com/color/48/bill.png" alt="Liquidación">
      <span>Liquidación</span>
    </a>
    
    <!-- SUBMENÚ CON LAS DOS EMPRESAS -->
    <div class="submenu" id="liquidacionSubmenu">
      <a href="<?= $url_hospital ?>" class="submenu-item" title="Liquidación Hospital Maicao">
        <img src="https://img.icons8.com/color/48/hospital.png" alt="Hospital">
        <span>Hospital Maicao</span>
      </a>
      <a href="<?= $url_puestos ?>" class="submenu-item" title="Liquidación Puestos de Salud">
        <img src="https://img.icons8.com/color/48/hospital-3.png" alt="Puestos">
        <span>Puestos de Salud</span>
      </a>
    </div>
  </div>

  <a class="rail-item" href="https://asociacion.asociaciondetransportistaszonanorte.io/tele/prueba.php?view=graph" title="Mapa préstamos">
    <img src="https://img.icons8.com/color/48/share-3.png" alt="Mapa">
    <span>Mapa</span>
  </a>

  <!-- Pago (usando la URL que ya tenías) -->
  <?php
  $url_pago = "https://asociacion.asociaciondetransportistaszonanorte.io/tele/pago.php?desde=$desde&hasta=$hasta&empresa=Hospital";
  ?>
  <a class="rail-item" href="<?= $url_pago ?>" title="Pago">
    <img src="https://img.icons8.com/color/48/paid.png" alt="Pago">
    <span>Pago</span>
  </a>

  <a class="rail-item" href="https://asociacion.asociaciondetransportistaszonanorte.io/tele/admin_prestamos.php?view=cards" title="Editar préstamos">
    <img src="https://img.icons8.com/color/48/edit-file.png" alt="Editar">
    <span>Editar</span>
  </a>

  <a class="rail-item" href="https://asociacion.asociaciondetransportistaszonanorte.io/tele/urgente.php" title="Liquidación prestamistas">
    <img src="https://img.icons8.com/color/64/loan.png" alt="Días">
    <span>liquidacion prestamistas</span>
  </a>
  
  <a class="rail-item" href="https://asociacion.asociaciondetransportistaszonanorte.io/tele/ver_foto_cuenta.php" title="Cuentas guardadas">
    <img src="https://img.icons8.com/color/48/picture.png" alt="Foto">
    <span>Cuentas de cobro guardadas</span>
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

  // === EFECTO DE MAGNIFICACIÓN SOLO EN PC (no en táctil) ===
  const isTouch = (
    'ontouchstart' in window ||
    navigator.maxTouchPoints > 0 ||
    window.matchMedia('(pointer: coarse)').matches
  );

  const items = [...rail.querySelectorAll('.rail-item')];

  if (!isTouch) {
    // Solo escritorio: efecto de agrandar según la posición del mouse
    rail.addEventListener('mousemove', e => {
      const max  = 140;
      const base = 70;
      const y    = e.clientY;
      items.forEach(it => {
        const r = it.getBoundingClientRect();
        const d = Math.abs(y - (r.top + r.height/2));
        if (d < max) {
          const scale = 1 + (1 - d/max) * 0.3;
          const s = base * scale;
          it.style.width  = s + 'px';
          it.style.height = s + 'px';
          it.style.zIndex = 1000 - d;
        } else {
          it.style.width  = base + 'px';
          it.style.height = base + 'px';
          it.style.zIndex = 'auto';
        }
      });
    });
    rail.addEventListener('mouseleave', () =>
      items.forEach(i => {
        i.style.width  = '70px';
        i.style.height = '70px';
        i.style.zIndex = 'auto';
      })
    );
  } else {
    // En móviles, asegurar tamaño fijo
    items.forEach(i => {
      i.style.width  = '70px';
      i.style.height = '70px';
      i.style.zIndex = 'auto';
    });
  }

  // Prevenir que el submenú se cierre al hacer hover sobre él
  const submenu = document.getElementById('liquidacionSubmenu');
  if (submenu) {
    submenu.addEventListener('mouseenter', () => {
      submenu.style.opacity = '1';
      submenu.style.visibility = 'visible';
    });
  }
})();
</script>