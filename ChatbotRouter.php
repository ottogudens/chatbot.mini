<?php
/**
 * ChatbotRouter.php — INFRA-3
 * Handles rule-based matching (exact + fuzzy) before delegating to AI.
 * Extracted from message.php to improve maintainability.
 */

class ChatbotRouter
{
    private $conn;
    private $assistant_id;

    public function __construct($conn, $assistant_id)
    {
        $this->conn         = $conn;
        $this->assistant_id = $assistant_id;
    }

    /**
     * Normalize a string: lowercase, remove accents, strip non-alphanumeric
     */
    public static function normalize(string $text): string
    {
        $text = mb_strtolower(trim($text), 'UTF-8');
        $text = preg_replace('/[áàäâ]/u', 'a', $text);
        $text = preg_replace('/[éèëê]/u', 'e', $text);
        $text = preg_replace('/[íìïî]/u', 'i', $text);
        $text = preg_replace('/[óòöô]/u', 'o', $text);
        $text = preg_replace('/[úùüû]/u', 'u', $text);
        $text = preg_replace('/[^a-z0-9\s]/i', '', $text);
        return $text;
    }

    /**
     * Load all rules applicable to this assistant (global + specific)
     */
    public function loadRules(): array
    {
        $stmt = mysqli_prepare(
            $this->conn,
            $this->assistant_id
                ? "SELECT queries, replies, category FROM chatbot WHERE assistant_id IS NULL OR assistant_id = ?"
                : "SELECT queries, replies, category FROM chatbot WHERE assistant_id IS NULL"
        );

        if ($stmt && $this->assistant_id) {
            mysqli_stmt_bind_param($stmt, "i", $this->assistant_id);
        }

        $rules = [];
        if ($stmt) {
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            if ($result) {
                while ($row = mysqli_fetch_assoc($result)) {
                    $rules[] = $row;
                }
            }
        }
        return $rules;
    }

    /**
     * Try to match the message against rules.
     * Returns ['reply' => string, 'matched' => bool] or null if no match.
     */
    public function match(string $clean_msg): ?array
    {
        $rules = $this->loadRules();

        // 1. Exact match (highest priority)
        foreach ($rules as $rule) {
            $keywords = explode('|', $rule['queries']);
            foreach ($keywords as $keyword) {
                $clean_kw = self::normalize($keyword);
                if ($clean_msg === $clean_kw) {
                    return ['reply' => $rule['replies'], 'matched' => true];
                }
            }
        }

        // 2. Fuzzy/regex match with safely escaped keywords
        foreach ($rules as $rule) {
            $keywords = array_map('trim', explode('|', $rule['queries']));
            $escaped  = array_map(fn($kw) => preg_quote($kw, '/'), $keywords);
            $pattern  = '/\b(' . implode('|', $escaped) . ')\b/iu';
            if (@preg_match($pattern, $clean_msg)) {
                return ['reply' => $rule['replies'], 'matched' => true];
            }
        }

        return null;
    }

    /**
     * Get random query suggestions for the fallback response.
     */
    public function getSuggestions(int $limit = 3): array
    {
        $stmt = mysqli_prepare(
            $this->conn,
            $this->assistant_id
                ? "SELECT queries FROM chatbot WHERE (assistant_id IS NULL OR assistant_id = ?) ORDER BY RAND() LIMIT $limit"
                : "SELECT queries FROM chatbot WHERE assistant_id IS NULL ORDER BY RAND() LIMIT $limit"
        );

        if ($stmt && $this->assistant_id) {
            mysqli_stmt_bind_param($stmt, "i", $this->assistant_id);
        }

        $suggestions = [];
        if ($stmt) {
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            if ($result) {
                while ($row = mysqli_fetch_assoc($result)) {
                    $suggestions[] = ucfirst(explode('|', $row['queries'])[0]);
                }
            }
        }
        return $suggestions;
    }
}
