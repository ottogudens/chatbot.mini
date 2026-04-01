<?php
require_once 'db.php';

class FlowManager {
    private $conn;
    private $assistant_id;
    private $remote_jid;

    public function __construct($conn, $assistant_id, $remote_jid) {
        $this->conn = $conn;
        $this->assistant_id = intval($assistant_id);
        $this->remote_jid = mysqli_real_escape_string($conn, $remote_jid);
    }

    /**
     * Get the current state of the conversation.
     */
    public function getState() {
        $q = mysqli_query($this->conn, "SELECT * FROM conversation_state WHERE remote_jid = '$this->remote_jid' AND assistant_id = $this->assistant_id");
        return mysqli_fetch_assoc($q);
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
        $query = "INSERT INTO conversation_state (remote_jid, assistant_id, is_paused, paused_until) 
                  VALUES ('$this->remote_jid', $this->assistant_id, 1, '$until')
                  ON DUPLICATE KEY UPDATE is_paused = 1, paused_until = '$until'";
        return mysqli_query($this->conn, $query);
    }

    /**
     * Resume the bot (End Handover).
     */
    public function resume() {
        $query = "UPDATE conversation_state SET is_paused = 0, paused_until = NULL, current_flow_id = NULL, current_step_id = NULL 
                  WHERE remote_jid = '$this->remote_jid' AND assistant_id = $this->assistant_id";
        return mysqli_query($this->conn, $query);
    }

    /**
     * Detect if a message triggers a flow.
     */
    public function findTrigger($text) {
        $text = strtolower(trim($text));
        $q = mysqli_query($this->conn, "SELECT id FROM conversation_flows WHERE assistant_id = $this->assistant_id AND is_active = 1 AND LOWER(trigger_keyword) = '$text' LIMIT 1");
        $flow = mysqli_fetch_assoc($q);
        return $flow ? $flow['id'] : null;
    }

    /**
     * Process an incoming message within a flow.
     */
    public function process($text, $interactive_id = null) {
        $state = $this->getState();
        $flow_id = null;
        $step_id = null;

        if ($state && $state['current_flow_id']) {
            $flow_id = $state['current_flow_id'];
            $step_id = $state['current_step_id'];
        } else {
            $flow_id = $this->findTrigger($text);
        }

        if (!$flow_id) return null;

        // Determine next step
        $next_step = $this->getNextStep($flow_id, $step_id, $interactive_id);
        if ($next_step) {
            $this->saveState($flow_id, $next_step['id']);
            return $this->formatMessage($next_step);
        } else {
            // End of flow
            $this->saveState(null, null);
            return null;
        }
    }

    private function getNextStep($flow_id, $current_step_id, $interactive_id) {
        if ($current_step_id === null) {
            // Start of flow: get step with lowest order
            $q = mysqli_query($this->conn, "SELECT * FROM flow_steps WHERE flow_id = $flow_id ORDER BY step_order ASC LIMIT 1");
            return mysqli_fetch_assoc($q);
        }

        // Logical branching based on interactive_id (button/list ID)
        if ($interactive_id) {
            // Check current step config for next_step mapping
            $q = mysqli_query($this->conn, "SELECT interactive_config, next_step_id FROM flow_steps WHERE id = $current_step_id");
            $curr = mysqli_fetch_assoc($q);
            $config = json_decode($curr['interactive_config'], true);
            
            if (isset($config['branches'][$interactive_id])) {
                $nid = intval($config['branches'][$interactive_id]);
                $q2 = mysqli_query($this->conn, "SELECT * FROM flow_steps WHERE id = $nid");
                return mysqli_fetch_assoc($q2);
            }
        }

        // Default next step
        $q = mysqli_query($this->conn, "SELECT next_step_id FROM flow_steps WHERE id = $current_step_id");
        $curr = mysqli_fetch_assoc($q);
        if ($curr && $curr['next_step_id']) {
            $nid = $curr['next_step_id'];
            $q2 = mysqli_query($this->conn, "SELECT * FROM flow_steps WHERE id = $nid");
            return mysqli_fetch_assoc($q2);
        }

        return null;
    }

    private function saveState($flow_id, $step_id) {
        $fid = $flow_id ? intval($flow_id) : 'NULL';
        $sid = $step_id ? intval($step_id) : 'NULL';
        $query = "INSERT INTO conversation_state (remote_jid, assistant_id, current_flow_id, current_step_id) 
                  VALUES ('$this->remote_jid', $this->assistant_id, $fid, $sid)
                  ON DUPLICATE KEY UPDATE current_flow_id = $fid, current_step_id = $sid";
        return mysqli_query($this->conn, $query);
    }

    private function formatMessage($step) {
        $config = json_decode($step['interactive_config'], true);
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
        $q = mysqli_query($this->conn, "SELECT phone_number FROM authorized_agents WHERE assistant_id = $this->assistant_id AND is_active = 1");
        $agents = [];
        while($r = mysqli_fetch_assoc($q)) {
            $agents[] = $r['phone_number'];
        }
        return $agents;
    }
}
