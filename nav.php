<?php
// nav_lateral.php — Barra lateral con botón circular y ítems con IMAGEN
?>
<style>
:root{
  --nav-bg:#0b0b0b; --nav-br:#222; --txt:#eaeaea; --sub:#a8a8a8;
  --rail-w: 86px; --panel-w: 300px; --z-nav: 1000; --z-overlay: 999;
}

/* Botón circular (hamburger) */
.menu-toggle{
  position: fixed; left: 18px; top: 18px; width: 54px; height: 54px;
  border-radius: 50%; background:#111; color:#fff; display:grid; place-items:center;
  cursor:pointer; z-index:calc(var(--z-nav)+2); border:1px solid var(--nav-br);
  box-shadow:0 6px 16px rgba(0,0,0,.25); transition:transform .2s ease, background .25s ease;
}
.menu-toggle:hover{ transform: translateY(-1px); }
.menu-toggle .bars{ position:relative; width:26px; height:2px; background:#fff; }
.menu-toggle .bars::before,.menu-toggle .bars::after{
  content:""; position:absolute; left:0; width:26px; height:2px; background:#fff;
  transition:transform .25s ease, top .25s ease, opacity .2s ease;
}
.menu-toggle .bars::before{ top:-8px; } .menu-toggle .bars::after{ top:8px; }
.menu-toggle.is-open .bars{ background:transparent; }
.menu-toggle.is-open .bars::before{ top:0; transform:rotate(45deg); }
.menu-toggle.is-open .bars::after{ top:0; transform:rotate(-45deg); }

/* Overlay */
.nav-overlay{ position:fixed; inset:0; background:rgba(0,0,0,.25);
  opacity:0; pointer-events:none; transition:opacity .25s ease; z-index:var(--z-overlay); }
.nav-overlay.is-visible{ opacity:1; pointer-events:auto; }

/* Contenedor lateral */
.nav-shell{
  position:fixed; left:0; top:0; height:100vh; z-index:var(--z-nav); display:flex; gap:8px;
  transform: translateX(calc(-1 * (var(--rail-w) + 14px)));
  transition: transform .28s ease;
}
.nav-shell.is-open{ transform: translateX(0); }

/* Rail (columna angosta con íconos) */
.rail{
  width: var(--rail-w); height: 100vh; display:flex; flex-direction:column;
  align-items:center; padding:90px 10px 14px; background:rgba(0,0,0,.45);
  backdrop-filter: blur(4px); border-right:1px solid var(--nav-br); border-top-right-radius:12px; border-bottom-right-radius:12px;
}

/* Panel con texto */
.panel{
  width: var(--panel-w); height: 100vh; background:var(--nav-bg); border-left:1px solid var(--nav-br);
  padding:90px 14px 20px; overflow:auto; scrollbar-width:thin; scrollbar-color:#333 transparent;
  box-shadow: 10px 0 24px rgba(0,0,0,.35);
  border-top-right-radius:12px; border-bottom-right-radius:12px;
}
.panel::-webkit-scrollbar{ width:8px; } .panel::-webkit-scrollbar-thumb{ background:#333; border-radius:8px; }

/* Lista de navegación */
.nav{ display:flex; flex-direction:column; gap:10px; }
.nav-item{
  display:flex; gap:12px; align-items:center; text-decoration:none; background:#0e0e0e;
  border:1px solid var(--nav-br); border-radius:12px; padding:10px; transition:transform .15s ease, background .2s ease, border-color .2s ease;
  color:var(--txt);
}
.nav-item:hover{ background:#131313; border-color:#2a2a2a; transform: translateX(2px); }
.thumb{
  width:32px; height:32px; border-radius:8px; object-fit:cover; background:#1d1d1d; border:1px solid #2a2a2a;
  box-shadow: inset 0 0 0 1px #0003;
}
.item-title{ font-weight:600; font-size:15px; line-height:1.1; color:var(--txt); }
.item-sub{ font-size:12px; color:var(--sub); }

/* Botones del rail (icon-only) */
.rail-btn{
  width:52px; height:52px; display:grid; place-items:center; margin:6px 0; border-radius:12px;
  background:#0c0c0c; border:1px solid var(--nav-br); cursor:pointer; transition: transform .15s ease, background .2s ease, border-color .2s ease;
}
.rail-btn:hover{ background:#141414; border-color:#2a2a2a; transform: scale(1.03); }
.rail-btn img{ width:26px; height:26px; object-fit:contain; }

/* Ocultar panel de texto en <768px> si quieres solo iconos (opcional) */
@media (max-width: 768px){
  .panel{ display:none; }
}
.body-lock{ overflow:hidden; }
</style>

<!-- Botón -->
<button class="menu-toggle" id="menuToggle" aria-label="Abrir menú" aria-expanded="false" aria-controls="navShell">
  <span class="bars" aria-hidden="true"></span>
</button>

<!-- Overlay -->
<div class="nav-overlay" id="navOverlay" hidden></div>

<!-- Barra lateral -->
<aside class="nav-shell" id="navShell">
  <!-- Rail con botones de solo imagen -->
  <div class="rail">
    <a class="rail-btn" href="index2.php" title="Inicio">
      <img src="privado/home.png" alt="Inicio">
    </a>
    <a class="rail-btn" href="informe.php" title="Informe de viajes">
      <img src="privado/report.png" alt="Informe">
    </a>
    <a class="rail-btn" href="https://asociacion.asociaciondetransportistaszonanorte.io/tele/liquidacion.php?desde=2025-09-29&hasta=2025-10-12&empresa=Hospital" title="Liquidación">
      <img src="privado/list.png" alt="Liquidación">
    </a>
    <a class="rail-btn" href="https://asociacion.asociaciondetransportistaszonanorte.io/tele/prueba.php?view=graph" title="Mapa préstamos">
      <img src="privado/graph.png" alt="Mapa préstamos">
    </a>
    <a class="rail-btn" href="https://asociacion.asociaciondetransportistaszonanorte.io/tele/admin_prestamos.php?view=cards" title="Editar préstamos">
      <img src="privado/cards.png" alt="Editar préstamos">
    </a>
  </div>

  <!-- Panel con texto + imagen -->
  <nav class="panel" aria-label="Navegación principal">
    <div class="nav">
      <a class="nav-item" href="index2.php">
        <img class="thumb" src="privado/home.png" alt="Inicio">
        <div><div class="item-title">Inicio</div><div class="item-sub">Panel principal</div></div>
      </a>

      <a class="nav-item" href="informe.php">
        <img class="thumb" src="privado/report.png" alt="Informe de viajes">
        <div><div class="item-title">Informe de viajes</div><div class="item-sub">Filtros y reportes</div></div>
      </a>

      <a class="nav-item" href="https://asociacion.asociaciondetransportistaszonanorte.io/tele/liquidacion.php?desde=2025-09-29&hasta=2025-10-12&empresa=Hospital">
        <img class="thumb" src="privado/list.png" alt="Liquidación">
        <div><div class="item-title">Liquidación</div><div class="item-sub">Rango de fechas</div></div>
      </a>

      <a class="nav-item" href="https://asociacion.asociaciondetransportistaszonanorte.io/tele/prueba.php?view=graph">
        <img class="thumb" src="privado/graph.png" alt="Mapa préstamos">
        <div><div class="item-title">Mapa préstamos</div><div class="item-sub">D3 interactivo</div></div>
      </a>

      <a class="nav-item" href="https://asociacion.asociaciondetransportistaszonanorte.io/tele/admin_prestamos.php?view=cards">
        <img class="thumb" src="privado/cards.png" alt="Editar préstamos">
        <div><div class="item-title">Editar préstamos</div><div class="item-sub">CRUD y tarjetas</div></div>
      </a>
    </div>
  </nav>
</aside>

<script>
(function(){
  const toggleBtn = document.getElementById('menuToggle');
  const shell = document.getElementById('navShell');
  const overlay = document.getElementById('navOverlay');
  const body = document.body;

  function openNav(){
    shell.classList.add('is-open');
    toggleBtn.classList.add('is-open');
    overlay.hidden = false; overlay.classList.add('is-visible');
    toggleBtn.setAttribute('aria-expanded','true');
    body.classList.add('body-lock');
  }
  function closeNav(){
    shell.classList.remove('is-open');
    toggleBtn.classList.remove('is-open');
    overlay.classList.remove('is-visible');
    toggleBtn.setAttribute('aria-expanded','false');
    body.classList.remove('body-lock');
    setTimeout(()=>overlay.hidden=true,200);
  }
  toggleBtn.addEventListener('click', ()=> shell.classList.contains('is-open') ? closeNav() : openNav());
  overlay.addEventListener('click', closeNav);
  window.addEventListener('keydown', e => { if(e.key==='Escape' && shell.classList.contains('is-open')) closeNav(); });
})();
</script>
