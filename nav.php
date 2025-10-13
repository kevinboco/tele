<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>Asociación de Transportistas</title>
<style>
:root{
  --bg:#0b0f14;
  --panel:rgba(20,24,30,.72);
  --panel-strong:rgba(20,24,30,.9);
  --text:#e6eef8;
  --text-dim:#a8b3c7;
  --brand:#2dd4bf; /* menta */
  --brand-2:#60a5fa; /* azul */
  --ring: rgba(99, 179, 237, .55);
  --blur: 14px;
  --radius: 18px;
  --shadow: 0 10px 30px rgba(0,0,0,.35);
}

* { box-sizing: border-box; }


/* ===== Botón flotante ===== */
.menu-toggle{
  position: fixed;
  right: 20px; bottom: 22px;
  width: 56px; height: 56px;
  display: grid; place-items: center;
  border-radius: 50%;
  background: radial-gradient(120% 120% at 30% 30%, #1f2937 0%, #0f172a 100%);
  box-shadow: var(--shadow);
  cursor: pointer;
  z-index: 1002;
  border: 1px solid rgba(255,255,255,.06);
  transition: transform .2s ease, box-shadow .2s ease, background .3s ease;
}
.menu-toggle:hover{ transform: translateY(-2px); box-shadow: 0 14px 34px rgba(0,0,0,.45); }
.menu-toggle:active{ transform: translateY(0); }
.menu-toggle:focus-visible{ outline: 3px solid var(--ring); outline-offset: 3px; border-radius: 50%; }

.menu-toggle .bars{
  width: 22px; height: 2px; background: #fff; border-radius: 2px; position: relative; transition:.35s ease;
}
.menu-toggle .bars::before,
.menu-toggle .bars::after{
  content:""; position:absolute; left:0; width:22px; height:2px; background:#fff; border-radius:2px; transition:.35s ease;
}
.menu-toggle .bars::before{ top:-7px; }
.menu-toggle .bars::after{ top:7px; }
.menu-toggle.active .bars{ background:transparent; }
.menu-toggle.active .bars::before{ transform: rotate(45deg); top:0; }
.menu-toggle.active .bars::after{ transform: rotate(-45deg); top:0; }

/* ===== Overlay ===== */
.overlay{
  position: fixed; inset: 0;
  background: rgba(0,0,0,.45);
  opacity: 0; pointer-events: none;
  transition: opacity .25s ease;
  z-index: 1000;
}
.overlay.show{ opacity: 1; pointer-events: auto; }

/* ===== Panel lateral ===== */
.menu{
  position: fixed; top: 0; right: -320px;
  width: 300px; max-width: 85vw; height: 100%;
  background: var(--panel);
  backdrop-filter: blur(var(--blur));
  -webkit-backdrop-filter: blur(var(--blur));
  border-left: 1px solid rgba(255,255,255,.08);
  box-shadow: -10px 0 35px rgba(0,0,0,.35);
  transition: right .35s cubic-bezier(.22,.61,.36,1);
  z-index: 1001;
  display: flex; flex-direction: column;
}
.menu.active{ right: 0; }

.menu header{
  padding: 22px 18px 12px;
  border-bottom: 1px solid rgba(255,255,255,.06);
  background:
    radial-gradient(120% 120% at 0% 0%, rgba(45,212,191,.25), transparent 60%),
    radial-gradient(120% 120% at 100% 0%, rgba(96,165,250,.22), transparent 60%);
}
.brand{
  display:flex; align-items:center; gap:12px;
}
.brand .logo{
  width:36px; height:36px; border-radius: 10px;
  display:grid; place-items:center;
  background: linear-gradient(135deg, var(--brand), var(--brand-2));
  color:#001019; font-weight: 800;
}
.brand .meta{ line-height: 1.1; }
.brand .meta .title{ font-weight:700; letter-spacing:.2px; }
.brand .meta .subtitle{ font-size:12px; color:var(--text-dim); }

/* Buscador */
.search-wrap{ padding: 12px 16px 14px; }
.search{
  display:flex; align-items:center; gap:10px;
  background: rgba(255,255,255,.06);
  border: 1px solid rgba(255,255,255,.09);
  border-radius: 12px; padding: 10px 12px;
}
.search input{
  width:100%; font: inherit; color: var(--text);
  background: transparent; border: 0; outline: 0;
}
.search kbd{
  margin-left:auto; font-size:11px; color: var(--text-dim);
  background: rgba(255,255,255,.08); border:1px solid rgba(255,255,255,.12);
  border-bottom-width:2px; padding:2px 6px; border-radius:6px;
}

/* Lista de enlaces */
.nav{
  overflow: auto; padding: 10px 8px 18px; gap: 8px;
  display:flex; flex-direction: column; flex:1;
}
.group-label{
  margin: 10px 10px 6px; font-size: 11px; text-transform: uppercase;
  letter-spacing: .12em; color: var(--text-dim);
}
a.nav-item{
  display:flex; align-items:center; gap: 12px;
  padding: 12px 12px;
  margin: 0 8px; border-radius: 12px; text-decoration: none;
  color: var(--text);
  border: 1px solid rgba(255,255,255,.06);
  background: linear-gradient(180deg, rgba(255,255,255,.03), rgba(255,255,255,.02));
  transition: transform .15s ease, background .2s ease, border-color .2s ease, box-shadow .2s ease;
}
a.nav-item:hover{
  background: linear-gradient(180deg, rgba(255,255,255,.07), rgba(255,255,255,.03));
  border-color: rgba(255,255,255,.12);
  box-shadow: 0 6px 14px rgba(0,0,0,.25);
  transform: translateY(-1px);
}
a.nav-item:focus-visible{ outline: 3px solid var(--ring); outline-offset: 2px; }
a.nav-item.active{ border-color: var(--brand-2); box-shadow: 0 0 0 3px rgba(96,165,250,.25) inset; }

.icon{
  width: 22px; height: 22px; display: inline-block; flex: 0 0 auto;
  opacity:.95;
}
.item-text{ display:flex; flex-direction: column; line-height:1.15; }
.item-title{ font-weight: 600; }
.item-sub{ font-size: 12px; color: var(--text-dim); }

/* Footer */
.menu footer{
  padding: 12px 14px 18px;
  border-top: 1px solid rgba(255,255,255,.06);
  display:flex; gap:10px; justify-content: space-between; align-items:center;
  background: linear-gradient(180deg, rgba(0,0,0,.0), rgba(0,0,0,.12));
}
.badge{
  font-size: 11px; color:#0b1220; background: linear-gradient(135deg,var(--brand),var(--brand-2));
  padding: 6px 10px; border-radius: 999px; font-weight: 700; letter-spacing:.02em;
  border: 1px solid rgba(255,255,255,.25);
}

/* Contenido de demo (ajusta a tu página) */
.content{ padding: 28px; max-width: 1100px; margin: 0 auto; }
h1{ font-weight:800; letter-spacing:.3px; margin: 10px 0 8px; }
p.lead{ color: var(--text-dim); }

/* Swipe zone (móvil) */
.swipe-zone{
  position: fixed; top:0; right:0; width: 20px; height: 100vh; z-index: 999;
}
@media (min-width: 900px){
  .swipe-zone{ display:none; }
}
</style>
</head>
<body>

<!-- Botón flotante -->
<button class="menu-toggle" id="menuToggle" aria-label="Abrir menú" aria-controls="sideMenu" aria-expanded="false">
  <span class="bars" aria-hidden="true"></span>
</button>

<!-- Área para detectar swipe desde el borde en móvil -->
<div class="swipe-zone" id="swipeZone" aria-hidden="true"></div>

<!-- Overlay -->
<div class="overlay" id="overlay" tabindex="-1" aria-hidden="true"></div>

<!-- Panel lateral -->
<nav class="menu" id="sideMenu" aria-hidden="true" aria-label="Navegación lateral">
  <header>
    <div class="brand">
      <div class="logo">AT</div>
      <div class="meta">
        <div class="title">Asociación de Transportistas</div>
        <div class="subtitle">Zona Norte Wuinpumuin</div>
      </div>
    </div>
  </header>

  <div class="search-wrap">
    <div class="search" role="search">
      <!-- Icono lupa -->
      <svg class="icon" viewBox="0 0 24 24" fill="none" aria-hidden="true">
        <path d="M21 21l-4.3-4.3M10.5 18a7.5 7.5 0 1 1 0-15 7.5 7.5 0 0 1 0 15Z" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>
      </svg>
      <input id="navSearch" type="search" placeholder="Buscar… (Ctrl/Cmd + K)" aria-label="Buscar en el menú"/>
      <kbd id="kb">Ctrl K</kbd>
    </div>
  </div>

  <div class="nav" id="navList">
    <div class="group-label">General</div>

    <a class="nav-item" href="index2.php" data-title="inicio">
      <svg class="icon" viewBox="0 0 24 24" fill="none" aria-hidden="true">
        <path d="M4 10.5 12 4l8 6.5V20a1 1 0 0 1-1 1h-5v-6H10v6H5a1 1 0 0 1-1-1v-9.5Z" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/>
      </svg>
      <span class="item-text">
        <span class="item-title">Inicio</span>
        <span class="item-sub">Panel principal</span>
      </span>
    </a>

    <a class="nav-item" href="informe.php" data-title="informe de viajes">
      <svg class="icon" viewBox="0 0 24 24" fill="none" aria-hidden="true">
        <path d="M4 5h12l4 4v10a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V6a1 1 0 0 1 1-1Z" stroke="currentColor" stroke-width="1.6"/>
        <path d="M15 4v5h5" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>
        <path d="M7 13h10M7 17h6" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>
      </svg>
      <span class="item-text">
        <span class="item-title">Informe de viajes</span>
        <span class="item-sub">Filtros y reportes</span>
      </span>
    </a>

    <a class="nav-item" href="https://asociacion.asociaciondetransportistaszonanorte.io/tele/liquidacion.php?desde=2025-09-29&hasta=2025-10-12&empresa=Hospital" data-title="liquidacion de viajes">
      <svg class="icon" viewBox="0 0 24 24" fill="none" aria-hidden="true">
        <path d="M4 6h16M4 12h16M4 18h16" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>
      </svg>
      <span class="item-text">
        <span class="item-title">Liquidación de viajes</span>
        <span class="item-sub">Rango de fechas</span>
      </span>
    </a>

    <div class="group-label">Préstamos</div>

    <a class="nav-item" href="https://asociacion.asociaciondetransportistaszonanorte.io/tele/prueba.php?view=graph" data-title="ver mapa prestamos">
      <svg class="icon" viewBox="0 0 24 24" fill="none" aria-hidden="true">
        <path d="M4 18l6-3 4 2 6-3V6l-6 3-4-2-6 3v8Z" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/>
      </svg>
      <span class="item-text">
        <span class="item-title">Ver mapa préstamos</span>
        <span class="item-sub">D3 interactivo</span>
      </span>
    </a>

    <a class="nav-item" href="https://asociacion.asociaciondetransportistaszonanorte.io/tele/admin_prestamos.php?view=cards" data-title="editar info prestamos">
      <svg class="icon" viewBox="0 0 24 24" fill="none" aria-hidden="true">
        <path d="M4 6a2 2 0 0 1 2-2h6l6 6v8a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6Z" stroke="currentColor" stroke-width="1.6"/>
        <path d="M14 4v6h6" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>
      </svg>
      <span class="item-text">
        <span class="item-title">Editar info préstamos</span>
        <span class="item-sub">CRUD y tarjetas</span>
      </span>
    </a>
  </div>

  <footer>
    <span class="badge">AT ZN Wuinpumuin</span>
    <small style="color:var(--text-dim)">© 2025</small>
  </footer>
</nav>

<!-- Contenido de ejemplo (borra si no lo necesitas) -->


<script>
(function(){
  const menu = document.getElementById('sideMenu');
  const toggle = document.getElementById('menuToggle');
  const overlay = document.getElementById('overlay');
  const search = document.getElementById('navSearch');
  const navItems = Array.from(document.querySelectorAll('.nav-item'));
  const swipeZone = document.getElementById('swipeZone');
  const kb = document.getElementById('kb');

  // Mostrar "Cmd K" en Mac
  if (navigator.platform.toUpperCase().includes('MAC')) kb.textContent = '⌘ K';

  function openMenu(){
    menu.classList.add('active');
    overlay.classList.add('show');
    toggle.classList.add('active');
    toggle.setAttribute('aria-expanded','true');
    menu.setAttribute('aria-hidden','false');
    setTimeout(()=> search?.focus(), 160);
    // focus trap simple
    document.addEventListener('focus', trap, true);
  }
  function closeMenu(){
    menu.classList.remove('active');
    overlay.classList.remove('show');
    toggle.classList.remove('active');
    toggle.setAttribute('aria-expanded','false');
    menu.setAttribute('aria-hidden','true');
    document.removeEventListener('focus', trap, true);
    toggle.focus();
  }
  function trap(e){
    if (!menu.contains(e.target)) {
      e.stopPropagation();
      search?.focus();
    }
  }

  toggle.addEventListener('click', ()=>{
    if(menu.classList.contains('active')) closeMenu(); else openMenu();
  });
  overlay.addEventListener('click', closeMenu);
  document.addEventListener('keydown', (e)=>{
    // Esc para cerrar
    if (e.key === 'Escape' && menu.classList.contains('active')) {
      e.preventDefault(); closeMenu();
    }
    // Ctrl/Cmd + K para enfocar buscador
    if ((e.ctrlKey || e.metaKey) && (e.key.toLowerCase() === 'k')) {
      e.preventDefault();
      if(!menu.classList.contains('active')) openMenu();
      search.focus();
    }
  });

  // Filtrado de enlaces por texto
  search.addEventListener('input', (e)=>{
    const q = e.target.value.trim().toLowerCase();
    navItems.forEach(a=>{
      const t = (a.dataset.title || a.textContent).toLowerCase();
      a.style.display = t.includes(q) ? '' : 'none';
    });
  });

  // Marcar activo según URL actual (básico)
  const here = location.href.replace(location.origin,'');
  navItems.forEach(a=>{
    try{
      const href = a.getAttribute('href');
      if (href && here.includes(href)) a.classList.add('active');
    }catch(_){}
  });

  // Swipe para abrir (desde borde derecho) & cerrar
  let startX = null, startY = null, tracking = false;
  swipeZone.addEventListener('touchstart', (e)=>{
    const t = e.touches[0];
    startX = t.clientX; startY = t.clientY; tracking = true;
  }, {passive:true});
  swipeZone.addEventListener('touchmove', (e)=>{
    if(!tracking) return;
    const t = e.touches[0];
    const dx = startX - t.clientX, dy = Math.abs(startY - t.clientY);
    // detecta gesto hacia la izquierda (abrir) con poca desviación vertical
    if (dx < -30 && dy < 40) { openMenu(); tracking=false; }
  }, {passive:true});
  swipeZone.addEventListener('touchend', ()=> tracking=false);

  // Swipe dentro del panel para cerrar
  menu.addEventListener('touchstart', (e)=>{
    const t = e.touches[0];
    menu._sx = t.clientX; menu._sy = t.clientY;
  }, {passive:true});
  menu.addEventListener('touchmove', (e)=>{
    if(menu._sx == null) return;
    const t = e.touches[0];
    const dx = t.clientX - menu._sx, dy = Math.abs(t.clientY - menu._sy);
    if (dx > 40 && dy < 40) { closeMenu(); menu._sx = null; }
  }, {passive:true});

  // Cierra al navegar (mejor UX en móvil)
  navItems.forEach(a=> a.addEventListener('click', ()=> closeMenu()));
})();
</script>
</body>
</html>
