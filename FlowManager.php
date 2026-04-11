<?php
/**
 * FlowManager.php
 * Gestiona el estado de flujos conversacionales y el modo handover humano.
 * El constructor recibe $conn desde el contexto que lo instancia (message.php),
 * por lo que NO se require db.php aquí — evita doble inicialización.
 */

class FlowManager {
    private $conn;
    private $assistant_id;
    private $remote_jid;

    public function __construct($conn, $assistant_id, $remote_jid) {
        $this->conn         = $conn;
        $this->assistant_id = intval($assistant_id);
        // SEC: Clamp remote_jid para prevenir valores excesivamente largos en BD.
        // Los JIDs de WhatsApp tienen formato "521234567890@s.whatsapp.net" (~50 chars).
        $this->remote_jid   = mb_substr((string)$remote_jid, 0, 100);
    }

    /**
     * Get the current state of the conversation.
     */
    public function getState() {
        $stmt = mysqli_prepare($this->conn, "SELECT * FROM conversation_state WHERE remote_jid = ? AND assistant_id = ?");
        mysqli_stmt_bind_param($stmt, "si", $this->remote_jid, $this->assistant_id);
        mysqli_stmt_execute($stmt);
        return mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    }

    /**
     * Check if the bot is currently paused (Human Handover).
     */
    public function isPaused() {
        $state = $this->getState();
        if (!$state) return false;
        
        if ($state['is_paused'] == 1) {
            // Check if pause expired
            if ($state['paused_until'] && strtotime($state['paused_until']) < time()) {
                $this->resume();
                return false;
            }
            return true;
        }
        return false;
    }

    /**
     * Pause the bot for a specific duration (in seconds).
     */
    public function pause($duration = 7200) {
        $until = date('Y-m-d H:i:s', time() + $duration);
        $stmt = mysqli_prepare($this->conn, "INSERT INTO conversation_state (remote_jid, assistant_id, is_paused, paused_until) 
                  VALUES (?, ?, 1, ?)
                  ON DUPLICATE KEY UPDATE is_paused = 1, paused_until = ?");
        mysqli_stmt_bind_param($stmt, "siss", $this->remote_jid, $this->assistant_id, $until, $until);
        return mysqli_stmt_execute($stmt);
    }

    /**
     * Resume the bot (End Handover).
     */
    public function resume() {
        $stmt = mysqli_prepare($this->conn, "UPDATE conversation_state SET is_paused = 0, paused_until = NULL, current_flow_id = NULL, current_step_id = NULL 
                  WHERE remote_jid = ? AND assistant_id = ?");
        mysqli_stmt_bind_param($stmt, "si", $this->remote_jid, $this->assistant_id);
        return mysqli_stmt_execute($stmt);
    }

    /**
     * Detect if a message triggers a flow.
     */
    public function findTrigger($text) {
        $text = strtolower(trim($text));
        $stmt = mysqli_prepare($this->conn, "SELECT id FROM conversation_flows WHERE assistant_id = ? AND is_active = 1 AND LOWER(trigger_keyword) = ? LIMIT 1");
        mysqli_stmt_bind_param($stmt, "is", $this->assistant_id, $text);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $flow = mysqli_fetch_assoc($res);
        return $flow ? $flow['id'] : null;
    }

    /**
     * Process an incoming message within a flow.
     */
    public function process($text, $interactive_id = null) {
        $state = $this->getState();
        $flow_id = null;
        $step_order = null;

        if ($state && $state['current_flow_id']) {
            $flow_id = $state['current_flow_id'];
            $step_order = $state['current_step_id']; // This now stores the step_order geographically
        } else {
            $flow_id = $this->findTrigger($text);
        }

        if (!$flow_id) return null;

        // Determine next step
        $next_step = $this->getNextStep($flow_id, $step_order, $interactive_id);
        if ($next_step) {
            $this->saveState($flow_id, $next_step['step_order']);
            return $this->formatMessage($next_step);
        } else {
            // End of flow
            $this->saveState(null, null);
            return null;
        }
    }

    private function getNextStep($flow_id, $current_step_order, $interactive_id) {
        if ($current_step_order === null) {
            // Start of flow: get step with lowest order
            $stmt = mysqli_prepare($this->conn, "SELECT * FROM flow_steps WHERE flow_id = ? ORDER BY step_order ASC LIMIT 1");
            mysqli_stmt_bind_param($stmt, "i", $flow_id);
            mysqli_stmt_execute($stmt);
            return mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        }

        // Logical branching based on interactive_id (button/list ID)
        if ($interactive_id) {
            // Check current step config for next_step mapping (using step_order branch mapping)
            $stmt = mysqli_prepare($this->conn, "SELECT interactive_config FROM flow_steps WHERE flow_id = ? AND step_order = ?");
            mysqli_stmt_bind_param($stmt, "ii", $flow_id, $current_step_order);
            mysqli_stmt_execute($stmt);
            $curr = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
            
            if ($curr) {
                $config = json_decode($curr['interactive_config'] ?? '[]', true);
                if (isset($config['branches'][$interactive_id])) {
                    $target_order = intval($config['branches'][$interactive_id]);
                    if ($target_order > 0) {
                        $stmt2 = mysqli_prepare($this->conn, "SELECT * FROM flow_steps WHERE flow_id = ? AND step_order = ?");
                        mysqli_stmt_bind_param($stmt2, "ii", $flow_id, $target_order);
                        mysqli_stmt_execute($stmt2);
                        return mysqli_fetch_assoc(mysqli_stmt_get_result($stmt2));
                    }
                }
            }
        }

        // Default next step: sequential progression (current + 1)
        $next_order = $current_step_order + 1;
        $stmt = mysqli_prepare($this->conn, "SELECT * FROM flow_steps WHERE flow_id = ? AND step_order = ?");
        mysqli_stmt_bind_param($stmt, "ii", $flow_id, $next_order);
        mysqli_stmt_execute($stmt);
        return mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    }

    private function saveState($flow_id, $step_id) {
        // Use prepared statement for UPSERT logic
        $stmt = mysqli_prepare($this->conn, "INSERT INTO conversation_state (remote_jid, assistant_id, current_flow_id, current_step_id) 
                  VALUES (?, ?, ?, ?)
                  ON DUPLICATE KEY UPDATE current_flow_id = VALUES(current_flow_id), current_step_id = VALUES(current_step_id)");
        mysqli_stmt_bind_param($stmt, "siii", $this->remote_jid, $this->assistant_id, $flow_id, $step_id);
        return mysqli_stmt_execute($stmt);
    }

    private function formatMessage($step) {
        $config = json_decode($step['interactive_config'] ?? '[]', true);
        return [
            'reply' => $step['content'],
            'type' => $step['step_type'],
            'interactive' => $config['interactive'] ?? null,
            'matched' => true
        ];
    }

    /**
     * Get authorized agents for handover.
     */
    public function getAuthorizedAgents() {
        $stmt = mysqli_prepare($this->conn, "SELECT phone_number FROM authorized_agents WHERE assistant_id = ? AND is_active = 1");
        mysqli_stmt_bind_param($stmt, "i", $this->assistant_id);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $agents = [];
        while($r = mysqli_fetch_assoc($res)) {
            $agents[] = $r['phone_number'];
        }
        return $agents;
    }
}
