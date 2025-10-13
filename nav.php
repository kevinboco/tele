<?php
// nav_minirail_text.php — Riel vertical con texto fijo bajo íconos
?>
<style>
:root {
  --bg: #0a0a0a;
  --br: #1f1f1f;
  --btn: #111;
  --z-rail: 1000;
  --z-overlay: 999;
}

/* Botón circular */
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
.menu-toggle:hover {
  transform: translateY(-1px);
}
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
.menu-toggle .bars::before {
  top: -8px;
}
.menu-toggle .bars::after {
  top: 8px;
}
.menu-toggle.is-open .bars {
  background: transparent;
}
.menu-toggle.is-open .bars::before {
  top: 0;
  transform: rotate(45deg);
}
.menu-toggle.is-open .bars::after {
  top: 0;
  transform: rotate(-45deg);
}

/* Overlay */
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

/* Riel */
.mini-rail {
  position: fixed;
  left: 10px;
  top: 84px;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 10px;
  padding: 10px 8px;
  background: var(--bg);
  border: 1px solid var(--br);
  border-radius: 16px;
  box-shadow: 0 12px 28px rgba(0, 0, 0, 0.35);
  height: auto;
  transform: translateX(-90px);
  transition: transform 0.26s ease;
  z-index: var(--z-rail);
}
.mini-rail.is-open {
  transform: translateX(0);
}

/* Botones */
.rail-item {
  position: relative;
  width: 64px;
  height: 64px;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  background: #0e0e0e;
  border: 1px solid #202020;
  border-radius: 10px;
  box-shadow: inset 0 0 0 2px #0f0f0f;
  text-decoration: none;
  color: #eaeaea;
  font-size: 11px;
  transition: transform 0.12s ease, border-color 0.2s ease;
}
.rail-item:hover {
  border-color: #2b2b2b;
  box-shadow: inset 0 0 0 2px #1a1a1a;
  transform: scale(1.08);
}
.rail-item img {
  width: 26px;
  height: 26px;
  margin-bottom: 4px;
  object-fit: contain;
  filter: drop-shadow(0 0 2px rgba(0, 255, 180, 0.35));
}

@media (max-width: 480px) {
  .mini-rail {
    left: 8px;
  }
}
</style>

<!-- Botón -->
<button id="menuToggle" class="menu-toggle" aria-label="Abrir menú" aria-expanded="false">
  <span class="bars" aria-hidden="true"></span>
</button>

<!-- Overlay -->
<div id="navOverlay" class="nav-overlay" hidden></div>

<!-- Riel con texto debajo -->
<nav id="miniRail" class="mini-rail" aria-label="Menú lateral">

  <a class="rail-item" href="index2.php" title="Inicio">
    <img src="https://cdn-icons-png.flaticon.com/512/25/25694.png" alt="Inicio">
    Inicio
  </a>

  <a class="rail-item" href="informe.php" title="Informe de viajes">
    <img src="https://cdn-icons-png.flaticon.com/512/1828/1828911.png" alt="Informe">
    Informe
  </a>

  <a class="rail-item" href="https://asociacion.asociaciondetransportistaszonanorte.io/tele/liquidacion.php?desde=2025-09-29&hasta=2025-10-12&empresa=Hospital" title="Liquidación">
    <img src="https://cdn-icons-png.flaticon.com/512/942/942751.png" alt="Liquidación">
    Liquidación
  </a>

  <a class="rail-item" href="https://asociacion.asociaciondetransportistaszonanorte.io/tele/prueba.php?view=graph" title="Mapa préstamos">
    <img src="https://cdn-icons-png.flaticon.com/512/565/565547.png" alt="Mapa préstamos">
    Mapa
  </a>

  <a class="rail-item" href="https://asociacion.asociaciondetransportistaszonanorte.io/tele/admin_prestamos.php?view=cards" title="Editar préstamos">
    <img src="https://cdn-icons-png.flaticon.com/512/1827/1827951.png" alt="Editar préstamos">
    Editar
  </a>

  <a class="rail-item" href="https://asociacion.asociaciondetransportistaszonanorte.io/tele/tatiana.php" title="Días">
    <img src="https://cdn-icons-png.flaticon.com/512/2921/2921222.png" alt="Días">
    Días
  </a>

</nav>

<script>
(function(){
  const toggleBtn = document.getElementById('menuToggle');
  const rail = document.getElementById('miniRail');
  const overlay = document.getElementById('navOverlay');

  function openRail(){
    rail.classList.add('is-open');
    toggleBtn.classList.add('is-open');
    overlay.hidden = false;
    overlay.classList.add('is-visible');
    document.body.style.overflow = 'hidden';
  }
  function closeRail(){
    rail.classList.remove('is-open');
    toggleBtn.classList.remove('is-open');
    overlay.classList.remove('is-visible');
    document.body.style.overflow = '';
    setTimeout(()=>overlay.hidden = true, 180);
  }
  toggleBtn.addEventListener('click', ()=> rail.classList.contains('is-open') ? closeRail() : openRail());
  overlay.addEventListener('click', closeRail);
  window.addEventListener('keydown', e => { if(e.key==='Escape' && rail.classList.contains('is-open')) closeRail(); });

  // efecto de magnificación vertical
  const items = Array.from(rail.querySelectorAll('.rail-item'));
  rail.addEventListener('mousemove', e => {
    const max = 140, base = 64, y = e.clientY;
    items.forEach(it=>{
      const r = it.getBoundingClientRect();
      const d = Math.abs(y - (r.top + r.height/2));
      if (d < max){
        const scale = 1 + (1 - d/max)*0.3;
        const s = base * scale;
        it.style.width = s+'px';
        it.style.height = s+'px';
        it.style.zIndex = 1000 - d;
      } else {
        it.style.width = base+'px';
        it.style.height = base+'px';
        it.style.zIndex = 'auto';
      }
    });
  });
  rail.addEventListener('mouseleave', ()=> items.forEach(i=>{i.style.width='64px';i.style.height='64px';i.style.zIndex='auto';}));
})();
</script>
