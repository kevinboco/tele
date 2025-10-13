<?php
// nav_minirail.php — Riel vertical minimalista + botón circular (como la imagen)
?>
<style>
:root{
  --rail-w: 64px;          /* ancho del riel */
  --gap: 10px;
  --bg: #0a0a0a;
  --br: #1f1f1f;
  --btn-bg: #111;
  --z-rail: 1000; --z-overlay: 999;
}

/* Botón circular (hamburger) */
.menu-toggle{
  position: fixed; left: 14px; top: 14px; width: 56px; height: 56px;
  border-radius: 50%; background: var(--btn-bg); color:#fff; display:grid; place-items:center;
  border: 1px solid var(--br); box-shadow: 0 8px 18px rgba(0,0,0,.35);
  cursor:pointer; z-index: calc(var(--z-rail) + 2);
  transition: transform .18s ease, background .25s ease;
}
.menu-toggle:hover{ transform: translateY(-1px); }
.menu-toggle .bars{
  position:relative; width:26px; height:2px; background:#fff; border-radius:2px;
}
.menu-toggle .bars::before,.menu-toggle .bars::after{
  content:""; position:absolute; left:0; width:26px; height:2px; background:#fff; border-radius:2px;
  transition: transform .22s ease, top .22s ease, opacity .2s ease;
}
.menu-toggle .bars::before{ top:-8px; }
.menu-toggle .bars::after{ top:8px; }
.menu-toggle.is-open .bars{ background:transparent; }
.menu-toggle.is-open .bars::before{ top:0; transform:rotate(45deg); }
.menu-toggle.is-open .bars::after{ top:0; transform:rotate(-45deg); }

/* Overlay suave */
.nav-overlay{
  position: fixed; inset: 0; background: rgba(0,0,0,.15);
  opacity:0; pointer-events:none; transition: opacity .2s ease; z-index: var(--z-overlay);
}
.nav-overlay.is-visible{ opacity:1; pointer-events:auto; }

/* Riel vertical (como en tu captura) */
.mini-rail{
  position: fixed; left: 10px; top: 84px; height: calc(100vh - 100px);
  width: var(--rail-w);
  display:flex; flex-direction:column; align-items:center;
  background: var(--bg);
  border: 1px solid var(--br);
  border-radius: 16px;
  padding: 10px 8px;
  gap: var(--gap);
  z-index: var(--z-rail);
  box-shadow: 0 12px 28px rgba(0,0,0,.35);
  transform: translateX(-90px);        /* oculto por defecto */
  transition: transform .26s ease;
}
.mini-rail.is-open{ transform: translateX(0); }

/* Botones del riel (solo icono cuadrado) */
.rail-item{
  width: 48px; height: 48px; display:grid; place-items:center;
  background: #0e0e0e; border: 1px solid #202020; border-radius: 10px;
  box-shadow: inset 0 0 0 2px #0f0f0f;          /* contorno interno similar a tu captura */
  text-decoration:none;
  transition: transform .12s ease, border-color .2s ease, box-shadow .2s ease;
}
.rail-item:hover{ transform: scale(1.05); border-color:#2b2b2b; box-shadow: inset 0 0 0 2px #1a1a1a; }
.rail-item:active{ transform: scale(0.98); }

.rail-item img{
  width: 28px; height: 28px; object-fit: contain; display:block;
  /* Borde luminoso tipo “neón” suave (ajústalo si quieres verde/cian) */
  filter: drop-shadow(0 0 2px rgba(0,255,180,.35));
}

/* En móvil que no empuje el contenido al abrir */
@media (max-width: 480px){
  .mini-rail{ left: 8px; }
}
</style>

<!-- Botón circular -->
<button id="menuToggle" class="menu-toggle" aria-label="Abrir menú" aria-expanded="false" aria-controls="miniRail">
  <span class="bars" aria-hidden="true"></span>
</button>

<!-- Overlay -->
<div id="navOverlay" class="nav-overlay" hidden></div>

<!-- Riel minimalista (solo iconos) -->
<nav id="miniRail" class="mini-rail" aria-label="Navegación rápida">
  <!-- Reemplaza las imágenes por las tuyas -->
  <a class="rail-item" href="index2.php" title="Inicio">
    <img src="privado/home.png" alt="Inicio">
  </a>

  <a class="rail-item" href="informe.php" title="Informe de viajes">
    <img src="privado/report.png" alt="Informe de viajes">
  </a>

  <a class="rail-item" href="https://asociacion.asociaciondetransportistaszonanorte.io/tele/liquidacion.php?desde=2025-09-29&hasta=2025-10-12&empresa=Hospital" title="Liquidación">
    <img src="privado/list.png" alt="Liquidación">
  </a>

  <a class="rail-item" href="https://asociacion.asociaciondetransportistaszonanorte.io/tele/prueba.php?view=graph" title="Mapa préstamos">
    <img src="privado/graph.png" alt="Mapa préstamos">
  </a>

  <a class="rail-item" href="https://asociacion.asociaciondetransportistaszonanorte.io/tele/admin_prestamos.php?view=cards" title="Editar préstamos">
    <img src="privado/cards.png" alt="Editar préstamos">
  </a>
</nav>

<script>
(function(){
  const toggleBtn = document.getElementById('menuToggle');
  const rail = document.getElementById('miniRail');
  const overlay = document.getElementById('navOverlay');
  const body = document.body;

  function openRail(){
    rail.classList.add('is-open');
    toggleBtn.classList.add('is-open');
    overlay.hidden = false; overlay.classList.add('is-visible');
    toggleBtn.setAttribute('aria-expanded','true');
    body.style.overflow = 'hidden';
  }
  function closeRail(){
    rail.classList.remove('is-open');
    toggleBtn.classList.remove('is-open');
    overlay.classList.remove('is-visible');
    toggleBtn.setAttribute('aria-expanded','false');
    body.style.overflow = '';
    setTimeout(()=>overlay.hidden = true, 180);
  }
  toggleBtn.addEventListener('click', ()=> rail.classList.contains('is-open') ? closeRail() : openRail());
  overlay.addEventListener('click', closeRail);
  window.addEventListener('keydown', e => { if(e.key==='Escape' && rail.classList.contains('is-open')) closeRail(); });
})();
</script>
