<?php
// nav_color.php — Menú lateral con íconos a color, texto fijo, magnificación y submenú

// === Fechas dinámicas ===
$desde   = "2026-01-31"; // <-- puedes cambiarla manualmente cuando necesites
$hasta   = date("Y-m-d"); // fecha actual automática
$empresa = "Hospital";
?>
<style>
:root {
  --bg: #0a0a0a;
  --br: #11f1f1f;
  --btn: #111;
  --z-hamburger: 1002;  /* El botón hamburguesa por encima de todo */
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
  z-index: var(--z-hamburger); /* Ahora sí, por encima de todo */
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

  transform: translateX(-120%); /* Cambiado de -90px a -120% para asegurar que se oculte */
  transition: transform 0.26s ease;
  z-index: var(--z-rail);
}
.mini-rail.is-open { 
  transform: translateX(0); 
}

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
.rail-item:hover img { filter: saturate(1.6); transform: translateY(-2px); }
.rail-item:hover span { color: #00e0a0; }

/* === CONTENEDOR PARA EL SUBMENÚ === */
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
  box-shadow: inset 0 0 0 2px #0f0f0f;
  text-decoration: none;
  color: #eaeaea;
  font-size: 12px;
  transition: transform 0.15s ease, border-color 0.2s ease;
  cursor: pointer;
  box-sizing: border-box;
}
.menu-item-with-submenu .main-button img {
  width: 30px;
  height: 30px;
  margin-bottom: 5px;
  object-fit: contain;
  transition: filter 0.15s ease, transform 0.12s ease;
  filter: saturate(1.1);
}
.menu-item-with-submenu .main-button:hover {
  border-color: #2b2b2b;
  box-shadow: inset 0 0 0 2px #1a1a1a;
  transform: scale(1.07);
}
.menu-item-with-submenu .main-button:hover img { filter: saturate(1.6); transform: translateY(-2px); }
.menu-item-with-submenu .main-button:hover span { color: #00e0a0; }

/* === ESTILOS DEL SUBMENÚ === */
.submenu {
  position: absolute;
  top: 0;
  left: 100%;
  margin-left: 8px;
  background: var(--bg);
  border: 1px solid var(--br);
  border-radius: 12px;
  padding: 8px;
  display: none;
  flex-direction: column;
  gap: 8px;
  z-index: var(--z-submenu);
  box-shadow: 0 12px 28px rgba(0, 0, 0, 0.5);
  min-width: 180px;
}

.menu-item-with-submenu:hover .submenu {
  display: flex;
}

.submenu-item {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 10px 12px;
  background: #0e0e0e;
  border: 1px solid #202020;
  border-radius: 8px;
  text-decoration: none;
  color: #eaeaea;
  font-size: 13px;
  white-space: nowrap;
  transition: background 0.2s ease;
}

.submenu-item:hover {
  background: #1a1a1a;
  border-color: #2b2b2b;
  color: #00e0a0;
}

.submenu-item img {
  width: 20px;
  height: 20px;
  object-fit: contain;
  filter: saturate(1.1);
}

/* Puente invisible para hover suave */
.menu-item-with-submenu::after {
  content: '';
  position: absolute;
  top: 0;
  right: -10px;
  width: 10px;
  height: 100%;
}

@media (max-width: 480px) {
  .mini-rail {
    left: 8px;
    top: 72px;
    max-height: calc(100vh - 90px);
  }
  
  .submenu {
    min-width: 160px;
  }
  
  .submenu-item {
    padding: 8px 10px;
    font-size: 12px;
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

  <!-- === BOTÓN DE LIQUIDACIÓN CON SUBMENÚ === -->
  <div class="menu-item-with-submenu">
    <div class="main-button" title="Liquidación">
      <img src="https://img.icons8.com/color/48/bill.png" alt="Liquidación">
      <span>Liquidación</span>
    </div>

    <div class="submenu">
      <a class="submenu-item" href="https://asociacion.asociaciondetransportistaszonanorte.io/tele/liquidacion.php?desde=<?= $desde ?>&hasta=<?= $hasta ?>&empresas%5B%5D=Hospital&empresas%5B%5D=P.campa%C3%B1a-maicao" title="Liquidación Hospital Maicao">
        <img src="https://img.icons8.com/color/48/hospital.png" alt="Hospital">
        <span>Hospital Maicao</span>
      </a>
      <a class="submenu-item" href="https://asociacion.asociaciondetransportistaszonanorte.io/tele/liquidacion.php?desde=<?= $desde ?>&hasta=<?= $hasta ?>&empresas%5B%5D=Puestos%20de%20Salud" title="Liquidación Puestos de Salud">
        <img src="https://img.icons8.com/color/48/health-checkup.png" alt="Puestos de Salud">
        <span>Puestos de Salud</span>
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
    <span>Liquidación prestamistas</span>
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

  // Función para abrir el menú
  function openRail() {
    rail.classList.add('is-open');
    btn.classList.add('is-open');
    overlay.hidden = false;
    // Pequeño retraso para que la transición funcione
    setTimeout(() => {
      overlay.classList.add('is-visible');
    }, 10);
    document.body.style.overflow = 'hidden';
  }
  
  // Función para cerrar el menú
  function closeRail() {
    rail.classList.remove('is-open');
    btn.classList.remove('is-open');
    overlay.classList.remove('is-visible');
    document.body.style.overflow = '';
    // Ocultar el overlay después de la transición
    setTimeout(() => {
      overlay.hidden = true;
    }, 260);
  }

  // Evento click en el botón hamburguesa
  btn.addEventListener('click', (e) => {
    e.stopPropagation();
    if (rail.classList.contains('is-open')) {
      closeRail();
    } else {
      openRail();
    }
  });
  
  // Evento click en el overlay para cerrar
  overlay.addEventListener('click', closeRail);
  
  // Cerrar con tecla Escape
  window.addEventListener('keydown', e => {
    if (e.key === 'Escape' && rail.classList.contains('is-open')) {
      closeRail();
    }
  });

  // === EFECTO DE MAGNIFICACIÓN ===
  const isTouch = (
    'ontouchstart' in window ||
    navigator.maxTouchPoints > 0 ||
    window.matchMedia('(pointer: coarse)').matches
  );

  // Seleccionar todos los elementos que deben magnificarse
  const items = [...rail.querySelectorAll('.rail-item, .menu-item-with-submenu')];

  if (!isTouch) {
    let magnifyTimer;
    
    rail.addEventListener('mousemove', e => {
      clearTimeout(magnifyTimer);
      
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
          it.style.zIndex = Math.floor(1000 - d);
          it.style.transition = 'none';
        } else {
          it.style.width  = base + 'px';
          it.style.height = base + 'px';
          it.style.zIndex = 'auto';
          it.style.transition = 'width 0.15s ease, height 0.15s ease';
        }
      });
    });
    
    rail.addEventListener('mouseleave', () => {
      items.forEach(i => {
        i.style.width  = '70px';
        i.style.height = '70px';
        i.style.zIndex = 'auto';
        i.style.transition = 'width 0.15s ease, height 0.15s ease';
      });
    });
  } else {
    items.forEach(i => {
      i.style.width  = '70px';
      i.style.height = '70px';
      i.style.zIndex = 'auto';
    });
  }

  // === MANEJO TÁCTIL PARA EL SUBMENÚ ===
  if (isTouch) {
    const submenuParent = document.querySelector('.menu-item-with-submenu');
    if (submenuParent) {
      const mainButton = submenuParent.querySelector('.main-button');
      const submenu = submenuParent.querySelector('.submenu');
      
      mainButton.addEventListener('click', (e) => {
        e.preventDefault();
        e.stopPropagation();
        
        // Cerrar otros submenús si los hubiera
        document.querySelectorAll('.submenu').forEach(s => {
          if (s !== submenu) {
            s.style.display = 'none';
          }
        });

        // Toggle del submenú actual
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