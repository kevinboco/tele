<?php
// nav.php - Barra lateral (dock vertical) con botón circular (hamburger)
?>

<style>
/* ===== Reset mínimo para evitar herencias raras ===== */
:root { --nav-bg:#060606; --nav-br:#222; --btn-bg:#111; --z-nav: 1000; --z-overlay: 999; }
* { box-sizing: border-box; }

/* ===== Botón circular (hamburger) ===== */
.menu-toggle {
  position: fixed;
  left: 18px;
  top: 18px;
  width: 54px;
  height: 54px;
  border-radius: 50%;
  background: var(--btn-bg);
  color: #fff;
  display: grid;
  place-items: center;
  cursor: pointer;
  z-index: calc(var(--z-nav) + 2);
  border: 1px solid var(--nav-br);
  box-shadow: 0 6px 16px rgba(0,0,0,.25);
  transition: transform .2s ease, background .25s ease;
}
.menu-toggle:hover { transform: translateY(-1px); }
.menu-toggle:active { transform: translateY(0); }

/* Icono 3 líneas */
.menu-toggle .bars {
  position: relative;
  width: 26px;
  height: 2px;
  background: #fff;
  transition: background .2s ease;
}
.menu-toggle .bars::before,
.menu-toggle .bars::after{
  content:"";
  position: absolute;
  left: 0;
  width: 26px;
  height: 2px;
  background: #fff;
  transition: transform .25s ease, top .25s ease, opacity .2s ease;
}
.menu-toggle .bars::before{ top: -8px; }
.menu-toggle .bars::after{ top: 8px; }

/* Estado abierto (morph a X) */
.menu-toggle.is-open .bars { background: transparent; }
.menu-toggle.is-open .bars::before{
  top: 0;
  transform: rotate(45deg);
}
.menu-toggle.is-open .bars::after{
  top: 0;
  transform: rotate(-45deg);
}

/* ===== Overlay para clic fuera ===== */
.nav-overlay {
  position: fixed;
  inset: 0;
  background: rgba(0,0,0,.25);
  opacity: 0;
  pointer-events: none;
  transition: opacity .25s ease;
  z-index: var(--z-overlay);
}
.nav-overlay.is-visible{
  opacity: 1;
  pointer-events: auto;
}

/* ===== Contenedor de barra (dock vertical) ===== */
.dock-outer {
  position: fixed;
  top: 0;
  left: 0;
  height: 100vh;
  width: 92px;                /* ancho de la barra */
  display: flex;
  align-items: flex-start;
  justify-content: center;
  padding-top: 90px;          /* despega de la parte superior (debajo del botón) */
  z-index: var(--z-nav);
  transform: translateX(-120px);   /* oculta hacia la izquierda */
  transition: transform .28s ease;
}
.dock-outer.is-open {
  transform: translateX(0);        /* entra */
}

/* Panel interno del dock (vertical) */
.dock-panel {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: .75rem;
  border-radius: 1rem;
  background-color: var(--nav-bg);
  border: 1px solid var(--nav-br);
  padding: .5rem .5rem 1rem;
  width: 76px;                     /* para que el efecto de escala no rompa */
  max-height: calc(100vh - 120px);
  overflow: auto;
  scrollbar-width: thin;
  scrollbar-color: #333 transparent;
}
.dock-panel::-webkit-scrollbar { width: 6px; }
.dock-panel::-webkit-scrollbar-thumb { background:#333; border-radius:4px; }

/* Ítems */
.dock-item {
  position: relative;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  border-radius: 10px;
  background-color: var(--nav-bg);
  border: 1px solid var(--nav-br);
  box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1),
              0 2px 4px -1px rgba(0, 0, 0, 0.06);
  cursor: pointer;
  outline: none;
  width: 50px;
  height: 50px;
  transition: width .2s ease, height .2s ease, transform .2s ease;
}
.dock-item:focus-visible{
  box-shadow: 0 0 0 2px #fff3, 0 0 0 4px #0ea5e9;
}

.dock-icon {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 100%; height: 100%;
}

.dock-label {
  position: absolute;
  left: calc(100% + 8px);
  top: 50%;
  transform: translateY(-50%);
  white-space: nowrap;
  border-radius: .375rem;
  border: 1px solid var(--nav-br);
  background-color: var(--nav-bg);
  padding: .2rem .5rem;
  font-size: .75rem;
  color: #fff;
  opacity: 0;
  pointer-events: none;
  transition: opacity .18s ease, transform .18s ease;
}
.dock-item:hover .dock-label,
.dock-item:focus .dock-label{
  opacity: 1;
  transform: translateY(-50%) translateX(2px);
}

/* Responsivo: si la pantalla es muy angosta, acercamos la barra para que no tape contenido importante */
@media (max-width: 480px){
  .dock-outer { width: 86px; }
  .dock-panel { width: 70px; }
}

/* Utilidad para bloquear scroll del body cuando la barra está abierta en móvil */
.body-lock { overflow: hidden; }
</style>

<!-- Botón circular -->
<button class="menu-toggle" id="menuToggle" aria-label="Abrir menú" aria-expanded="false" aria-controls="dockNav">
  <span class="bars" aria-hidden="true"></span>
</button>

<!-- Overlay -->
<div class="nav-overlay" id="navOverlay" hidden></div>

<!-- Barra lateral -->
<nav class="dock-outer" id="dockNav" role="navigation" aria-label="Barra de navegación">
  <div class="dock-panel" role="toolbar" aria-label="Dock vertical">
    <button class="dock-item" tabindex="0" aria-label="ver pedidos" onclick="window.location.href='listar_pedidos.php'">
      <div class="dock-icon">
        <img src="privado/entrega-de-pedidos.png" alt="ver pedidos" width="32" height="32" />
      </div>
      <div class="dock-label">ver pedidos</div>
    </button>

    <button class="dock-item" tabindex="0" aria-label="crear ramo" onclick="window.location.href='crear_ramo.php'">
      <div class="dock-icon">
        <img src="privado/flores.png" alt="crear ramo" width="32" height="32" />
      </div>
      <div class="dock-label">crear ramo</div>
    </button>

    <button class="dock-item" tabindex="0" aria-label="crear pedido" onclick="window.location.href='crear_pedido.php'">
      <div class="dock-icon">
        <img src="privado/libro.png" alt="crear pedido" width="32" height="32" />
      </div>
      <div class="dock-label">crear pedido</div>
    </button>

    <button class="dock-item" tabindex="0" aria-label="catálogo" onclick="window.location.href='listar_catalogo.php'">
      <div class="dock-icon">
        <img src="privado/catalogar.png" alt="catálogo" width="32" height="32" />
      </div>
      <div class="dock-label">catálogo</div>
    </button>

    <button class="dock-item" tabindex="0" aria-label="ajustar precio ramo" onclick="window.location.href='ajustar_precios.php'">
      <div class="dock-icon">
        <img src="privado/precio.png" alt="ajustar precio ramo" width="32" height="32" />
      </div>
      <div class="dock-label">ajustar precio</div>
    </button>

    <button class="dock-item" tabindex="0" aria-label="ver ganancias" onclick="window.location.href='gananciasG.php'">
      <div class="dock-icon">
        <img src="privado/ganancia.png" alt="ver ganancias" width="32" height="32" />
      </div>
      <div class="dock-label">ganancias</div>
    </button>

    <button class="dock-item" tabindex="0" aria-label="ver calendario" onclick="window.location.href='calendario_pedidos.php'">
      <div class="dock-icon">
        <img src="privado/calendario.png" alt="ver calendario" width="32" height="32" />
      </div>
      <div class="dock-label">calendario</div>
    </button>
  </div>
</nav>

<script>
/* ========= Toggling, accesibilidad y overlay ========= */
(function(){
  const toggleBtn = document.getElementById('menuToggle');
  const dock = document.getElementById('dockNav');
  const overlay = document.getElementById('navOverlay');
  const body = document.body;
  const firstFocusable = () => dock.querySelector('.dock-item');

  function openNav(){
    dock.classList.add('is-open');
    toggleBtn.classList.add('is-open');
    overlay.hidden = false;
    overlay.classList.add('is-visible');
    toggleBtn.setAttribute('aria-expanded','true');
    body.classList.add('body-lock');
    // Enfocar el primer botón del dock
    setTimeout(() => { firstFocusable()?.focus(); }, 50);
  }

  function closeNav(){
    dock.classList.remove('is-open');
    toggleBtn.classList.remove('is-open');
    overlay.classList.remove('is-visible');
    toggleBtn.setAttribute('aria-expanded','false');
    body.classList.remove('body-lock');
    // Retrasa el hidden para permitir la animación de desvanecido
    setTimeout(() => { overlay.hidden = true; }, 200);
    toggleBtn.focus();
  }

  toggleBtn.addEventListener('click', () => {
    const isOpen = dock.classList.contains('is-open');
    if(isOpen) closeNav(); else openNav();
  });

  overlay.addEventListener('click', closeNav);

  // Cerrar con Esc
  window.addEventListener('keydown', (e) => {
    if(e.key === 'Escape'){
      if(dock.classList.contains('is-open')) closeNav();
    }
  });

  // Cerrar si se navega (cambia la URL)
  window.addEventListener('pageshow', () => closeNav());

  /* ========= Magnificación vertical (eje Y) ========= */
  const items = dock.querySelectorAll('.dock-item');
  dock.addEventListener('mousemove', (e) => {
    const panelRect = dock.getBoundingClientRect();
    const mouseY = e.clientY;
    items.forEach(item => {
      const rect = item.getBoundingClientRect();
      const centerY = rect.top + rect.height / 2;
      const distance = Math.abs(mouseY - centerY);
      const maxDistance = 140;             // rango de influencia
      if (distance < maxDistance) {
        const scale = 1 + (1 - distance / maxDistance) * 0.35; // ~1 → 1.35
        const size = 50 * scale;
        item.style.width = size + 'px';
        item.style.height = size + 'px';
        item.style.zIndex = 1000 - distance;
      } else {
        item.style.width = '50px';
        item.style.height = '50px';
        item.style.zIndex = 'auto';
      }
    });
  });

  dock.addEventListener('mouseleave', () => {
    items.forEach(item => {
      item.style.width = '50px';
      item.style.height = '50px';
      item.style.zIndex = 'auto';
    });
  });
})();
</script>
