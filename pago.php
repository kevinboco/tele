<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>Ajuste de Pago - Dashboard Moderno</title>
<script src="https://cdn.tailwindcss.com"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<style>
  .num { font-variant-numeric: tabular-nums; }
  .glass-effect {
    background: rgba(255, 255, 255, 0.1);
    backdrop-filter: blur(10px);
    border: 1px solid rgba(255, 255, 255, 0.2);
  }
  .conductor-card {
    transition: all 0.3s ease;
    border-left: 4px solid #3b82f6;
  }
  .conductor-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 10px 25px -5px rgba(59, 130, 246, 0.1);
  }
  .progress-bar {
    transition: width 0.5s ease-in-out;
  }
  .tab-active {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    box-shadow: 0 4px 15px 0 rgba(116, 75, 162, 0.3);
  }
  .stat-card {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    position: relative;
    overflow: hidden;
  }
  .stat-card::before {
    content: '';
    position: absolute;
    top: -50%;
    right: -50%;
    width: 100%;
    height: 100%;
    background: rgba(255, 255, 255, 0.1);
    transform: rotate(30deg);
  }
</style>
</head>
<body class="bg-gradient-to-br from-slate-50 to-blue-50 min-h-screen text-slate-800">
  
  <!-- Header Principal -->
  <header class="bg-white/80 backdrop-blur-lg border-b border-slate-200 sticky top-0 z-40">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
      <div class="flex justify-between items-center py-4">
        <div class="flex items-center space-x-3">
          <div class="w-10 h-10 bg-gradient-to-r from-blue-500 to-purple-600 rounded-xl flex items-center justify-center">
            <span class="text-white font-bold text-lg">💰</span>
          </div>
          <div>
            <h1 class="text-xl font-bold bg-gradient-to-r from-blue-600 to-purple-600 bg-clip-text text-transparent">
              Ajuste de Pago
            </h1>
            <p class="text-sm text-slate-500">Sistema de gestión de pagos</p>
          </div>
        </div>
        
        <div class="flex items-center space-x-3">
          <button class="bg-gradient-to-r from-amber-500 to-orange-500 text-white px-4 py-2 rounded-xl font-semibold shadow-lg hover:shadow-xl transition-all flex items-center space-x-2">
            <span>⭐</span>
            <span>Guardar Cuenta</span>
          </button>
          <button class="bg-gradient-to-r from-blue-500 to-cyan-500 text-white px-4 py-2 rounded-xl font-semibold shadow-lg hover:shadow-xl transition-all flex items-center space-x-2">
            <span>📚</span>
            <span>Cuentas Guardadas</span>
          </button>
        </div>
      </div>
    </div>
  </header>

  <!-- Filtros Mejorados -->
  <section class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
    <div class="glass-effect rounded-2xl p-6 mb-8">
      <h2 class="text-lg font-semibold text-slate-800 mb-4 flex items-center space-x-2">
        <span>🎛️</span>
        <span>Filtros del Reporte</span>
      </h2>
      <form class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <div>
          <label class="block text-sm font-medium text-slate-700 mb-2">Fecha Desde</label>
          <input type="date" class="w-full rounded-xl border border-slate-300 px-4 py-3 focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all">
        </div>
        <div>
          <label class="block text-sm font-medium text-slate-700 mb-2">Fecha Hasta</label>
          <input type="date" class="w-full rounded-xl border border-slate-300 px-4 py-3 focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all">
        </div>
        <div>
          <label class="block text-sm font-medium text-slate-700 mb-2">Empresa</label>
          <select class="w-full rounded-xl border border-slate-300 px-4 py-3 focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all">
            <option>Todas las empresas</option>
            <option>Transportes ABC</option>
            <option>Logística XYZ</option>
          </select>
        </div>
        <div class="flex items-end">
          <button class="w-full bg-gradient-to-r from-green-500 to-emerald-600 text-white py-3 rounded-xl font-semibold shadow-lg hover:shadow-xl transition-all">
            Aplicar Filtros
          </button>
        </div>
      </form>
    </div>
  </section>

  <!-- Dashboard de Métricas -->
  <section class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
      <!-- Tarjeta 1 -->
      <div class="stat-card rounded-2xl p-6 text-white relative overflow-hidden">
        <div class="relative z-10">
          <div class="flex items-center justify-between mb-4">
            <div class="text-3xl">👥</div>
            <div class="text-2xl font-bold">24</div>
          </div>
          <h3 class="text-lg font-semibold mb-1">Conductores Activos</h3>
          <p class="text-blue-100 text-sm">En el periodo seleccionado</p>
        </div>
      </div>

      <!-- Tarjeta 2 -->
      <div class="stat-card rounded-2xl p-6 text-white relative overflow-hidden">
        <div class="relative z-10">
          <div class="flex items-center justify-between mb-4">
            <div class="text-3xl">💰</div>
            <div class="text-2xl font-bold">$48.2M</div>
          </div>
          <h3 class="text-lg font-semibold mb-1">Total Facturado</h3>
          <p class="text-blue-100 text-sm">Base para cálculos</p>
        </div>
      </div>

      <!-- Tarjeta 3 -->
      <div class="stat-card rounded-2xl p-6 text-white relative overflow-hidden">
        <div class="relative z-10">
          <div class="flex items-center justify-between mb-4">
            <div class="text-3xl">📊</div>
            <div class="text-2xl font-bold">$42.5M</div>
          </div>
          <h3 class="text-lg font-semibold mb-1">Total a Pagar</h3>
          <p class="text-blue-100 text-sm">Después de descuentos</p>
        </div>
      </div>

      <!-- Tarjeta 4 -->
      <div class="stat-card rounded-2xl p-6 text-white relative overflow-hidden">
        <div class="relative z-10">
          <div class="flex items-center justify-between mb-4">
            <div class="text-3xl">🎯</div>
            <div class="text-2xl font-bold">88%</div>
          </div>
          <h3 class="text-lg font-semibold mb-1">Eficiencia</h3>
          <p class="text-blue-100 text-sm">Rendimiento del periodo</p>
        </div>
      </div>
    </div>
  </section>

  <!-- Sistema de Pestañas -->
  <section class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
    <div class="bg-white rounded-2xl shadow-lg border border-slate-200 overflow-hidden mb-8">
      <!-- Navegación de Pestañas -->
      <div class="flex overflow-x-auto border-b border-slate-200">
        <button class="tab-active px-8 py-4 font-semibold text-sm whitespace-nowrap transition-all">
          📋 Vista de Tarjetas
        </button>
        <button class="px-8 py-4 font-semibold text-slate-600 hover:text-slate-800 whitespace-nowrap transition-all">
          📊 Vista de Tabla
        </button>
        <button class="px-8 py-4 font-semibold text-slate-600 hover:text-slate-800 whitespace-nowrap transition-all">
          📈 Gráficos
        </button>
        <button class="px-8 py-4 font-semibold text-slate-600 hover:text-slate-800 whitespace-nowrap transition-all">
          💰 Resumen Financiero
        </button>
      </div>

      <!-- Contenido de Pestaña Activa -->
      <div class="p-6">
        <!-- Grid de Tarjetas de Conductores -->
        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-6">
          
          <!-- Tarjeta de Conductor Ejemplo 1 -->
          <div class="conductor-card bg-white rounded-2xl border border-slate-200 p-6 shadow-sm">
            <div class="flex items-center justify-between mb-4">
              <div class="flex items-center space-x-3">
                <div class="w-12 h-12 bg-gradient-to-r from-blue-500 to-cyan-500 rounded-full flex items-center justify-center">
                  <span class="text-white font-bold text-lg">JP</span>
                </div>
                <div>
                  <h3 class="font-semibold text-slate-800">Juan Pérez</h3>
                  <p class="text-slate-500 text-sm">15 viajes completados</p>
                </div>
              </div>
              <span class="bg-green-100 text-green-700 px-3 py-1 rounded-full text-sm font-semibold">
                $2.45M
              </span>
            </div>

            <!-- Métricas Rápidas -->
            <div class="space-y-3 mb-4">
              <div class="flex justify-between items-center">
                <span class="text-slate-600 text-sm">Base:</span>
                <span class="font-semibold">$2,850,000</span>
              </div>
              <div class="flex justify-between items-center">
                <span class="text-slate-600 text-sm">Ajuste:</span>
                <span class="font-semibold text-red-500">-$125,000</span>
              </div>
              <div class="flex justify-between items-center">
                <span class="text-slate-600 text-sm">Préstamos:</span>
                <span class="font-semibold text-orange-500">$180,000</span>
              </div>
            </div>

            <!-- Barra de Progreso -->
            <div class="mb-4">
              <div class="flex justify-between text-sm mb-2">
                <span class="text-slate-600">Progreso de pago</span>
                <span class="font-semibold">85%</span>
              </div>
              <div class="w-full bg-slate-200 rounded-full h-2">
                <div class="bg-gradient-to-r from-green-400 to-emerald-500 h-2 rounded-full progress-bar" style="width: 85%"></div>
              </div>
            </div>

            <!-- Acciones -->
            <div class="flex space-x-2">
              <button class="flex-1 bg-blue-500 text-white py-2 rounded-lg text-sm font-semibold hover:bg-blue-600 transition">
                Ver Detalles
              </button>
              <button class="flex-1 bg-slate-100 text-slate-700 py-2 rounded-lg text-sm font-semibold hover:bg-slate-200 transition">
                Exportar
              </button>
            </div>
          </div>

          <!-- Tarjeta de Conductor Ejemplo 2 -->
          <div class="conductor-card bg-white rounded-2xl border border-slate-200 p-6 shadow-sm">
            <div class="flex items-center justify-between mb-4">
              <div class="flex items-center space-x-3">
                <div class="w-12 h-12 bg-gradient-to-r from-purple-500 to-pink-500 rounded-full flex items-center justify-center">
                  <span class="text-white font-bold text-lg">MG</span>
                </div>
                <div>
                  <h3 class="font-semibold text-slate-800">María González</h3>
                  <p class="text-slate-500 text-sm">12 viajes completados</p>
                </div>
              </div>
              <span class="bg-green-100 text-green-700 px-3 py-1 rounded-full text-sm font-semibold">
                $1.89M
              </span>
            </div>

            <div class="space-y-3 mb-4">
              <div class="flex justify-between items-center">
                <span class="text-slate-600 text-sm">Base:</span>
                <span class="font-semibold">$2,150,000</span>
              </div>
              <div class="flex justify-between items-center">
                <span class="text-slate-600 text-sm">Ajuste:</span>
                <span class="font-semibold text-green-500">+$45,000</span>
              </div>
              <div class="flex justify-between items-center">
                <span class="text-slate-600 text-sm">Préstamos:</span>
                <span class="font-semibold text-orange-500">$95,000</span>
              </div>
            </div>

            <div class="mb-4">
              <div class="flex justify-between text-sm mb-2">
                <span class="text-slate-600">Progreso de pago</span>
                <span class="font-semibold">92%</span>
              </div>
              <div class="w-full bg-slate-200 rounded-full h-2">
                <div class="bg-gradient-to-r from-green-400 to-emerald-500 h-2 rounded-full progress-bar" style="width: 92%"></div>
              </div>
            </div>

            <div class="flex space-x-2">
              <button class="flex-1 bg-blue-500 text-white py-2 rounded-lg text-sm font-semibold hover:bg-blue-600 transition">
                Ver Detalles
              </button>
              <button class="flex-1 bg-slate-100 text-slate-700 py-2 rounded-lg text-sm font-semibold hover:bg-slate-200 transition">
                Exportar
              </button>
            </div>
          </div>

          <!-- Tarjeta de Conductor Ejemplo 3 -->
          <div class="conductor-card bg-white rounded-2xl border border-slate-200 p-6 shadow-sm">
            <div class="flex items-center justify-between mb-4">
              <div class="flex items-center space-x-3">
                <div class="w-12 h-12 bg-gradient-to-r from-orange-500 to-red-500 rounded-full flex items-center justify-center">
                  <span class="text-white font-bold text-lg">CR</span>
                </div>
                <div>
                  <h3 class="font-semibold text-slate-800">Carlos Rodríguez</h3>
                  <p class="text-slate-500 text-sm">18 viajes completados</p>
                </div>
              </div>
              <span class="bg-green-100 text-green-700 px-3 py-1 rounded-full text-sm font-semibold">
                $3.12M
              </span>
            </div>

            <div class="space-y-3 mb-4">
              <div class="flex justify-between items-center">
                <span class="text-slate-600 text-sm">Base:</span>
                <span class="font-semibold">$3,450,000</span>
              </div>
              <div class="flex justify-between items-center">
                <span class="text-slate-600 text-sm">Ajuste:</span>
                <span class="font-semibold text-red-500">-$230,000</span>
              </div>
              <div class="flex justify-between items-center">
                <span class="text-slate-600 text-sm">Préstamos:</span>
                <span class="font-semibold text-orange-500">$320,000</span>
              </div>
            </div>

            <div class="mb-4">
              <div class="flex justify-between text-sm mb-2">
                <span class="text-slate-600">Progreso de pago</span>
                <span class="font-semibold">78%</span>
              </div>
              <div class="w-full bg-slate-200 rounded-full h-2">
                <div class="bg-gradient-to-r from-green-400 to-emerald-500 h-2 rounded-full progress-bar" style="width: 78%"></div>
              </div>
            </div>

            <div class="flex space-x-2">
              <button class="flex-1 bg-blue-500 text-white py-2 rounded-lg text-sm font-semibold hover:bg-blue-600 transition">
                Ver Detalles
              </button>
              <button class="flex-1 bg-slate-100 text-slate-700 py-2 rounded-lg text-sm font-semibold hover:bg-slate-200 transition">
                Exportar
              </button>
            </div>
          </div>

        </div>
      </div>
    </div>
  </section>

  <!-- Panel de Resumen Financiero -->
  <section class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
      
      <!-- Distribución de Pagos -->
      <div class="bg-white rounded-2xl p-6 border border-slate-200 shadow-sm">
        <h3 class="font-semibold text-slate-800 mb-4 flex items-center space-x-2">
          <span>📊</span>
          <span>Distribución de Pagos</span>
        </h3>
        <div class="space-y-4">
          <div>
            <div class="flex justify-between text-sm mb-1">
              <span class="text-slate-600">Retención 3.5%</span>
              <span class="font-semibold">$1,687,000</span>
            </div>
            <div class="w-full bg-slate-200 rounded-full h-2">
              <div class="bg-red-500 h-2 rounded-full progress-bar" style="width: 3.5%"></div>
            </div>
          </div>
          <div>
            <div class="flex justify-between text-sm mb-1">
              <span class="text-slate-600">4x1000</span>
              <span class="font-semibold">$192,800</span>
            </div>
            <div class="w-full bg-slate-200 rounded-full h-2">
              <div class="bg-orange-500 h-2 rounded-full progress-bar" style="width: 0.4%"></div>
            </div>
          </div>
          <div>
            <div class="flex justify-between text-sm mb-1">
              <span class="text-slate-600">Aporte 10%</span>
              <span class="font-semibold">$4,820,000</span>
            </div>
            <div class="w-full bg-slate-200 rounded-full h-2">
              <div class="bg-blue-500 h-2 rounded-full progress-bar" style="width: 10%"></div>
            </div>
          </div>
        </div>
      </div>

      <!-- Resumen de Ajustes -->
      <div class="bg-white rounded-2xl p-6 border border-slate-200 shadow-sm">
        <h3 class="font-semibold text-slate-800 mb-4 flex items-center space-x-2">
          <span>⚖️</span>
          <span>Resumen de Ajustes</span>
        </h3>
        <div class="space-y-3">
          <div class="flex justify-between items-center p-3 bg-green-50 rounded-lg">
            <span class="text-green-700 font-medium">Ajustes Positivos</span>
            <span class="text-green-700 font-bold">+$2.1M</span>
          </div>
          <div class="flex justify-between items-center p-3 bg-red-50 rounded-lg">
            <span class="text-red-700 font-medium">Ajustes Negativos</span>
            <span class="text-red-700 font-bold">-$1.8M</span>
          </div>
          <div class="flex justify-between items-center p-3 bg-blue-50 rounded-lg">
            <span class="text-blue-700 font-medium">Neto Ajustes</span>
            <span class="text-blue-700 font-bold">+$300,000</span>
          </div>
        </div>
      </div>

      <!-- Acciones Rápidas -->
      <div class="bg-white rounded-2xl p-6 border border-slate-200 shadow-sm">
        <h3 class="font-semibold text-slate-800 mb-4 flex items-center space-x-2">
          <span>🚀</span>
          <span>Acciones Rápidas</span>
        </h3>
        <div class="space-y-3">
          <button class="w-full bg-gradient-to-r from-purple-500 to-pink-500 text-white py-3 rounded-xl font-semibold hover:shadow-lg transition-all">
            📥 Exportar Reporte PDF
          </button>
          <button class="w-full bg-gradient-to-r from-cyan-500 to-blue-500 text-white py-3 rounded-xl font-semibold hover:shadow-lg transition-all">
            📊 Generar Gráficos
          </button>
          <button class="w-full bg-gradient-to-r from-emerald-500 to-green-500 text-white py-3 rounded-xl font-semibold hover:shadow-lg transition-all">
            💾 Guardar Configuración
          </button>
        </div>
      </div>

    </div>
  </section>

  <!-- Footer -->
  <footer class="bg-white/80 backdrop-blur-lg border-t border-slate-200 mt-12">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
      <div class="flex flex-col md:flex-row justify-between items-center">
        <div class="flex items-center space-x-2 mb-4 md:mb-0">
          <div class="w-6 h-6 bg-gradient-to-r from-blue-500 to-purple-600 rounded"></div>
          <span class="font-semibold text-slate-700">Sistema de Ajuste de Pago</span>
        </div>
        <div class="text-slate-500 text-sm">
          © 2024 Todos los derechos reservados | v2.1.0
        </div>
      </div>
    </div>
  </footer>

  <script>
    // Animaciones simples para las tarjetas
    document.addEventListener('DOMContentLoaded', function() {
      // Efecto hover en tarjetas
      const cards = document.querySelectorAll('.conductor-card');
      cards.forEach(card => {
        card.addEventListener('mouseenter', function() {
          this.style.transform = 'translateY(-4px)';
        });
        card.addEventListener('mouseleave', function() {
          this.style.transform = 'translateY(0)';
        });
      });

      // Sistema de pestañas
      const tabs = document.querySelectorAll('button[class*="px-8 py-4"]');
      tabs.forEach(tab => {
        tab.addEventListener('click', function() {
          tabs.forEach(t => t.classList.remove('tab-active'));
          this.classList.add('tab-active');
        });
      });

      // Animación de barras de progreso
      const progressBars = document.querySelectorAll('.progress-bar');
      progressBars.forEach(bar => {
        const width = bar.style.width;
        bar.style.width = '0';
        setTimeout(() => {
          bar.style.width = width;
        }, 300);
      });
    });
  </script>

</body>
</html>