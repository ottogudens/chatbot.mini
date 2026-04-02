<?php
require_once 'db.php';

/**
 * Migration 16: Interactive Flows, Human Handover, and Authorized Agents.
 * 
 * - conversation_flows: Root table for flows.
 * - flow_steps: Indivial steps within a flow (text + buttons/list).
 * - conversation_state: Tracks user progress in a flow or handover state.
 * - authorized_agents: Authorized phone numbers for handover per assistant.
 * - assistants: Update to store default handover settings.
 */

$queries = [
    // 1. Conversation Flows
    "CREATE TABLE IF NOT EXISTS conversation_flows (
        id INT AUTO_INCREMENT PRIMARY KEY,
        assistant_id INT NOT NULL,
        name VARCHAR(255) NOT NULL,
        trigger_keyword VARCHAR(255) NULL,
        is_active TINYINT(1) DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

    // 2. Flow Steps
    "CREATE TABLE IF NOT EXISTS flow_steps (
        id INT AUTO_INCREMENT PRIMARY KEY,
        flow_id INT NOT NULL,
        step_order INT NOT NULL DEFAULT 1,
        step_type ENUM('text', 'buttons', 'list') DEFAULT 'text',
        content TEXT NOT NULL, -- The main text of the message
        interactive_config JSON NULL, -- Buttons/List options
        next_step_id INT NULL, -- Default next step if no branching
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

    // 3. Conversation State
    "CREATE TABLE IF NOT EXISTS conversation_state (
        id INT AUTO_INCREMENT PRIMARY KEY,
        remote_jid VARCHAR(255) NOT NULL,
        assistant_id INT NOT NULL,
        current_flow_id INT NULL,
        current_step_id INT NULL,
        is_paused TINYINT(1) DEFAULT 0, -- 1 if human handover is active
        paused_until DATETIME NULL,
        metadata JSON NULL,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY `unique_convo` (remote_jid, assistant_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

    // 4. Authorized Agents
    "CREATE TABLE IF NOT EXISTS authorized_agents (
        id INT AUTO_INCREMENT PRIMARY KEY,
        assistant_id INT NOT NULL,
        agent_name VARCHAR(255) NULL,
        phone_number VARCHAR(50) NOT NULL,
        is_active TINYINT(1) DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY `unique_agent` (assistant_id, phone_number)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

    // 5. Update Assistants table
    "ALTER TABLE assistants 
     ADD COLUMN handover_enabled TINYINT(1) DEFAULT 0 AFTER voice_enabled,
     ADD COLUMN handover_phone_default VARCHAR(50) NULL AFTER handover_enabled;"
];

foreach ($queries as $i => $q) {
    try {
        if (mysqli_query($conn, $q)) {
            echo "Step " . ($i+1) . " of Migration 16 completed successfully.\n";
        }
    } catch (mysqli_sql_exception $e) {
        $err = $e->getMessage();
        if (strpos($err, 'Duplicate column') !== false || strpos($err, 'already exists') !== false) {
            echo "Step " . ($i+1) . " already applied.\n";
        } else {
            echo "Error in Step " . ($i+1) . ": " . $err . "\n";
        }
    }
}

?>
