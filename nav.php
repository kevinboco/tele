<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Navbar flotante</title>
<style>
body {
  margin: 0;
  font-family: Arial, sans-serif;
  background: #f4f4f4;
}

/* === BOTÓN FLOTANTE === */
.menu-toggle {
  position: fixed;
  top: 20px;
  right: 25px;
  width: 45px;
  height: 45px;
  border-radius: 50%;
  background-color: #111;
  color: white;
  display: flex;
  justify-content: center;
  align-items: center;
  cursor: pointer;
  z-index: 1001;
  transition: background 0.3s ease;
}

.menu-toggle:hover {
  background-color: #333;
}

/* === ICONO DE LÍNEAS === */
.menu-toggle div {
  position: relative;
  width: 22px;
  height: 2px;
  background: white;
  transition: 0.3s;
}
.menu-toggle div::before,
.menu-toggle div::after {
  content: '';
  position: absolute;
  left: 0;
  width: 22px;
  height: 2px;
  background: white;
  transition: 0.3s;
}
.menu-toggle div::before {
  top: -7px;
}
.menu-toggle div::after {
  top: 7px;
}

/* === ANIMACIÓN CRUZ === */
.menu-toggle.active div {
  background: transparent;
}
.menu-toggle.active div::before {
  transform: rotate(45deg);
  top: 0;
}
.menu-toggle.active div::after {
  transform: rotate(-45deg);
  top: 0;
}

/* === MENÚ DESPLEGABLE === */
.menu {
  position: fixed;
  top: 0;
  right: -250px;
  width: 200px;
  height: 100%;
  background-color: #222;
  color: white;
  padding-top: 80px;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 25px;
  transition: right 0.4s ease;
  z-index: 1000;
}

.menu.active {
  right: 0;
}

/* === LINKS === */
.menu a {
  color: white;
  text-decoration: none;
  font-size: 17px;
  transition: color 0.3s ease;
}

.menu a:hover {
  color: #00bfff;
}

/* === SIMULACIÓN DE CONTENIDO === */
.content {
  padding: 100px 20px;
}
</style>
</head>
<body>

<!-- Botón flotante -->
<div class="menu-toggle" id="menu-toggle">
  <div></div>
</div>

<!-- Menú lateral -->
<div class="menu" id="menu">
  <a href="bienvenida.php">Inicio</a>
  <a href="formulario_asociado.php">Asociados</a>
  <a href="ver_documentos.php">Documentos</a>
  <a href="ver_cuentas_cobro.php">Cuentas</a>
  <a href="estadisticas.php">Estadísticas</a>
</div>

<!-- Contenido de ejemplo -->
<div class="content">
  <h1>Bienvenido</h1>
  <p>Haz clic en el botón de la esquina superior derecha para abrir el menú.</p>
</div>

<script>
const toggle = document.getElementById('menu-toggle');
const menu = document.getElementById('menu');

toggle.addEventListener('click', () => {
  toggle.classList.toggle('active');
  menu.classList.toggle('active');
});
</script>

</body>
</html>
