<?php
require_once 'auth.php';
check_auth();
?>
<!DOCTYPE html>
<html lang="es" data-theme="dark">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SkaleBot - Panel de Administración</title>
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
            background: radial-gradient(circle at 15% 50%, rgba(139, 92, 246, 0.15), transparent 25%),
                radial-gradient(circle at 85% 30%, rgba(56, 189, 248, 0.15), transparent 25%);
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

        /* Modal Styles */
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
            width: 500px;
            max-width: 90%;
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

        .modal-header h2 {
            font-size: 20px;
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
        .form-group textarea {
            width: 100%;
            background: var(--input-bg);
            border: 1px solid var(--glass-border);
            color: white;
            padding: 12px;
            border-radius: 8px;
            font-family: 'Inter', sans-serif;
            outline: none;
        }

        .form-group input:focus,
        .form-group textarea:focus {
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

        .nav-tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            border-bottom: 1px solid var(--td-border);
            padding-bottom: 10px;
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
    </style>
</head>

<body>
    <div class="glass-bg"></div>

    <div class="container">
        <div class="header">
            <h1><i class="fa-solid fa-robot"></i> SkaleBot Admin</h1>
            <div class="header-actions" style="display:flex; gap:10px;">
                <a href="index.php" class="btn btn-outline"><i class="fa-solid fa-arrow-left"></i> Volver al Chat</a>
                <a href="auth.php?action=logout" class="btn btn-danger"><i class="fa-solid fa-right-from-bracket"></i>
                    Salir</a>
            </div>
        </div>

        <div class="dashboard-cards" id="stats-container">
            <!-- Stats loaded via JS -->
            <div class="card">
                <div class="card-icon"><i class="fa-solid fa-spinner fa-spin"></i></div>
                <div class="card-info">
                    <h3>...</h3>
                    <p>Cargando stats...</p>
                </div>
            </div>
        </div>

        <div class="panel" style="padding: 20px;">
            <h3 style="margin-bottom: 20px; font-size: 16px; color: var(--text-muted); text-transform: uppercase;"><i
                    class="fa-solid fa-chart-line"></i> Actividad últimos 7 días</h3>
            <div style="height: 250px; position: relative;">
                <canvas id="activityChart"></canvas>
            </div>
        </div>

        <div class="panel">
            <div class="nav-tabs">
                <button class="nav-tab active" data-target="rules-tab"><i class="fa-solid fa-book"></i> Reglas de
                    Q&A</button>
                <button class="nav-tab" data-target="logs-tab"><i class="fa-solid fa-list"></i> Logs de
                    Conversación</button>
            </div>

            <!-- RULES TAB -->
            <div id="rules-tab" class="tab-content active">
                <div class="panel-header">
                    <h2>Base de Conocimiento</h2>
                    <button class="btn" onclick="openModal()"><i class="fa-solid fa-plus"></i> Nueva Regla</button>
                </div>
                <div style="overflow-x: auto;">
                    <table id="rules-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Categoría</th>
                                <th>Consultas (separadas por |)</th>
                                <th>Respuesta del Bot</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td colspan="5" style="text-align:center; padding:20px;">Cargando reglas...</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- LOGS TAB -->
            <div id="logs-tab" class="tab-content">
                <div class="panel-header">
                    <h2>Últimas 100 Interacciones</h2>
                </div>
                <div style="overflow-x: auto;">
                    <table id="logs-table">
                        <thead>
                            <tr>
                                <th>Fecha/Hora</th>
                                <th>Mensaje del Usuario</th>
                                <th>Respuesta Entregada</th>
                                <th>Estado</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td colspan="4" style="text-align:center; padding:20px;">Cargando logs...</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Rule Modal -->
    <div class="modal-overlay" id="rule-modal">
        <div class="modal">
            <div class="modal-header">
                <h2 id="modal-title">Nueva Regla</h2>
                <button class="close-modal" onclick="closeModal()"><i class="fa-solid fa-xmark"></i></button>
            </div>
            <form id="rule-form">
                <input type="hidden" id="rule-id" name="id">
                <div class="form-group">
                    <label>Categoría</label>
                    <input type="text" id="rule-category" name="category" placeholder="Ej. saludo, soporte, general"
                        required value="general">
                </div>
                <div class="form-group">
                    <label>Consultas Relacionadas (separadas por el símbolo |)</label>
                    <input type="text" id="rule-queries" name="queries" placeholder="Ej. hola|buenas|que tal" required>
                    <div class="form-help">Si el usuario escribe algo que contenga ALGUNA de estas palabras/frases, el
                        bot enviará esta respuesta.</div>
                </div>
                <div class="form-group">
                    <label>Respuesta del Chatbot</label>
                    <textarea id="rule-replies" name="replies" placeholder="¡Hola! ¿En qué te puedo ayudar?"
                        required></textarea>
                </div>
                <div style="display:flex; justify-content:flex-end; gap:10px; margin-top:20px;">
                    <button type="button" class="btn btn-outline" onclick="closeModal()">Cancelar</button>
                    <button type="submit" class="btn"><i class="fa-solid fa-save"></i> Guardar Regla</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        $(document).ready(function () {
            loadStats();
            loadRules();
            initChart();

            // Tabs Logic
            $('.nav-tab').on('click', function () {
                $('.nav-tab').removeClass('active');
                $(this).addClass('active');

                $('.tab-content').removeClass('active');
                $('#' + $(this).data('target')).addClass('active');

                if ($(this).data('target') === 'logs-tab') {
                    loadLogs();
                }
            });

            // Form Submit Logic
            $('#rule-form').on('submit', function (e) {
                e.preventDefault();
                const id = $('#rule-id').val();
                const action = id ? 'update' : 'create';

                $.ajax({
                    url: 'api.php?action=' + action,
                    type: 'POST',
                    data: $(this).serialize(),
                    dataType: 'json',
                    success: function (res) {
                        if (res.status === 'success') {
                            closeModal();
                            loadRules();
                            loadStats();
                        } else {
                            alert(res.message);
                        }
                    }
                });
            });
        });

        function loadStats() {
            $.get('api.php?action=stats', function (res) {
                if (res.status === 'success') {
                    const d = res.data;
                    $('#stats-container').html(`
                        <div class="card">
                            <div class="card-icon"><i class="fa-solid fa-book-open"></i></div>
                            <div class="card-info"><h3>${d.total_rules}</h3><p>Reglas Activas</p></div>
                        </div>
                        <div class="card">
                            <div class="card-icon"><i class="fa-solid fa-comments"></i></div>
                            <div class="card-info"><h3>${d.total_interactions}</h3><p>Interacciones Totales</p></div>
                        </div>
                        <div class="card">
                            <div class="card-icon"><i class="fa-solid fa-bullseye"></i></div>
                            <div class="card-info"><h3>${d.accuracy}%</h3><p>Tasa de Precisión</p></div>
                        </div>
                    `);
                }
            }, 'json');
        }

        let activityChart = null;

        function initChart() {
            $.get('api.php?action=chart_data', function (res) {
                if (res.status === 'success') {
                    const canvas = document.getElementById('activityChart');
                    if (!canvas) return;
                    const ctx = canvas.getContext('2d');
                    
                    if (activityChart) activityChart.destroy();
                    
                    activityChart = new Chart(ctx, {
                        type: 'line',
                        data: {
                            labels: res.labels,
                            datasets: [{
                                label: 'Interacciones',
                                data: res.values,
                                borderColor: '#8b5cf6',
                                backgroundColor: 'rgba(139, 92, 246, 0.1)',
                                borderWidth: 3,
                                fill: true,
                                tension: 0.4,
                                pointBackgroundColor: '#8b5cf6',
                                pointRadius: 4
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: { display: false }
                            },
                            scales: {
                                y: {
                                    beginAtZero: true,
                                    grid: { color: 'rgba(255, 255, 255, 0.05)' },
                                    ticks: { color: '#94a3b8', stepSize: 1 }
                                },
                                x: {
                                    grid: { display: false },
                                    ticks: { color: '#94a3b8' }
                                }
                            }
                        }
                    });
                }
            }, 'json');
        }

        function loadRules() {
            $.get('api.php?action=list', function (res) {
                if (res.status === 'success') {
                    let html = '';
                    res.data.forEach(r => {
                        html += `
                            <tr>
                                <td style="color:var(--text-muted)">#${r.id}</td>
                                <td><span class="badge">${r.category || 'general'}</span></td>
                                <td style="max-width:300px; word-wrap:break-word">${r.queries}</td>
                                <td style="max-width:400px; word-wrap:break-word">${r.replies}</td>
                                <td>
                                    <button class="btn btn-outline" style="padding:6px 10px; font-size:12px;" onclick='editRule(${JSON.stringify(r).replace(/'/g, "&#39;")})'><i class="fa-solid fa-pen"></i></button>
                                    <button class="btn btn-danger" style="padding:6px 10px; font-size:12px;" onclick="deleteRule(${r.id})"><i class="fa-solid fa-trash"></i></button>
                                </td>
                            </tr>
                        `;
                    });
                    $('#rules-table tbody').html(html || '<tr><td colspan="5" style="text-align:center;">No hay reglas creadas.</td></tr>');
                }
            }, 'json');
        }

        function loadLogs() {
            $.get('api.php?action=logs', function (res) {
                if (res.status === 'success') {
                    let html = '';
                    res.data.forEach(l => {
                        html += `
                            <tr>
                                <td style="color:var(--text-muted); font-size:12px;">${l.created_at}</td>
                                <td>${l.user_message}</td>
                                <td style="max-width:400px; font-size:13px; color:var(--text-muted)">${l.bot_reply.substring(0, 80)}${l.bot_reply.length > 80 ? '...' : ''}</td>
                                <td>
                                    ${l.matched == 1
                                ? '<span class="badge success"><i class="fa-solid fa-check"></i> Entendido</span>'
                                : '<span class="badge failed"><i class="fa-solid fa-xmark"></i> Fallido</span>'}
                                </td>
                            </tr>
                        `;
                    });
                    $('#logs-table tbody').html(html || '<tr><td colspan="4" style="text-align:center;">No hay interacciones registradas.</td></tr>');
                }
            }, 'json');
        }

        function openModal() {
            $('#rule-form')[0].reset();
            $('#rule-id').val('');
            $('#modal-title').text('Nueva Regla');
            $('#rule-modal').addClass('active');
        }

        function closeModal() {
            $('#rule-modal').removeClass('active');
        }

        function editRule(rule) {
            $('#rule-id').val(rule.id);
            $('#rule-category').val(rule.category);
            $('#rule-queries').val(rule.queries);
            $('#rule-replies').val(rule.replies);
            $('#modal-title').text('Editar Regla');
            $('#rule-modal').addClass('active');
        }

        function deleteRule(id) {
            if (confirm('¿Estás seguro de que deseas eliminar esta regla?')) {
                $.post('api.php?action=delete', { id: id }, function (res) {
                    if (res.status === 'success') {
                        loadRules();
                        loadStats();
                    } else {
                        alert(res.message);
                    }
                }, 'json');
            }
        }
    </script>
</body>

</html>