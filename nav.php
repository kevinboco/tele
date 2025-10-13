<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>Asociación de Transportistas</title>
<style>
:root{
  --bg:#0b0f14;
  --panel:rgba(20,24,30,.75);
  --text:#e6eef8;
  --text-dim:#a8b3c7;
  --brand:#2dd4bf;
  --brand-2:#60a5fa;
  --ring: rgba(99, 179, 237, .55);
  --blur: 14px;
  --radius: 18px;
  --shadow: 0 10px 30px rgba(0,0,0,.35);
}

body{
  margin:0;
  font-family: system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif;
  background: linear-gradient(135deg,#0a0f14 0%, #0d1220 60%, #0b0f14 100%);
  color: var(--text);
}

/* ===== Botón flotante ===== */
.menu-toggle{
  position: fixed;
  right: 22px; bottom: 24px;
  width: 60px; height: 60px;
  border-radius: 50%;
  background: radial-gradient(120% 120% at 30% 30%, #1f2937 0%, #0f172a 100%);
  box-shadow: var(--shadow);
  display: flex; align-items: center; justify-content: center;
  cursor: pointer;
  border: 1px solid rgba(255,255,255,.06);
  z-index: 1003;
  transition: 0.3s;
}
.menu-toggle:hover{ transform: translateY(-2px); }

.menu-toggle .bars{
  width: 24px; height: 2px;
  background: #fff; border-radius: 2px;
  position: relative; transition:.35s ease;
}
.menu-toggle .bars::before,
.menu-toggle .bars::after{
  content:""; position:absolute; left:0; width:24px; height:2px;
  background:#fff; border-radius:2px; transition:.35s ease;
}
.menu-toggle .bars::before{ top:-7px; }
.menu-toggle .bars::after{ top:7px; }
.menu-toggle.active .bars{ background:transparent; }
.menu-toggle.active .bars::before{ transform: rotate(45deg); top:0; }
.menu-toggle.active .bars::after{ transform: rotate(-45deg); top:0; }

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
  z-index: 1002;
  display: flex; flex-direction: column;
}
.menu.active{ right: 0; }

/* Cabecera */
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
.brand .meta .title{ font-weight:700; }
.brand .meta .subtitle{ font-size:12px; color:var(--text-dim); }

/* Links */
.nav{
  overflow-y: auto; padding: 10px 8px 18px; gap: 8px;
  display:flex; flex-direction: column; flex:1;
}
a.nav-item{
  display:flex; align-items:center; gap: 12px;
  padding: 12px 12px;
  margin: 0 8px; border-radius: 12px;
  text-decoration: none;
  color: var(--text);
  border: 1px solid rgba(255,255,255,.06);
  background: rgba(255,255,255,.04);
  transition: 0.2s;
}
a.nav-item:hover{
  background: rgba(255,255,255,.08);
  border-color: rgba(255,255,255,.12);
  transform: translateX(-2px);
}
a.nav-item.active{
  border-color: var(--brand-2);
  box-shadow: 0 0 0 3px rgba(96,165,250,.25) inset;
}
.icon{
  width:22px; height:22px; flex-shrink:0;
}
.item-title{ font-weight:600; }
.item-sub{ font-size:12px; color:var(--text-dim); }

.menu footer{
  padding: 12px 14px 18px;
  border-top: 1px solid rgba(255,255,255,.06);
  text-align:center;
}
.badge{
  font-size: 11px;
  color:#0b1220;
  background: linear-gradient(135deg,var(--brand),var(--brand-2));
  padding: 6px 10px; border-radius: 999px;
  font-weight:700;
}
</style>
</head>

<body>

<!-- Botón circular -->
<button class="menu-toggle" id="menuToggle" aria-label="Abrir menú">
  <span class="bars"></span>
</button>

<!-- Menú lateral -->
<nav class="menu" id="sideMenu">
  <header>
    <div class="brand">
      <div class="logo">AT</div>
      <div class="meta">
        <div class="title">Asociación de Transportistas</div>
        <div class="subtitle">Zona Norte Wuinpumuin</div>
      </div>
    </div>
  </header>

  <div class="nav">
    <a class="nav-item" href="index2.php">
      <svg class="icon" viewBox="0 0 24 24" fill="none"><path d="M4 10.5 12 4l8 6.5V20a1 1 0 0 1-1 1h-5v-6H10v6H5a1 1 0 0 1-1-1v-9.5Z" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/></svg>
      <div><div class="item-title">Inicio</div><div class="item-sub">Panel principal</div></div>
    </a>

    <a class="nav-item" href="informe.php">
      <svg class="icon" viewBox="0 0 24 24" fill="none"><path d="M4 5h12l4 4v10a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V6a1 1 0 0 1 1-1Z" stroke="currentColor" stroke-width="1.6"/><path d="M15 4v5h5" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/><path d="M7 13h10M7 17h6" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>
      <div><div class="item-title">Informe de viajes</div><div class="item-sub">Filtros y reportes</div></div>
    </a>

    <a class="nav-item" href="https://asociacion.asociaciondetransportistaszonanorte.io/tele/liquidacion.php?desde=2025-09-29&hasta=2025-10-12&empresa=Hospital">
      <svg class="icon" viewBox="0 0 24 24" fill="none"><path d="M4 6h16M4 12h16M4 18h16" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>
      <div><div class="item-title">Liquidación</div><div class="item-sub">Rango de fechas</div></div>
    </a>

    <a class="nav-item" href="https://asociacion.asociaciondetransportistaszonanorte.io/tele/prueba.php?view=graph">
      <svg class="icon" viewBox="0 0 24 24" fill="none"><path d="M4 18l6-3 4 2 6-3V6l-6 3-4-2-6 3v8Z" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/></svg>
      <div><div class="item-title">Mapa préstamos</div><div class="item-sub">D3 interactivo</div></div>
    </a>

    <a class="nav-item" href="https://asociacion.asociaciondetransportistaszonanorte.io/tele/admin_prestamos.php?view=cards">
      <svg class="icon" viewBox="0 0 24 24" fill="none"><path d="M4 6a2 2 0 0 1 2-2h6l6 6v8a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6Z" stroke="currentColor" stroke-width="1.6"/><path d="M14 4v6h6" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>
      <div><div class="item-title">Editar préstamos</div><div class="item-sub">CRUD y tarjetas</div></div>
    </a>
  </div>

  <footer>
    <span class="badge">AT ZN Wuinpumuin © 2025</span>
  </footer>
</nav>

<script>
// ============ Funcionalidad ===============
const toggle = document.getElementById('menuToggle');
const menu = document.getElementById('sideMenu');

toggle.addEventListener('click', ()=>{
  menu.classList.toggle('active');
  toggle.classList.toggle('active');
});
document.addEventListener('click', (e)=>{
  if(menu.classList.contains('active') && !menu.contains(e.target) && !toggle.contains(e.target)){
    menu.classList.remove('active');
    toggle.classList.remove('active');
  }
});
</script>

</body>
</html>
