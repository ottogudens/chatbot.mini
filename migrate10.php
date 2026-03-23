<?php
/**
 * Migration 10: Create gemini_response_cache table
 * Implements OPT-3: Cache identical Gemini queries to reduce API cost
 */
require_once 'db.php';

echo "Iniciando migración 10: Crear tabla gemini_response_cache...\n";

// Table to cache frequent/identical queries to Gemini
$query = "CREATE TABLE IF NOT EXISTS gemini_response_cache (
    id INT AUTO_INCREMENT PRIMARY KEY,
    cache_key VARCHAR(64) NOT NULL UNIQUE COMMENT 'MD5 of assistant_id + normalized message',
    assistant_id INT NOT NULL,
    user_message TEXT NOT NULL,
    cached_reply TEXT NOT NULL,
    hit_count INT DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_hit_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NOT NULL,
    INDEX idx_cache_key (cache_key),
    INDEX idx_assistant (assistant_id),
    INDEX idx_expires (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

if (mysqli_query($conn, $query)) {
    echo "✓ Tabla 'gemini_response_cache' creada o ya existía.\n";
} else {
    echo "Error en la migración: " . mysqli_error($conn) . "\n";
}

// Create event to auto-delete expired cache entries (if events are enabled)
$event_query = "CREATE EVENT IF NOT EXISTS cleanup_gemini_cache
    ON SCHEDULE EVERY 1 HOUR
    DO DELETE FROM gemini_response_cache WHERE expires_at < NOW()";

if (!mysqli_query($conn, $event_query)) {
    // Events may not be enabled — that's OK, manual cleanup will work
    echo "Info: No se pudo crear el evento de limpieza automática (puede que los eventos de MySQL estén desactivados). Se hará limpieza manual.\n";
} else {
    echo "✓ Evento de limpieza automática de caché creado.\n";
}

echo "Migración 10 completada.\n";
