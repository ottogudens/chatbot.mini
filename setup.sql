CREATE DATABASE IF NOT EXISTS chatbot CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE chatbot;



-- Chatbot Q&A Table
DROP TABLE IF EXISTS chatbot;
CREATE TABLE chatbot (
    id INT AUTO_INCREMENT PRIMARY KEY,
    queries VARCHAR(255) NOT NULL,
    replies TEXT NOT NULL,
    category VARCHAR(50) DEFAULT 'general',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Conversation Logs Table
DROP TABLE IF EXISTS conversation_logs;
CREATE TABLE conversation_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_message TEXT NOT NULL,
    bot_reply TEXT NOT NULL,
    matched BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Clear existing data if re-running
TRUNCATE TABLE chatbot;

-- Insert rich seed data
INSERT INTO chatbot (queries, replies, category) VALUES
-- Saludos
('hola|buenas|que tal|saludos|hey', '¡Hola! Qué gusto saludarte. ¿En qué puedo ayudarte hoy?', 'saludo'),
('buenos dias|buen dia', '¡Buenos días! Espero que tengas una excelente jornada. ¿En qué te ayudo?', 'saludo'),
('buenas tardes', '¡Buenas tardes! ¿Cómo va tu día? dime en qué puedo asistirte.', 'saludo'),
('buenas noches', '¡Buenas noches! Aquí estoy si necesitas algo antes de descansar.', 'saludo'),
('como estas|como te va|que tal estas', '¡Estoy excelente, funcionando al 100%! Gracias por preguntar. ¿Y tú qué tal?', 'saludo'),
('adios|chao|hasta luego|nos vemos|bye', '¡Hasta luego! Ha sido un placer conversar contigo. Vuelve cuando quieras.', 'despedida'),
('gracias|muchas gracias|te lo agradezco|amable', '¡De nada! Es un placer para mí poder ayudarte. ¿Necesitas algo más?', 'cortesia'),
('de nada|no hay de que', '¡Genial! Seguimos en contacto.', 'cortesia'),

-- Identidad y Funciones
('quien eres|que eres|como te llamas', 'Soy SkaleBot, un asistente virtual diseñado para ayudarte a resolver dudas y conversar un poco.', 'identidad'),
('que haces|para que sirves|que puedes hacer|ayuda', 'Puedo conversar contigo, responder preguntas frecuentes, registrar nuestra charla, y aprender nuevas respuestas si mi administrador me las enseña. ¡Pruébame!', 'funciones'),
('quien te creo|creador|desarrollador', 'Fui creado por el equipo de Skale IA como un proyecto interactivo.', 'identidad'),
('eres humano|eres un robot|eres una ia', 'Soy un programa informático (un chatbot). No tengo emociones, ¡pero hago mi mejor esfuerzo por ser amigable!', 'identidad'),

-- Diversión / Chistes
('cuentame un chiste|dime un chiste|broma|hazme reir', '¿Por qué los pájaros no usan Facebook? Porque ya tienen Twitter. 😄', 'diversion'),
('otro chiste|mas chistes', '¿Qué le dice un bit al otro? Nos vemos en el bus.', 'diversion'),
('jaja|jajaja|jeje|que risa|gracioso', '¡Me alegra haberte hecho reír! ¿En qué más puedo ayudarte?', 'diversion'),
('como esta el clima|clima|tiempo', 'Lo siento, no tengo una ventana para mirar afuera ni conexión a servicios del clima por ahora. ¡Pero espero que haga un hermoso día donde estés!', 'general'),

-- Soporte / Contacto
('contacto|hablar con humano|agente|soporte|telefono', 'Si necesitas hablar con un asesor humano, puedes escribirnos a contacto@skale.ia o dejarnos un mensaje y te contactaremos pronto.', 'soporte'),
('horario|a que hora abren|cuando atienden', 'Nuestro servicio automatizado está disponible 24/7. El equipo humano atiende de Lunes a Viernes de 9:00 a 18:00 hrs.', 'soporte'),

-- Varios
('que hora es|hora', '¡Es la hora de seguir aprendiendo! Como soy un bot interno, no verifico tu zona horaria exacta, pero siempre es un buen momento para charlar.', 'general'),
('te amo|te quiero', '¡Aww, qué lindo! Yo también te aprecio mucho como usuario. 💙', 'cortesia'),
('insulto|tonto|idiota|estupido', 'Por favor, mantengamos una conversación respetuosa. Estoy aquí para ayudar en lo que pueda.', 'moderacion');
