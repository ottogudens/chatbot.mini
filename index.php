<!DOCTYPE html>
<html lang="es" data-theme="dark">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SkaleBot - Asistente IA</title>
    <!-- Google Fonts: Inter -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
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
                    <div class="title">SkaleBot</div>
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
                <a href="admin.php" id="admin-btn" title="Panel de Administración">
                    <i class="fa-solid fa-gear"></i>
                </a>
            </div>
        </div>

        <div class="form" id="chat-box">
            <!-- Initial Greeting -->
            <div class="bot-inbox inbox init-anim">
                <div class="icon">
                    <i class="fa-solid fa-robot"></i>
                </div>
                <div class="msg-header">
                    <p>¡Hola! Soy SkaleBot. ¿En qué te puedo ayudar hoy?</p>
                    <span class="timestamp" id="init-time"></span>
                </div>
            </div>
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

            // --- History Persistence Logic ---
            const STORAGE_KEY = 'skalebot_history';

            function loadHistory() {
                const history = JSON.parse(localStorage.getItem(STORAGE_KEY) || '[]');
                history.forEach(item => {
                    appendMessage(item.type, item.text, item.time, false); // 'false' means don't save again
                });
                // Add initial greeting if history is empty
                if (history.length === 0) {
                    const initialTime = new Date();
                    const initialTimeStr = initialTime.getHours().toString().padStart(2, '0') + ':' + initialTime.getMinutes().toString().padStart(2, '0');
                    appendMessage('bot', '¡Hola! Soy SkaleBot. ¿En qué te puedo ayudar hoy?', initialTimeStr, true);
                }
            }

            function saveToHistory(type, text, time) {
                const history = JSON.parse(localStorage.getItem(STORAGE_KEY) || '[]');
                history.push({ type, text, time });
                localStorage.setItem(STORAGE_KEY, JSON.stringify(history));
            }

            function appendMessage(type, text, time, save = true) {
                let msgHtml = '';
                if (type === 'user') {
                    msgHtml = `
                        <div class="user-inbox inbox msg-anim">
                            <div class="msg-header">
                                <p>${text}</p>
                                <span class="timestamp">${time}</span>
                            </div>
                        </div>`;
                } else { // type === 'bot'
                    msgHtml = `
                        <div class="bot-inbox inbox msg-anim">
                            <div class="icon">
                                <i class="fa-solid fa-robot"></i>
                            </div>
                            <div class="msg-header">
                                <p>${text}</p>
                                <span class="timestamp">${time}</span>
                            </div>
                        </div>`;
                }
                $("#chat-box").append(msgHtml);
                if (save) saveToHistory(type, text, time);
            }
            // ---------------------------------

            loadHistory();
            scrollToBottom(); // Scroll to bottom after loading history

            // Clear Chat Logic
            $('#clear-chat').on('click', function () {
                if (confirm('¿Seguro que quieres borrar todo el historial?')) {
                    localStorage.removeItem(STORAGE_KEY);
                    location.reload(); // Reload to show fresh state with initial greeting
                }
            });

            // Theme Toggle Logic
            const themeToggleBtn = $('#theme-toggle');
            themeToggleBtn.on('click', function () {
                const htmlTag = $('html');
                const isDark = htmlTag.attr('data-theme') === 'dark';

                if (isDark) {
                    htmlTag.attr('data-theme', 'light');
                    themeToggleBtn.html('<i class="fa-solid fa-sun"></i>');
                } else {
                    htmlTag.attr('data-theme', 'dark');
                    themeToggleBtn.html('<i class="fa-solid fa-moon"></i>');
                }
            });

            // Auto-scroll function
            function scrollToBottom() {
                const chatBox = $("#chat-box");
                chatBox.animate({ scrollTop: chatBox.prop("scrollHeight") }, 400);
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

                // 3. AJAX Request
                setTimeout(() => {
                    $.ajax({
                        url: 'message.php',
                        type: 'POST',
                        dataType: 'json',
                        data: { text: textValue },
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
                                    suggHtml += `<button class="sugg-btn">${sugg}</button>`;
                                });
                                $("#suggestions-area").html(suggHtml);
                            }

                            scrollToBottom();
                        },
                        error: function () {
                            // Fallback on error
                            $("#typing-indicator").addClass('hidden');
                            appendMessage('bot', '<span style="color:var(--danger)">Error de conexión con el servidor.</span>', timeString, false); // Don't save errors
                            scrollToBottom();
                        }
                    });
                }, 800);
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
        });
    </script>
</body>

</html>