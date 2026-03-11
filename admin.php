<?php
require_once 'auth.php';
check_auth();
$is_superadmin = ($_SESSION['role'] ?? 'client') === 'superadmin';
?>
<!DOCTYPE html>
<html lang="es" data-theme="dark">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SkaleBot - Panel Administrativo Múltiple</title>
    <!-- Google Fonts: Inter -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- FontAwesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <style>
        :root {
            --bg-color: #0f172a;
            --glass-bg: rgba(30, 41, 59, 0.7);
            --glass-border: rgba(255, 255, 255, 0.1);
            --primary: #8b5cf6;
            --primary-hover: #7c3aed;
            --danger: #ef4444;
            --success: #10b981;
            --text-main: #f8fafc;
            --text-muted: #cbd5e1;
            --td-border: rgba(255, 255, 255, 0.05);
            --input-bg: rgba(15, 23, 42, 0.6);
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
            padding: 40px 20px;
        }

        .glass-bg {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: -1;
            background: radial-gradient(circle at 15% 50%, rgba(139, 92, 246, 0.15), transparent 25%), radial-gradient(circle at 85% 30%, rgba(56, 189, 248, 0.15), transparent 25%);
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            flex-wrap: wrap;
            gap: 15px;
        }

        .header h1 {
            font-size: 28px;
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .header h1 i {
            color: var(--primary);
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

        .global-selector {
            display: flex;
            align-items: center;
            gap: 10px;
            background: var(--glass-bg);
            padding: 10px 20px;
            border-radius: 8px;
            border: 1px solid var(--glass-border);
        }

        .global-selector select {
            background: var(--input-bg);
            color: white;
            border: 1px solid var(--glass-border);
            padding: 8px 12px;
            border-radius: 6px;
            outline: none;
        }

        .dashboard-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .card {
            background: var(--glass-bg);
            backdrop-filter: blur(16px);
            border: 1px solid var(--glass-border);
            padding: 20px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .card-icon {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            background: rgba(139, 92, 246, 0.1);
            color: var(--primary);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
        }

        .card-info h3 {
            font-size: 24px;
            margin-bottom: 4px;
        }

        .card-info p {
            color: var(--text-muted);
            font-size: 14px;
        }

        .panel {
            background: var(--glass-bg);
            backdrop-filter: blur(16px);
            border: 1px solid var(--glass-border);
            border-radius: 12px;
            padding: 24px;
            margin-bottom: 30px;
            overflow: hidden;
        }

        .panel-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 14px;
        }

        th,
        td {
            text-align: left;
            padding: 12px 16px;
            border-bottom: 1px solid var(--td-border);
        }

        th {
            color: var(--text-muted);
            font-weight: 500;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .badge {
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
            background: rgba(139, 92, 246, 0.15);
            color: #c4b5fd;
        }

        .badge.failed {
            background: rgba(239, 68, 68, 0.15);
            color: #fca5a5;
        }

        .badge.success {
            background: rgba(16, 185, 129, 0.15);
            color: #6ee7b7;
        }

        .nav-tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            border-bottom: 1px solid var(--td-border);
            padding-bottom: 10px;
            overflow-x: auto;
        }

        .nav-tab {
            background: none;
            border: none;
            color: var(--text-muted);
            cursor: pointer;
            padding: 8px 16px;
            font-size: 15px;
            font-weight: 500;
            border-radius: 6px;
            transition: all 0.2s;
            white-space: nowrap;
        }

        .nav-tab.active {
            background: rgba(139, 92, 246, 0.15);
            color: var(--primary);
        }

        .nav-tab:hover:not(.active) {
            color: white;
            background: rgba(255, 255, 255, 0.05);
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
        }

        /* Modals */
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.6);
            backdrop-filter: blur(4px);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 1000;
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
            width: 600px;
            max-width: 90%;
            max-height: 90vh;
            overflow-y: auto;
            border-radius: 16px;
            padding: 24px;
            transform: translateY(20px);
            transition: all 0.3s;
        }

        .modal-overlay.active .modal {
            transform: translateY(0);
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .close-modal {
            background: none;
            border: none;
            color: var(--text-muted);
            font-size: 20px;
            cursor: pointer;
        }

        .form-group {
            margin-bottom: 16px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: var(--text-muted);
            font-size: 14px;
        }

        .form-group input,
        .form-group textarea,
        .form-group select {
            width: 100%;
            background: var(--input-bg);
            border: 1px solid var(--glass-border);
            color: white;
            padding: 12px;
            border-radius: 8px;
            outline: none;
            font-family: 'Inter', sans-serif;
        }

        .form-group input:focus,
        .form-group textarea:focus,
        .form-group select:focus {
            border-color: var(--primary);
        }

        .form-group textarea {
            resize: vertical;
            min-height: 100px;
        }

        .form-help {
            font-size: 12px;
            color: var(--text-muted);
            margin-top: 6px;
        }

        /* ===== RESPONSIVE: Mobile Admin Panel ===== */
        @media (max-width: 768px) {
            body {
                font-size: 14px;
            }

            /* Stack layout vertically on mobile */
            .container {
                flex-direction: column;
                height: auto;
                min-height: 100vh;
            }

            /* Sidebar becomes a top bar */
            .sidebar {
                width: 100%;
                height: auto;
                flex-direction: row;
                flex-wrap: wrap;
                padding: 10px;
                gap: 6px;
                justify-content: center;
                border-right: none;
                border-bottom: 1px solid var(--border);
            }

            .sidebar .logo {
                width: 100%;
                text-align: center;
                margin-bottom: 4px;
                font-size: 16px;
            }

            .sidebar .logo small {
                display: none;
            }

            .nav-btn {
                flex-direction: column;
                padding: 8px 10px;
                font-size: 11px;
                gap: 4px;
                flex: 1;
                min-width: 60px;
                max-width: 90px;
                border-radius: 10px;
                text-align: center;
            }

            .sidebar .sep,
            .sidebar p {
                display: none;
            }

            /* Main content fills the screen */
            .main-content {
                width: 100%;
                overflow-y: auto;
            }

            /* Global bar wraps nicely */
            .global-bar {
                flex-wrap: wrap;
                gap: 8px;
                padding: 10px 15px;
            }

            .global-bar select,
            .global-bar .btn {
                flex: 1;
                min-width: 120px;
            }

            /* Panel headers stack */
            .panel-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }

            /* Tables become scrollable */
            table {
                font-size: 12px;
                min-width: 480px;
            }

            th,
            td {
                padding: 8px 10px;
            }

            /* Modals go full screen */
            .modal {
                width: 100% !important;
                max-width: 100% !important;
                height: 100%;
                max-height: 100%;
                border-radius: 0;
                margin: 0;
                overflow-y: auto;
            }

            .modal-overlay {
                align-items: flex-end;
            }

            /* Stats cards wrap */
            .stats-grid {
                grid-template-columns: 1fr 1fr;
                gap: 10px;
            }

            .stat-card {
                padding: 14px;
            }

            .stat-number {
                font-size: 22px;
            }
        }

        @media (max-width: 400px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }

            .nav-btn {
                max-width: 70px;
                font-size: 10px;
            }
        }
    </style>
</head>

<body>
    <div class="glass-bg"></div>

    <div class="container">
        <div class="header">
            <h1><i class="fa-solid fa-robot"></i> SkaleBot Admin</h1>

            <div class="global-selector">
                <label for="global-assistant-select"><i class="fa-solid fa-headset"></i> Asistente Activo:</label>
                <select id="global-assistant-select">
                    <option value="">Global (Todos)</option>
                    <!-- Populated by JS -->
                </select>
                <button class="btn btn-outline" style="padding: 6px 10px;" onclick="copyChatLink()"
                    title="Copiar Link del Chat"><i class="fa-solid fa-link"></i></button>
            </div>

            <div class="header-actions" style="display:flex; gap:10px;">
                <a href="index.php" class="btn btn-outline" id="btn-chat-link"><i class="fa-solid fa-comment-dots"></i>
                    Ir al Chat</a>
                <a href="auth.php?action=logout" class="btn btn-danger"><i class="fa-solid fa-right-from-bracket"></i>
                    Salir</a>
            </div>
        </div>

        <div class="panel">
            <div class="nav-tabs">
                <button class="nav-tab active" data-target="dashboard-tab"><i class="fa-solid fa-chart-line"></i>
                    Resumen</button>
                <?php if ($is_superadmin): ?>
                    <button class="nav-tab" data-target="clients-tab"><i class="fa-solid fa-building"></i> Clientes</button>
                    <button class="nav-tab" data-target="users-tab"><i class="fa-solid fa-users"></i> Usuarios</button>
                <?php endif; ?>
                <button class="nav-tab" data-target="assistants-tab"><i class="fa-solid fa-robot"></i>
                    Asistentes</button>
                <button class="nav-tab" data-target="info-tab"><i class="fa-solid fa-database"></i> Fuentes de
                    Info</button>
                <button class="nav-tab" data-target="integrations-tab"><i class="fa-brands fa-google-drive"></i>
                    Integraciones</button>
                <button class="nav-tab" data-target="rules-tab"><i class="fa-solid fa-book"></i> Reglas Q&A</button>
                <button class="nav-tab" data-target="logs-tab"><i class="fa-solid fa-list"></i> Logs</button>
                <button class="nav-tab" data-target="appointments-tab"><i class="fa-regular fa-calendar-check"></i> Reservas</button>
            </div>

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
                <div class="panel" style="padding: 20px; border:none; background:rgba(0,0,0,0.2);">
                    <h3
                        style="margin-bottom: 20px; font-size: 14px; color: var(--text-muted); text-transform: uppercase;">
                        <i class="fa-solid fa-chart-line"></i> Actividad últimos 7 días
                    </h3>
                    <div style="height: 250px; position: relative;">
                        <canvas id="activityChart"></canvas>
                    </div>
                </div>
            </div>

            <?php if ($is_superadmin): ?>
                <!-- CLIENTS TAB -->
                <div id="clients-tab" class="tab-content">
                    <div class="panel-header">
                        <h2>Gestión de Clientes</h2>
                        <button class="btn" onclick="openClientModal()"><i class="fa-solid fa-plus"></i> Nuevo
                            Cliente</button>
                    </div>
                    <div style="overflow-x: auto;">
                        <table id="clients-table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Nombre</th>
                                    <th>Tipo</th>
                                    <th>Email Contacto</th>
                                    <th>Creado</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td colspan="5" style="text-align:center;">Cargando...</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- USERS TAB -->
                <div id="users-tab" class="tab-content">
                    <div class="panel-header">
                        <h2>Gestión de Usuarios App</h2>
                        <button class="btn" onclick="openUserModal()"><i class="fa-solid fa-plus"></i> Nuevo
                            Usuario</button>
                    </div>
                    <div style="overflow-x: auto;">
                        <table id="users-table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Usuario</th>
                                    <th>Rol</th>
                                    <th>Cliente Asignado</th>
                                    <th>Creado</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td colspan="6" style="text-align:center;">Cargando...</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>

            <!-- ASSISTANTS TAB -->
            <div id="assistants-tab" class="tab-content">
                <div class="panel-header">
                    <h2>Gestión de Asistentes</h2>
                    <button class="btn" onclick="openAssistantModal()"><i class="fa-solid fa-plus"></i> Nuevo
                        Asistente</button>
                </div>
                <div style="overflow-x: auto;">
                    <table id="assistants-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Nombre</th>
                                <th>Cliente ID</th>
                                <th>Prompt</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td colspan="5" style="text-align:center;">Cargando...</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- INFO SOURCES TAB -->
            <div id="info-tab" class="tab-content">
                <div class="panel-header">
                    <h2>Fuentes de Información (Contexto)</h2>
                    <button class="btn" onclick="openInfoModal()"><i class="fa-solid fa-plus"></i> Nueva Fuente</button>
                </div>
                <div style="overflow-x: auto;">
                    <table id="info-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Título</th>
                                <th>Contenido Corto</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td colspan="4" style="text-align:center;">Seleccione un asistente o cargando...</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- INTEGRATIONS TAB -->
            <div id="integrations-tab" class="tab-content">
                <div class="panel-header">
                    <h2>Integraciones (Google Drive & Calendar)</h2>
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

            <!-- RULES TAB -->
            <div id="rules-tab" class="tab-content">
                <div class="panel-header">
                    <h2>Reglas Exactas de Q&A</h2>
                    <button class="btn" onclick="openRuleModal()"><i class="fa-solid fa-plus"></i> Nueva Regla</button>
                </div>
                <div style="overflow-x: auto;">
                    <table id="rules-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Cat.</th>
                                <th>Consultas</th>
                                <th>Respuesta</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td colspan="5" style="text-align:center;">Cargando...</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- LOGS TAB -->
            <div id="logs-tab" class="tab-content">
                <div class="panel-header">
                    <h2>Últimas Interacciones</h2>
                </div>
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
                        <tbody>
                            <tr>
                                <td colspan="4" style="text-align:center;">Cargando...</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- APPOINTMENTS TAB -->
            <div id="appointments-tab" class="tab-content">
                <div class="panel-header">
                    <h2><i class="fa-regular fa-calendar-check" style="color:#f59e0b;"></i> Reservas del Asistente</h2>
                    <button class="btn btn-outline" onclick="loadAppointments()"><i class="fa-solid fa-rotate"></i> Actualizar</button>
                </div>
                <p style="color:var(--text-muted); font-size:13px; margin-bottom:15px;">Reservas creadas por el asistente en Google Calendar. Puedes cancelarlas desde aquí.</p>
                <div style="overflow-x: auto;">
                    <table id="appointments-table">
                        <thead>
                            <tr>
                                <th>Fecha</th>
                                <th>Hora</th>
                                <th>Cliente</th>
                                <th>Email</th>
                                <th>Teléfono</th>
                                <th>Asistente</th>
                                <th>Estado</th>
                                <th>Acción</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr><td colspan="8" style="text-align:center;">Seleccione un asistente o cargando...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

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
                            <option value="gemini-2.0-flash-001">gemini-2.0-flash-001 (versión estable anclada)</option>
                            <option value="gemini-1.5-flash">gemini-1.5-flash (alternativa estable)</option>
                            <option value="gemini-1.5-pro">gemini-1.5-pro (más capaz, más lento)</option>
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

    <script>
        // Global State
        const IS_SUPERADMIN = <?php echo $is_superadmin ? 'true' : 'false'; ?>;
        let currentAssistantId = null;
        let clientsCache = [];
        let assistantsCache = [];

        $(document).ready(function () {
            // Setup Tabs
            $('.nav-tab').on('click', function () {
                $('.nav-tab').removeClass('active'); $(this).addClass('active');
                $('.tab-content').removeClass('active'); $('#' + $(this).data('target')).addClass('active');
            });

            // Handle Global Assistant Change
            $('#global-assistant-select').on('change', function () {
                currentAssistantId = $(this).val();
                let url = currentAssistantId ? 'index.php?assistant=' + currentAssistantId : 'index.php';
                $('#btn-chat-link').attr('href', url);
                reloadAssistantDependantViews();
            });

            // Initial Loads
            loadClients();
            if (IS_SUPERADMIN) {
                setTimeout(loadUsers, 500); // ensure clientsCache is loaded
            }
            loadAssistants(true); // true = also reload select
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
            loadLogs();
            loadDriveStatus();
            loadCalendarSettings();
            loadAppointments();
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
            $.get(url, function(res) {
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
                            <td>${a.appointment_time.substring(0,5)}</td>
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
            $.post('api.php?action=appointments_cancel', { id: id }, function(res) {
                alert(res.message || (res.status === 'success' ? 'Reserva cancelada.' : 'Error al cancelar.'));
                if (res.status === 'success') loadAppointments();
            }, 'json').fail(function() {
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
            }, 'json').fail(function(xhr) {
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
            $('#assistant-modal-title').text('Editar Asistente');
            $('#assistant-modal').addClass('active');
        }
        function submitAssistant(e) {
            e.preventDefault();
            const action = $('#assistant-id').val() ? 'assistants_update' : 'assistants_create';
            $.post('api.php?action=' + action, $('#assistant-form').serialize(), function (res) {
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

        // --- Utilities ---
        function closeModal(id) { $('#' + id).removeClass('active'); }
        function copyChatLink() {
            let url = window.location.origin + window.location.pathname.replace('admin.php', 'index.php');
            if (currentAssistantId) url += '?assistant=' + currentAssistantId;
            navigator.clipboard.writeText(url).then(() => {
                alert("Link copiado: " + url);
            });
        }
    </script>
</body>

</html>