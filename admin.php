<?php
require_once 'auth.php';
check_auth();
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
                <button class="nav-tab" data-target="clients-tab"><i class="fa-solid fa-building"></i> Clientes</button>
                <button class="nav-tab" data-target="assistants-tab"><i class="fa-solid fa-robot"></i>
                    Asistentes</button>
                <button class="nav-tab" data-target="info-tab"><i class="fa-solid fa-database"></i> Fuentes de
                    Info</button>
                <button class="nav-tab" data-target="rules-tab"><i class="fa-solid fa-book"></i> Reglas Q&A</button>
                <button class="nav-tab" data-target="logs-tab"><i class="fa-solid fa-list"></i> Logs</button>
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
                    <label>Nombre del Cliente o Empresa</label>
                    <input type="text" id="client-name" name="name" required>
                </div>
                <div class="form-group">
                    <label>Email de Contacto</label>
                    <input type="email" id="client-email" name="contact_email">
                </div>
                <div style="display:flex; justify-content:flex-end; gap:10px; margin-top:20px;">
                    <button type="button" class="btn btn-outline" onclick="closeModal('client-modal')">Cancelar</button>
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
                <div class="form-group">
                    <label>Cliente</label>
                    <select id="assistant-client" name="client_id" required></select>
                </div>
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
        let clientsCache = [];
        let assistantsCache = [];
        let currentAssistantId = '';

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
            loadAssistants(true); // true = also reload select
        });

        function reloadAssistantDependantViews() {
            loadStats();
            initChart();
            loadInfoSources();
            loadRules();
            loadLogs();
        }

        // --- Clients ---
        function loadClients() {
            $.get('api.php?action=clients_list', function (res) {
                if (res.status === 'success') {
                    clientsCache = res.data;
                    let html = '';
                    let optHtml = '';
                    res.data.forEach(c => {
                        html += `<tr><td>${c.id}</td><td>${c.name}</td><td>${c.contact_email || '-'}</td><td>${c.created_at}</td>
                            <td><button class="btn btn-outline" onclick='editClient(${JSON.stringify(c).replace(/'/g, "&#39;")})'><i class="fa-solid fa-pen"></i></button>
                            <button class="btn btn-danger" onclick="deleteClient(${c.id})"><i class="fa-solid fa-trash"></i></button></td></tr>`;
                        optHtml += `<option value="${c.id}">${c.name}</option>`;
                    });
                    $('#clients-table tbody').html(html || '<tr><td colspan="5">No hay clientes.</td></tr>');
                    $('#assistant-client').html(optHtml);
                }
            }, 'json');
        }
        function openClientModal() { $('#client-form')[0].reset(); $('#client-id').val(''); $('#client-modal-title').text('Nuevo Cliente'); $('#client-modal').addClass('active'); }
        function editClient(c) { $('#client-id').val(c.id); $('#client-name').val(c.name); $('#client-email').val(c.contact_email); $('#client-modal-title').text('Editar Cliente'); $('#client-modal').addClass('active'); }
        function submitClient(e) {
            e.preventDefault();
            const action = $('#client-id').val() ? 'clients_update' : 'clients_create';
            $.post('api.php?action=' + action, $('#client-form').serialize(), function (res) {
                if (res.status === 'success') { closeModal('client-modal'); loadClients(); } else alert(res.message || 'Error');
            }, 'json');
        }
        function deleteClient(id) { if (confirm('¿Eliminar cliente? Se borrarán sus asistentes asociados.')) { $.post('api.php?action=clients_delete', { id }, res => { if (res.status === 'success') { loadClients(); loadAssistants(true); } else alert('Error'); }, 'json'); } }

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
                        $('#global-assistant-select').html(optHtml);
                        $('#global-assistant-select').val(currentAssistantId);
                        reloadAssistantDependantViews();
                    }
                }
            }, 'json');
        }
        function openAssistantModal() { $('#assistant-form')[0].reset(); $('#assistant-id').val(''); $('#assistant-modal-title').text('Nuevo Asistente'); $('#assistant-modal').addClass('active'); }
        function editAssistant(a) { $('#assistant-id').val(a.id); $('#assistant-client').val(a.client_id); $('#assistant-name').val(a.name); $('#assistant-prompt').val(a.system_prompt); $('#assistant-modal-title').text('Editar Asistente'); $('#assistant-modal').addClass('active'); }
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
                        } catch(e) {
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