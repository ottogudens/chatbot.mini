<?php
require_once 'auth.php';
require_once 'db.php';
check_auth();
$is_superadmin = ($_SESSION['role'] ?? 'client') === 'superadmin';

// Look for the Internal Support Assistant
$support_assistant_id = null;
$q_support = mysqli_query($conn, "SELECT id FROM assistants WHERE name = 'Asistente de Soporte Skale IA' LIMIT 1");
if ($q_support && mysqli_num_rows($q_support) > 0) {
    $row_support = mysqli_fetch_assoc($q_support);
    $support_assistant_id = $row_support['id'];
}
?>
<script>
    const SUPPORT_ASSISTANT_ID = <?php echo json_encode($support_assistant_id); ?>;
</script>
<!DOCTYPE html>
<html lang="es" data-theme="dark">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SkaleBot - Panel Administrativo Múltiple</title>
    <!-- PWA Manifest & Theme -->
    <link rel="manifest" href="/manifest.json">
    <meta name="theme-color" content="#8b5cf6">
    <meta name="mobile-web-app-capable" content="yes">
    <!-- iOS PWA Support -->
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="SkaleBot Admin">
    <link rel="apple-touch-icon" href="/icons/apple-touch-icon.png">
    <link rel="icon" type="image/png" href="/icons/icon-192.png">
    <!-- Google Fonts: Inter -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- FontAwesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <style>
        :root {
            --bg-color: #080f1e;
            --sidebar-bg: #0c1a30;
            --glass-bg: rgba(12, 26, 48, 0.75);
            --glass-border: rgba(0, 212, 255, 0.12);
            --primary: #00d4ff;
            --primary-hover: #00b8e6;
            --danger: #ef4444;
            --success: #10b981;
            --text-main: #e8f4ff;
            --text-muted: #8ab3cc;
            --td-border: rgba(0, 212, 255, 0.06);
            --input-bg: rgba(8, 15, 30, 0.7);
            --sidebar-width: 260px;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Inter', sans-serif;
        }

        body {
            background-color: var(--bg-color);
            color: var(--text-main);
            min-height: 100vh;
            display: flex;
        }

        .glass-bg-fx {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: -1;
            background: radial-gradient(circle at 10% 40%, rgba(0, 212, 255, 0.08), transparent 30%),
                radial-gradient(circle at 85% 20%, rgba(30, 74, 122, 0.15), transparent 35%),
                radial-gradient(circle at 50% 90%, rgba(0, 100, 180, 0.06), transparent 40%);
        }

        /* ----- Sidebar ----- */
        .sidebar {
            width: var(--sidebar-width);
            background: var(--sidebar-bg);
            border-right: 1px solid var(--glass-border);
            height: 100vh;
            position: fixed;
            left: 0;
            top: 0;
            display: flex;
            flex-direction: column;
            z-index: 100;
            transition: transform 0.3s ease;
        }

        .sidebar-logo {
            padding: 18px 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            border-bottom: 1px solid var(--glass-border);
        }

        .sidebar-logo img {
            width: 36px;
            height: 36px;
            object-fit: contain;
            filter: drop-shadow(0 0 8px rgba(0, 212, 255, 0.5));
        }

        .sidebar-logo span {
            font-size: 18px;
            font-weight: 700;
            background: linear-gradient(135deg, #00d4ff, #38bdf8);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            letter-spacing: -0.3px;
        }

        .sidebar-nav {
            flex: 1;
            padding: 10px 0;
            overflow-y: auto;
        }

        .sidebar-category {
            padding: 20px 24px 8px;
            font-size: 11px;
            font-weight: 700;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 1.2px;
            opacity: 0.6;
        }

        .nav-tab {
            display: flex;
            align-items: center;
            gap: 12px;
            width: 100%;
            background: none;
            border: none;
            color: var(--text-muted);
            padding: 12px 24px;
            cursor: pointer;
            font-size: 15px;
            font-weight: 500;
            text-align: left;
            transition: all 0.2s;
        }

        .nav-tab:hover {
            background: rgba(255, 255, 255, 0.05);
            color: white;
        }

        .nav-tab.active {
            background: rgba(139, 92, 246, 0.15);
            color: var(--primary);
        }

        .sidebar-footer {
            padding: 16px;
            border-top: 1px solid var(--glass-border);
        }

        /* ----- Main Content ----- */
        .main-wrapper {
            flex: 1;
            margin-left: var(--sidebar-width);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        .top-bar {
            height: 70px;
            background: rgba(15, 23, 42, 0.8);
            backdrop-filter: blur(8px);
            border-bottom: 1px solid var(--glass-border);
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 30px;
            position: sticky;
            top: 0;
            z-index: 90;
        }

        .mobile-toggle {
            display: none;
            background: none;
            border: none;
            color: white;
            font-size: 24px;
            cursor: pointer;
        }

        .content-area {
            padding: 30px;
            max-width: 1400px;
            width: 100%;
            margin: 0 auto;
        }

        /* ----- Common Elements (Panels, Buttons, etc.) ----- */
        .dashboard-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .card {
            background: var(--glass-bg);
            backdrop-filter: blur(16px);
            border: 1px solid var(--glass-border);
            padding: 24px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .card-icon {
            width: 54px;
            height: 54px;
            border-radius: 12px;
            background: rgba(139, 92, 246, 0.1);
            color: var(--primary);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 26px;
        }

        .card-info h3 {
            font-size: 26px;
            margin-bottom: 4px;
        }

        .card-info p {
            color: var(--text-muted);
            font-size: 14px;
        }

        .btn {
            background: var(--primary);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 500;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.2s;
            font-size: 14px;
        }

        .btn:hover {
            background: var(--primary-hover);
            transform: translateY(-1px);
        }

        .btn-danger {
            background: var(--danger);
        }

        .btn-danger:hover {
            background: #dc2626;
        }

        .btn-outline {
            background: transparent;
            border: 1px solid var(--primary);
        }

        .btn-outline:hover {
            background: rgba(139, 92, 246, 0.1);
        }

        .panel {
            background: var(--glass-bg);
            backdrop-filter: blur(16px);
            border: 1px solid var(--glass-border);
            border-radius: 12px;
            padding: 24px;
            margin-bottom: 30px;
        }

        .panel-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
            gap: 15px;
            flex-wrap: wrap;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 14px;
        }

        th,
        td {
            text-align: left;
            padding: 14px 16px;
            border-bottom: 1px solid var(--td-border);
        }

        th {
            color: var(--text-muted);
            font-weight: 600;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
            background: rgba(139, 92, 246, 0.15);
            color: #c4b5fd;
            text-transform: uppercase;
        }

        .field-tag-container {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-top: 10px;
            padding: 12px;
            background: rgba(0, 0, 0, 0.2);
            border-radius: 10px;
            border: 1px dashed var(--glass-border);
            min-height: 50px;
        }

        .field-tag {
            background: var(--primary);
            color: white;
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 12px;
            display: flex;
            align-items: center;
            gap: 8px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            animation: fadeIn 0.3s ease;
        }

        .field-tag i {
            cursor: pointer;
            font-size: 10px;
            padding: 4px;
            border-radius: 50%;
            background: rgba(0, 0, 0, 0.1);
            transition: background 0.2s;
        }

        .field-tag i:hover {
            background: rgba(255, 255, 255, 0.2);
        }

        .badge.failed {
            background: rgba(239, 68, 68, 0.15);
            color: #fca5a5;
        }

        .badge.success {
            background: rgba(16, 185, 129, 0.15);
            color: #6ee7b7;
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
            animation: fadeIn 0.3s ease;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(5px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Modals */
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.7);
            backdrop-filter: blur(6px);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 2000;
            opacity: 0;
            pointer-events: none;
            transition: all 0.3s;
        }

        .modal-overlay.active {
            opacity: 1;
            pointer-events: auto;
        }

        .modal {
            background: #1e293b;
            border: 1px solid var(--glass-border);
            width: 650px;
            max-width: 95%;
            max-height: 90vh;
            overflow-y: auto;
            border-radius: 16px;
            padding: 30px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: var(--text-muted);
            font-size: 14px;
            font-weight: 500;
        }

        .form-group input,
        .form-group textarea,
        .form-group select {
            width: 100%;
            background: var(--input-bg);
            border: 1px solid var(--glass-border);
            color: white;
            padding: 12px 16px;
            border-radius: 8px;
            outline: none;
            transition: border-color 0.2s;
        }

        .form-group input:focus,
        .form-group textarea:focus,
        .form-group select:focus {
            border-color: var(--primary);
        }

        /* Responsive Mobile */
        @media (max-width: 1024px) {
            .sidebar {
                transform: translateX(-100%);
                width: 280px;
            }

            .sidebar.active {
                transform: translateX(0);
                box-shadow: 20px 0 50px rgba(0, 0, 0, 0.5);
            }

            .sidebar-overlay {
                display: none;
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: rgba(0, 0, 0, 0.5);
                backdrop-filter: blur(4px);
                z-index: 95;
            }

            .sidebar-overlay.active {
                display: block;
            }

            .main-wrapper {
                margin-left: 0;
            }

            .mobile-toggle {
                display: block;
            }

            .content-area {
                padding: 15px;
            }

            .top-bar {
                padding: 0 15px;
                gap: 10px;
            }

            /* Top Bar Content Optimization */
            .global-selector label {
                display: none;
            }

            /* Hide label on mobile */
            .global-selector select {
                max-width: 140px;
            }

            .header-actions #btn-chat-link span {
                display: none;
            }

            /* Could hide text, but we'll stick to full icon btn for now */
        }

        @media (max-width: 480px) {
            .top-bar {
                flex-wrap: nowrap;
                height: 60px;
            }

            .dashboard-cards {
                grid-template-columns: 1fr;
            }

            .panel-header {
                flex-direction: column;
                align-items: stretch;
                gap: 12px;
            }

            .btn {
                width: 100%;
                justify-content: center;
            }

            .header-actions {
                display: none;
            }

            /* Hide extra chat link on very small screens to save space */
        }

        /* Utilities */
        .sidebar-overlay {
            transition: opacity 0.3s ease;
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
            animation: fadeIn 0.3s ease;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(10px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
    </style>
</head>

<body>
    <div class="glass-bg-fx"></div>

    <!-- SIDEBAR -->
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-logo">
            <img src="icons/logo-skale.png" alt="Skale" onerror="this.style.display='none'">
            <span>Skale Admin</span>
        </div>

        <nav class="sidebar-nav">
            <div class="sidebar-category">Gestión</div>
            <button class="nav-tab active" data-target="dashboard-tab"><i class="fa-solid fa-chart-line"></i>
                Resumen</button>
            <?php if ($is_superadmin): ?>
                <button class="nav-tab" data-target="clients-tab"><i class="fa-solid fa-building"></i> Clientes</button>
                <button class="nav-tab" data-target="users-tab"><i class="fa-solid fa-users"></i> Usuarios</button>
            <?php endif; ?>
            <button class="nav-tab" data-target="assistants-tab"><i class="fa-solid fa-robot"></i> Asistentes</button>

            <div class="sidebar-category">Inteligencia</div>
            <button class="nav-tab" data-target="info-tab"><i class="fa-solid fa-database"></i> Conocimiento</button>
            <button class="nav-tab" data-target="rules-tab"><i class="fa-solid fa-book"></i> Reglas Q&A</button>
            <button class="nav-tab" data-target="pdf-templates-tab"><i class="fa-solid fa-file-pdf"></i> Plantillas Canvas</button>

            <div class="sidebar-category">Marketing</div>
            <button class="nav-tab" data-target="leads-tab"><i class="fa-solid fa-address-book"></i> Prospectos</button>
            <button class="nav-tab" data-target="campaigns-tab"><i class="fa-solid fa-bullhorn"></i> Campañas <span class="badge" style="background:var(--primary); color:#000; font-size:9px; padding:2px 5px; margin-left:5px;">NUEVO</span></button>

            <div class="sidebar-category">Operaciones</div>
            <button class="nav-tab" data-target="integrations-tab"><i class="fa-brands fa-google-drive"></i> Integraciones</button>
            <button class="nav-tab" data-target="appointments-tab"><i class="fa-regular fa-calendar-check"></i> Reservas</button>
            <button class="nav-tab" data-target="logs-tab"><i class="fa-solid fa-list"></i> Historial Chats</button>
            <button class="nav-tab" data-target="pdf-generated-tab"><i class="fa-solid fa-file-invoice"></i> Documentos Gen.</button>

            <div class="sidebar-category">Soporte</div>
            <button class="nav-tab" data-target="help-tab" style="color:#60a5fa;"><i class="fa-solid fa-circle-info"></i> Centro de Ayuda</button>
            <button class="nav-tab" onclick="openSupportChat()" style="color:#10b981; margin-top:5px;">
                <i class="fa-solid fa-robot"></i> <b>Manual Virtual</b>
            </button>
        </nav>

        <div class="sidebar-footer">
            <a href="auth.php?action=logout" class="btn btn-danger" style="width:100%; justify-content:center;">
                <i class="fa-solid fa-right-from-bracket"></i> Salir
            </a>
        </div>
    </aside>
    <div class="sidebar-overlay" id="sidebar-overlay"></div>

    <!-- MAIN CONTENT -->
    <main class="main-wrapper">
        <header class="top-bar">
            <button class="mobile-toggle" id="mobile-toggle">
                <i class="fa-solid fa-bars"></i>
            </button>

            <div class="global-selector" style="display:flex; align-items:center; gap:12px;">
                <label for="global-assistant-select" style="font-size:13px; color:var(--text-muted); font-weight:500;">
                    <i class="fa-solid fa-headset"></i> Asistente Activo:
                </label>
                <select id="global-assistant-select"
                    style="background:var(--input-bg); border:1px solid var(--glass-border); color:white; padding:6px 12px; border-radius:6px; font-size:13px; outline:none;">
                    <option value="">Global (Todos)</option>
                    <!-- Populated by JS -->
                </select>
                <button class="btn btn-outline" style="padding: 6px 10px; height:32px;" onclick="copyChatLink()"
                    title="Copiar Link del Chat">
                    <i class="fa-solid fa-link"></i>
                </button>
            </div>
            <div class="header-actions">
                <a href="index.php" class="btn" id="btn-chat-link"
                    style="background:rgba(139, 92, 246, 0.1); color:var(--primary); border:1px solid var(--primary);">
                    <i class="fa-solid fa-comment-dots"></i> <span>Ir al Chat</span>
                </a>
            </div>
        </header>

        <div class="content-area">

            <!-- DASHBOARD TAB -->
            <div id="dashboard-tab" class="tab-content active">
                <div class="dashboard-cards" id="stats-container">
                    <div class="card">
                        <div class="card-icon"><i class="fa-solid fa-spinner fa-spin"></i></div>
                        <div class="card-info">
                            <h3>...</h3>
                            <p>Cargando stats...</p>
                        </div>
                    </div>
                </div>
                <div class="panel" style="padding: 20px; border:none; background:rgba(0,0,0,0.2); margin-top: 20px;">
                    <h3
                        style="margin-bottom: 20px; font-size: 14px; color: var(--text-muted); text-transform: uppercase;">
                        <i class="fa-solid fa-chart-line"></i> Actividad últimos 7 días
                    </h3>
                    <div style="height: 300px;">
                        <canvas id="activityChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- CLIENTS TAB -->
            <?php if ($is_superadmin): ?>
                <div id="clients-tab" class="tab-content">
                    <div class="panel-header">
                        <h2>Gestión de Clientes</h2>
                        <button class="btn" onclick="openClientModal()"><i class="fa-solid fa-plus"></i> Nuevo
                            Cliente</button>
                    </div>
                    <div class="panel">
                        <div style="overflow-x: auto;">
                            <table id="clients-table">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Nombre</th>
                                        <th>Tipo</th>
                                        <th>Contacto</th>
                                        <th>Creado el</th>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody></tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- USERS TAB -->
                <div id="users-tab" class="tab-content">
                    <div class="panel-header">
                        <h2>Usuarios de la Aplicación</h2>
                        <button class="btn" onclick="openUserModal()"><i class="fa-solid fa-user-plus"></i> Nuevo
                            Usuario</button>
                    </div>
                    <div class="panel">
                        <div style="overflow-x: auto;">
                            <table id="users-table">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Usuario</th>
                                        <th>Rol</th>
                                        <th>Cliente</th>
                                        <th>Creado el</th>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody></tbody>
                            </table>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- ASSISTANTS TAB -->
            <div id="assistants-tab" class="tab-content">
                <div class="panel-header">
                    <h2>Asistentes</h2>
                    <button class="btn" onclick="openAssistantModal()"><i class="fa-solid fa-plus"></i> Nuevo
                        Asistente</button>
                </div>
                <div class="panel">
                    <div style="overflow-x: auto;">
                        <table id="assistants-table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Nombre</th>
                                    <th>Cliente</th>
                                    <th>Prompt</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- INFO TAB -->
            <div id="info-tab" class="tab-content">
                <div class="panel-header">
                    <h2>Fuentes de Información de Gemini</h2>
                    <button class="btn" onclick="openInfoModal()"><i class="fa-solid fa-cloud-arrow-up"></i> Nueva
                        Fuente</button>
                </div>
                <div class="panel">
                    <div style="overflow-x: auto;">
                        <table id="info-table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Título / Archivo</th>
                                    <th>Contenido</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- RULES TAB -->
            <div id="rules-tab" class="tab-content">
                <div class="panel-header">
                    <h2>Reglas de Contexto y Respuestas</h2>
                    <button class="btn" onclick="openRuleModal()"><i class="fa-solid fa-plus"></i> Nueva Regla</button>
                </div>
                <div class="panel">
                    <div style="overflow-x: auto;">
                        <table id="rules-table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Categoría</th>
                                    <th>Consultas</th>
                                    <th>Respuesta</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- LEADS TAB -->
            <div id="leads-tab" class="tab-content">
                <div class="panel-header">
                    <h2>Base de Datos de Prospectos</h2>
                    <div style="display:flex; gap:10px;">
                        <button class="btn btn-outline" onclick="exportLeads()"><i class="fa-solid fa-download"></i>
                            Exportar CSV</button>
                        <button class="btn" onclick="openLeadModal()"><i class="fa-solid fa-plus"></i> Nuevo
                            Prospecto</button>
                    </div>
                </div>
                <div class="panel">
                    <div style="overflow-x: auto;">
                        <table id="leads-table">
                            <thead>
                                <tr>
                                    <th style="width:40px;"><input type="checkbox" id="select-all-leads" onclick="toggleSelectAllLeads(this.checked)"></th>
                                    <th>ID</th>
                                    <th>Asistente</th>
                                    <th>Nombre</th>
                                    <th>Contacto</th>
                                    <th>Estado</th>
                                    <th>Notas</th>
                                    <th>Fecha</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- CAMPAIGNS TAB -->
            <div id="campaigns-tab" class="tab-content">
                <div class="panel-header">
                    <h2>Campañas de Marketing</h2>
                    <button class="btn" onclick="openCampaignModal()"><i class="fa-solid fa-bullhorn"></i> Nueva Campaña</button>
                </div>
                <div class="panel">
                    <div style="overflow-x: auto;">
                        <table id="campaigns-table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Nombre de Campaña</th>
                                    <th>Público Objetivo</th>
                                    <th>Estado</th>
                                    <th>Fecha Envío</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- INTEGRATIONS TAB -->
            <div id="integrations-tab" class="tab-content">
                <div class="panel-header">
                    <h2>Integraciones (WhatsApp, Drive & Calendar)</h2>
                </div>

                <!-- WHATSAPP -->
                <div class="panel" style="border:none; background:rgba(0,0,0,0.2); margin-bottom: 20px;">
                    <div
                        style="display:flex; justify-content:space-between; align-items:flex-start; flex-wrap:wrap; gap:15px;">
                        <div style="flex:1; min-width:280px;">
                            <h3 style="margin-bottom: 10px;"><i class="fa-brands fa-whatsapp"
                                    style="color:#25D366;"></i>
                                Vinculación con WhatsApp</h3>
                            <p style="color:var(--text-muted); font-size:13px; margin-bottom:15px; margin-top:5px;">
                                Vincula este
                                asistente a un número de WhatsApp escaneando el código QR.</p>
                        </div>

                        <!-- Voice Message Toggle in Integrations Panel -->
                        <div id="voice-settings-panel"
                            style="background:rgba(255,255,255,0.05); padding:15px; border-radius:10px; border:1px solid var(--glass-border); min-width:250px;">
                            <h4 style="font-size:13px; margin-bottom:10px; color:var(--primary);"><i
                                    class="fa-solid fa-microphone"></i> Mensajes de Voz</h4>
                            <div style="display:flex; align-items:center; gap:10px;">
                                <label class="switch">
                                    <input type="checkbox" id="integration-voice-toggle"
                                        onchange="toggleVoiceFromIntegration(this.checked)">
                                    <span class="slider round"></span>
                                </label>
                                <span id="voice-status-text" style="font-size:12px;">Habilitados</span>
                            </div>
                            <p style="font-size:11px; color:var(--text-muted); margin-top:8px;">Si se deshabilita, el
                                bot solo responderá a mensajes de texto.</p>
                        </div>
                    </div>

                    <div id="whatsapp-container" style="margin-top:20px;">
                        <div id="whatsapp-status-display" style="margin-bottom: 15px;">
                            <i class="fa-solid fa-spinner fa-spin"></i> Cargando estado...
                        </div>

                        <div id="whatsapp-qr-container"
                            style="display:none; text-align:center; background:white; padding:20px; border-radius:12px; width:fit-content; margin:0 auto 15px;">
                            <img id="whatsapp-qr-img" src="" alt="QR Code" style="display:block; max-width:250px;">
                            <p style="color:#333; font-size:12px; margin-top:10px;">Escanea este código con WhatsApp</p>
                        </div>

                        <div id="whatsapp-actions">
                            <button id="btn-whatsapp-connect" class="btn" style="background:#25D366;"><i
                                    class="fa-solid fa-link"></i> Vincular WhatsApp</button>
                            <button id="btn-whatsapp-disconnect" class="btn btn-danger" style="display:none;"><i
                                    class="fa-solid fa-link-slash"></i> Desvincular</button>
                        </div>
                    </div>
                </div>

                <!-- DRIVE -->
                <div class="panel" style="border:none; background:rgba(0,0,0,0.2); margin-bottom: 20px;">
                    <div id="drive-status-container" style="margin-bottom: 2rem;">
                        <i class="fa-solid fa-spinner fa-spin"></i> Cargando estado de conexión...
                    </div>

                    <div id="drive-files-container" style="display:none;">
                        <h3>Tus Archivos en Drive</h3>
                        <div id="drive-breadcrumb" style="margin: 10px 0; font-size: 14px; color: var(--primary);">
                            <span style="cursor:pointer; text-decoration:underline;" onclick="loadDriveFiles('root')">Mi
                                Unidad</span>
                        </div>
                        <p style="color:var(--text-muted); font-size:13px; margin-bottom:15px; margin-top:5px;">
                            Selecciona archivos o entra en carpetas. Los archivos se sincronizarán como Fuentes de
                            Información.</p>
                        <div style="overflow-x: auto;">
                            <table id="drive-files-table">
                                <thead>
                                    <tr>
                                        <th>Nombre</th>
                                        <th>Modificación</th>
                                        <th>Acción</th>
                                    </tr>
                                </thead>
                                <tbody></tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- CALENDAR -->
                <div class="panel" style="border:none; background:rgba(0,0,0,0.2);" id="calendar-settings-container">
                    <h3 style="margin-bottom: 10px;"><i class="fa-regular fa-calendar-check" style="color:#f59e0b;"></i>
                        Configuración de Google Calendar</h3>
                    <p style="color:var(--text-muted); font-size:13px; margin-bottom:15px; margin-top:5px;">Define tu
                        disponibilidad para que el Asistente pueda agendar citas automáticamente en tu calendario de
                        Google vinculado.</p>
                    <form id="calendar-settings-form" onsubmit="submitCalendarSettings(event)">
                        <input type="hidden" name="client_id" id="cal-client-id">
                        <div class="form-group" style="margin-bottom: 20px;">
                            <label>Calendario Específico</label>
                            <div style="display:flex; gap:10px; margin-top:5px;">
                                <select name="calendar_id" id="cal-id" style="flex:1;">
                                    <option value="primary">Calendario Principal (Default)</option>
                                </select>
                                <button type="button" class="btn btn-outline btn-sm" onclick="createNewCalendar()">+
                                    Crear Nuevo</button>
                            </div>
                            <p style="font-size:12px; color:var(--text-muted); margin-top:5px;">Selecciona el calendario
                                donde se guardarán las citas del asistente.</p>
                        </div>

                        <div class="form-group">
                            <label>Días de Atención (Selecciona los días disponibles)</label>
                            <div style="display:flex; gap:15px; flex-wrap:wrap; margin-top: 5px;">
                                <label><input type="checkbox" name="available_days[]" value="1"> Lunes</label>
                                <label><input type="checkbox" name="available_days[]" value="2"> Martes</label>
                                <label><input type="checkbox" name="available_days[]" value="3"> Miércoles</label>
                                <label><input type="checkbox" name="available_days[]" value="4"> Jueves</label>
                                <label><input type="checkbox" name="available_days[]" value="5"> Viernes</label>
                                <label><input type="checkbox" name="available_days[]" value="6"> Sábado</label>
                                <label><input type="checkbox" name="available_days[]" value="0"> Domingo</label>
                            </div>
                        </div>
                        <div style="display:flex; gap:20px;">
                            <div class="form-group" style="flex:1;">
                                <label>Hora Inicio</label>
                                <input type="time" name="start_time" id="cal-start-time" required>
                            </div>
                            <div class="form-group" style="flex:1;">
                                <label>Hora Fin</label>
                                <input type="time" name="end_time" id="cal-end-time" required>
                            </div>
                        </div>
                        <div style="display:flex; gap:20px;">
                            <div class="form-group" style="flex:1;">
                                <label>Duración de Cita (minutos)</label>
                                <input type="number" name="slot_duration_minutes" id="cal-duration" placeholder="30"
                                    required>
                            </div>
                            <div class="form-group" style="flex:1;">
                                <label>Zona Horaria</label>
                                <input type="text" name="timezone" id="cal-timezone" placeholder="America/Santiago"
                                    value="America/Santiago" required>
                            </div>
                        </div>
                        <div style="margin-top:20px;">
                            <button type="submit" class="btn" id="btn-save-calendar"><i class="fa-solid fa-save"></i>
                                Guardar Horario</button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- APPOINTMENTS TAB -->
            <div id="appointments-tab" class="tab-content">
                <div class="panel-header">
                    <h2><i class="fa-regular fa-calendar-check" style="color:#f59e0b;"></i> Reservas del Asistente</h2>
                    <button class="btn btn-outline" onclick="loadAppointments()"><i class="fa-solid fa-rotate"></i>
                        Actualizar</button>
                </div>
                <div class="panel">
                    <p style="color:var(--text-muted); font-size:13px; margin-bottom:15px;">Reservas creadas por el
                        asistente en Google Calendar. Puedes cancelarlas desde aquí.</p>
                    <div style="overflow-x: auto;">
                        <table id="appointments-table">
                            <thead>
                                <tr>
                                    <th>Fecha</th>
                                    <th>Hora</th>
                                    <th>Nombre</th>
                                    <th>Email</th>
                                    <th>Teléfono</th>
                                    <th>Asistente</th>
                                    <th>Estado</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- LOGS TAB -->
            <div id="logs-tab" class="tab-content">
                <div class="panel-header">
                    <h2>Últimas Interacciones</h2>
                    <button class="btn btn-outline" onclick="loadLogs()"><i class="fa-solid fa-rotate"></i>
                        Actualizar</button>
                </div>
                <div class="panel">
                    <div style="overflow-x: auto;">
                        <table id="logs-table">
                            <thead>
                                <tr>
                                    <th>Fecha</th>
                                    <th>Usuario</th>
                                    <th>Bot</th>
                                    <th>Estado</th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- PDF TEMPLATES TAB -->
            <!-- PDF TEMPLATES TAB -->
            <div id="pdf-templates-tab" class="tab-content">
                <style>
                    .tpl-grid {
                        display: grid;
                        grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
                        gap: 18px;
                        margin-top: 20px;
                    }
                    .tpl-card {
                        background: var(--glass-bg);
                        border: 1px solid var(--glass-border);
                        border-radius: 14px;
                        padding: 20px;
                        display: flex;
                        flex-direction: column;
                        gap: 12px;
                        transition: border-color 0.2s, transform 0.2s;
                        position: relative;
                    }
                    .tpl-card:hover {
                        border-color: var(--primary);
                        transform: translateY(-2px);
                    }
                    .tpl-card-header {
                        display: flex;
                        align-items: flex-start;
                        gap: 14px;
                    }
                    .tpl-icon {
                        width: 44px;
                        height: 44px;
                        border-radius: 10px;
                        display: flex;
                        align-items: center;
                        justify-content: center;
                        font-size: 20px;
                        flex-shrink: 0;
                    }
                    .tpl-icon.pdf  { background: rgba(239,68,68,0.15);  color:#f87171; }
                    .tpl-icon.txt  { background: rgba(59,130,246,0.15);  color:#60a5fa; }
                    .tpl-icon.html { background: rgba(245,158,11,0.15);  color:#fbbf24; }
                    .tpl-icon.sys  { background: rgba(99,102,241,0.15);  color:#818cf8; }
                    .tpl-meta { flex: 1; min-width: 0; }
                    .tpl-meta h4 {
                        font-size: 15px;
                        font-weight: 600;
                        white-space: nowrap;
                        overflow: hidden;
                        text-overflow: ellipsis;
                        margin-bottom: 4px;
                    }
                    .tpl-meta .tpl-desc {
                        font-size: 12px;
                        color: var(--text-muted);
                        display: -webkit-box;
                        -webkit-line-clamp: 2;
                        -webkit-box-orient: vertical;
                        overflow: hidden;
                    }
                    .tpl-fields {
                        display: flex;
                        flex-wrap: wrap;
                        gap: 6px;
                    }
                    .tpl-field-tag {
                        background: rgba(139,92,246,0.12);
                        border: 1px solid rgba(139,92,246,0.3);
                        color: #c4b5fd;
                        font-size: 11px;
                        padding: 3px 8px;
                        border-radius: 20px;
                        font-family: monospace;
                    }
                    .tpl-field-tag.more {
                        background: rgba(255,255,255,0.05);
                        border-color: rgba(255,255,255,0.1);
                        color: var(--text-muted);
                    }
                    .tpl-actions {
                        display: flex;
                        gap: 8px;
                        margin-top: auto;
                        padding-top: 8px;
                        border-top: 1px solid var(--glass-border);
                    }
                    .tpl-actions .btn { padding: 7px 12px; font-size: 12px; }
                    .tpl-empty {
                        grid-column: 1 / -1;
                        text-align: center;
                        padding: 60px 20px;
                        color: var(--text-muted);
                    }
                    .tpl-empty i { font-size: 48px; margin-bottom: 16px; opacity: 0.3; display: block; }
                    .templates-toolbar {
                        display: flex;
                        gap: 12px;
                        align-items: center;
                        flex-wrap: wrap;
                    }
                    .templates-toolbar input, .templates-toolbar select {
                        background: var(--input-bg);
                        border: 1px solid var(--glass-border);
                        color: white;
                        padding: 9px 14px;
                        border-radius: 8px;
                        outline: none;
                        font-size: 13px;
                        transition: border-color 0.2s;
                    }
                    .templates-toolbar input:focus, .templates-toolbar select:focus {
                        border-color: var(--primary);
                    }
                    .tpl-source-badge {
                        position: absolute;
                        top: 12px;
                        right: 12px;
                        font-size: 10px;
                        padding: 2px 8px;
                        border-radius: 12px;
                    }
                    @media(max-width:600px) {
                        .tpl-grid { grid-template-columns: 1fr; }
                        .templates-toolbar { flex-direction: column; align-items: stretch; }
                    }
                </style>

                <div class="panel-header">
                    <h2><i class="fa-solid fa-file-pdf" style="color:var(--primary);"></i> Plantillas PDF</h2>
                    <div style="display:flex;gap:8px;flex-wrap:wrap;">
                        <button class="btn" style="background:linear-gradient(135deg,var(--primary),#7c3aed);" onclick="openCanvasEditor(null)">
                            <i class="fa-solid fa-pen-ruler"></i> Diseñar en Canvas
                        </button>
                        <button class="btn btn-outline" onclick="openPDFTemplateModal()"><i class="fa-solid fa-upload"></i> Subir Plantilla</button>
                    </div>
                </div>

                <!-- Toolbar -->
                <div class="panel" style="padding:16px 20px; margin-bottom:0;">
                    <div class="templates-toolbar">
                        <input type="text" id="tpl-search" placeholder="&#xf002; Buscar plantilla..." oninput="filterTemplates()" style="flex:1; min-width:180px;">
                        <select id="tpl-filter-type" onchange="filterTemplates()">
                            <option value="">Todos los tipos</option>
                            <option value="pdf">PDF</option>
                            <option value="txt">Texto (.txt)</option>
                            <option value="html">HTML</option>
                        </select>
                        <select id="tpl-filter-source" onchange="filterTemplates()">
                            <option value="">Todas las fuentes</option>
                            <option value="canvas">Canvas (diseñadas)</option>
                            <option value="db">Personalizadas</option>
                            <option value="static">Sistema</option>
                        </select>
                        <button class="btn btn-outline" onclick="loadPDFTemplates()" title="Actualizar"><i class="fa-solid fa-rotate"></i></button>
                    </div>
                </div>

                <!-- Grid -->
                <div class="tpl-grid" id="tpl-grid"></div>

                <!-- Info footer -->
                <div style="margin-top:16px; font-size:12px; color:var(--text-muted);">
                    <i class="fa-solid fa-circle-info"></i>
                    Sube archivos <b>.pdf</b> para que Gemini los complete con IA, o <b>.txt/.html</b> con marcadores <code>{{campo}}</code> para sustitución directa.
                    <span id="tpl-count" style="float:right;"></span>
                </div>
            </div>

            <!-- CANVAS TEMPLATE EDITOR MODAL -->
            <div id="canvas-editor-modal" style="display:none;position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,0.7);overflow-y:auto;">
              <style>
                #canvas-editor-modal .ce-wrap{max-width:100%;margin:0;background:var(--bg-secondary,#1e1e2e);border-radius:0;overflow:hidden;display:flex;flex-direction:column;height:100vh;}
                #canvas-editor-modal .ce-topbar{background:var(--primary,#6366f1);padding:18px 28px;display:flex;align-items:center;justify-content:space-between;}
                #canvas-editor-modal .ce-topbar h2{color:#fff;font-size:18px;margin:0;}
                #canvas-editor-modal .ce-tabs{display:flex;border-bottom:1px solid rgba(255,255,255,0.1);background:rgba(0,0,0,0.2);}
                #canvas-editor-modal .ce-tab{padding:12px 20px;cursor:pointer;font-size:13px;color:rgba(255,255,255,0.6);border-bottom:3px solid transparent;transition:all .2s;}
                #canvas-editor-modal .ce-tab.active{color:#fff;border-bottom-color:var(--primary,#6366f1);font-weight:600;}
                #canvas-editor-modal .ce-body{flex:1;padding:24px 28px;overflow-y:auto;}
                #canvas-editor-modal .ce-section{display:none;} #canvas-editor-modal .ce-section.active{display:block;}
                #canvas-editor-modal .ce-label{font-size:12px;color:rgba(255,255,255,0.6);margin-bottom:5px;text-transform:uppercase;letter-spacing:0.05em;}
                #canvas-editor-modal .ce-row{display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:16px;}
                #canvas-editor-modal .ce-row.full{grid-template-columns:1fr;}
                #canvas-editor-modal .ce-field{display:flex;flex-direction:column;gap:5px;}
                #canvas-editor-modal .ce-field input,#canvas-editor-modal .ce-field select,#canvas-editor-modal .ce-field textarea{background:rgba(255,255,255,0.07);border:1px solid rgba(255,255,255,0.15);border-radius:8px;color:#fff;padding:9px 13px;font-size:13px;width:100%;}
                #canvas-editor-modal .ce-field textarea{resize:vertical;min-height:80px;}
                #canvas-editor-modal .section-block{background:rgba(255,255,255,0.06);border:1px solid rgba(255,255,255,0.12);border-radius:12px;padding:16px;margin-bottom:12px;cursor:grab;position:relative;}
                #canvas-editor-modal .section-block .sb-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:10px;}
                #canvas-editor-modal .section-block .sb-title{font-size:14px;font-weight:600;color:#fff;}
                #canvas-editor-modal .section-block .sb-icon{font-size:18px;margin-right:10px;color:var(--primary,#6366f1);}
                #canvas-editor-modal .section-adder{display:flex;gap:8px;flex-wrap:wrap;margin-top:8px;}
                #canvas-editor-modal .sa-pill{background:rgba(99,102,241,0.15);border:1px dashed rgba(99,102,241,0.4);padding:7px 14px;border-radius:20px;cursor:pointer;font-size:12px;color:rgba(255,255,255,0.7);transition:all .2s;}
                #canvas-editor-modal .sa-pill:hover{background:rgba(99,102,241,0.35);color:#fff;}
                #canvas-editor-modal .field-row{display:flex;gap:8px;align-items:center;background:rgba(255,255,255,0.05);border-radius:8px;padding:10px 14px;margin-bottom:8px;}
                #canvas-editor-modal .field-row input{flex:1;background:transparent;border:none;color:#fff;font-size:13px;outline:none;}
                #canvas-editor-modal .field-row select{background:rgba(255,255,255,0.07);border:1px solid rgba(255,255,255,0.12);border-radius:6px;color:#fff;padding:4px 8px;font-size:12px;}
                #canvas-editor-modal .ce-footer{padding:16px 28px;border-top:1px solid rgba(255,255,255,0.1);display:flex;justify-content:space-between;align-items:center;background:rgba(0,0,0,0.2);}
                #canvas-editor-modal .color-row{display:flex;gap:12px;align-items:center;}
                #canvas-editor-modal .color-swatch{display:flex;flex-direction:column;gap:5px;}
                #canvas-editor-modal .color-swatch input[type=color]{width:60px;height:40px;border:none;border-radius:8px;cursor:pointer;background:none;padding:2px;}
                #canvas-editor-modal .logo-preview{width:80px;height:80px;object-fit:contain;border-radius:8px;border:1px solid rgba(255,255,255,0.15);margin-top:8px;display:none;}
                #canvas-editor-modal .checklist-item{display:flex;gap:8px;align-items:center;background:rgba(255,255,255,0.05);border-radius:8px;padding:8px 12px;margin-bottom:6px;}
                #canvas-editor-modal .checklist-item input{flex:1;background:transparent;border:none;color:#fff;outline:none;font-size:13px;}
                #canvas-editor-modal .preview-frame{width:100%;height:calc(100vh - 200px);border:none;border-radius:12px;background:#fff;}
              </style>
              <div class="ce-wrap">
                <!-- Topbar -->
                <div class="ce-topbar">
                  <h2><i class="fa-solid fa-pen-ruler"></i> Editor de Plantilla Canvas</h2>
                  <button onclick="closeCanvasEditor()" style="background:rgba(255,255,255,0.15);border:none;color:#fff;padding:8px 18px;border-radius:8px;cursor:pointer;"><i class="fa-solid fa-xmark"></i> Cerrar</button>
                </div>
                <!-- Tabs -->
                <div class="ce-tabs">
                  <div class="ce-tab active" onclick="ceSwitchTab(0)"><i class="fa-solid fa-sliders"></i> Configuración</div>
                  <div class="ce-tab" onclick="ceSwitchTab(1)"><i class="fa-solid fa-palette"></i> Diseño</div>
                  <div class="ce-tab" onclick="ceSwitchTab(2)"><i class="fa-solid fa-building"></i> Empresa</div>
                  <div class="ce-tab" onclick="ceSwitchTab(3)"><i class="fa-solid fa-layer-group"></i> Secciones</div>
                  <div class="ce-tab" onclick="ceSwitchTab(4)"><i class="fa-solid fa-tags"></i> Campos</div>
                  <div class="ce-tab" onclick="ceSwitchTab(5)"><i class="fa-solid fa-eye"></i> Vista Previa</div>
                </div>
                <!-- Body -->
                <div class="ce-body">
                  <!-- TAB 0: Config -->
                  <div class="ce-section active" id="ce-s0">
                    <h3 style="color:var(--primary);margin-bottom:20px;">Configuración Básica</h3>
                    <div class="ce-row">
                      <div class="ce-field"><div class="ce-label">Nombre de la plantilla *</div><input id="ce-name" type="text" placeholder="Ej: Presupuesto Taller XYZ"></div>
                      <div class="ce-field"><div class="ce-label">Tipo de documento</div>
                        <select id="ce-doctype">
                          <option value="budget">Presupuesto</option>
                          <option value="receipt">Recibo / Factura</option>
                          <option value="vehicle_inspection">Inspección Vehicular</option>
                          <option value="vehicle_diagnostic">Diagnóstico Vehicular</option>
                          <option value="generic">Genérico</option>
                        </select>
                      </div>
                    </div>
                    <?php if ($is_superadmin): ?>
                    <div class="ce-row" id="ce-client-row">
                      <div class="ce-field"><div class="ce-label">Cliente asignado *</div>
                        <select id="ce-client-id">
                          <option value="">Selecciona un cliente...</option>
                        </select>
                      </div>
                    </div>
                    <?php endif; ?>
                    <div class="ce-row full">
                      <div class="ce-field"><div class="ce-label">Descripción (guía para la IA)</div><textarea id="ce-desc" placeholder="Describe cuándo y cómo el asistente debe usar esta plantilla..."></textarea></div>
                    </div>
                    <div style="background:rgba(99,102,241,0.1);border:1px solid rgba(99,102,241,0.3);border-radius:10px;padding:14px;margin-top:8px;font-size:13px;color:rgba(255,255,255,0.7);">
                      <i class="fa-solid fa-robot" style="color:var(--primary);"></i>
                      <strong style="color:#fff;"> Tip para la IA:</strong> La descripción y los campos definidos son la instrucción que recibe el asistente. Cuanto más específica sea, mejor sabrá cuándo generar este documento.
                    </div>
                  </div>
                  <!-- TAB 1: Design -->
                  <div class="ce-section" id="ce-s1">
                    <h3 style="color:var(--primary);margin-bottom:20px;">Diseño Visual</h3>
                    <div class="color-row" style="flex-wrap:wrap;gap:20px;">
                      <div class="color-swatch"><div class="ce-label">Color Principal</div><input type="color" id="ce-color-primary" value="#1a3a5c"></div>
                      <div class="color-swatch"><div class="ce-label">Color de Acento</div><input type="color" id="ce-color-accent" value="#f0a500"></div>
                    </div>
                    <div class="ce-row" style="margin-top:20px;">
                      <div class="ce-field"><div class="ce-label">Fuente</div>
                        <select id="ce-font">
                          <option value="Arial">Arial (moderna)</option>
                          <option value="Helvetica">Helvetica (limpia)</option>
                          <option value="Times">Times New Roman (clásica)</option>
                          <option value="Courier">Courier (técnica)</option>
                        </select>
                      </div>
                    </div>
                    <div style="margin-top:20px;">
                      <div class="ce-label">Vista previa de colores</div>
                      <div id="ce-color-preview" style="margin-top:10px;border-radius:10px;overflow:hidden;background:#fff;">
                        <div id="ce-color-bar" style="height:40px;display:flex;align-items:center;padding:0 20px;"><span id="ce-color-title" style="color:#fff;font-weight:bold;font-size:14px;">DOCUMENTO EJEMPLO</span></div>
                        <div style="padding:12px 20px;display:flex;gap:12px;">
                          <div id="ce-accent-pill" style="padding:5px 14px;border-radius:20px;font-size:12px;color:#fff;">Acento</div>
                          <span style="color:#333;font-size:13px;">Texto del documento de ejemplo</span>
                        </div>
                      </div>
                    </div>
                  </div>
                  <!-- TAB 2: Empresa -->
                  <div class="ce-section" id="ce-s2">
                    <h3 style="color:var(--primary);margin-bottom:20px;">Datos de Empresa / Encabezado</h3>
                    <div class="ce-row">
                      <div class="ce-field"><div class="ce-label">Nombre de la empresa</div><input id="ce-company" type="text" placeholder="Ej: Taller Mecánico La Estrella"></div>
                      <div class="ce-field"><div class="ce-label">RUT / ID Fiscal</div><input id="ce-rut" type="text" placeholder="Ej: 76.123.456-7"></div>
                    </div>
                    <div class="ce-row">
                      <div class="ce-field"><div class="ce-label">Dirección</div><input id="ce-address" type="text" placeholder="Ej: Av. Principal 123, Santiago"></div>
                      <div class="ce-field"><div class="ce-label">Teléfono</div><input id="ce-phone" type="text" placeholder="Ej: +56 9 1234 5678"></div>
                    </div>
                    <div class="ce-row">
                      <div class="ce-field"><div class="ce-label">Email</div><input id="ce-email" type="text" placeholder="Ej: contacto@empresa.cl"></div>
                    </div>
                    <div style="margin-top:16px;">
                      <div class="ce-label">Logo de empresa</div>
                      <input type="file" id="ce-logo-file" accept="image/*" onchange="ceUploadLogo()" style="margin-top:8px;">
                      <img id="ce-logo-preview" class="logo-preview" src="" alt="Logo">
                      <input type="hidden" id="ce-logo-url" value="">
                    </div>
                  </div>
                  <!-- TAB 3: Sections -->
                  <div class="ce-section" id="ce-s3">
                    <h3 style="color:var(--primary);margin-bottom:6px;">Secciones del Documento</h3>
                    <p style="font-size:13px;color:rgba(255,255,255,0.6);margin-bottom:16px;">Arrastra para reordenar. Haz clic en <i class="fa-solid fa-trash"></i> para eliminar.</p>
                    <div id="ce-sections-list" style="min-height:80px;"></div>
                    <div class="section-adder" style="margin-top:16px;">
                      <div class="ce-label" style="width:100%;margin-bottom:6px;">Agregar sección:</div>
                      <span class="sa-pill" onclick="ceAddSection('client_info')"><i class="fa-solid fa-user"></i> Datos del Cliente</span>
                      <span class="sa-pill" onclick="ceAddSection('vehicle_info')"><i class="fa-solid fa-car"></i> Datos del Vehículo</span>
                      <span class="sa-pill" onclick="ceAddSection('general_info')"><i class="fa-solid fa-circle-info"></i> Info General</span>
                      <span class="sa-pill" onclick="ceAddSection('items_table')"><i class="fa-solid fa-table-list"></i> Tabla de Ítems</span>
                      <span class="sa-pill" onclick="ceAddSection('checklist')"><i class="fa-solid fa-list-check"></i> Lista de Verificación</span>
                      <span class="sa-pill" onclick="ceAddSection('notes')"><i class="fa-solid fa-note-sticky"></i> Observaciones</span>
                      <span class="sa-pill" onclick="ceAddSection('signature')"><i class="fa-solid fa-signature"></i> Firma</span>
                      <span class="sa-pill" onclick="ceAddSection('footer')"><i class="fa-solid fa-align-center"></i> Pie de página</span>
                    </div>
                  </div>
                  <!-- TAB 4: Fields -->
                  <div class="ce-section" id="ce-s4">
                    <h3 style="color:var(--primary);margin-bottom:6px;">Campos (Datos que solicita la IA)</h3>
                    <p style="font-size:13px;color:rgba(255,255,255,0.6);margin-bottom:16px;">Estos son los datos que el asistente solicitará al usuario antes de generar el documento.</p>
                    <div id="ce-fields-list"></div>
                    <button class="btn btn-outline" style="margin-top:12px;" onclick="ceAddField()"><i class="fa-solid fa-plus"></i> Agregar campo</button>
                  </div>
                  <!-- TAB 5: Preview -->
                  <div class="ce-section" id="ce-s5">
                    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px;">
                      <h3 style="color:var(--primary);margin:0;">Vista Previa del PDF</h3>
                      <button class="btn" onclick="ceGeneratePreview()"><i class="fa-solid fa-eye"></i> Generar Preview</button>
                    </div>
                    <p style="font-size:12px;color:rgba(255,255,255,0.5);margin-bottom:12px;">Se generará con datos de ejemplo basados en tus campos definidos.</p>
                    <div id="ce-preview-container" style="border-radius:12px;overflow:hidden;">
                      <div style="text-align:center;padding:60px;color:rgba(255,255,255,0.4);">
                        <i class="fa-solid fa-file-pdf" style="font-size:48px;margin-bottom:16px;"></i>
                        <p>Haz clic en "Generar Preview" para ver cómo quedará tu plantilla.</p>
                      </div>
                    </div>
                  </div>
                </div>
                <!-- Footer -->
                <div class="ce-footer">
                  <div id="ce-status" style="font-size:13px;color:rgba(255,255,255,0.5);"></div>
                  <div style="display:flex;gap:8px;">
                    <button class="btn btn-outline" onclick="closeCanvasEditor()">Cancelar</button>
                    <button class="btn" onclick="ceSave()"><i class="fa-solid fa-floppy-disk"></i> Guardar Plantilla</button>
                  </div>
                </div>
              </div>
            </div>

            <!-- CANVAS EDITOR JS -->
            <script>
            (function(){
              window._ceState = { id: null, client_id: null, sections: [], fields: [] };
              const sectionMeta = {
                client_info:  { icon: 'fa-user',         label: 'Datos del Cliente',          color: '#6366f1' },
                vehicle_info: { icon: 'fa-car',          label: 'Datos del Vehículo',         color: '#0ea5e9' },
                general_info: { icon: 'fa-circle-info',  label: 'Información General',        color: '#8b5cf6' },
                items_table:  { icon: 'fa-table-list',   label: 'Tabla de Ítems',             color: '#f59e0b' },
                checklist:    { icon: 'fa-list-check',   label: 'Lista de Verificación',      color: '#10b981' },
                notes:        { icon: 'fa-note-sticky',  label: 'Observaciones / Notas',      color: '#f97316' },
                signature:    { icon: 'fa-signature',    label: 'Firmas',                     color: '#64748b' },
                footer:       { icon: 'fa-align-center', label: 'Pie de Página',              color: '#94a3b8' },
              };

              window.openCanvasEditor = function(templateData) {
                window._ceState = { id: null, client_id: null, sections: [], fields: [] };
                // Reset form
                ['ce-name','ce-desc','ce-company','ce-rut','ce-address','ce-phone','ce-email'].forEach(id => { const el = document.getElementById(id); if(el) el.value=''; });
                document.getElementById('ce-doctype').value = 'budget';
                document.getElementById('ce-font').value = 'Arial';
                document.getElementById('ce-color-primary').value = '#1a3a5c';
                document.getElementById('ce-color-accent').value = '#f0a500';
                document.getElementById('ce-logo-url').value = '';
                document.getElementById('ce-logo-preview').style.display = 'none';
                document.getElementById('ce-status').textContent = '';

                if (templateData && templateData.template_config) {
                  const c = JSON.parse(templateData.template_config);
                  window._ceState.id = templateData.id;
                  window._ceState.client_id = templateData.client_id;
                  window._ceState.sections = c.sections || [];
                  window._ceState.fields = c.fields || [];
                  document.getElementById('ce-name').value = templateData.name || '';
                  document.getElementById('ce-desc').value = templateData.description || '';
                  document.getElementById('ce-doctype').value = c.doc_type || 'generic';
                  document.getElementById('ce-font').value = (c.design||{}).font || 'Arial';
                  document.getElementById('ce-color-primary').value = (c.design||{}).primary_color || '#1a3a5c';
                  document.getElementById('ce-color-accent').value = (c.design||{}).accent_color || '#f0a500';
                  const h = c.header || {};
                  ['company','rut','address','phone','email'].forEach(f => {
                    const el = document.getElementById('ce-'+f);
                    if (el) el.value = h['company_'+f] || h[f] || '';
                  });
                  if (h.logo_url) {
                    document.getElementById('ce-logo-url').value = h.logo_url;
                    const prev = document.getElementById('ce-logo-preview');
                    prev.src = h.logo_url; prev.style.display = 'block';
                  }
                }

                // Populate clients for superadmin
                if (window.IS_SUPERADMIN) {
                  const clientSelect = document.getElementById('ce-client-id');
                  if (clientSelect && window.clientsCache) {
                    let opts = '<option value="">Selecciona un cliente...</option>';
                    window.clientsCache.forEach(c => {
                      opts += `<option value="${c.id}" ${window._ceState.client_id == c.id ? 'selected' : ''}>${c.name}</option>`;
                    });
                    clientSelect.innerHTML = opts;
                  }
                }

                ceSwitchTab(0);
                ceRenderSections();
                ceRenderFields();
                ceUpdateColorPreview();
                document.getElementById('canvas-editor-modal').style.display = 'block';
                document.body.style.overflow = 'hidden';
              };

              window.closeCanvasEditor = function() {
                document.getElementById('canvas-editor-modal').style.display = 'none';
                document.body.style.overflow = '';
              };

              window.ceSwitchTab = function(idx) {
                document.querySelectorAll('#canvas-editor-modal .ce-tab').forEach((t,i) => t.classList.toggle('active', i===idx));
                document.querySelectorAll('#canvas-editor-modal .ce-section').forEach((s,i) => s.classList.toggle('active', i===idx));
              };

              window.ceAddSection = function(type) {
                const extra = {};
                if (type === 'items_table') { extra.show_totals = true; extra.tax_rate = 0; extra.columns = [{key:'descripcion',label:'Descripción',w:80},{key:'cantidad',label:'Cant.',w:20},{key:'precio',label:'Precio',w:40},{key:'total',label:'Total',w:40}]; }
                if (type === 'checklist') { extra.items = [{key:'motor',label:'Motor'},{key:'frenos',label:'Frenos'},{key:'suspension',label:'Suspensión'},{key:'neumaticos',label:'Neumáticos'}]; }
                if (type === 'notes') { extra.field = 'observaciones'; }
                if (type === 'footer') { extra.text = 'Gracias por su preferencia.'; }
                _ceState.sections.push({ type, ...extra });
                ceRenderSections();
              };

              window.ceRenderSections = function() {
                const el = document.getElementById('ce-sections-list');
                el.innerHTML = '';
                _ceState.sections.forEach((sec, idx) => {
                  const meta = sectionMeta[sec.type] || { icon: 'fa-file', label: sec.type, color: '#888' };
                  const div = document.createElement('div');
                  div.className = 'section-block';
                  div.draggable = true;
                  div.dataset.idx = idx;
                  let extraHtml = '';
                  if (sec.type === 'footer' || sec.type === 'notes') {
                    extraHtml = `<input type="text" value="${(sec.text||sec.label||'')}" placeholder="Texto..." oninput="_ceState.sections[${idx}].${sec.type==='footer'?'text':'label'}=this.value" style="width:100%;background:rgba(255,255,255,0.07);border:1px solid rgba(255,255,255,0.15);border-radius:6px;color:#fff;padding:6px 10px;font-size:12px;margin-top:8px;">`;
                  }
                  if (sec.type === 'items_table') {
                    extraHtml = `<label style="font-size:12px;color:rgba(255,255,255,0.6);margin-top:8px;display:flex;align-items:center;gap:8px;"><input type="checkbox" ${sec.show_totals?'checked':''} onchange="_ceState.sections[${idx}].show_totals=this.checked"> Mostrar totales</label><label style="font-size:12px;color:rgba(255,255,255,0.6);margin-top:6px;display:flex;align-items:center;gap:8px;">IVA% <input type="number" value="${sec.tax_rate||0}" min="0" max="100" oninput="_ceState.sections[${idx}].tax_rate=parseFloat(this.value)" style="width:60px;background:rgba(255,255,255,0.07);border:1px solid rgba(255,255,255,0.15);border-radius:6px;color:#fff;padding:4px 8px;"></label>`;
                  }
                  if (sec.type === 'checklist') {
                    const items = (sec.items||[]).map((it,ii) => `<div class="checklist-item"><input value="${it.label}" placeholder="Ítem a verificar" oninput="_ceState.sections[${idx}].items[${ii}].label=this.value;_ceState.sections[${idx}].items[${ii}].key=this.value.toLowerCase().replace(/\\s+/g,'_')" ><button onclick="_ceState.sections[${idx}].items.splice(${ii},1);ceRenderSections()" style="background:none;border:none;color:#ef4444;cursor:pointer;"><i class="fa-solid fa-times"></i></button></div>`).join('');
                    extraHtml = `<div style="margin-top:8px;">${items}</div><button onclick="_ceState.sections[${idx}].items=(_ceState.sections[${idx}].items||[]);_ceState.sections[${idx}].items.push({key:'nuevo',label:'Nuevo ítem'});ceRenderSections()" style="background:none;border:1px dashed rgba(255,255,255,0.3);color:rgba(255,255,255,0.6);border-radius:6px;padding:4px 10px;cursor:pointer;font-size:12px;margin-top:6px;"><i class="fa-solid fa-plus"></i> Agregar ítem</button>`;
                  }
                  if (sec.type === 'client_info' || sec.type === 'vehicle_info' || sec.type === 'general_info') {
                    const currFields = (sec.fields||[]);
                    extraHtml = `<div style="font-size:12px;color:rgba(255,255,255,0.6);margin-top:8px;">Campos vinculados: ${currFields.length > 0 ? currFields.join(', ') : '<em>Se vinculan automáticamente desde la pestaña Campos</em>'}</div>`;
                  }
                  div.innerHTML = `<div class="sb-header"><div><i class="fa-solid ${meta.icon} sb-icon" style="color:${meta.color};"></i><span class="sb-title">${meta.label}</span></div><div style="display:flex;gap:6px;"><button onclick="_ceState.sections.splice(${idx},1);ceRenderSections()" style="background:none;border:none;color:#ef4444;cursor:pointer;"><i class="fa-solid fa-trash"></i></button></div></div>${extraHtml}`;
                  el.appendChild(div);
                });
                ceLinkSectionFields();
              };

              function ceLinkSectionFields() {
                // Automatically link defined fields to the first client_info / vehicle_info / general_info section
                const clientFields = _ceState.fields.filter(f => f.section === 'client_info' || !f.section).map(f => f.name);
                const vehicleFields = _ceState.fields.filter(f => f.section === 'vehicle_info').map(f => f.name);
                _ceState.sections.forEach((sec, idx) => {
                  if (sec.type === 'client_info') _ceState.sections[idx].fields = clientFields;
                  if (sec.type === 'vehicle_info') _ceState.sections[idx].fields = vehicleFields;
                  if (sec.type === 'general_info') _ceState.sections[idx].fields = _ceState.fields.map(f => f.name);
                });
              }

              window.ceAddField = function() {
                _ceState.fields.push({ name: 'campo_' + (_ceState.fields.length+1), label: 'Nuevo campo', type: 'text', required: true, section: 'client_info' });
                ceRenderFields();
              };

              window.ceRenderFields = function() {
                const el = document.getElementById('ce-fields-list');
                el.innerHTML = '';
                if (_ceState.fields.length === 0) {
                  el.innerHTML = '<div style="color:rgba(255,255,255,0.4);font-size:13px;padding:20px;">Sin campos definidos. Agrega campos para que la IA sepa qué datos solicitar.</div>';
                  return;
                }
                _ceState.fields.forEach((f, idx) => {
                  const row = document.createElement('div');
                  row.className = 'field-row';
                  row.innerHTML = `
                    <div style="display:grid;grid-template-columns:1fr 1.2fr auto auto auto;gap:8px;width:100%;align-items:center;">
                      <input value="${f.name}" placeholder="nombre_campo" oninput="_ceState.fields[${idx}].name=this.value.toLowerCase().replace(/\\s+/g,'_')" title="Nombre (key interno)">
                      <input value="${f.label}" placeholder="Pregunta para el usuario" oninput="_ceState.fields[${idx}].label=this.value" title="Etiqueta / pregunta">
                      <select onchange="_ceState.fields[${idx}].type=this.value">
                        <option value="text" ${f.type==='text'?'selected':''}>Texto</option>
                        <option value="number" ${f.type==='number'?'selected':''}>Número</option>
                        <option value="date" ${f.type==='date'?'selected':''}>Fecha</option>
                      </select>
                      <select onchange="_ceState.fields[${idx}].section=this.value" title="Sección">
                        <option value="client_info" ${(f.section||'client_info')==='client_info'?'selected':''}>Cliente</option>
                        <option value="vehicle_info" ${f.section==='vehicle_info'?'selected':''}>Vehículo</option>
                        <option value="general_info" ${f.section==='general_info'?'selected':''}>General</option>
                      </select>
                      <button onclick="_ceState.fields.splice(${idx},1);ceRenderFields()" style="background:none;border:none;color:#ef4444;cursor:pointer;"><i class="fa-solid fa-trash"></i></button>
                    </div>`;
                  el.appendChild(row);
                });
              };

              window.ceUpdateColorPreview = function() {
                const c = document.getElementById('ce-color-primary').value;
                const a = document.getElementById('ce-color-accent').value;
                document.getElementById('ce-color-bar').style.background = c;
                document.getElementById('ce-accent-pill').style.background = a;
              };
              document.getElementById('ce-color-primary').addEventListener('input', ceUpdateColorPreview);
              document.getElementById('ce-color-accent').addEventListener('input', ceUpdateColorPreview);

              window.ceUploadLogo = function() {
                const file = document.getElementById('ce-logo-file').files[0];
                if (!file) return;
                const fd = new FormData();
                fd.append('logo', file);
                // Note: no CSRF needed for logo_upload (not in csrf_protected_actions)
                $.ajax({
                  url: 'api.php?action=pdf_templates_logo_upload',
                  type: 'POST',
                  data: fd,
                  processData: false,
                  contentType: false,
                  dataType: 'json',
                  success: function(res) {
                    if (res.status === 'success') {
                      document.getElementById('ce-logo-url').value = res.url;
                      const prev = document.getElementById('ce-logo-preview');
                      prev.src = res.url; prev.style.display = 'block';
                    } else {
                      alert('Error al subir logo: ' + (res.message || 'desconocido'));
                    }
                  },
                  error: function(xhr) {
                    alert('Error de conexión al subir logo: ' + xhr.status);
                  }
                });
              };

              window.ceBuildConfig = function() {
                ceLinkSectionFields();
                return {
                  doc_type: document.getElementById('ce-doctype').value,
                  design: {
                    primary_color: document.getElementById('ce-color-primary').value,
                    accent_color: document.getElementById('ce-color-accent').value,
                    font: document.getElementById('ce-font').value,
                  },
                  header: {
                    company_name: document.getElementById('ce-company').value,
                    company_rut: document.getElementById('ce-rut').value,
                    company_address: document.getElementById('ce-address').value,
                    company_phone: document.getElementById('ce-phone').value,
                    company_email: document.getElementById('ce-email').value,
                    logo_url: document.getElementById('ce-logo-url').value,
                  },
                  sections: _ceState.sections,
                  fields: _ceState.fields,
                };
              };

              window.ceGeneratePreview = function() {
                const config = ceBuildConfig();
                const cont = document.getElementById('ce-preview-container');
                cont.innerHTML = '<div style="text-align:center;padding:40px;color:rgba(255,255,255,0.5);"><i class="fa-solid fa-spinner fa-spin" style="font-size:32px;"></i><p>Generando PDF de ejemplo...</p></div>';
                const csrfToken = '<?= $_SESSION["csrf_token"] ?? "" ?>';
                $.ajax({
                  url: 'api.php?action=pdf_templates_preview',
                  type: 'POST',
                  data: { 
                    template_config: JSON.stringify(config), 
                    csrf_token: csrfToken,
                    client_id: getClientIdForAPI()
                  },
                  dataType: 'json',
                  success: function(res) {
                    if (res.status === 'success' && res.url) {
                      cont.innerHTML = `<iframe class="preview-frame" src="${res.url}#toolbar=0"></iframe>`;
                    } else {
                      cont.innerHTML = '<div style="text-align:center;padding:40px;color:#ef4444;"><i class="fa-solid fa-triangle-exclamation"></i> Error: ' + (res.message||'desconocido') + '</div>';
                    }
                  },
                  error: function(xhr) {
                    cont.innerHTML = '<div style="text-align:center;padding:40px;color:#ef4444;"><i class="fa-solid fa-triangle-exclamation"></i> Error de conexión: HTTP ' + xhr.status + '. Revisa los logs del servidor.</div>';
                  }
                });
              };

              window.ceSave = function() {
                const name = document.getElementById('ce-name').value.trim();
                if (!name) { alert('Ingresa un nombre para la plantilla.'); ceSwitchTab(0); return; }
                const config = ceBuildConfig();
                const statusEl = document.getElementById('ce-status');
                statusEl.textContent = 'Guardando...';
                statusEl.style.color = 'rgba(255,255,255,0.5)';
                const csrfToken = '<?= $_SESSION["csrf_token"] ?? "" ?>';
                const postData = {
                  name: name,
                  description: document.getElementById('ce-desc').value,
                  doc_type: document.getElementById('ce-doctype').value,
                  template_config: JSON.stringify(config),
                  csrf_token: csrfToken,
                  client_id: (window.IS_SUPERADMIN ? document.getElementById('ce-client-id').value : getClientIdForAPI()) || window._ceState.client_id
                };
                if (!postData.client_id) { alert('Debes seleccionar un cliente.'); return; }
                if (window._ceState.id) postData.id = window._ceState.id;
                $.ajax({
                  url: 'api.php?action=pdf_templates_save_config',
                  type: 'POST',
                  data: postData,
                  dataType: 'json',
                  success: function(res) {
                    if (res.status === 'success') {
                      _ceState.id = res.id;
                      statusEl.style.color = '#10b981';
                      statusEl.textContent = '✓ Guardado exitosamente (ID: ' + res.id + ')';
                      if (typeof loadPDFTemplates === 'function') loadPDFTemplates();
                    } else {
                      statusEl.style.color = '#ef4444';
                      statusEl.textContent = 'Error: ' + (res.message || 'desconocido');
                    }
                  },
                  error: function(xhr) {
                    statusEl.style.color = '#ef4444';
                    let msg = 'Error de conexión HTTP ' + xhr.status;
                    if (xhr.status === 200) {
                      msg = 'Error de respuesta (No JSON). Posible error PHP internamente.';
                      console.error('Respuesta malformada:', xhr.responseText);
                      // If it looks like HTML/PHP error, show first bit
                      if (xhr.responseText && xhr.responseText.length > 5) {
                         msg = 'Error PHP: ' + xhr.responseText.substring(0, 100).replace(/<[^>]*>/g, '') + '...';
                      }
                    } else {
                      try {
                        const r = JSON.parse(xhr.responseText);
                        msg = 'Error: ' + (r.message || msg);
                      } catch(e) { /* ignore parse error on non-200 */ }
                    }
                    statusEl.textContent = msg;
                    console.error('ceSave error:', xhr.status, xhr.responseText);
                  }
                });
              };

              // Allow editing existing canvas template from the card
              window.openCanvasEditorTemplate = function(tplId) {
                $.get('api.php?action=pdf_templates_list', function(res) {
                  const tpl = (res.data||[]).find(t => String(t.id) === String(tplId));
                  if (tpl) openCanvasEditor(tpl);
                }, 'json');
              };

            })();
            </script>

            <!-- GENERATED DOCUMENTS TAB -->
            <div id="pdf-generated-tab" class="tab-content">
                <div class="panel-header">
                    <h2>Documentos Generados</h2>
                    <button class="btn btn-outline" onclick="loadGeneratedDocs()"><i class="fa-solid fa-rotate"></i> Actualizar</button>
                </div>
                <div class="panel">
                    <p style="color:var(--text-muted); font-size:13px; margin-bottom:15px;">Documentos creados por el asistente durante las conversaciones.</p>
                    <div style="overflow-x: auto;">
                        <table id="pdf-generated-table">
                            <thead>
                                <tr>
                                    <th>Fecha</th>
                                    <th>Asistente</th>
                                    <th>Plantilla</th>
                                    <th>Archivo</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- CAMPAIGN MODAL -->
            <div id="campaign-modal" style="display:none; position:fixed; inset:0; z-index:9999; background:rgba(0,0,0,0.7); overflow-y:auto;">
                <div class="panel" style="max-width:100%; min-height:100vh; margin:0; position:relative; border-radius:0; display:flex; flex-direction:column;">
                    <div class="panel-header" style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; padding:20px 30px; background:var(--primary); border-radius:0;">
                        <h2 style="margin:0; color:white;"><i class="fa-solid fa-bullhorn"></i> Campaña de Marketing</h2>
                        <button class="close-btn" onclick="closeModal('campaign-modal')" style="background:rgba(255,255,255,0.2); border:none; color:white; font-size:20px; cursor:pointer; padding:5px 15px; border-radius:8px;">&times; Cerrar</button>
                    </div>
                    <div style="flex:1; padding:30px; max-width:800px; margin:0 auto; width:100%;">
                        <form id="campaign-form" onsubmit="submitCampaign(event)">
                            <input type="hidden" name="id" id="campaign-id">
                            
                            <?php if ($is_superadmin): ?>
                            <div class="form-group" style="margin-bottom:15px;" id="campaign-client-row">
                                <label style="display:block; margin-bottom:5px; color:var(--primary);">Cliente Asignado *</label>
                                <select name="client_id" id="campaign-client-id" onchange="loadCampaignLeads()" style="width:100%; padding:10px; border-radius:8px; border:1px solid var(--glass-border); background:rgba(255,255,255,0.05); color:white;">
                                    <option value="">Selecciona un cliente...</option>
                                </select>
                            </div>
                            <?php endif; ?>
                            <div class="form-group" style="margin-bottom:15px;">
                                <label style="display:block; margin-bottom:5px;">Nombre de la Campaña</label>
                                <input type="text" name="name" id="campaign-name" required placeholder="Ej: Oferta de Verano 2026" style="width:100%; padding:10px; border-radius:8px; border:1px solid var(--glass-border); background:rgba(255,255,255,0.05); color:white;">
                            </div>
                            <div class="form-group" style="margin-bottom:15px;">
                                <label style="display:block; margin-bottom:5px;">Mensaje (WhatsApp)</label>
                                <textarea name="message" id="campaign-message" required rows="6" placeholder="Escribe el mensaje que recibirán tus clientes..." style="width:100%; padding:10px; border-radius:8px; border:1px solid var(--glass-border); background:rgba(255,255,255,0.05); color:white;"></textarea>
                                <small style="color:var(--text-muted); font-size:11px;">Escribe el mensaje que se enviará. Recuerda que no será procesado por la IA.</small>
                            </div>
                                                    <!-- Attachment Field -->
                            <div class="form-group" style="margin-bottom:15px;">
                                <label style="display:block; margin-bottom:5px;"><i class="fa-solid fa-paperclip"></i> Adjunto (Imagen, Video o Documento)</label>
                                <div style="display:flex; gap:10px; align-items:center;">
                                    <input type="file" name="attachment" id="campaign-attachment" accept="image/*,video/*,.pdf,.doc,.docx" style="flex:1; padding:8px; border-radius:8px; border:1px solid var(--glass-border); background:rgba(255,255,255,0.05); color:white; font-size:13px;">
                                    <button type="button" class="btn btn-outline" onclick="document.getElementById('campaign-attachment').value=''" style="padding:8px 12px;"><i class="fa-solid fa-trash"></i></button>
                                </div>
                                <small style="color:var(--text-muted); font-size:11px;">Selecciona un archivo si deseas enviarlo junto al mensaje.</small>
                            </div>

                            <div class="form-group" style="margin-bottom:15px;">
                                <label style="display:block; margin-bottom:5px;">Público Objetivo</label>
                                <select name="target_type" id="campaign-target-type" onchange="toggleCampaignLeadSelection()" style="width:100%; padding:10px; border-radius:8px; border:1px solid var(--glass-border); background:rgba(255,255,255,0.05); color:white;">
                                    <option value="all">Todos los Prospectos</option>
                                    <option value="selected">Solo Prospectos Seleccionados</option>
                                </select>
                            </div>
                            <!-- EMBEDDED LEADS SELECTOR -->
                            <div id="campaign-leads-selector" style="display:none; margin-bottom:20px; background:rgba(0,0,0,0.2); padding:15px; border-radius:12px; border:1px solid var(--glass-border);">
                                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px;">
                                    <label style="font-weight:bold; color:var(--primary);">Seleccionar Prospectos</label>
                                    <div style="font-size:11px; color:var(--text-muted);"><i class="fa-solid fa-info-circle"></i> Los prospectos se filtran por el cliente seleccionado arriba.</div>
                                </div>
                                <div style="max-height:300px; overflow-y:auto; border:1px solid rgba(255,255,255,0.05); border-radius:8px;">
                                    <table id="campaign-leads-table" style="font-size:13px; width:100%;">
                                        <thead style="position:sticky; top:0; background:var(--bg-secondary); z-index:1;">
                                            <tr>
                                                <th style="width:30px;"><input type="checkbox" id="campaign-select-all-leads" onclick="toggleSelectAllCampaignLeads(this.checked)"></th>
                                                <th>Nombre</th>
                                                <th>Contacto</th>
                                                <th>Estado</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr><td colspan="4" style="text-align:center; padding:20px; color:var(--text-muted);">Cargando prospectos...</td></tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <div id="campaign-lead-notice" style="display:none; padding:12px; background:rgba(0,212,255,0.1); border-radius:8px; font-size:12px; margin-bottom:15px; border:1px solid var(--glass-border); color:var(--primary);">
                                <i class="fa-solid fa-circle-info"></i> Selecciona los prospectos en la lista superior antes de proceder.
                            </div>

                        <div style="text-align:right; margin-top:20px; padding-bottom:30px;">
                            <button type="button" class="btn btn-outline" onclick="closeModal('campaign-modal')">Cancelar</button>
                            <button type="submit" class="btn">Guardar Campaña</button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- HELP & TUTORIALS TAB -->
            <div id="help-tab" class="tab-content">
                <style>
                    .help-card {
                        background: rgba(255, 255, 255, 0.03);
                        border: 1px solid var(--glass-border);
                        border-radius: 12px;
                        padding: 24px;
                        transition: all 0.3s ease;
                        height: 100%;
                    }

                    .help-card:hover {
                        background: rgba(255, 255, 255, 0.05);
                        border-color: var(--primary);
                        transform: translateY(-2px);
                    }

                    .help-icon {
                        font-size: 28px;
                        color: var(--primary);
                        margin-bottom: 20px;
                        display: block;
                    }

                    .help-card h4 {
                        font-size: 18px;
                        margin-bottom: 12px;
                        color: white;
                    }

                    .help-card p {
                        font-size: 13.5px;
                        color: var(--text-muted);
                        line-height: 1.6;
                    }

                    .step-badge {
                        display: inline-flex;
                        align-items: center;
                        justify-content: center;
                        width: 24px;
                        height: 24px;
                        background: var(--primary);
                        color: white;
                        border-radius: 50%;
                        font-size: 12px;
                        font-weight: bold;
                        margin-right: 8px;
                    }

                    .help-feature-list {
                        list-style: none;
                        margin-top: 15px;
                    }

                    .help-feature-list li {
                        font-size: 13px;
                        margin-bottom: 8px;
                        display: flex;
                        align-items: center;
                        gap: 8px;
                    }

                    .help-feature-list i {
                        color: var(--success);
                        font-size: 11px;
                    }
                </style>

                <div class="panel">
                    <div class="panel-header" style="flex-direction: column; align-items: flex-start; gap: 8px;">
                        <h2 style="font-size: 28px; font-weight: 700;">Centro de Ayuda Skale IA</h2>
                        <p style="color: var(--text-muted); font-size: 15px;">Guía rápida para configurar el ecosistema de tus asistentes inteligentes.</p>
                    </div>

                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 24px;">
                        <!-- Step 1: Assistants -->
                        <div class="help-card">
                            <i class="fa-solid fa-robot help-icon" style="color: #60a5fa;"></i>
                            <h4><span class="step-badge">1</span> Configura tu Asistente</h4>
                            <p>El primer paso es crear un asistente para tu cliente.</p>
                            <ul class="help-feature-list">
                                <li><i class="fa-solid fa-check"></i> <b>System Prompt:</b> Describe su personalidad (ej: "Eres un vendedor amable...").</li>
                                <li><i class="fa-solid fa-check"></i> <b>Temperatura:</b> 0.3 para datos exactos, 0.7 para conversación natural.</li>
                                <li><i class="fa-solid fa-check"></i> <b>Modelo:</b> Recomendamos Gemini 2.0 Flash por rapidez.</li>
                            </ul>
                        </div>

                        <!-- Step 2: Training -->
                        <div class="help-card">
                            <i class="fa-solid fa-graduation-cap help-icon" style="color: #10b981;"></i>
                            <h4><span class="step-badge">2</span> Entrenamiento Artificial</h4>
                            <p>Dale "cerebro" a la IA subiendo información real de la empresa.</p>
                            <ul class="help-feature-list">
                                <li><i class="fa-solid fa-check"></i> <b>PDFs:</b> Sube catálogos, listas de precios o manuales.</li>
                                <li><i class="fa-solid fa-check"></i> <b>Links:</b> Pega la URL de tu web para que la IA la "lea".</li>
                                <li><i class="fa-solid fa-check"></i> <b>Texto:</b> Copia y pega información suelta importante.</li>
                            </ul>
                        </div>

                        <!-- Step 3: Automation -->
                        <div class="help-card">
                            <i class="fa-solid fa-bolt-lightning help-icon" style="color: #f59e0b;"></i>
                            <h4><span class="step-badge">3</span> Automatización Pro</h4>
                            <p>Convierte conversaciones en ventas y citas automáticamente.</p>
                            <ul class="help-feature-list">
                                <li><i class="fa-solid fa-check"></i> <b>Prospectos (Leads):</b> La IA detectará interesados y los listará aquí.</li>
                                <li><i class="fa-solid fa-check"></i> <b>Calendario:</b> Agendamiento automático en Google Calendar.</li>
                                <li><i class="fa-solid fa-check"></i> <b>PDF Maker:</b> Crea cotizaciones al vuelo desde una plantilla.</li>
                            </ul>
                        </div>

                        <!-- Tips -->
                        <div class="help-card" style="border: 1px dashed var(--primary); background: rgba(139, 92, 246, 0.05);">
                            <i class="fa-solid fa-lightbulb help-icon"></i>
                            <h4>Consejos de Oro</h4>
                            <p>Para obtener mejores resultados, sigue estas prácticas:</p>
                            <ul class="help-feature-list">
                                <li><i class="fa-solid fa-star"></i> Sé específico en el System Prompt sobre lo que NO debe decir.</li>
                                <li><i class="fa-solid fa-star"></i> Prueba el chat con el botón lateral cada vez que cambies algo.</li>
                                <li><i class="fa-solid fa-star"></i> Revisa los Logs para ver dónde se confunde la IA y mejorar sus reglas.</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div> <!-- end content-area -->
    </main> <!-- end main-wrapper -->

    <!-- MODALS -->

    <!-- Client Modal -->
    <div class="modal-overlay" id="client-modal">
        <div class="modal">
            <div class="modal-header">
                <h2 id="client-modal-title">Nuevo Cliente</h2>
                <button type="button" class="close-modal" onclick="closeModal('client-modal')"><i
                        class="fa-solid fa-xmark"></i></button>
            </div>
            <form id="client-form" onsubmit="submitClient(event)">
                <input type="hidden" id="client-id" name="id">

                <div class="form-group">
                    <label>Tipo de Cliente</label>
                    <select id="client-type" name="type" onchange="toggleClientFields()" required>
                        <option value="particular">Persona Particular</option>
                        <option value="empresa">Empresa / Institución</option>
                    </select>
                </div>

                <!-- Fields for Particular -->
                <div id="fields-particular">
                    <div class="form-group">
                        <label>Nombre Completo</label>
                        <input type="text" id="client-name-particular" placeholder="Ej: Juan Pérez">
                    </div>
                </div>

                <!-- Fields for Empresa -->
                <div id="fields-empresa" style="display:none;">
                    <div style="display:flex; gap:15px;">
                        <div class="form-group" style="flex:2;">
                            <label>Razón Social</label>
                            <input type="text" id="client-name-empresa" placeholder="Ej: Skale IA SpA">
                        </div>
                        <div class="form-group" style="flex:1;">
                            <label>RUT</label>
                            <input type="text" id="client-rut" name="rut" placeholder="12.345.678-k">
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Dirección</label>
                        <input type="text" id="client-address" name="address" placeholder="Av. Siempre Viva 123">
                    </div>
                    <div class="form-group">
                        <label>Giro</label>
                        <input type="text" id="client-giro" name="business_line" placeholder="Servicios Tecnológicos">
                    </div>

                    <h3
                        style="font-size:14px; margin:20px 0 10px; color:var(--primary); border-bottom:1px solid rgba(255,255,255,0.1); padding-bottom:5px;">
                        Datos del Representante</h3>
                    <div class="form-group">
                        <label>Nombre Representante</label>
                        <input type="text" id="client-rep-name" name="representative_name">
                    </div>
                    <div style="display:flex; gap:15px;">
                        <div class="form-group" style="flex:1;">
                            <label>Email Representante</label>
                            <input type="email" id="client-rep-email" name="representative_email">
                        </div>
                        <div class="form-group" style="flex:1;">
                            <label>Teléfono Representante</label>
                            <input type="text" id="client-rep-phone" name="representative_phone">
                        </div>
                    </div>
                </div>

                <h3
                    style="font-size:14px; margin:20px 0 10px; color:var(--primary); border-bottom:1px solid rgba(255,255,255,0.1); padding-bottom:5px;">
                    Datos de Contacto Central</h3>
                <div style="display:flex; gap:15px;">
                    <div class="form-group" style="flex:1;">
                        <label>Email Principal (Login)</label>
                        <input type="email" id="client-email" name="contact_email" required>
                    </div>
                    <div class="form-group" style="flex:1;">
                        <label>Teléfono Principal</label>
                        <input type="text" id="client-phone" name="phone">
                    </div>
                </div>

                <!-- Hidden name field that will be synced before submit -->
                <input type="hidden" id="client-name" name="name">

                <div id="password-hint"
                    style="font-size:12px; color:var(--text-muted); margin-top:10px; background:rgba(0,0,0,0.2); padding:10px; border-radius:5px;">
                    <i class="fa-solid fa-info-circle"></i> Al crear el cliente, se generará automáticamente un acceso
                    con la contraseña: <b>admin123!</b>
                </div>

                <div style="display:flex; justify-content:flex-end; gap:10px; margin-top:20px;">
                    <button type="button" class="btn btn-outline" onclick="closeModal('client-modal')">Cancelar</button>
                    <button type="submit" class="btn">Guardar Cliente</button>
                </div>
            </form>
        </div>
    </div>

    <!-- User Modal -->
    <div class="modal-overlay" id="user-modal">
        <div class="modal">
            <div class="modal-header">
                <h2 id="user-modal-title">Nuevo Usuario</h2>
                <button type="button" class="close-modal" onclick="closeModal('user-modal')"><i
                        class="fa-solid fa-xmark"></i></button>
            </div>
            <form id="user-form" onsubmit="submitUser(event)">
                <input type="hidden" id="user-id" name="id">
                <div class="form-group">
                    <label>Nombre de Usuario (Login)</label>
                    <input type="text" id="user-name" name="username" required autocomplete="off">
                </div>
                <div class="form-group">
                    <label>Contraseña</label>
                    <input type="password" id="user-password" name="password" required autocomplete="new-password">
                </div>
                <div class="form-group">
                    <label>Rol</label>
                    <select id="user-role" name="role" required onchange="toggleUserClient()">
                        <option value="client">Cliente Local</option>
                        <option value="superadmin">Superadmin Global</option>
                    </select>
                </div>
                <div class="form-group" id="user-client-group">
                    <label>Cliente Asignado</label>
                    <select id="user-client" name="client_id"></select>
                </div>
                <div style="display:flex; justify-content:flex-end; gap:10px; margin-top:20px;">
                    <button type="button" class="btn btn-outline" onclick="closeModal('user-modal')">Cancelar</button>
                    <button type="submit" class="btn">Guardar</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Assistant Modal -->
    <div class="modal-overlay" id="assistant-modal">
        <div class="modal">
            <div class="modal-header">
                <h2 id="assistant-modal-title">Nuevo Asistente</h2>
                <button type="button" class="close-modal" onclick="closeModal('assistant-modal')"><i
                        class="fa-solid fa-xmark"></i></button>
            </div>
            <form id="assistant-form" onsubmit="submitAssistant(event)">
                <input type="hidden" id="assistant-id" name="id">
                <?php if ($is_superadmin): ?>
                    <div class="form-group">
                        <label>Cliente</label>
                        <select id="assistant-client" name="client_id" required></select>
                    </div>
                <?php endif; ?>
                <div class="form-group">
                    <label>Nombre del Asistente</label>
                    <input type="text" id="assistant-name" name="name" required placeholder="Ej. Soporte Ventas">
                </div>
                <div class="form-group">
                    <label>System Prompt (Instrucciones para la IA)</label>
                    <textarea id="assistant-prompt" name="system_prompt"
                        placeholder="Eres un asistente experto en..."></textarea>
                    <div class="form-help">Define su personalidad, formato de respuesta y reglas generales.</div>
                </div>

                <!-- AI Configuration Section -->
                <div style="border:1px solid var(--border);border-radius:8px;padding:16px;margin-top:8px;">
                    <h4
                        style="margin:0 0 14px 0;color:var(--accent);font-size:13px;text-transform:uppercase;letter-spacing:1px;">
                        <i class="fa-solid fa-microchip"></i> Configuración IA
                    </h4>
                    <div class="form-group">
                        <label>Modelo Gemini</label>
                        <select id="assistant-model" name="gemini_model">
                            <option value="gemini-2.5-flash">gemini-2.5-flash (estable, recomendado)</option>
                            <option value="gemini-1.5-flash">gemini-1.5-flash (clásico, muy rápido)</option>
                            <option value="gemini-1.5-pro">gemini-1.5-pro (inteligencia máxima, más lento)</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Estilo de Respuesta</label>
                        <select id="assistant-style" name="response_style">
                            <option value="concise">Conciso (2-3 oraciones, directo al punto)</option>
                            <option value="balanced" selected>Balanceado (completo sin excesos)</option>
                            <option value="detailed">Detallado (respuestas exhaustivas)</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Tokens Máximos: <strong id="max-tokens-display">1500</strong></label>
                        <input type="range" id="assistant-max-tokens" name="max_output_tokens" min="256" max="4096"
                            step="128" value="1500"
                            oninput="document.getElementById('max-tokens-display').textContent = this.value"
                            style="width:100%;accent-color:var(--accent);">
                        <div class="form-help">Mayor = respuestas más largas. ~750 tokens ≈ 500 palabras.</div>
                    </div>
                    <div class="form-group">
                        <label>Temperatura: <strong id="temp-display">0.7</strong></label>
                        <input type="range" id="assistant-temp" name="temperature" min="0" max="2" step="0.1"
                            value="0.7"
                            oninput="document.getElementById('temp-display').textContent = parseFloat(this.value).toFixed(1)"
                            style="width:100%;accent-color:var(--accent);">
                        <div class="form-help">0 = preciso y consistente &nbsp;|&nbsp; 2 = creativo e impredecible</div>
                    </div>
                    <div class="form-group" style="margin-bottom:0; display:flex; align-items:center; gap:12px;">
                        <label class="switch">
                            <input type="checkbox" id="assistant-voice-enabled" name="voice_enabled" checked>
                            <span class="slider round"></span>
                        </label>
                        <span style="font-size:14px;">Habilitar procesamiento de mensajes de voz</span>
                    </div>
                </div>

                <div style="display:flex; justify-content:flex-end; gap:10px; margin-top:20px;">
                    <button type="button" class="btn btn-outline"
                        onclick="closeModal('assistant-modal')">Cancelar</button>
                    <button type="submit" class="btn">Guardar</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Info Source Modal -->
    <div class="modal-overlay" id="info-modal">
        <div class="modal">
            <div class="modal-header">
                <h2 id="info-modal-title">Nueva Fuente de Información</h2>
                <button type="button" class="close-modal" onclick="closeModal('info-modal')"><i
                        class="fa-solid fa-xmark"></i></button>
            </div>
            <form id="info-form" onsubmit="submitInfo(event)" enctype="multipart/form-data">
                <input type="hidden" id="info-id" name="id">
                <input type="hidden" id="info-assistant-id" name="assistant_id">

                <div class="form-group">
                    <label>Tipo de Fuente</label>
                    <div style="display: flex; gap: 15px; margin-top: 5px;">
                        <label><input type="radio" name="type" value="text" checked onchange="toggleInfoType()">
                            Texto</label>
                        <label><input type="radio" name="type" value="link" onchange="toggleInfoType()"> Enlace
                            (URL)</label>
                        <label><input type="radio" name="type" value="file" onchange="toggleInfoType()"> Archivo (PDF,
                            TXT, Img ext)</label>
                    </div>
                </div>

                <div class="form-group">
                    <label>Título / Referencia</label>
                    <input type="text" id="info-title" name="title" required placeholder="Ej. Políticas de Devolución">
                </div>

                <div class="form-group" id="info-container-text">
                    <label>Contenido (Texto Largo)</label>
                    <textarea id="info-content" name="content_text" style="min-height:200px;"></textarea>
                    <div class="form-help">Pega aquí el texto que servirá como contexto base para que la IA responda
                        mejor.</div>
                </div>

                <div class="form-group" id="info-container-link" style="display:none;">
                    <label>URL / Enlace</label>
                    <input type="url" id="info-url" placeholder="https://ejemplo.com/pagina">
                    <div class="form-help">El sistema intentará extraer el texto de esta página web.</div>
                </div>

                <div class="form-group" id="info-container-file" style="display:none;">
                    <label>Archivo (Máx 500MB)</label>
                    <input type="file" id="info-file" name="file_upload"
                        accept=".txt,.pdf,.csv,.md,.csv,.jpg,.jpeg,.png,.webp">
                    <div class="form-help">El archivo se enviará a Gemini. Asegúrate de que no pese más de 500MB ni
                        contenga información extremadamente sensible permanente.</div>

                    <div id="upload-progress-container" style="display:none; margin-top: 15px;">
                        <div style="font-size: 12px; margin-bottom: 5px; color: var(--text-muted);"
                            id="upload-status-text">Subiendo archivo... 0%</div>
                        <div
                            style="width: 100%; background: rgba(255,255,255,0.1); border-radius: 10px; height: 10px; overflow: hidden;">
                            <div id="upload-progress-bar"
                                style="width: 0%; height: 100%; background: var(--primary); transition: width 0.2s;">
                            </div>
                        </div>
                    </div>
                </div>

                <div style="display:flex; justify-content:flex-end; gap:10px; margin-top:20px;">
                    <button type="button" class="btn btn-outline" onclick="closeModal('info-modal')">Cancelar</button>
                    <button type="submit" class="btn" id="btn-submit-info">Guardar</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Rule Modal -->
    <div class="modal-overlay" id="rule-modal">
        <div class="modal">
            <div class="modal-header">
                <h2 id="rule-modal-title">Nueva Regla</h2>
                <button type="button" class="close-modal" onclick="closeModal('rule-modal')"><i
                        class="fa-solid fa-xmark"></i></button>
            </div>
            <form id="rule-form" onsubmit="submitRule(event)">
                <input type="hidden" id="rule-id" name="id">
                <input type="hidden" id="rule-assistant-id" name="assistant_id">
                <div class="form-group">
                    <label>Categoría</label>
                    <input type="text" id="rule-category" name="category" required value="general">
                </div>
                <div class="form-group">
                    <label>Consultas (separadas por |)</label>
                    <input type="text" id="rule-queries" name="queries" required>
                </div>
                <div class="form-group">
                    <label>Respuesta</label>
                    <textarea id="rule-replies" name="replies" required></textarea>
                </div>
                <div style="display:flex; justify-content:flex-end; gap:10px; margin-top:20px;">
                    <button type="button" class="btn btn-outline" onclick="closeModal('rule-modal')">Cancelar</button>
                    <button type="submit" class="btn">Guardar</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Template Modal -->
    <div class="modal-overlay" id="tpl-edit-modal">
        <div class="modal" style="max-width:480px;">
            <div class="modal-header">
                <h3><i class="fa-solid fa-pen"></i> Editar Plantilla</h3>
                <button class="close-modal" onclick="closeModal('tpl-edit-modal')">&times;</button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label>Nombre de la plantilla <span style="color:var(--primary)">*</span></label>
                    <input type="text" id="edit-tpl-name" class="form-control" placeholder="Ej: Contrato de Arriendo" maxlength="120">
                </div>
                <div class="form-group">
                    <label>Descripci&oacute;n <span style="color:var(--text-muted);font-size:12px;">(opcional)</span></label>
                    <textarea id="edit-tpl-desc" class="form-control" rows="3" placeholder="Describe para qu&eacute; sirve esta plantilla..."></textarea>
                </div>
                <div style="display:flex;gap:10px;justify-content:flex-end;margin-top:20px;">
                    <button class="btn btn-outline" onclick="closeModal('tpl-edit-modal')">Cancelar</button>
                    <button class="btn" id="btn-save-tpl-edit" onclick="submitEditTemplate()"><i class="fa-solid fa-floppy-disk"></i> Guardar Cambios</button>
                </div>
            </div>
        </div>
    </div>

    <!-- PDF Template Modal -->
    <div class="modal-overlay" id="pdf-template-modal">
        <div class="modal">
            <div class="modal-header">
                <h2>Cargar Nueva Plantilla PDF</h2>
                <button class="close-modal" onclick="closeModal('pdf-template-modal')">&times;</button>
            </div>
            <form id="pdf-template-form" onsubmit="submitPDFTemplate(event)">
                <!-- STEP 1: Upload -->
                <div id="pdf-template-step-1">
                    <?php if ($is_superadmin): ?>
                        <div class="form-group">
                            <label>Cliente</label>
                            <select name="client_id" required>
                                <?php
                                require_once 'db.php';
                                $q = mysqli_query($conn, "SELECT id, name FROM clients");
                                while ($c = mysqli_fetch_assoc($q))
                                    echo "<option value='{$c['id']}'>{$c['name']}</option>";
                                ?>
                            </select>
                        </div>
                    <?php endif; ?>
                    <div class="form-group">
                        <label>Nombre de la Plantilla (Ej: Factura Simple)</label>
                        <input type="text" name="name" id="pdf-tpl-name" placeholder="Factura, Recibo, etc." required>
                    </div>
                    <div class="form-group">
                        <label>Descripción / Propósito</label>
                        <textarea name="description" placeholder="Ej: Para enviar después de una asesoría técnica..." rows="2"></textarea>
                    </div>
                    <div class="form-group">
                        <label>Archivo de Plantilla (.txt o .pdf)</label>
                        <input type="file" name="template_file" id="pdf-tpl-file" accept=".txt,.pdf" required>
                        <p class="form-help">Sube un archivo de texto con <code>{{marcadores}}</code> o un PDF para que la IA lo analice.</p>
                    </div>
                    <div style="text-align:right; margin-top:20px;">
                        <button type="button" class="btn btn-outline" onclick="closeModal('pdf-template-modal')">Cancelar</button>
                        <button type="button" class="btn" id="btn-analyze-pdf" onclick="analyzePDFTemplate()">Analizar y Continuar <i class="fa-solid fa-arrow-right"></i></button>
                    </div>
                </div>

                <!-- STEP 2: Review Fields -->
                <div id="pdf-template-step-2" style="display:none;">
                    <h3 style="font-size:15px; margin-bottom:10px; color:var(--primary);">Campos Identificados</h3>
                    <p style="font-size:13px; color:var(--text-muted); margin-bottom:15px;">Estos son los datos que el asistente pedirá al usuario. Puedes agregar o quitar campos.</p>
                    
                    <div class="form-group">
                        <label>Agregar Nuevo Campo</label>
                        <div style="display:flex; gap:10px;">
                            <input type="text" id="new-field-name" placeholder="Ej: monto_total">
                            <button type="button" class="btn btn-outline" onclick="addFieldTag()" style="padding:0 15px;"><i class="fa-solid fa-plus"></i></button>
                        </div>
                    </div>

                    <div class="field-tag-container" id="placeholder-tags">
                        <!-- Tags populated via JS -->
                    </div>

                    <input type="hidden" name="placeholders" id="final-placeholders">
                    <input type="hidden" name="temp_file_path" id="pdf-temp-path">

                    <div style="text-align:right; margin-top:25px; display:flex; justify-content:space-between; align-items:center;">
                        <button type="button" class="btn btn-outline" onclick="showUploadStep()"><i class="fa-solid fa-arrow-left"></i> Atrás</button>
                        <button type="submit" class="btn" id="btn-submit-pdf-template">Confirmar y Guardar Plantilla</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- LEAD MODAL -->
    <div class="modal-overlay" id="lead-modal">
        <div class="modal">
            <div class="modal-header">
                <h2 id="lead-modal-title">Nuevo Prospecto</h2>
                <button class="close-btn" onclick="closeModal('lead-modal')">&times;</button>
            </div>
            <form id="lead-form" onsubmit="submitLead(event)">
                <input type="hidden" name="id" id="lead-id">
                <div class="form-group">
                    <label>Nombre del Prospecto</label>
                    <input type="text" name="name" id="lead-name" required>
                </div>
                <div class="form-group">
                    <label>Teléfono</label>
                    <input type="text" name="phone" id="lead-phone">
                </div>
                <div class="form-group">
                    <label>Email</label>
                    <input type="email" name="email" id="lead-email">
                </div>
                <div class="form-group">
                    <label>Estado</label>
                    <select name="status" id="lead-status">
                        <option value="nuevo">Nuevo</option>
                        <option value="contactado">Contactado</option>
                        <option value="interesado">Interesado</option>
                        <option value="cerrado">Cerrado (Vendido)</option>
                        <option value="descartado">Descartado</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Notas / Requerimiento</label>
                    <textarea name="notes" id="lead-notes" rows="4"></textarea>
                </div>
                <div style="text-align:right; margin-top:20px;">
                    <button type="button" class="btn btn-outline" onclick="closeModal('lead-modal')">Cancelar</button>
                    <button type="submit" class="btn">Guardar Prospecto</button>
                </div>
            </form>
        </div>
    </div>

    <!-- SUPPORT CHAT MODAL -->
    <div class="modal-overlay" id="support-chat-modal">
        <div class="modal" style="max-width: 500px; height: 600px; display: flex; flex-direction: column; padding: 0; overflow: hidden;">
            <div class="modal-header" style="padding: 15px 20px; background: var(--sidebar-bg);">
                <h2 style="font-size: 16px;"><i class="fa-solid fa-robot" style="color:var(--success);"></i> Manual Virtual de Skale IA</h2>
                <button class="close-btn" onclick="closeModal('support-chat-modal')">&times;</button>
            </div>
            <div id="support-chat-messages" style="flex: 1; overflow-y: auto; padding: 20px; display: flex; flex-direction: column; gap: 12px; background: rgba(0,0,0,0.1);">
                <div style="background: var(--glass-bg); padding: 12px 16px; border-radius: 12px 12px 12px 2px; align-self: flex-start; max-width: 85%; border: 1px solid var(--glass-border); font-size: 14px; line-height: 1.5;">
                    ¡Hola! Soy tu asistente de soporte de <b>Skale IA</b>. <br><br>
                    Puedo ayudarte a entender cómo configurar asistentes, conectar WhatsApp, crear plantillas canvas o cualquier otra duda sobre la plataforma. <br><br>
                    <b>¿En qué puedo ayudarte hoy?</b>
                </div>
            </div>
            <div style="padding: 15px 20px; border-top: 1px solid var(--glass-border); display: flex; gap: 10px; background: var(--sidebar-bg);">
                <input type="text" id="support-chat-input" placeholder="Pregunta sobre Skale IA..." style="flex: 1; background: var(--input-bg); border: 1px solid var(--glass-border); color: white; padding: 10px 15px; border-radius: 8px; outline: none;" onkeypress="if(event.key==='Enter') sendSupportMessage()">
                <button class="btn" onclick="sendSupportMessage()" style="padding: 0 15px;"><i class="fa-solid fa-paper-plane"></i></button>
            </div>
        </div>
    </div>

    <script>
        // Global State
        window.IS_SUPERADMIN = <?php echo $is_superadmin ? 'true' : 'false'; ?>;
        window.id_client_sesion = <?php echo json_encode($_SESSION['client_id'] ?? null); ?>;
        // OPT-2: CSRF token for all mutating API calls
        const CSRF_TOKEN = "<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES); ?>";

        function openSupportChat() {
            if (!SUPPORT_ASSISTANT_ID) {
                alert('El asistente de soporte aún no ha sido configurado. Por favor, ejecuta migrate12.php.');
                return;
            }
            $('#support-chat-modal').fadeIn(200);
            $('#support-chat-input').focus();
        }

        function sendSupportMessage() {
            const input = $('#support-chat-input');
            const text = input.val().trim();
            if (!text) return;

            const chatMessages = $('#support-chat-messages');
            
            // Append User Message
            chatMessages.append(`
                <div style="background: var(--primary); color: #000; padding: 12px 16px; border-radius: 12px 12px 2px 12px; align-self: flex-end; max-width: 85%; font-size: 14px; line-height: 1.5; font-weight:500;">
                    ${text.replace(/\n/g, '<br>')}
                </div>
            `);
            
            input.val('');
            chatMessages.scrollTop(chatMessages[0].scrollHeight);

            // Show Typing Indicator
            const typingId = 'typing-' + Date.now();
            chatMessages.append(`
                <div id="${typingId}" style="background: var(--glass-bg); padding: 12px 16px; border-radius: 12px 12px 12px 2px; align-self: flex-start; max-width: 85%; border: 1px solid var(--glass-border); font-size: 14px;">
                    <i class="fa-solid fa-spinner fa-spin"></i> Escribiendo...
                </div>
            `);
            chatMessages.scrollTop(chatMessages[0].scrollHeight);

            // Send to API
            $.ajax({
                url: 'message.php',
                type: 'POST',
                dataType: 'json',
                data: {
                    text: text,
                    assistant_id: SUPPORT_ASSISTANT_ID,
                    csrf_token: CSRF_TOKEN
                },
                success: function(res) {
                    $(`#${typingId}`).remove();
                    const reply = res.reply || 'No recibí respuesta del asistente.';
                    chatMessages.append(`
                        <div style="background: var(--glass-bg); padding: 12px 16px; border-radius: 12px 12px 12px 2px; align-self: flex-start; max-width: 85%; border: 1px solid var(--glass-border); font-size: 14px; line-height: 1.5;">
                            ${reply.replace(/\n/g, '<br>')}
                        </div>
                    `);
                    chatMessages.scrollTop(chatMessages[0].scrollHeight);
                },
                error: function() {
                    $(`#${typingId}`).remove();
                    chatMessages.append(`
                        <div style="background: rgba(239, 68, 68, 0.1); color: var(--danger); padding: 12px 16px; border-radius: 12px; align-self: center; font-size: 13px; border: 1px solid var(--danger);">
                            Error de conexión con el soporte.
                        </div>
                    `);
                }
            });
        }
        window.currentAssistantId = null;
        window.clientsCache = [];
        window.assistantsCache = [];
 
        // Auto-refresh Manager for Dashboard
        const DashboardAutoRefresh = {
            interval: null,
            activeTab: null,
            init: function(tabId) {
                this.stop();
                this.activeTab = tabId;
                let delay = 0;
                let func = null;

                if (tabId === 'logs-tab') { delay = 10000; func = loadLogs; }
                else if (tabId === 'leads-tab') { delay = 30000; func = loadLeads; }
                else if (tabId === 'appointments-tab') { delay = 60000; func = loadAppointments; }
                else if (tabId === 'pdf-generated-tab') { delay = 20000; func = loadGeneratedDocs; }
                else if (tabId === 'dashboard-tab') { delay = 300000; func = () => { loadStats(); initChart(); }; }

                if (func && delay > 0) {
                    this.interval = setInterval(() => {
                        // Only refresh if an assistant is selected and tab is visible
                        if (currentAssistantId && document.visibilityState === 'visible') {
                            func();
                        }
                    }, delay);
                }
            },
            stop: function() {
                if (this.interval) {
                    clearInterval(this.interval);
                    this.interval = null;
                }
            }
        };

        // --- Marketing Campaigns Logic ---
        function openCampaignModal(id = null) {
            const form = document.getElementById('campaign-form');
            form.reset();
            document.getElementById('campaign-id').value = id || '';
            $('#campaign-lead-notice').hide();
            $('#campaign-leads-selector').hide();
            
            if (window.IS_SUPERADMIN) {
                const clientSelect = document.getElementById('campaign-client-id');
                if (clientSelect && window.clientsCache) {
                    let opts = '<option value="">Selecciona un cliente...</option>';
                    window.clientsCache.forEach(c => {
                        opts += `<option value="${c.id}">${c.name}</option>`;
                    });
                    clientSelect.innerHTML = opts;
                }
            }

            if (id) {
                // Load for edit (not implemented in API yet)
            }
            
            $('#campaign-modal').fadeIn(200);
        }

        function toggleSelectAllCampaignLeads(checked) {
            $('.campaign-lead-checkbox').prop('checked', checked);
        }

        function toggleCampaignLeadSelection() {
            const type = document.getElementById('campaign-target-type').value;
            const selector = $('#campaign-leads-selector');
            const notice = $('#campaign-lead-notice');
            
            if (type === 'selected') {
                selector.slideDown(200);
                notice.fadeIn(200);
                loadCampaignLeads(); // Fetch leads for the current client
            } else {
                selector.slideUp(200);
                notice.fadeOut(200);
            }
        }

        function loadCampaignLeads() {
            const tbody = $('#campaign-leads-table tbody');
            tbody.html('<tr><td colspan="4" style="text-align:center; padding:20px;"><i class="fa-solid fa-spinner fa-spin"></i> Cargando prospectos...</td></tr>');
            
            let cid = window.IS_SUPERADMIN ? document.getElementById('campaign-client-id').value : getClientIdForAPI();
            if (!cid && !window.IS_SUPERADMIN) cid = window.id_client_sesion;

            if (!cid) {
                tbody.html('<tr><td colspan="4" style="text-align:center; padding:20px; color:var(--text-muted);">Selecciona un cliente para ver sus prospectos.</td></tr>');
                return;
            }

            $.get(`api.php?action=leads_list&client_id=${cid}`, function(res) {
                if (res.status === 'success' && res.data.length > 0) {
                    let html = '';
                    res.data.forEach(l => {
                        let contact = l.phone || l.email || '---';
                        html += `
                            <tr>
                                <td><input type="checkbox" class="campaign-lead-checkbox" value="${l.id}"></td>
                                <td>${l.name}</td>
                                <td>${contact}</td>
                                <td><span class="badge" style="font-size:10px; padding:2px 6px;">${l.status}</span></td>
                            </tr>
                        `;
                    });
                    tbody.html(html);
                } else {
                    tbody.html('<tr><td colspan="4" style="text-align:center; padding:20px; color:var(--text-muted);">No se encontraron prospectos para este cliente.</td></tr>');
                }
            });
        }

        function submitCampaign(e) {
            e.preventDefault();
            const fd = new FormData(e.target);
            fd.append('csrf_token', CSRF_TOKEN);
            
            let cid = window.IS_SUPERADMIN ? document.getElementById('campaign-client-id').value : getClientIdForAPI();
            if (cid) fd.append('client_id', cid);

            // GATHER SELECTED LEADS IF TYPE IS SELECTED
            const type = document.getElementById('campaign-target-type').value;
            if (type === 'selected') {
                const selectedLeads = [];
                $('.campaign-lead-checkbox:checked').each(function() {
                    selectedLeads.push($(this).val());
                });
                if (selectedLeads.length === 0) {
                    alert('Por favor selecciona al menos un prospecto para esta campaña.');
                    return;
                }
                fd.append('lead_ids', selectedLeads.join(','));
            }

            $.ajax({
                url: 'api.php?action=campaigns_create',
                type: 'POST',
                data: fd,
                processData: false,
                contentType: false,
                dataType: 'json',
                success: function(res) {
                    if (res.status === 'success') {
                        closeModal('campaign-modal');
                        loadCampaigns();
                    } else {
                        alert('Error: ' + res.message);
                    }
                }
            });
        }

        function loadCampaigns() {
            let cid = getClientIdForAPI();
            let u = `api.php?action=campaigns_list`;
            if (cid) u += '&client_id=' + cid;
            $.get(u, function(res) {
                const tbody = $('#campaigns-table tbody');
                tbody.empty();
                if (res.status === 'success') {
                    res.data.forEach(c => {
                        const targetLabel = c.target_type === 'all' ? '<span class="badge" style="background:#3b82f6;">Todos</span>' : '<span class="badge" style="background:#8b5cf6;">Seleccionados</span>';
                        let statusHtml = '';
                        if (c.status === 'pending') statusHtml = '<span class="status-badge status-offline">Pendiente</span>';
                        else if (c.status === 'sent') statusHtml = '<span class="status-badge status-online">Enviada</span>';
                        else statusHtml = '<span class="status-badge status-error">Error</span>';

                        tbody.append(`
                            <tr>
                                <td>${c.id}</td>
                                <td><b>${c.name}</b></td>
                                <td>${targetLabel}</td>
                                <td>${statusHtml}</td>
                                <td>${c.sent_at || '---'}</td>
                                <td>
                                    <div class="actions">
                                        ${c.status === 'pending' ? `<button class="btn btn-sm btn-success" onclick="sendCampaign(${c.id})" title="Enviar ahora"><i class="fa-solid fa-paper-plane"></i></button>` : ''}
                                        <button class="btn btn-sm btn-danger" onclick="deleteCampaign(${c.id})"><i class="fa-solid fa-trash"></i></button>
                                    </div>
                                </td>
                            </tr>
                        `);
                    });
                }
            });
        }

        function deleteCampaign(id) {
            if (!confirm('¿Seguro que quieres eliminar esta campaña?')) return;
            $.post('api.php?action=campaigns_delete', { id, csrf_token: CSRF_TOKEN }, function(res) {
                if (res.status === 'success') loadCampaigns();
            });
        }

        function sendCampaign(id) {
            if (!currentAssistantId) {
                alert('Debes seleccionar un asistente (vincular WhatsApp) para realizar el envío.');
                return;
            }
            
            // Get selected leads if target_type is selected
            const selectedLeads = [];
            $('.lead-checkbox:checked').each(function() {
                selectedLeads.push($(this).val());
            });

            if (!confirm('¿Confirmas el envío masivo de esta campaña por WhatsApp?')) return;

            const btn = event.currentTarget;
            const originalHtml = btn.innerHTML;
            btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i>';
            btn.disabled = true;

            $.post('api.php?action=campaigns_send', { 
                id, 
                assistant_id: currentAssistantId, 
                lead_ids: selectedLeads.join(','),
                csrf_token: CSRF_TOKEN 
            }, function(res) {
                btn.innerHTML = originalHtml;
                btn.disabled = false;
                if (res.status === 'success') {
                    alert(`Éxito: ${res.sent} mensajes enviados. Errores: ${res.failed}`);
                    loadCampaigns();
                } else {
                    alert('Error: ' + res.message);
                }
            });
        }

        // --- Lead Selection Logic ---
        function toggleSelectAllLeads(checked) {
            $('.lead-checkbox').prop('checked', checked);
        }

        // Configure jQuery to automatically include CSRF token in all POST requests
        $.ajaxSetup({
            beforeSend: function(xhr, settings) {
                if (settings.type === 'POST' || settings.type === 'post') {
                    if (typeof settings.data === 'string') {
                        settings.data += '&csrf_token=' + encodeURIComponent(CSRF_TOKEN);
                    } else if (settings.data instanceof FormData) {
                        settings.data.append('csrf_token', CSRF_TOKEN);
                    } else if (typeof settings.data === 'object' && settings.data !== null) {
                        settings.data.csrf_token = CSRF_TOKEN;
                    }
                }
            }
        });

        $(document).ready(function () {
            // Setup Tabs
            $('.nav-tab').on('click', function () {
                $('.nav-tab').removeClass('active'); $(this).addClass('active');
                const target = $(this).data('target');
                $('.tab-content').removeClass('active'); $('#' + target).addClass('active');
                 if (target === 'pdf-templates-tab') loadPDFTemplates();
                 if (target === 'pdf-generated-tab') loadGeneratedDocs();
                 if (target === 'leads-tab') loadLeads();
                 if (target === 'campaigns-tab') loadCampaigns();
                 if (target === 'integrations-tab') {
                     loadDriveStatus();
                     reloadWhatsAppIntegration();
                 }
                 // Start/Stop automated refresh based on tab
                 DashboardAutoRefresh.init(target);
             });

            // Handle Global Assistant Change
            $('#global-assistant-select').on('change', function () {
                currentAssistantId = $(this).val();
                 let url = currentAssistantId ? 'index.php?assistant=' + currentAssistantId : 'index.php';
                 $('#btn-chat-link').attr('href', url);
                 reloadAssistantDependantViews();
                 // Re-init refresh for the current tab if needed
                 if (DashboardAutoRefresh.activeTab) DashboardAutoRefresh.init(DashboardAutoRefresh.activeTab);
             });

            // Initial Loads
            loadClients();
            if (IS_SUPERADMIN) {
                setTimeout(loadUsers, 500); // ensure clientsCache is loaded
            }
            loadPDFTemplates();
            loadAssistants(true); // true = also reload select
            DashboardAutoRefresh.init('dashboard-tab');

            // Sidebar Toggle for Mobile
            $('#mobile-toggle, #sidebar-overlay').on('click', function () {
                $('#sidebar, #sidebar-overlay').toggleClass('active');
            });

            // Close sidebar when clicking a link on mobile
            $('.sidebar .nav-tab').on('click', function () {
                if ($(window).width() <= 1024) {
                    $('#sidebar, #sidebar-overlay').removeClass('active');
                }
            });
        });

        function getClientIdForAPI() {
            if (IS_SUPERADMIN && currentAssistantId) {
                let ast = assistantsCache.find(a => a.id == currentAssistantId);
                return ast ? ast.client_id : null;
            }
            return null; // backend will use session client_id if client
        }

        function reloadAssistantDependantViews() {
            loadStats();
            initChart();
            loadInfoSources();
            loadRules();
            syncVoiceToggleState();
            loadLogs();
            loadLeads();
            loadCalendarSettings();
            reloadWhatsAppIntegration();
            loadAppointments();
            loadGeneratedDocs();
        }

        function syncVoiceToggleState() {
            if (!currentAssistantId) {
                $('#voice-settings-panel').css('opacity', '0.5').css('pointer-events', 'none');
                return;
            }
            $('#voice-settings-panel').css('opacity', '1').css('pointer-events', 'auto');
            const ast = assistantsCache.find(a => a.id == currentAssistantId);
            if (ast) {
                const isEnabled = ast.voice_enabled == 1;
                $('#integration-voice-toggle').prop('checked', isEnabled);
                $('#voice-status-text').text(isEnabled ? 'Habilitados' : 'Deshabilitados');
            }
        }

        function toggleVoiceFromIntegration(enabled) {
            if (!currentAssistantId) return;
            const ast = assistantsCache.find(a => a.id == currentAssistantId);
            if (!ast) return;

            // Prepare save data (reuse assistant update logic)
            const data = {
                id: ast.id,
                name: ast.name,
                system_prompt: ast.system_prompt,
                gemini_model: ast.gemini_model,
                temperature: ast.temperature,
                max_output_tokens: ast.max_output_tokens,
                response_style: ast.response_style,
                voice_enabled: enabled ? 1 : 0
            };

            $('#voice-status-text').text('Guardando...');
            $.post('api.php?action=assistants_update', data, function (res) {
                if (res.status === 'success') {
                    ast.voice_enabled = enabled ? 1 : 0;
                    $('#voice-status-text').text(enabled ? 'Habilitados' : 'Deshabilitados');
                    // Show a small toast or notification if available, otherwise just silent success
                } else {
                    alert('Error al guardar configuración de voz');
                    syncVoiceToggleState();
                }
            }, 'json');
        }

        // --- Appointments ---
        function loadAppointments() {
            let cid = getClientIdForAPI();
            if (IS_SUPERADMIN && !cid) {
                $('#appointments-table tbody').html('<tr><td colspan="8" style="text-align:center; color:var(--text-muted);">Seleccione un asistente para ver sus reservas.</td></tr>');
                return;
            }
            $('#appointments-table tbody').html('<tr><td colspan="8" style="text-align:center;"><i class="fa-solid fa-spinner fa-spin"></i> Cargando...</td></tr>');
            let url = 'api.php?action=appointments_list' + (cid ? '&client_id=' + cid : '');
            if (currentAssistantId) url += '&assistant_id=' + currentAssistantId;
            $.get(url, function (res) {
                if (res.status === 'success') {
                    if (!res.data.length) {
                        $('#appointments-table tbody').html('<tr><td colspan="8" style="text-align:center; color:var(--text-muted);">No hay reservas registradas.</td></tr>');
                        return;
                    }
                    let html = '';
                    res.data.forEach(a => {
                        let statusBadge = a.status === 'confirmed'
                            ? '<span class="badge success">Confirmada</span>'
                            : '<span class="badge failed">Cancelada</span>';
                        let cancelBtn = a.status === 'confirmed'
                            ? `<button class="btn btn-danger" style="padding:5px 10px; font-size:12px;" onclick="cancelAppointment(${a.id})"><i class="fa-solid fa-ban"></i> Cancelar</button>`
                            : '<span style="color:var(--text-muted); font-size:12px;">—</span>';
                        html += `<tr>
                            <td>${a.appointment_date}</td>
                            <td>${a.appointment_time.substring(0, 5)}</td>
                            <td><b>${a.user_name}</b></td>
                            <td>${a.user_email}</td>
                            <td>${a.user_phone}</td>
                            <td><span style="font-size:12px; color:var(--primary);">${a.assistant_name}</span></td>
                            <td>${statusBadge}</td>
                            <td>${cancelBtn}</td>
                        </tr>`;
                    });
                    $('#appointments-table tbody').html(html);
                } else {
                    $('#appointments-table tbody').html('<tr><td colspan="8" style="text-align:center; color:#ef4444;">' + (res.message || 'Error cargando reservas.') + '</td></tr>');
                }
            }, 'json');
        }

        function cancelAppointment(id) {
            if (!confirm('¿Cancelar esta reserva? Se eliminará también del Google Calendar del cliente.')) return;
            $.post('api.php?action=appointments_cancel', { id: id }, function (res) {
                alert(res.message || (res.status === 'success' ? 'Reserva cancelada.' : 'Error al cancelar.'));
                if (res.status === 'success') loadAppointments();
            }, 'json').fail(function () {
                alert('Error de red al cancelar la reserva.');
            });
        }

        function disconnectGoogle() {
            if (!confirm('¿Desconectar tu cuenta de Google? Perderás el acceso a Drive y Calendar hasta volver a conectarla.')) return;
            let cid = getClientIdForAPI();
            $.post('api_drive.php?action=disconnect' + (cid ? '&client_id=' + cid : ''), {}, function (res) {
                if (res.status === 'success') {
                    alert('Cuenta desconectada correctamente. Ahora puedes volver a conectar con los nuevos permisos.');
                    loadDriveStatus();
                    loadCalendarSettings();
                } else {
                    alert(res.message || 'Error al desconectar.');
                }
            }, 'json');
        }

        // --- Drive Integrations ---
        function loadDriveStatus() {
            let cid = getClientIdForAPI();
            if (IS_SUPERADMIN && !cid) {
                $('#drive-status-container').html('<p style="color:var(--text-muted)">Seleccione un Asistente en el menú superior para ver la integración de su cuenta.</p>');
                $('#drive-files-container').hide();
                $('#calendar-settings-container').hide();
                return;
            }
            $('#calendar-settings-container').show();

            let targetUrl = 'api_drive.php?action=status' + (cid ? '&client_id=' + cid : '');

            $.get(targetUrl, function (res) {
                if (res.status === 'success') {
                    if (res.connected) {
                        $('#drive-status-container').html(`
                            <div style="display:flex; align-items:center; gap:15px; flex-wrap:wrap;">
                                <i class="fa-brands fa-google-drive" style="font-size:32px; color:#10b981;"></i>
                                <div>
                                    <h3 style="color:#10b981; margin-bottom:5px;">Conectado a Google</h3>
                                    <p style="color:var(--text-muted); font-size:13px;">Tu cuenta está vinculada (Drive y Calendar). Puedes explorar y sincronizar archivos.</p>
                                </div>
                                <button class="btn btn-danger" style="margin-left:auto;" onclick="disconnectGoogle()">
                                    <i class="fa-solid fa-link-slash"></i> Desconectar
                                </button>
                            </div>
                        `);
                        $('#drive-files-container').show();
                        loadDriveFiles();
                    } else {
                        $('#drive-files-container').hide();
                        let authUrlTarget = 'api_drive.php?action=auth_url' + (cid ? '&client_id=' + cid : '');
                        $.get(authUrlTarget, function (urlRes) {
                            if (urlRes.status === 'success') {
                                $('#drive-status-container').html(`
                                    <div style="display:flex; align-items:center; gap:15px;">
                                        <i class="fa-brands fa-google" style="font-size:32px; color:var(--text-muted);"></i>
                                        <div>
                                            <h3 style="margin-bottom:10px;">Google Drive & Calendar Desconectado</h3>
                                            <a href="${urlRes.url}" class="btn" style="background:#4285F4;"><i class="fa-brands fa-google"></i> Conectar con Google</a>
                                        </div>
                                    </div>
                                `);
                            }
                        }, 'json');
                    }
                } else if (res.status === 'error') {
                    $('#drive-status-container').html(`<p style="color:#ef4444">${res.message}</p>`);
                }
            }, 'json');
        }

        // --- WhatsApp Management ---
        let whatsappPollInterval = null;
        let whatsappConnecting = false; // Flag: true while waiting for QR/connection after clicking connect

        function updateWhatsAppUI() {
            if (!currentAssistantId) {
                $('#whatsapp-container').hide();
                return;
            }
            $('#whatsapp-container').show();

            $.get('api.php?action=whatsapp_status&assistant_id=' + currentAssistantId, function (res) {
                const status = res.status;
                let statusHtml = '';

                if (status === 'connected') {
                    whatsappConnecting = false;
                    statusHtml = '<span class="badge success"><i class="fa-solid fa-check"></i> Conectado</span>';
                    $('#btn-whatsapp-connect').hide();
                    $('#btn-whatsapp-disconnect').show();
                    $('#whatsapp-qr-container').hide();
                    // Stop polling once connected
                    if (whatsappPollInterval) { clearInterval(whatsappPollInterval); whatsappPollInterval = null; }
                } else if (status === 'connecting') {
                    statusHtml = '<span class="badge" style="background:rgba(245,158,11,0.15); color:#f59e0b;"><i class="fa-solid fa-spinner fa-spin"></i> Esperando escaneo de QR...</span>';
                    $('#btn-whatsapp-connect').hide();
                    $('#btn-whatsapp-disconnect').show();
                    $('#whatsapp-qr-container').show();
                    loadWhatsAppQR();
                } else if (status === 'offline') {
                    whatsappConnecting = false;
                    statusHtml = '<span class="badge failed"><i class="fa-solid fa-circle-exclamation"></i> Servicio Offline</span>';
                    $('#btn-whatsapp-connect').hide();
                    $('#btn-whatsapp-disconnect').hide();
                    $('#whatsapp-qr-container').hide();
                } else {
                    // status === 'disconnected'
                    if (whatsappConnecting) {
                        // Still waiting for the WhatsApp service to initialize and generate the QR
                        // Keep polling — do not stop the interval
                        statusHtml = '<span class="badge" style="background:rgba(245,158,11,0.15); color:#f59e0b;"><i class="fa-solid fa-spinner fa-spin"></i> Iniciando sesión...</span>';
                        $('#btn-whatsapp-connect').hide();
                        $('#btn-whatsapp-disconnect').show();
                        $('#whatsapp-qr-container').hide();
                    } else {
                        statusHtml = '<span class="badge failed">Desconectado</span>';
                        $('#btn-whatsapp-connect').show();
                        $('#btn-whatsapp-disconnect').hide();
                        $('#whatsapp-qr-container').hide();
                        // Only stop polling when truly disconnected and not waiting for init
                        if (whatsappPollInterval) { clearInterval(whatsappPollInterval); whatsappPollInterval = null; }
                    }
                }

                $('#whatsapp-status-display').html('Estado: ' + statusHtml);
            }, 'json');
        }

        function loadWhatsAppQR() {
            $.get('api.php?action=whatsapp_qr&assistant_id=' + currentAssistantId, function (res) {
                if (res.status === 'qr' && res.qr) {
                    $('#whatsapp-qr-img').attr('src', res.qr);
                    $('#whatsapp-qr-container').show();
                } else if (res.status === 'connected') {
                    updateWhatsAppUI();
                }
            }, 'json');
        }

        $('#btn-whatsapp-connect').on('click', function () {
            const btn = $(this);
            btn.prop('disabled', true).html('<i class="fa-solid fa-spinner fa-spin"></i> Iniciando...');

            // Mark as connecting and start polling IMMEDIATELY, before API response
            whatsappConnecting = true;
            if (whatsappPollInterval) clearInterval(whatsappPollInterval);
            whatsappPollInterval = setInterval(updateWhatsAppUI, 3000);

            $.post('api.php?action=whatsapp_connect', { assistant_id: currentAssistantId }, function (res) {
                btn.prop('disabled', false).html('<i class="fa-solid fa-link"></i> Vincular WhatsApp');
                if (res.status === 'initializing' || res.status === 'connecting') {
                    updateWhatsAppUI();
                } else {
                    // Connection failed to start
                    whatsappConnecting = false;
                    if (whatsappPollInterval) { clearInterval(whatsappPollInterval); whatsappPollInterval = null; }
                    alert(res.message || 'Error al iniciar conexión');
                }
            }, 'json').fail(function () {
                btn.prop('disabled', false).html('<i class="fa-solid fa-link"></i> Vincular WhatsApp');
                whatsappConnecting = false;
                if (whatsappPollInterval) { clearInterval(whatsappPollInterval); whatsappPollInterval = null; }
                alert('Error de red al intentar conectar.');
            });
        });

        $('#btn-whatsapp-disconnect').on('click', function () {
            if (!confirm('¿Seguro que quieres desvincular este asistente de WhatsApp?')) return;

            const btn = $(this);
            btn.prop('disabled', true).html('<i class="fa-solid fa-spinner fa-spin"></i> Desvinculando...');

            $.post('api.php?action=whatsapp_disconnect', { assistant_id: currentAssistantId }, function (res) {
                btn.prop('disabled', false).html('<i class="fa-solid fa-link-slash"></i> Desvincular');
                whatsappConnecting = false;
                updateWhatsAppUI();
            }, 'json');
        });

        function reloadWhatsAppIntegration() {
            whatsappConnecting = false;
            if (whatsappPollInterval) { clearInterval(whatsappPollInterval); whatsappPollInterval = null; }
            updateWhatsAppUI();
        }

        function loadDriveFiles(folderId = 'root', folderName = 'Mi Unidad') {
            $('#drive-files-table tbody').html('<tr><td colspan="3" style="text-align:center;"><i class="fa-solid fa-spinner fa-spin"></i> Cargando...</td></tr>');
            let cid = getClientIdForAPI();

            // Update Breadcrumb
            if (folderId === 'root') {
                $('#drive-breadcrumb').html('<span style="cursor:pointer; text-decoration:underline;" onclick="loadDriveFiles(\'root\')">Mi Unidad</span>');
            } else {
                // Simplified: just show current vs root
                $('#drive-breadcrumb').html('<span style="cursor:pointer; text-decoration:underline;" onclick="loadDriveFiles(\'root\')">Mi Unidad</span> / <b>' + folderName + '</b>');
            }

            $.get('api_drive.php?action=list_files&folder_id=' + folderId + (cid ? '&client_id=' + cid : ''), function (res) {
                if (res.files) {
                    let html = '';
                    res.files.forEach(f => {
                        let isFolder = f.mimeType === 'application/vnd.google-apps.folder';
                        let icon = isFolder ? '<i class="fa-solid fa-folder" style="color:#f59e0b"></i>' : '<i class="fa-brands fa-google-drive" style="color:#4285F4"></i>';
                        let actionBtn = isFolder
                            ? `<button class="btn btn-outline btn-sm" onclick="loadDriveFiles('${f.id}', '${f.name}')"><i class="fa-solid fa-folder-open"></i> Abrir</button>`
                            : `<button class="btn btn-outline btn-sm" onclick="syncDriveFile('${f.id}', '${f.name}', '${f.mimeType}')"><i class="fa-solid fa-cloud-arrow-down"></i> Sincronizar</button>`;

                        html += `<tr>
                             <td>${icon} <b>${f.name}</b></td>
                             <td><span style="font-size:12px; color:var(--text-muted);">${new Date(f.modifiedTime).toLocaleString()}</span></td>
                             <td>${actionBtn}</td>
                          </tr>`;
                    });
                    $('#drive-files-table tbody').html(html || '<tr><td colspan="3" style="text-align:center;">Carpeta vacía o sin documentos compatibles.</td></tr>');
                } else {
                    $('#drive-files-table tbody').html('<tr><td colspan="3" style="text-align:center; color:#ef4444;">Error cargando archivos.</td></tr>');
                }
            }, 'json');
        }

        function syncDriveFile(fileId, fileName, mimeType) {
            if (!currentAssistantId) {
                alert("Primero selecciona un Asistente en la parte superior ('Asistente Activo') al cual quieres sincronizar este archivo, o entra a un asistente específico.");
                return;
            }
            let cid = getClientIdForAPI();
            if (confirm(`¿Sincronizar "${fileName}" al Asistente actual? (Esto creará una nueva Fuente de Información)`)) {
                let btn = $(event.currentTarget);
                let originalText = btn.html();
                btn.html('<i class="fa-solid fa-spinner fa-spin"></i> Sincronizando...').prop('disabled', true);

                let reqData = { file_id: fileId, file_name: fileName, mime_type: mimeType, assistant_id: currentAssistantId };
                if (cid) reqData.client_id = cid;

                $.post('api_drive.php?action=sync_file', reqData, function (res) {
                    btn.html(originalText).prop('disabled', false);
                    if (res.status === 'success') {
                        alert(`¡Sincronización exitosa! ${fileName} subido a Gemini.`);
                        loadInfoSources();
                    } else {
                        alert(res.message || 'Error desconocido al sincronizar.');
                    }
                }, 'json').fail(function () {
                    btn.html(originalText).prop('disabled', false);
                    alert("Error de red o procesamiento al sincronizar desde Drive.");
                });
            }
        }

        // --- Calendar Settings ---
        function loadCalendarSettings() {
            let cid = getClientIdForAPI();
            if (IS_SUPERADMIN && !cid) return; // Wait for selection

            $.get('api.php?action=calendar_settings_get' + (cid ? '&client_id=' + cid : ''), function (res) {
                if (res.status === 'success' && res.data) {
                    let d = res.data;
                    $('#cal-client-id').val(d.client_id);
                    $('#cal-start-time').val(d.start_time);
                    $('#cal-end-time').val(d.end_time);
                    $('#cal-duration').val(d.slot_duration_minutes);
                    $('#cal-timezone').val(d.timezone);

                    // Specific Calendar ID
                    loadGoogleCalendars(d.calendar_id);

                    // Checkboxes
                    $('#calendar-settings-form input[type=checkbox]').prop('checked', false);
                    if (d.available_days) {
                        d.available_days.split(',').forEach(day => {
                            $(`#calendar-settings-form input[value="${day}"]`).prop('checked', true);
                        });
                    }
                }
            }, 'json');
        }

        function loadGoogleCalendars(selectedId) {
            let cid = getClientIdForAPI();
            $.get('api_drive.php?action=list_calendars' + (cid ? '&client_id=' + cid : ''), function (res) {
                if (res.items) {
                    let html = '';
                    res.items.forEach(c => {
                        let sel = (c.id === selectedId) ? 'selected' : '';
                        html += `<option value="${c.id}" ${sel}>${c.summary} ${c.primary ? '(Principal)' : ''}</option>`;
                    });
                    $('#cal-id').html(html);
                }
            }, 'json');
        }

        function createNewCalendar() {
            let summary = prompt("Nombre para el nuevo calendario:", "Asistente Skale IA");
            if (!summary) return;

            let cid = getClientIdForAPI();
            $.post('api_drive.php?action=create_calendar' + (cid ? '&client_id=' + cid : ''), { summary: summary }, function (res) {
                if (res.id) {
                    alert("Calendario creado exitosamente.");
                    loadGoogleCalendars(res.id);
                } else {
                    // Show detailed error from Google API if available
                    let errorMsg = "Error creando calendario.";
                    if (res.error && res.error.message) {
                        errorMsg += "\n\nDetalle: " + res.error.message;
                        if (res.error.status === 'PERMISSION_DENIED' || res.error.code === 403) {
                            errorMsg += "\n\n⚠️ Sin permisos suficientes. Para crear calendarios nuevos, debes desconectar tu cuenta de Google y volver a conectarla para otorgar el permiso necesario.";
                        }
                    }
                    alert(errorMsg);
                }
            }, 'json').fail(function (xhr) {
                alert("Error de red o respuesta inválida al crear el calendario.");
            });
        }

        function submitCalendarSettings(e) {
            e.preventDefault();
            let btn = $('#btn-save-calendar');
            let originalText = btn.html();
            btn.html('<i class="fa-solid fa-spinner fa-spin"></i> Guardando...').prop('disabled', true);

            let formData = $('#calendar-settings-form').serialize();
            let cid = getClientIdForAPI();
            if (IS_SUPERADMIN && cid) formData += '&client_id=' + cid;

            $.post('api.php?action=calendar_settings_update', formData, function (res) {
                btn.html(originalText).prop('disabled', false);
                if (res.status === 'success') {
                    alert('Configuración de calendario guardada correctamente.');
                } else {
                    alert(res.message || 'Error guardando.');
                }
            }, 'json').fail(function () {
                btn.html(originalText).prop('disabled', false);
                alert('Error de red al intentar guardar.');
            });
        }

        // --- Clients ---
        function loadClients() {
            $.get('api.php?action=clients_list', function (res) {
                if (res.status === 'success') {
                    clientsCache = res.data;
                    let html = '';
                    let optHtml = '';
                    res.data.forEach(c => {
                        let clientJson = JSON.stringify(c).replace(/'/g, "&#39;");
                        let typeBadge = c.type === 'empresa' ? '<span class="badge success">Empresa</span>' : '<span class="badge">Particular</span>';
                        html += `<tr><td>${c.id}</td><td>${c.name}</td><td>${typeBadge}</td><td>${c.contact_email || '-'}</td><td>${c.created_at}</td>
                            <td><button class="btn btn-outline" onclick='editClient(${clientJson})'><i class="fa-solid fa-pen"></i></button>
                            <button class="btn btn-danger" onclick="deleteClient(${c.id})"><i class="fa-solid fa-trash"></i></button></td></tr>`;
                        optHtml += `<option value="${c.id}">${c.name}</option>`;
                    });
                    $('#clients-table tbody').html(html || '<tr><td colspan="5">No hay clientes.</td></tr>');
                    $('#assistant-client').html(optHtml);
                }
            }, 'json');
        }
        function toggleClientFields() {
            const type = $('#client-type').val();
            if (type === 'particular') {
                $('#fields-particular').show();
                $('#fields-empresa').hide();
                $('#client-name-particular').prop('required', true);
                $('#client-name-empresa').prop('required', false);
            } else {
                $('#fields-particular').hide();
                $('#fields-empresa').show();
                $('#client-name-particular').prop('required', false);
                $('#client-name-empresa').prop('required', true);
            }
        }
        function openClientModal() {
            $('#client-form')[0].reset();
            $('#client-id').val('');
            $('#client-modal-title').text('Nuevo Cliente');
            $('#password-hint').show();
            toggleClientFields();
            $('#client-modal').addClass('active');
        }
        function editClient(c) {
            $('#client-id').val(c.id);
            $('#client-type').val(c.type || 'particular');
            $('#client-email').val(c.contact_email);
            $('#client-phone').val(c.phone);
            $('#client-rut').val(c.rut);
            $('#client-address').val(c.address);
            $('#client-giro').val(c.business_line);
            $('#client-rep-name').val(c.representative_name);
            $('#client-rep-email').val(c.representative_email);
            $('#client-rep-phone').val(c.representative_phone);

            if (c.type === 'empresa') {
                $('#client-name-empresa').val(c.name);
            } else {
                $('#client-name-particular').val(c.name);
            }

            $('#client-modal-title').text('Editar Cliente');
            $('#password-hint').hide();
            toggleClientFields();
            $('#client-modal').addClass('active');
        }
        function submitClient(e) {
            e.preventDefault();
            // Sync the Master Name field
            const type = $('#client-type').val();
            const finalName = type === 'particular' ? $('#client-name-particular').val() : $('#client-name-empresa').val();
            $('#client-name').val(finalName);

            const action = $('#client-id').val() ? 'clients_update' : 'clients_create';
            $.post('api.php?action=' + action, $('#client-form').serialize(), function (res) {
                if (res.status === 'success') {
                    closeModal('client-modal');
                    loadClients();
                    if (action === 'clients_create') alert("Cliente creado exitosamente. Se ha generado un usuario con el email proporcionado y la clave admin123!");
                } else alert(res.message || 'Error');
            }, 'json');
        }
        function deleteClient(id) { if (confirm('¿Eliminar cliente? Se borrarán sus asistentes asociados.')) { $.post('api.php?action=clients_delete', { id }, res => { if (res.status === 'success') { loadClients(); loadAssistants(true); } else alert('Error'); }, 'json'); } }

        // --- Users ---
        function loadUsers() {
            if (!IS_SUPERADMIN) return;
            $.get('api.php?action=users_list', function (res) {
                if (res.status === 'success') {
                    let html = '';
                    res.data.forEach(u => {
                        let badgeClass = u.role === 'superadmin' ? 'badge success' : 'badge';
                        let userJson = JSON.stringify(u).replace(/'/g, "&#39;");
                        html += `<tr><td>${u.id}</td><td>${u.username}</td><td><span class="${badgeClass}">${u.role}</span></td><td>${u.client_name || '-'}</td><td>${u.created_at}</td>
                            <td><button class="btn btn-outline" onclick='editUser(${userJson})'><i class="fa-solid fa-pen"></i></button>
                            <button class="btn btn-danger" onclick="deleteUser(${u.id})"><i class="fa-solid fa-trash"></i></button></td></tr>`;
                    });
                    $('#users-table tbody').html(html || '<tr><td colspan="6">No hay usuarios asignables.</td></tr>');

                    let optHtml = '<option value="">(Ninguno / Global)</option>';
                    clientsCache.forEach(c => optHtml += `<option value="${c.id}">${c.name}</option>`);
                    $('#user-client').html(optHtml);
                }
            }, 'json');
        }
        function toggleUserClient() {
            if ($('#user-role').val() === 'superadmin') {
                $('#user-client-group').hide(); $('#user-client').val('');
            } else {
                $('#user-client-group').show();
            }
        }
        function openUserModal() {
            $('#user-form')[0].reset();
            $('#user-id').val('');
            $('#user-modal-title').text('Nuevo Usuario app');
            $('#user-password').prop('required', true);
            toggleUserClient();
            $('#user-modal').addClass('active');
        }
        function editUser(u) {
            $('#user-id').val(u.id);
            $('#user-name').val(u.username);
            $('#user-role').val(u.role);
            $('#user-client').val(u.client_id || '');
            $('#user-password').prop('required', false); // Optional on edit
            $('#user-modal-title').text('Editar Usuario');
            toggleUserClient();
            $('#user-modal').addClass('active');
        }
        function submitUser(e) {
            e.preventDefault();
            const action = $('#user-id').val() ? 'users_update' : 'users_create';
            $.post('api.php?action=' + action, $('#user-form').serialize(), function (res) {
                if (res.status === 'success') { closeModal('user-modal'); loadUsers(); } else alert(res.message || 'Error');
            }, 'json');
        }
        function deleteUser(id) { if (confirm('¿Eliminar cuenta permanentemente?')) { $.post('api.php?action=users_delete', { id }, res => { if (res.status === 'success') { loadUsers(); } else alert(res.message || 'Error'); }, 'json'); } }

        // --- Assistants ---
        function loadAssistants(updateSelects = false) {
            $.get('api.php?action=assistants_list', function (res) {
                if (res.status === 'success') {
                    assistantsCache = res.data;
                    let html = '';
                    let optHtml = '<option value="">Global (Todos)</option>';
                    res.data.forEach(a => {
                        let clientName = clientsCache.find(c => c.id == a.client_id)?.name || a.client_id;
                        html += `<tr><td>${a.id}</td><td><b>${a.name}</b></td><td>${clientName}</td><td><span style="font-size:11px">${(a.system_prompt || '').substring(0, 30)}...</span></td>
                            <td><button class="btn btn-outline" onclick='editAssistant(${JSON.stringify(a).replace(/'/g, "&#39;")})'><i class="fa-solid fa-pen"></i></button>
                            <button class="btn btn-danger" onclick="deleteAssistant(${a.id})"><i class="fa-solid fa-trash"></i></button></td></tr>`;
                        optHtml += `<option value="${a.id}">${a.name} (${clientName})</option>`;
                    });
                    $('#assistants-table tbody').html(html || '<tr><td colspan="5">No hay asistentes.</td></tr>');
                    if (updateSelects) {
                        let globalOpt = IS_SUPERADMIN ? '<option value="">Global (Todos)</option>' : '';
                        $('#global-assistant-select').html(globalOpt + optHtml);
                        $('#global-assistant-select').val(currentAssistantId);
                        reloadAssistantDependantViews();
                    }
                }
            }, 'json');
        }
        function openAssistantModal() { $('#assistant-form')[0].reset(); $('#assistant-id').val(''); $('#assistant-modal-title').text('Nuevo Asistente'); $('#assistant-modal').addClass('active'); }
        function editAssistant(a) {
            $('#assistant-id').val(a.id);
            $('#assistant-client').val(a.client_id);
            $('#assistant-name').val(a.name);
            $('#assistant-prompt').val(a.system_prompt);
            // AI Config fields
            $('#assistant-model').val(a.gemini_model || 'gemini-2.5-flash');
            $('#assistant-style').val(a.response_style || 'balanced');
            const maxTok = a.max_output_tokens || 1500;
            $('#assistant-max-tokens').val(maxTok);
            $('#max-tokens-display').text(maxTok);
            const temp = parseFloat(a.temperature || 0.7).toFixed(1);
            $('#assistant-temp').val(temp);
            $('#temp-display').text(temp);
            $('#assistant-voice-enabled').prop('checked', a.voice_enabled == 1);
            $('#assistant-modal-title').text('Editar Asistente');
            $('#assistant-modal').addClass('active');
        }
        function submitAssistant(e) {
            e.preventDefault();
            const action = $('#assistant-id').val() ? 'assistants_update' : 'assistants_create';
            let data = $('#assistant-form').serialize();
            // Handle checkbox for serialize
            if (!$('#assistant-voice-enabled').is(':checked')) {
                data += '&voice_enabled=0';
            } else {
                data += '&voice_enabled=1';
            }
            $.post('api.php?action=' + action, data, function (res) {
                if (res.status === 'success') { closeModal('assistant-modal'); loadAssistants(true); } else alert(res.message || 'Error');
            }, 'json');
        }
        function deleteAssistant(id) { if (confirm('¿Eliminar asistente? Se borrarán sus fuentes de información y reglas asociadas.')) { $.post('api.php?action=assistants_delete', { id }, res => { if (res.status === 'success') { loadAssistants(true); } else alert('Error'); }, 'json'); } }

        // --- Info Sources ---
        function loadInfoSources() {
            if (!currentAssistantId) { $('#info-table tbody').html('<tr><td colspan="4" style="text-align:center;">Seleccione un asistente en el menú superior para ver sus fuentes.</td></tr>'); return; }
            $.get('api.php?action=info_list&assistant_id=' + currentAssistantId, function (res) {
                if (res.status === 'success') {
                    let html = '';
                    res.data.forEach(i => {
                        let icon = '<i class="fa-solid fa-align-left"></i>';
                        if (i.type === 'link') icon = '<i class="fa-solid fa-link"></i>';
                        if (i.type === 'file') icon = '<i class="fa-solid fa-file"></i>';

                        html += `<tr><td>${i.id}</td><td>${icon} ${i.title}</td><td><span style="font-size:11px">${(i.content_text || '').substring(0, 50)}...</span></td>
                            <td><button class="btn btn-outline" onclick='editInfo(${JSON.stringify(i).replace(/'/g, "&#39;")})'><i class="fa-solid fa-pen"></i></button>
                            <button class="btn btn-danger" onclick="deleteInfo(${i.id})"><i class="fa-solid fa-trash"></i></button></td></tr>`;
                    });
                    $('#info-table tbody').html(html || '<tr><td colspan="4" style="text-align:center;">No hay fuentes.</td></tr>');
                }
            }, 'json');
        }
        function openInfoModal() {
            if (!currentAssistantId) { alert("Debes seleccionar un asistente primero en el selector superior."); return; }
            $('#info-form')[0].reset();
            $('#info-id').val('');
            $('#info-assistant-id').val(currentAssistantId);
            $('#info-modal-title').text('Nueva Fuente');
            $('input[name="type"]').prop('disabled', false); // Allow changing type on new
            toggleInfoType();
            $('#upload-progress-container').hide();
            $('#info-modal').addClass('active');
        }
        function editInfo(i) {
            $('#info-id').val(i.id);
            $('#info-assistant-id').val(i.assistant_id);
            $('#info-title').val(i.title);

            // Set radio button and disable changes on editing
            $('input[name="type"]').prop('checked', false);
            $(`input[name="type"][value="${i.type || 'text'}"]`).prop('checked', true);
            $('input[name="type"]').prop('disabled', true); // Cannot change type once created

            if (i.type === 'text') {
                $('#info-content').val(i.content_text);
            } else if (i.type === 'link') {
                // Extract URL if we stored it (not strictly necessary to edit as it was scraped)
                $('#info-content').val(i.content_text);
            } else if (i.type === 'file') {
                // Files cannot be "edited", only replacing text or must delete and reupload
                $('#info-content').val(i.content_text);
            }

            toggleInfoType();
            $('#upload-progress-container').hide();
            $('#info-modal-title').text('Editar Fuente');
            $('#info-modal').addClass('active');
        }

        function toggleInfoType() {
            const type = $('input[name="type"]:checked').val();
            $('#info-container-text, #info-container-link, #info-container-file').hide();

            if (type === 'text') {
                $('#info-container-text').show();
                $('#info-content').prop('required', true);
                $('#info-url').prop('required', false);
                $('#info-file').prop('required', false);
            } else if (type === 'link') {
                $('#info-container-link').show();
                $('#info-content').prop('required', false);
                $('#info-url').prop('required', true);
                $('#info-file').prop('required', false);
            } else if (type === 'file') {
                $('#info-container-file').show();
                $('#info-content').prop('required', false);
                $('#info-url').prop('required', false);
                if (!$('#info-id').val()) { // Only required if new
                    $('#info-file').prop('required', true);
                }
            }
        }

        function submitInfo(e) {
            e.preventDefault();
            const isUpdate = $('#info-id').val() !== '';
            const action = isUpdate ? 'info_update' : 'info_create';

            const formData = new FormData($('#info-form')[0]);
            const type = $('input[name="type"]:checked').val();

            if (type === 'link') {
                formData.set('content_text', $('#info-url').val());
            }

            $('#btn-submit-info').prop('disabled', true).text('Guardando...');
            $('#upload-progress-container').hide();

            // If file upload, show progress
            let ajaxSettings = {
                url: 'api.php?action=' + action,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                dataType: 'json',
                success: function (res) {
                    $('#btn-submit-info').prop('disabled', false).text('Guardar');
                    if (res.status === 'success') {
                        closeModal('info-modal');
                        loadInfoSources();
                    } else {
                        alert(res.message || 'Error');
                    }
                },
                error: function (xhr) {
                    $('#btn-submit-info').prop('disabled', false).text('Guardar');
                    let errorMsg = 'Error en la comunicación con el servidor.';
                    if (xhr.responseText) {
                        try {
                            let json = JSON.parse(xhr.responseText);
                            errorMsg = json.message || errorMsg;
                        } catch (e) {
                            errorMsg = "Respuesta del servidor: " + xhr.responseText.substring(0, 500);
                        }
                    }
                    alert(errorMsg);
                }
            };

            if (type === 'file' && !isUpdate) {
                $('#upload-progress-container').show();
                ajaxSettings.xhr = function () {
                    var xhr = new window.XMLHttpRequest();
                    xhr.upload.addEventListener("progress", function (evt) {
                        if (evt.lengthComputable) {
                            var percentComplete = Math.round((evt.loaded / evt.total) * 100);
                            $('#upload-progress-bar').css('width', percentComplete + '%');
                            if (percentComplete < 100) {
                                $('#upload-status-text').text(`Subiendo archivo... ${percentComplete}%`);
                            } else {
                                $('#upload-status-text').text(`Archivo subido. Procesando con Gemini... (Esto puede tomar un minuto)`);
                            }
                        }
                    }, false);
                    return xhr;
                };
            }

            $.ajax(ajaxSettings);
        }
        function deleteInfo(id) { if (confirm('¿Eliminar fuente?')) { $.post('api.php?action=info_delete', { id }, res => { if (res.status === 'success') { loadInfoSources(); } else alert('Error'); }, 'json'); } }

        // --- Rules ---
        function loadRules() {
            let u = 'api.php?action=list';
            if (currentAssistantId) u += '&assistant_id=' + currentAssistantId;
            $.get(u, function (res) {
                if (res.status === 'success') {
                    let html = '';
                    res.data.forEach(r => {
                        html += `<tr><td>#${r.id}</td><td><span class="badge">${r.category || 'general'}</span></td>
                            <td style="max-width:200px;word-break:break-all;">${r.queries}</td><td style="max-width:300px;">${r.replies}</td>
                            <td><button class="btn btn-outline" onclick='editRule(${JSON.stringify(r).replace(/'/g, "&#39;")})'><i class="fa-solid fa-pen"></i></button>
                            <button class="btn btn-danger" onclick="deleteRule(${r.id})"><i class="fa-solid fa-trash"></i></button></td></tr>`;
                    });
                    $('#rules-table tbody').html(html || '<tr><td colspan="5" style="text-align:center;">No hay reglas.</td></tr>');
                }
            }, 'json');
        }
        function openRuleModal() { $('#rule-form')[0].reset(); $('#rule-id').val(''); $('#rule-assistant-id').val(currentAssistantId); $('#rule-modal-title').text('Nueva Regla'); $('#rule-modal').addClass('active'); }
        function editRule(r) { $('#rule-id').val(r.id); $('#rule-assistant-id').val(r.assistant_id); $('#rule-category').val(r.category); $('#rule-queries').val(r.queries); $('#rule-replies').val(r.replies); $('#rule-modal-title').text('Editar Regla'); $('#rule-modal').addClass('active'); }
        function submitRule(e) {
            e.preventDefault();
            const action = $('#rule-id').val() ? 'update' : 'create';
            $.post('api.php?action=' + action, $('#rule-form').serialize(), function (res) {
                if (res.status === 'success') { closeModal('rule-modal'); loadRules(); loadStats(); } else alert(res.message || 'Error');
            }, 'json');
        }
        function deleteRule(id) { if (confirm('¿Eliminar regla?')) { $.post('api.php?action=delete', { id }, res => { if (res.status === 'success') { loadRules(); loadStats(); } else alert('Error'); }, 'json'); } }

        // --- Logs ---
        function loadLogs() {
            let u = 'api.php?action=logs';
            if (currentAssistantId) u += '&assistant_id=' + currentAssistantId;
            $.get(u, function (res) {
                if (res.status === 'success') {
                    let html = '';
                    res.data.forEach(l => {
                        let status = l.matched == 1 ? '<span class="badge success"><i class="fa-solid fa-check"></i> OK</span>' : '<span class="badge failed"><i class="fa-solid fa-xmark"></i> Fail</span>';
                        html += `<tr><td style="font-size:12px">${l.created_at}</td><td>${l.user_message}</td><td style="max-width:400px; font-size:12px">${l.bot_reply.substring(0, 80)}...</td><td>${status}</td></tr>`;
                    });
                    $('#logs-table tbody').html(html || '<tr><td colspan="4" style="text-align:center;">No hay interacciones registradas.</td></tr>');
                }
            }, 'json');
        }

        // --- Leads ---
        function loadLeads() {
            let cid = getClientIdForAPI();
            let u = `api.php?action=leads_list`;
            if (cid) u += '&client_id=' + cid;
            if (currentAssistantId) u += '&assistant_id=' + currentAssistantId;
            $.get(u, function (res) {
                if (res.status === 'success') {
                    let html = '';
                    res.data.forEach(l => {
                        let contact = (l.phone || '') + (l.phone && l.email ? ' / ' : '') + (l.email || '');
                        let captured = '';
                        if (l.captured_data) {
                            try {
                                let cd = JSON.parse(l.captured_data);
                                for (let key in cd) captured += `<small><b>${key}:</b> ${cd[key]}</small><br>`;
                            } catch (e) { captured = l.captured_data; }
                        }
                        let statusBadge = `<span class="badge" style="background:var(--primary); color:white;">${l.status}</span>`;
                        if (l.status === 'nuevo') statusBadge = `<span class="badge" style="background:#3b82f6;">Nuevo</span>`;
                        if (l.status === 'cerrado') statusBadge = `<span class="badge" style="background:#10b981;">Cerrado</span>`;
                        if (l.status === 'descartado') statusBadge = `<span class="badge" style="background:#ef4444;">Descartado</span>`;

                        html += `<tr>
                            <td><input type="checkbox" class="lead-checkbox" value="${l.id}"></td>
                            <td>#${l.id}</td>
                            <td><span style="font-size:11px;">${l.assistant_name || 'N/A'}</span></td>
                            <td><b>${l.name || 'S/N'}</b></td>
                            <td style="font-size:13px;">${contact || 'Sin contacto'}</td>
                            <td>${statusBadge}</td>
                            <td style="font-size:12px; max-width:200px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">${l.notes || ''}</td>
                            <td><span style="font-size:11px;">${l.created_at}</span></td>
                            <td>
                                <button class="btn btn-outline btn-sm" onclick='editLead(${JSON.stringify(l).replace(/'/g, "&#39;")})'><i class="fa-solid fa-pen"></i></button>
                                <button class="btn btn-danger btn-sm" onclick="deleteLead(${l.id})"><i class="fa-solid fa-trash"></i></button>
                            </td>
                        </tr>`;
                    });
                    $('#leads-table tbody').html(html || '<tr><td colspan="8" style="text-align:center;">No hay prospectos capturados.</td></tr>');
                }
            }, 'json');
        }
        function openLeadModal() { $('#lead-form')[0].reset(); $('#lead-id').val(''); $('#lead-modal-title').text('Nuevo Prospecto'); $('#lead-modal').addClass('active'); }
        function editLead(l) {
            $('#lead-id').val(l.id);
            $('#lead-name').val(l.name);
            $('#lead-phone').val(l.phone);
            $('#lead-email').val(l.email);
            $('#lead-status').val(l.status);
            $('#lead-notes').val(l.notes);
            $('#lead-modal-title').text('Editar Prospecto');
            $('#lead-modal').addClass('active');
        }
        function submitLead(e) {
            e.preventDefault();
            const action = $('#lead-id').val() ? 'leads_update' : 'leads_create';
            $.post('api.php?action=' + action, $('#lead-form').serialize(), function (res) {
                if (res.status === 'success') { closeModal('lead-modal'); loadLeads(); } else alert(res.message || 'Error');
            }, 'json');
        }
        function deleteLead(id) { if (confirm('¿Eliminar prospecto?')) { $.post('api.php?action=leads_delete', { id }, res => { if (res.status === 'success') loadLeads(); else alert('Error'); }, 'json'); } }
        function exportLeads() {
            let u = 'api.php?action=leads_export';
            if (currentAssistantId) u += '&assistant_id=' + currentAssistantId;
            window.location.href = u;
        }

        // --- Stats & Charts ---
        function loadStats() {
            let u = 'api.php?action=stats';
            if (currentAssistantId) u += '&assistant_id=' + currentAssistantId;
            $.get(u, function (res) {
                if (res.status === 'success') {
                    $('#stats-container').html(`
                        <div class="card"><div class="card-icon"><i class="fa-solid fa-book-open"></i></div><div class="card-info"><h3>${res.data.total_rules}</h3><p>Reglas / Contexto</p></div></div>
                        <div class="card"><div class="card-icon"><i class="fa-solid fa-comments"></i></div><div class="card-info"><h3>${res.data.total_interactions}</h3><p>Interacciones</p></div></div>
                        <div class="card"><div class="card-icon"><i class="fa-solid fa-bullseye"></i></div><div class="card-info"><h3>${res.data.accuracy}%</h3><p>Precisión</p></div></div>
                    `);
                }
            }, 'json');
        }

        let activityChart = null;
        function initChart() {
            let u = 'api.php?action=chart_data';
            if (currentAssistantId) u += '&assistant_id=' + currentAssistantId;
            $.get(u, function (res) {
                if (res.status === 'success') {
                    const canvas = document.getElementById('activityChart');
                    if (!canvas) return;
                    if (activityChart) activityChart.destroy();
                    activityChart = new Chart(canvas.getContext('2d'), {
                        type: 'line',
                        data: {
                            labels: res.labels,
                            datasets: [{ label: 'Interacciones', data: res.values, borderColor: '#8b5cf6', backgroundColor: 'rgba(139, 92, 246, 0.1)', borderWidth: 3, fill: true, tension: 0.4 }]
                        },
                        options: {
                            responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } },
                            scales: { y: { beginAtZero: true, grid: { color: 'rgba(255, 255, 255, 0.05)' } }, x: { grid: { display: false } } }
                        }
                    });
                }
            }, 'json');
        }

        // --- PDF Templates ---
        let _tplData = []; // cache for client-side filtering

        function loadPDFTemplates() {
            const grid = $('#tpl-grid');
            grid.html('<div class="tpl-empty"><i class="fa-solid fa-spinner fa-spin"></i><p>Cargando plantillas...</p></div>');
            let u = 'api.php?action=pdf_templates_list';
            const selClient = IS_SUPERADMIN ? '' : '&client_id=' + (id_client_sesion || '');
            $.get(u + selClient, function (res) {
                if (res.status === 'success') {
                    _tplData = res.data || [];
                    renderTplGrid(_tplData);
                } else {
                    grid.html('<div class="tpl-empty"><i class="fa-solid fa-circle-exclamation"></i><p>Error al cargar las plantillas.</p></div>');
                }
            }, 'json').fail(() => {
                grid.html('<div class="tpl-empty"><i class="fa-solid fa-wifi"></i><p>Error de conexión.</p></div>');
            });
        }

        function tplFileIcon(id) {
            const ext = String(id).toLowerCase().split('.').pop();
            if (ext === 'pdf')  return '<div class="tpl-icon pdf"><i class="fa-solid fa-file-pdf"></i></div>';
            if (ext === 'html') return '<div class="tpl-icon html"><i class="fa-solid fa-code"></i></div>';
            if (ext === 'txt')  return '<div class="tpl-icon txt"><i class="fa-solid fa-file-lines"></i></div>';
            return '<div class="tpl-icon sys"><i class="fa-solid fa-file"></i></div>';
        }

        function renderTplGrid(data) {
            const grid = $('#tpl-grid');
            if (!data.length) {
                grid.html('<div class="tpl-empty"><i class="fa-solid fa-folder-open"></i><p>No hay plantillas disponibles.<br><small>Crea tu primera plantilla con el botón "+ Nueva Plantilla".</small></p></div>');
                $('#tpl-count').text('');
                return;
            }
            let html = '';
            data.forEach(t => {
                const isCustom  = t.source === 'db' || t.source === 'canvas';
                const isCanvas  = t.source === 'canvas';
                const srcBadge  = isCanvas
                    ? '<span class="badge success tpl-source-badge" style="background:linear-gradient(135deg,#6366f1,#7c3aed);"><i class="fa-solid fa-pen-ruler" style="margin-right:4px;"></i>Canvas</span>'
                    : isCustom
                        ? '<span class="badge success tpl-source-badge">Personalizada</span>'
                        : '<span class="badge tpl-source-badge">Sistema</span>';

                // Field tags — show max 5 then +N
                const fields = (t.placeholders || []);
                const visFields = fields.slice(0, 5);
                const extraCount = fields.length - visFields.length;
                let tagsHtml = visFields.map(p => `<span class="tpl-field-tag">{{${p}}}</span>`).join('');
                if (extraCount > 0) tagsHtml += `<span class="tpl-field-tag more">+${extraCount} más</span>`;
                if (!fields.length) tagsHtml = '<small style="color:var(--text-muted);font-size:11px;">Sin campos detectados</small>';

                // Detect icon type from file path or id
                const iconHtml = tplFileIcon(t.id);

                // File ext label
                const ext = String(t.id).toLowerCase().split('.').pop().toUpperCase();

                // Actions
                let actions = '';
                if (isCanvas) {
                    actions = `
                        <button class="btn" style="background:linear-gradient(135deg,var(--primary),#7c3aed);font-size:12px;" onclick="openCanvasEditorTemplate('${t.db_id}')" title="Editar en Canvas"><i class="fa-solid fa-pen-ruler"></i> Editar Canvas</button>
                        <button class="btn btn-danger" onclick="deletePDFTemplate(${t.db_id},'${escHtml(t.name)}')" title="Eliminar"><i class="fa-solid fa-trash"></i></button>`;
                } else if (isCustom) {
                    actions = `
                        <button class="btn btn-outline" onclick="editPDFTemplate(${t.db_id},'${escHtml(t.name)}','${escHtml(t.description||'')}')" title="Editar"><i class="fa-solid fa-pen"></i> Editar</button>
                        <button class="btn btn-outline" onclick="downloadTemplate('${escHtml(t.id)}')" title="Descargar"><i class="fa-solid fa-download"></i></button>
                        <button class="btn btn-danger" onclick="deletePDFTemplate(${t.db_id},'${escHtml(t.name)}')" title="Eliminar"><i class="fa-solid fa-trash"></i></button>`;
                } else {
                    actions = '<span style="color:var(--text-muted);font-size:12px;"><i class="fa-solid fa-shield"></i> Plantilla del sistema</span>';
                }

                html += `
                <div class="tpl-card" data-name="${escHtml(t.name.toLowerCase())}" data-ext="${ext.toLowerCase()}" data-source="${t.source}">
                    ${srcBadge}
                    <div class="tpl-card-header">
                        ${iconHtml}
                        <div class="tpl-meta">
                            <h4 title="${escHtml(t.name)}">${escHtml(t.name)}</h4>
                            <div class="tpl-desc">${t.description ? escHtml(t.description) : '<em style="opacity:.4">Sin descripción</em>'}</div>
                        </div>
                    </div>
                    <div class="tpl-fields">${tagsHtml}</div>
                    <div class="tpl-actions">${actions}</div>
                </div>`;
            });
            grid.html(html);
            $('#tpl-count').text(data.length + ' plantilla' + (data.length !== 1 ? 's' : ''));
        }

        function filterTemplates() {
            const search = $('#tpl-search').val().toLowerCase().trim();
            const type   = $('#tpl-filter-type').val().toLowerCase();
            const source = $('#tpl-filter-source').val();
            const filtered = _tplData.filter(t => {
                const name  = (t.name || '').toLowerCase();
                const ext   = String(t.id).split('.').pop().toLowerCase();
                const matchName   = !search || name.includes(search);
                const matchType   = !type   || ext === type;
                const matchSource = !source || t.source === source;
                return matchName && matchType && matchSource;
            });
            renderTplGrid(filtered);
        }

        function escHtml(s) {
            return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#039;');
        }

        function downloadTemplate(tplId) {
            window.open('api.php?action=pdf_templates_download&id=' + encodeURIComponent(tplId), '_blank');
        }

        // Edit modal
        let _editingTplId = null;
        function editPDFTemplate(id, name, desc) {
            _editingTplId = id;
            $('#edit-tpl-name').val(name);
            $('#edit-tpl-desc').val(desc);
            $('#tpl-edit-modal').addClass('active');
        }

        function submitEditTemplate() {
            const name = $('#edit-tpl-name').val().trim();
            if (!name) { alert('El nombre es obligatorio'); return; }
            const desc = $('#edit-tpl-desc').val().trim();
            $('#btn-save-tpl-edit').prop('disabled', true).html('<i class="fa-solid fa-spinner fa-spin"></i> Guardando...');
            $.post('api.php?action=pdf_templates_rename', { id: _editingTplId, name, description: desc }, res => {
                $('#btn-save-tpl-edit').prop('disabled', false).html('Guardar Cambios');
                if (res.status === 'success') {
                    closeModal('tpl-edit-modal');
                    loadPDFTemplates();
                } else {
                    alert(res.message || 'Error al guardar');
                }
            }, 'json');
        }

        function deletePDFTemplate(id, name) {
            if (confirm(`¿Eliminar la plantilla "${name}"?\nEsta acción no se puede deshacer.`)) {
                $.post('api.php?action=pdf_templates_delete', { id }, res => {
                    if (res.status === 'success') loadPDFTemplates();
                    else alert(res.message || 'Error');
                }, 'json');
            }
        }

        function renamePDFTemplate(id, currentName) {
            // Legacy — now replaced by editPDFTemplate
            editPDFTemplate(id, currentName, '');
        }

        function openPDFTemplateModal() {
            $('#pdf-template-form')[0].reset();
            $('#pdf-template-step-1').show();
            $('#pdf-template-step-2').hide();
            $('#placeholder-tags').empty();
            $('#pdf-template-modal').addClass('active');
        }

        function showUploadStep() {
            $('#pdf-template-step-1').show();
            $('#pdf-template-step-2').hide();
        }

        function analyzePDFTemplate() {
            const name = $('#pdf-tpl-name').val();
            const file = $('#pdf-tpl-file')[0].files[0];
            if (!name || !file) {
                alert('Por favor ingresa el nombre y selecciona un archivo.');
                return;
            }

            const formData = new FormData($('#pdf-template-form')[0]);
            $('#btn-analyze-pdf').prop('disabled', true).html('<i class="fa-solid fa-spinner fa-spin"></i> Analizando...');

            $.ajax({
                url: 'api.php?action=pdf_templates_analyze',
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                dataType: 'json',
                success: function (res) {
                    $('#btn-analyze-pdf').prop('disabled', false).html('Analizar y Continuar <i class="fa-solid fa-arrow-right"></i>');
                    if (res.status === 'success') {
                        $('#pdf-temp-path').val(res.temp_file);
                        $('#placeholder-tags').empty();
                        if (!res.detected_fields || res.detected_fields.length === 0) {
                            $('#placeholder-tags').html('<p style="color:var(--text-muted); font-size:13px; font-style:italic; margin-bottom:10px;">No se detectaron campos automáticamente. Puedes agregarlos manualmente abajo con el botón (+).</p>');
                        } else {
                            res.detected_fields.forEach(f => addFieldTag(f));
                        }
                        $('#pdf-template-step-1').hide();
                        $('#pdf-template-step-2').show();
                    } else {
                        alert(res.message || 'Error analizando PDF');
                    }
                },
                error: function () {
                    $('#btn-analyze-pdf').prop('disabled', false).html('Analizar y Continuar <i class="fa-solid fa-arrow-right"></i>');
                    alert('Error de conexión al analizar el PDF.');
                }
            });
        }

        function addFieldTag(name = null) {
            const field = name || $('#new-field-name').val().trim();
            if (!field) return;
            const safeField = field.toLowerCase().replace(/[^a-z0-9_]/g, '_');
            
            // Check if already exists
            let exists = false;
            $('.field-tag span').each(function() { if($(this).text() === safeField) exists = true; });
            if (exists && !name) { alert('El campo ya existe'); return; }

            const tag = $(`<div class="field-tag"><span>${safeField}</span><i class="fa-solid fa-xmark" onclick="removeFieldTag(this)"></i></div>`);
            $('#placeholder-tags').append(tag);
            if (!name) $('#new-field-name').val('');
            updateFinalPlaceholders();
        }

        function removeFieldTag(el) {
            $(el).parent().remove();
            updateFinalPlaceholders();
        }

        function updateFinalPlaceholders() {
            const fields = [];
            $('.field-tag span').each(function() { fields.push($(this).text()); });
            $('#final-placeholders').val(JSON.stringify(fields));
        }

        function submitPDFTemplate(e) {
            e.preventDefault();
            updateFinalPlaceholders();
            const formData = $('#pdf-template-form').serialize();
            $('#btn-submit-pdf-template').prop('disabled', true).text('Guardando...');

            $.post('api.php?action=pdf_templates_save', formData, function (res) {
                $('#btn-submit-pdf-template').prop('disabled', false).text('Confirmar y Guardar Plantilla');
                if (res.status === 'success') {
                    closeModal('pdf-template-modal');
                    loadPDFTemplates();
                } else {
                    alert(res.message || 'Error al guardar');
                }
            }, 'json');
        }

        function loadGeneratedDocs() {
            let cid = getClientIdForAPI();
            let u = 'api.php?action=pdf_generated_list';
            if (cid) u += '&client_id=' + cid;
            if (currentAssistantId) u += '&assistant_id=' + currentAssistantId;

            $.get(u, function (res) {
                if (res.status === 'success') {
                    let html = '';
                    res.data.forEach(d => {
                        html += `<tr>
                            <td>${d.created_at}</td>
                            <td>${d.assistant_name || 'Desconocido'}</td>
                            <td>${d.template_name}</td>
                            <td><a href="${d.file_url}" target="_blank" class="btn btn-sm"><i class="fa-solid fa-file-pdf"></i> Ver PDF</a></td>
                            <td>
                                <button class="btn btn-danger btn-sm" onclick="deleteGeneratedDoc(${d.id})" title="Eliminar"><i class="fa-solid fa-trash"></i></button>
                            </td>
                        </tr>`;
                    });
                    $('#pdf-generated-table tbody').html(html || '<tr><td colspan="5" style="text-align:center;">No hay documentos generados.</td></tr>');
                } else {
                    $('#pdf-generated-table tbody').html('<tr><td colspan="5" style="text-align:center; color:red;">Error: ' + (res.message || 'Error desconocido') + '</td></tr>');
                }
            }, 'json').fail(function() {
                $('#pdf-generated-table tbody').html('<tr><td colspan="5" style="text-align:center; color:red;">Error de conexión con el servidor.</td></tr>');
            });
        }

        function deleteGeneratedDoc(id) {
            if (confirm('¿Eliminar este documento permanentemente?')) {
                $.post('api.php?action=pdf_generated_delete', { id }, function(res) {
                    if (res.status === 'success') loadGeneratedDocs();
                    else alert(res.message || 'Error');
                }, 'json');
            }
        }


        // --- Utilities ---
        function closeModal(id) {
            const el = $('#' + id);
            // campaign-modal and canvas-editor-modal use display:none/block directly
            if (el.css('display') !== 'none') {
                el.fadeOut(200).removeClass('active');
            } else {
                el.removeClass('active');
            }
        }

        function copyChatLink() {
            let url = window.location.origin + window.location.pathname.replace('admin.php', 'index.php');
            if (currentAssistantId) url += '?assistant=' + currentAssistantId;
            navigator.clipboard.writeText(url).then(() => {
                alert("Link copiado: " + url);
            });
        }

        // ==========================================
        // Service Worker Registration (PWA)
        // ==========================================
        if ('serviceWorker' in navigator) {
            navigator.serviceWorker.register('/sw.js').catch(err => console.warn('SW error:', err));
        }
    </script>
</body>

</html>