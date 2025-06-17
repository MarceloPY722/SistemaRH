<?php
// Obtener información del usuario logueado
if (isset($_SESSION['usuario_id'])) {
    require_once __DIR__ . '/../../cnx/db_connect.php';
    $stmt = $conn->prepare("SELECT nombre_usuario, rol FROM usuarios WHERE id = ?");
    $stmt->bind_param("i", $_SESSION['usuario_id']);
    $stmt->execute();
    $usuario_actual = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}
?>
<!-- Sidebar -->
<div class="col-md-3 col-lg-2 px-0">
    <div class="sidebar modern-sidebar">
        <!-- Encabezado del sidebar -->
        <div class="sidebar-header">
            <div class="logo-container">
                <i class="fas fa-shield-alt logo-icon"></i>
                <div class="logo-text">
                    <h4>Sistema RH</h4>
                    <span class="subtitle">Policía Nacional</span>
                </div>
            </div>
        </div>
        
        <!-- Perfil del usuario -->
        <div class="user-profile">
            <div class="user-avatar">
                <i class="fas fa-user-circle"></i>
            </div>
            <div class="user-info">
                <span class="user-name"><?php echo isset($usuario_actual) ? htmlspecialchars($usuario_actual['nombre_usuario']) : 'Usuario'; ?></span>
                <span class="user-role"><?php echo isset($usuario_actual) ? htmlspecialchars($usuario_actual['rol']) : 'Sistema'; ?></span>
            </div>
        </div>
        
        <!-- Navegación principal -->
        <nav class="sidebar-nav">
            <ul class="nav-menu">
                <!-- Dashboard -->
                <li class="nav-item">
                    <a href="/SistemaRH/admin/index.php" class="nav-link">
                        <div class="nav-icon">
                            <i class="fas fa-tachometer-alt"></i>
                        </div>
                        <span class="nav-text">Dashboard</span>
                    </a>
                </li>

              
<li class="nav-item has-submenu">
                    <a href="#" class="nav-link">
                        <div class="nav-icon">
                            <i class="fas fa-users"></i>
                        </div>
                        <span class="nav-text">Gestión de Policías</span>
                        <i class="fas fa-chevron-right submenu-arrow"></i>
                    </a>
                    <ul class="submenu">
                        <li>
                            <a href="/SistemaRH/admin/policias/agregar.php" class="submenu-link">
                                <i class="fas fa-user-plus"></i>
                                <span>Agregar Policía</span>
                            </a>
                        </li>
                        <li>
                            <a href="/SistemaRH/admin/policias/index.php" class="submenu-link">
                                <i class="fas fa-eye"></i>
                                <span>Ver Policías</span>
                            </a>
                        </li>
                        <li>
                            <a href="/SistemaRH/admin/policias/especialidad/index.php" class="submenu-link">
                                <i class="fas fa-certificate"></i>
                                <span>Especialidades</span>
                            </a>
                        </li>
                        <li>
                            <a href="/SistemaRH/admin/policias/grado/" class="submenu-link">
                                <i class="fas fa-star"></i>
                                <span>Grados</span>
                            </a>
                        </li>
                        <li>
                            <a href="/SistemaRH/admin/policias/lugar_guardias/" class="submenu-link">
                                <i class="fas fa-map-marker-alt"></i>
                                <span>Lugares de Guardia</span>
                            </a>
                        </li>
                    </ul>
                </li>

                <!-- Programar Servicios -->
                <li class="nav-item">
                    <a href="/SistemaRH/admin/servicios/index.php" class="nav-link">
                        <div class="nav-icon">
                            <i class="fas fa-calendar-alt"></i>
                        </div>
                        <span class="nav-text">Programar Servicios</span>
                    </a>
                </li>

                <!-- Lista de Guardias -->
                <li class="nav-item">
                    <a href="/SistemaRH/admin/guardias/index.php" class="nav-link">
                        <div class="nav-icon">
                            <i class="fas fa-list-ul"></i>
                        </div>
                        <span class="nav-text">Lista de Guardias</span>
                    </a>
                </li>

                <!-- Gestión de Ausencias -->
                <li class="nav-item">
                    <a href="/SistemaRH/admin/ausencias/index.php" class="nav-link">
                        <div class="nav-icon">
                            <i class="fas fa-user-times"></i>
                        </div>
                        <span class="nav-text">Gestión de Ausencias</span>
                    </a>
                </li>

                <!-- Reportes -->
                <li class="nav-item">
                    <a href="/SistemaRH/admin/reportes/index.php" class="nav-link">
                        <div class="nav-icon">
                            <i class="fas fa-chart-bar"></i>
                        </div>
                        <span class="nav-text">Reportes</span>
                    </a>
                </li>

                <!-- Separador -->
                <li class="nav-separator"></li>

                <!-- Configuración -->
                <li class="nav-item has-submenu">
                    <a href="#" class="nav-link">
                        <div class="nav-icon">
                            <i class="fas fa-cog"></i>
                        </div>
                        <span class="nav-text">Configuración</span>
                        <i class="fas fa-chevron-right submenu-arrow"></i>
                    </a>
                    <ul class="submenu">
                        <li>
                            <a href="#" id="darkModeButton" class="submenu-link">
                                <i class="fas fa-moon"></i>
                                <span id="darkModeText">Modo Oscuro</span>
                            </a>
                        </li>
                        <li>
                            <a href="#" class="submenu-link">
                                <i class="fas fa-user-cog"></i>
                                <span>Perfil</span>
                            </a>
                        </li>
                    </ul>
                </li>

                <!-- Cerrar Sesión -->
                <li class="nav-item logout-item">
                    <a href="/SistemaRH/admin/logout.php" class="nav-link logout-link">
                        <div class="nav-icon">
                            <i class="fas fa-sign-out-alt"></i>
                        </div>
                        <span class="nav-text">Cerrar Sesión</span>
                    </a>
                </li>
            </ul>
        </nav>
    </div>
</div>

<style>
/* Variables CSS para colores y espaciado */
:root {
    --sidebar-bg: linear-gradient(135deg, #104c75 0%, #0d3d5c 100%);
    --sidebar-bg-dark: linear-gradient(135deg, #104c75 0%, #0d3d5c 100%);
    --sidebar-width: 100%; /* Cambiado de 280px a 100% */
    --sidebar-collapsed-width: 70px;
    --primary-color: #104c75;
    --secondary-color: #0d3d5c;
    --text-light: rgba(255, 255, 255, 0.9);
    --text-muted: rgba(255, 255, 255, 0.7);
    --hover-bg: rgba(255, 255, 255, 0.1);
    --active-bg: rgba(255, 255, 255, 0.2);
    --border-color: rgba(255, 255, 255, 0.1);
    --shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
}

/* Sidebar principal */
.sidebar.modern-sidebar {
    width: 100%; /* Asegurar que ocupe todo el ancho del contenedor */
    background: var(--sidebar-bg);
    min-height: 100vh; /* Cambiado para ocupar toda la altura */
    color: var(--text-light);
    position: relative;
    overflow: hidden;
    box-shadow: var(--shadow);
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    margin: 0; /* Eliminar márgenes */
    padding: 0; /* Eliminar padding externo */
}

/* Ajuste para el contenedor del sidebar */
.col-md-3.col-lg-2.px-0 {
    padding-left: 0 !important;
    padding-right: 0 !important;
    margin: 0;
}

.sidebar.modern-sidebar::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><pattern id="grain" width="100" height="100" patternUnits="userSpaceOnUse"><circle cx="50" cy="50" r="1" fill="%23ffffff" opacity="0.03"/></pattern></defs><rect width="100" height="100" fill="url(%23grain)"/></svg>') repeat;
    pointer-events: none;
}

/* Encabezado del sidebar */
.sidebar-header {
    padding: 25px 20px;
    border-bottom: 1px solid var(--border-color);
    position: relative;
    z-index: 1;
}

.logo-container {
    display: flex;
    align-items: center;
    gap: 15px;
}

.logo-icon {
    font-size: 2.2em;
    color: #fff;
    text-shadow: 0 2px 4px rgba(0, 0, 0, 0.3);
    animation: pulse 2s infinite;
}

@keyframes pulse {
    0%, 100% { transform: scale(1); }
    50% { transform: scale(1.05); }
}

.logo-text h4 {
    margin: 0;
    font-size: 1.4em;
    font-weight: 700;
    color: #fff;
    text-shadow: 0 1px 3px rgba(0, 0, 0, 0.3);
}

.subtitle {
    font-size: 0.8em;
    color: var(--text-muted);
    font-weight: 400;
    display: block;
    margin-top: 2px;
}

/* Perfil del usuario */
.user-profile {
    padding: 20px;
    display: flex;
    align-items: center;
    gap: 12px;
    border-bottom: 1px solid var(--border-color);
    background: rgba(255, 255, 255, 0.05);
    position: relative;
    z-index: 1;
}

.user-avatar {
    font-size: 2.5em;
    color: #fff;
    opacity: 0.9;
}

.user-info {
    flex: 1;
}

.user-name {
    display: block;
    font-weight: 600;
    color: #fff;
    font-size: 0.95em;
}

.user-role {
    display: block;
    font-size: 0.8em;
    color: var(--text-muted);
    margin-top: 2px;
}

/* Navegación */
.sidebar-nav {
    padding: 15px 0;
    position: relative;
    z-index: 1;
}

.nav-menu {
    list-style: none;
    padding: 0;
    margin: 0;
}

.nav-item {
    margin-bottom: 2px;
    position: relative;
}

.nav-link {
    display: flex;
    align-items: center;
    padding: 15px 20px;
    color: var(--text-light);
    text-decoration: none !important;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    position: relative;
    overflow: hidden;
}

.nav-link::before {
    content: '';
    position: absolute;
    left: 0;
    top: 0;
    bottom: 0;
    width: 4px;
    background: #fff;
    transform: scaleY(0);
    transition: transform 0.3s ease;
}

.nav-link:hover::before,
.nav-item.active .nav-link::before {
    transform: scaleY(1);
}

.nav-link:hover {
    background: var(--hover-bg);
    color: #fff;
    transform: translateX(8px);
}

.nav-item.active .nav-link {
    background: var(--active-bg);
    color: #fff;
    font-weight: 600;
}

.nav-icon {
    width: 24px;
    height: 24px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-right: 15px;
    font-size: 1.1em;
    transition: transform 0.3s ease;
}

.nav-link:hover .nav-icon {
    transform: scale(1.1);
}

.nav-text {
    flex: 1;
    font-size: 0.95em;
    font-weight: 500;
}

.submenu-arrow {
    font-size: 0.8em;
    transition: transform 0.3s ease;
    opacity: 0.7;
}

.nav-item.active .submenu-arrow {
    transform: rotate(90deg);
    opacity: 1;
}

/* Submenús */
.submenu {
    list-style: none;
    padding: 0;
    margin: 0;
    background: rgba(0, 0, 0, 0.2);
    max-height: 0;
    overflow: hidden;
    transition: max-height 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

.nav-item.active .submenu {
    max-height: 300px;
}

.submenu li {
    border-left: 2px solid rgba(255, 255, 255, 0.1);
    margin-left: 20px;
}

.submenu-link {
    display: flex;
    align-items: center;
    padding: 12px 20px 12px 25px;
    color: var(--text-muted);
    text-decoration: none !important;
    font-size: 0.9em;
    transition: all 0.3s ease;
    position: relative;
}

.submenu-link:hover {
    color: #fff;
    background: rgba(255, 255, 255, 0.1);
    transform: translateX(5px);
}

.submenu-link i {
    width: 18px;
    margin-right: 12px;
    font-size: 0.9em;
}

/* Separador */
.nav-separator {
    height: 1px;
    background: var(--border-color);
    margin: 15px 20px;
}

/* Elemento de logout */
.logout-item {
    margin-top: auto;
    border-top: 1px solid var(--border-color);
    padding-top: 15px;
    margin-top: 20px;
}

.logout-link {
    color: #ff6b6b !important;
    font-weight: 600;
}

.logout-link:hover {
    background: rgba(255, 107, 107, 0.1) !important;
    color: #ff5252 !important;
}

/* Modo oscuro */
body.dark-mode {
    background-color: #1a1a1a;
    color: #fff;
}

body.dark-mode .sidebar.modern-sidebar {
    background: var(--sidebar-bg-dark);
}

body.dark-mode .card {
    background-color: #2d2d2d;
    border-color: #404040;
    color: #fff;
}

/* Responsive */
@media (max-width: 768px) {
    .sidebar.modern-sidebar {
        position: fixed;
        left: -100%;
        top: 56px;
        width: 280px;
        z-index: 1000;
        transition: left 0.3s ease;
    }
    
    .sidebar.modern-sidebar.show {
        left: 0;
    }
    
    .sidebar-overlay {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(0, 0, 0, 0.5);
        z-index: 999;
        display: none;
    }
    
    .sidebar-overlay.show {
        display: block;
    }
}

/* Ajustes adicionales para el layout */
.container-fluid {
    padding: 0;
    margin: 0;
    max-width: 100%;
}

.container-fluid .row {
    margin: 0;
    width: 100%;
}

/* Asegurar que el contenido principal también se ajuste */
.col-md-9.col-lg-10 {
    padding-left: 0;
    min-height: 100vh;
}

.main-content {
    padding: 20px;
    background: #f8f9fa;
    min-height: 100vh;
}

/* Animaciones adicionales */
@keyframes slideInLeft {
    from {
        opacity: 0;
        transform: translateX(-20px);
    }
    to {
        opacity: 1;
        transform: translateX(0);
    }
}

.nav-item {
    animation: slideInLeft 0.3s ease forwards;
}

.nav-item:nth-child(1) { animation-delay: 0.1s; }
.nav-item:nth-child(2) { animation-delay: 0.2s; }
.nav-item:nth-child(3) { animation-delay: 0.3s; }
.nav-item:nth-child(4) { animation-delay: 0.4s; }
.nav-item:nth-child(5) { animation-delay: 0.5s; }
.nav-item:nth-child(6) { animation-delay: 0.6s; }
.nav-item:nth-child(7) { animation-delay: 0.7s; }
.nav-item:nth-child(8) { animation-delay: 0.8s; }
</style>

<!-- JavaScript mejorado para el funcionamiento del sidebar -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Inicialización del sidebar
    initSidebar();
    
    // Inicialización del modo oscuro
    initDarkMode();
    
    // Marcar elemento activo basado en la URL
    setActiveMenuItem();
});

function initSidebar() {
    const navItems = document.querySelectorAll('.nav-item.has-submenu');
    
    navItems.forEach(item => {
        const navLink = item.querySelector('.nav-link');
        
        navLink.addEventListener('click', function(e) {
            if (this.getAttribute('href') === '#') {
                e.preventDefault();
            }
            
            // Cerrar otros submenús
            navItems.forEach(otherItem => {
                if (otherItem !== item) {
                    otherItem.classList.remove('active');
                }
            });
            
            // Alternar el submenú actual
            item.classList.toggle('active');
        });
    });
}

function initDarkMode() {
    const darkModeButton = document.getElementById('darkModeButton');
    const darkModeText = document.getElementById('darkModeText');
    
    if (!darkModeButton || !darkModeText) return;
    
    // Verificar preferencia guardada
    const isDarkMode = localStorage.getItem('darkMode') === 'true';
    
    if (isDarkMode) {
        document.body.classList.add('dark-mode');
        darkModeText.textContent = 'Modo Claro';
        updateDarkModeIcon(true);
    }
    
    darkModeButton.addEventListener('click', function(e) {
        e.preventDefault();
        
        const isCurrentlyDark = document.body.classList.contains('dark-mode');
        
        if (isCurrentlyDark) {
            document.body.classList.remove('dark-mode');
            darkModeText.textContent = 'Modo Oscuro';
            localStorage.setItem('darkMode', 'false');
            updateDarkModeIcon(false);
        } else {
            document.body.classList.add('dark-mode');
            darkModeText.textContent = 'Modo Claro';
            localStorage.setItem('darkMode', 'true');
            updateDarkModeIcon(true);
        }
        
        // Animación suave
        document.body.style.transition = 'all 0.3s ease';
        setTimeout(() => {
            document.body.style.transition = '';
        }, 300);
    });
}

function updateDarkModeIcon(isDark) {
    const icon = document.querySelector('#darkModeButton i');
    if (icon) {
        icon.className = isDark ? 'fas fa-sun' : 'fas fa-moon';
    }
}

function setActiveMenuItem() {
    const currentPath = window.location.pathname;
    const navLinks = document.querySelectorAll('.nav-link, .submenu-link');
    
    navLinks.forEach(link => {
        const href = link.getAttribute('href');
        if (href && href !== '#' && currentPath.includes(href)) {
            // Marcar como activo
            const navItem = link.closest('.nav-item');
            if (navItem) {
                navItem.classList.add('active');
                
                // Si es un enlace de submenú, también activar el padre
                const parentNavItem = link.closest('.nav-item.has-submenu');
                if (parentNavItem) {
                    parentNavItem.classList.add('active');
                }
            }
        }
    });
}

// Función para toggle del sidebar en móviles
function toggleSidebar() {
    const sidebar = document.querySelector('.sidebar.modern-sidebar');
    const overlay = document.querySelector('.sidebar-overlay');
    
    if (sidebar) {
        sidebar.classList.toggle('show');
    }
    
    if (overlay) {
        overlay.classList.toggle('show');
    }
}

// Cerrar sidebar al hacer clic en el overlay
document.addEventListener('click', function(e) {
    if (e.target.classList.contains('sidebar-overlay')) {
        toggleSidebar();
    }
});
</script>