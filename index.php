<?php
require_once 'db.php';
// Start session to access CSRF token (generated on login)
if (session_status() === PHP_SESSION_NONE) session_start();
// Generate a CSRF token if it doesn't exist yet (for non-logged-in chat users)
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$assistant_id = isset($_GET['assistant']) && is_numeric($_GET['assistant']) ? intval($_GET['assistant']) : null;
$bot_name = "SkaleBot";
if ($assistant_id) {
    $ast_stmt = mysqli_prepare($conn, "SELECT name FROM assistants WHERE id = ?");
    mysqli_stmt_bind_param($ast_stmt, "i", $assistant_id);
    mysqli_stmt_execute($ast_stmt);
    $ast_res = mysqli_stmt_get_result($ast_stmt);
    if ($ast_row = mysqli_fetch_assoc($ast_res)) {
        $bot_name = htmlspecialchars($ast_row['name']);
    }
}
?>
<!DOCTYPE html>
<html lang="es" data-theme="dark">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>
        <?php echo $bot_name; ?> - Asistente IA
    </title>
    <!-- PWA Manifest & Theme -->
    <link rel="manifest" href="/manifest.json">
    <meta name="theme-color" content="#00d4ff">
    <meta name="mobile-web-app-capable" content="yes">
    <!-- iOS PWA Support -->
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="<?php echo $bot_name; ?>">
    <link rel="apple-touch-icon" href="/icons/apple-touch-icon.png">
    <link rel="icon" type="image/png" href="/icons/icon-192.png">
    <!-- Google Fonts: Inter + Outfit -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Outfit:wght@400;600;700;800&display=swap" rel="stylesheet">
    <!-- FontAwesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="style.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>

<body>

    <div class="glass-bg"></div>

    <div class="wrapper glass-panel">
        <div class="header">
            <div class="bot-info">
                <div class="bot-avatar">
                    <i class="fa-solid fa-robot"></i>
                    <span class="status-indicator"></span>
                </div>
                <div class="bot-details">
                    <div class="title">
                        <?php echo $bot_name; ?>
                    </div>
                    <div class="status-text">En línea</div>
                </div>
            </div>
            <div class="header-actions">
                <button id="clear-chat" title="Limpiar chat">
                    <i class="fa-solid fa-trash-can"></i>
                </button>
                <button id="theme-toggle" title="Cambiar tema">
                    <i class="fa-solid fa-moon"></i>
                </button>
                <button id="install-btn" title="Instalar App" style="display: none;">
                    <i class="fa-solid fa-download"></i>
                </button>
                <a href="admin.php" id="admin-btn" title="Panel de Administración">
                    <i class="fa-solid fa-gear"></i>
                </a>
            </div>
        </div>

        <div class="form" id="chat-box">
            <!-- Messages are rendered dynamically by JavaScript -->
        </div>

        <!-- Typing Indicator (Hidden by default) -->
        <div id="typing-indicator" class="bot-inbox inbox hidden">
            <div class="icon">
                <i class="fa-solid fa-robot"></i>
            </div>
            <div class="msg-header typing-bubble">
                <div class="typing-dot"></div>
                <div class="typing-dot"></div>
                <div class="typing-dot"></div>
            </div>
        </div>

        <!-- Quick Suggestions Area (Dynamic) -->
        <div id="suggestions-area" class="suggestions-container"></div>

        <div class="typing-field">
            <div class="input-data">
                <input id="data" type="text" placeholder="Escribe tu mensaje aquí..." autocomplete="off" required>
                <!-- Mic Button -->
                <button id="mic-btn" type="button" title="Usar micrófono"><i
                        class="fa-solid fa-microphone"></i></button>
                <button id="send-btn"><i class="fa-solid fa-paper-plane"></i></button>
            </div>
        </div>
    </div>

    <script>
        $(document).ready(function () {
            // Set initial timestamp (no longer used for a specific element, but good for current time)
            const now = new Date();
            const timeStr = now.getHours().toString().padStart(2, '0') + ':' + now.getMinutes().toString().padStart(2, '0');

            // Assistant Context
            const assistantId = <?php echo $assistant_id ? $assistant_id : 'null'; ?>;
            const botName = "<?php echo $bot_name; ?>";
            // CSRF token from server-side session (never exposes the internal bridge token)
            const csrfToken = "<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES); ?>";

            // --- History Persistence Logic ---
            const STORAGE_KEY = 'skalebot_history_' + (assistantId || 'global');

            function loadHistory() {
                const history = JSON.parse(localStorage.getItem(STORAGE_KEY) || '[]');
                history.forEach(item => {
                    appendMessage(item.type, item.text, item.time, false); // 'false' means don't save again
                });
                // Add initial greeting if history is empty
                if (history.length === 0) {
                    const initialTime = new Date();
                    const initialTimeStr = initialTime.getHours().toString().padStart(2, '0') + ':' + initialTime.getMinutes().toString().padStart(2, '0');
                    appendMessage('bot', `¡Hola! Soy ${botName}. ¿En qué te puedo ayudar hoy?`, initialTimeStr, true);
                }
            }

            function saveToHistory(type, text, time) {
                const history = JSON.parse(localStorage.getItem(STORAGE_KEY) || '[]');
                history.push({ type, text, time });
                localStorage.setItem(STORAGE_KEY, JSON.stringify(history));
            }

            // L5: XSS-safe helper: escapes HTML special chars for text content
            function escapeHtml(str) {
                return String(str)
                    .replace(/&/g, '&amp;')
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;')
                    .replace(/"/g, '&quot;')
                    .replace(/'/g, '&#039;');
            }

            // Safe nl2br: only converts newlines, no raw HTML allowed
            function safenl2br(str) {
                return escapeHtml(str).replace(/\n/g, '<br>');
            }

            function appendMessage(type, text, time, save = true) {
                let msgHtml = '';
                if (type === 'user') {
                    // User text: always escape — no HTML allowed from user input
                    const safeText = escapeHtml(text);
                    msgHtml = `
                        <div class="user-inbox inbox msg-anim">
                            <div class="msg-header">
                                <p>${safeText}</p>
                                <span class="timestamp">${escapeHtml(time)}</span>
                            </div>
                        </div>`;
                } else { // type === 'bot'
                    // Bot replies from Gemini may contain <br> tags added by nl2br server-side.
                    // We allow only <br> tags for line breaks, everything else is escaped.
                    const safeText = text.replace(/&lt;br\s*\/?&gt;/gi, '<br>')
                                        .replace(/<br\s*\/?>/gi, '\n') // normalize first
                                        .split('\n').map(line => escapeHtml(line)).join('<br>');
                    msgHtml = `
                        <div class="bot-inbox inbox msg-anim">
                            <div class="icon">
                                <i class="fa-solid fa-robot"></i>
                            </div>
                            <div class="msg-header">
                                <p>${safeText}</p>
                                <span class="timestamp">${escapeHtml(time)}</span>
                            </div>
                        </div>`;
                }
                $("#chat-box").append(msgHtml);
                if (save) saveToHistory(type, text, time);
            }
            // ---------------------------------

            loadHistory();
            scrollToBottom(); // Scroll to bottom after loading history

            // Confirm personalizado (reemplaza confirm() nativo que bloquea el hilo
            // y no respeta el tema dark/light del chat).
            function showConfirm(message, onConfirm) {
                const overlay = $(`
                    <div class="sk-confirm-overlay" role="dialog" aria-modal="true">
                        <div class="sk-confirm-box">
                            <span class="icon" aria-hidden="true">🗑️</span>
                            <h3>¿Borrar historial?</h3>
                            <p>${escapeHtml(message)}</p>
                            <div class="sk-confirm-actions">
                                <button class="sk-btn-cancel" id="sk-cancel">Cancelar</button>
                                <button class="sk-btn-confirm" id="sk-ok">Borrar</button>
                            </div>
                        </div>
                    </div>
                `);
                $('body').append(overlay);
                overlay.find('#sk-cancel').on('click', () => overlay.remove());
                overlay.find('#sk-ok').on('click', () => { overlay.remove(); onConfirm(); });
                // Cerrar con Escape
                $(document).one('keydown.confirm', (e) => {
                    if (e.key === 'Escape') overlay.remove();
                });
            }

            // Clear Chat Logic
            $('#clear-chat').on('click', function () {
                showConfirm('Esta acción eliminará todo el historial de conversación.', () => {
                    localStorage.removeItem(STORAGE_KEY);
                    location.reload();
                });
            });

            // Theme Toggle Logic — OPT-4: persist theme in localStorage
            const themeToggleBtn = $('#theme-toggle');
            // Restore saved theme on load
            const savedTheme = localStorage.getItem('skale_theme') || 'dark';
            $('html').attr('data-theme', savedTheme);
            themeToggleBtn.html(savedTheme === 'dark' ? '<i class="fa-solid fa-moon"></i>' : '<i class="fa-solid fa-sun"></i>');

            themeToggleBtn.on('click', function () {
                const htmlTag = $('html');
                const isDark = htmlTag.attr('data-theme') === 'dark';

                if (isDark) {
                    htmlTag.attr('data-theme', 'light');
                    themeToggleBtn.html('<i class="fa-solid fa-sun"></i>');
                    localStorage.setItem('skale_theme', 'light');
                } else {
                    htmlTag.attr('data-theme', 'dark');
                    themeToggleBtn.html('<i class="fa-solid fa-moon"></i>');
                    localStorage.setItem('skale_theme', 'dark');
                }
            });

            // Auto-scroll inteligente:
            // Solo hace scroll automático si el usuario ya está cerca del final
            // (dentro de 120px). Si el usuario scrolleó hacia arriba a leer
            // historial, no lo interrumpimos.
            function scrollToBottom(force = false) {
                const chatBox = $("#chat-box");
                const el = chatBox[0];
                const nearBottom = el.scrollHeight - el.scrollTop - el.clientHeight < 120;
                if (force || nearBottom) {
                    chatBox.animate({ scrollTop: el.scrollHeight }, 350);
                }
            }

            // Enter key support
            $("#data").keypress(function (e) {
                if (e.which == 13) {
                    $("#send-btn").click();
                    return false;
                }
            });

            // Handle suggestions click
            $(document).on('click', '.sugg-btn', function () {
                const text = $(this).text();
                $("#data").val(text);
                $("#send-btn").click();
                $("#suggestions-area").empty(); // Clear suggestions
            });

            // Send message logic
            $("#send-btn").on("click", function () {
                let textValue = $("#data").val().trim();
                if (textValue === "") return; // Prevent empty sends

                let currentTime = new Date();
                let timeString = currentTime.getHours().toString().padStart(2, '0') + ':' + currentTime.getMinutes().toString().padStart(2, '0');

                // 1. Append and Save User Message
                appendMessage('user', textValue, timeString, true);

                $("#data").val(''); // Clear input
                $("#suggestions-area").empty(); // Clear previous suggestions
                scrollToBottom();

                // 2. Show Typing Indicator
                $("#typing-indicator").removeClass('hidden');
                scrollToBottom();

                // 3. AJAX Request — UX-1: removed artificial 800ms delay
                $.ajax({
                    url: 'message.php',
                    type: 'POST',
                    dataType: 'json',
                    data: { text: textValue, assistant_id: assistantId, csrf_token: csrfToken },
                    success: function (result) {
                        // Hide typing indicator
                        $("#typing-indicator").addClass('hidden');

                        // Append and Save Bot Reply
                        const botTime = result.timestamp || timeString;
                        appendMessage('bot', result.reply, botTime, true);

                        // Add suggestions if available
                        if (result.suggestions && result.suggestions.length > 0) {
                                let suggHtml = '';
                                result.suggestions.forEach(sugg => {
                                    // escapeHtml aplicado SIEMPRE en sugerencias del servidor
                                    suggHtml += `<button class="sugg-btn">${escapeHtml(sugg)}</button>`;
                                });
                                $("#suggestions-area").html(suggHtml);
                            }

                        scrollToBottom();
                    },
                    error: function () {
                        $("#typing-indicator").addClass('hidden');
                        // Error sin HTML inline — clase CSS maneja el color
                        appendMessage('bot', 'Error de conexión. Por favor intenta de nuevo.', timeString, false);
                        scrollToBottom();
                    }
                });
            });

            // ==========================================
            // Web Speech API Logic for Voice Messages
            // ==========================================
            const micBtn = document.getElementById('mic-btn');
            const dataInput = document.getElementById('data');

            if ('webkitSpeechRecognition' in window || 'SpeechRecognition' in window) {
                const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
                const recognition = new SpeechRecognition();
                recognition.continuous = false; // Stop when the user stops talking
                recognition.interimResults = false; // Only get final results
                recognition.lang = 'es-ES'; // Set language to Spanish

                let isRecording = false;

                recognition.onstart = function () {
                    isRecording = true;
                    micBtn.classList.add('recording');
                    dataInput.placeholder = "Escuchando...";
                };

                recognition.onresult = function (event) {
                    const transcript = event.results[0][0].transcript;
                    dataInput.value = transcript;
                    // Automatically send the message after transcribing
                    $("#send-btn").click();
                };

                recognition.onerror = function (event) {
                    console.error("Error de reconocimiento de voz:", event.error);
                    dataInput.placeholder = "Error al escuchar. Usa el teclado.";
                };

                recognition.onend = function () {
                    isRecording = false;
                    micBtn.classList.remove('recording');
                    dataInput.placeholder = "Escribe tu mensaje aquí...";
                };

                micBtn.addEventListener('click', function () {
                    if (isRecording) {
                        recognition.stop();
                    } else {
                        recognition.start();
                    }
                });
            } else {
                console.warn("La API de reconocimiento de voz no está soportada en este navegador.");
                micBtn.style.display = 'none'; // Hide if not supported
            }
            // ==========================================
            // PWA Install Prompt Logic
            // ==========================================
            let deferredPrompt;
            const installBtn = document.getElementById('install-btn');

            window.addEventListener('beforeinstallprompt', (e) => {
                e.preventDefault();
                deferredPrompt = e;
                installBtn.style.display = 'inline-block';
            });

            if (installBtn) {
                installBtn.addEventListener('click', async () => {
                    if (deferredPrompt) {
                        deferredPrompt.prompt();
                        const { outcome } = await deferredPrompt.userChoice;
                        console.log(`User response to the install prompt: ${outcome}`);
                        deferredPrompt = null;
                        installBtn.style.display = 'none';
                    }
                });
            }

            window.addEventListener('appinstalled', () => {
                console.log('PWA was installed');
                if (installBtn) installBtn.style.display = 'none';
            });

            // ==========================================
            // Service Worker Registration (PWA)
            // ==========================================
            if ('serviceWorker' in navigator) {
                navigator.serviceWorker.register('/sw.js').catch(err => console.warn('SW error:', err));
            }
        });
    </script>
</body>

</html>