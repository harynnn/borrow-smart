<?php
class SessionHandler implements \SessionHandlerInterface {
    private $pdo;
    private $prefix = "PHPSESSID_";
    private $ttl;

    public function __construct($pdo, $ttl = 3600) {
        $this->pdo = $pdo;
        $this->ttl = $ttl;

        // Create sessions table if it doesn't exist
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS php_sessions (
                id VARCHAR(128) NOT NULL PRIMARY KEY,
                data TEXT,
                timestamp INT UNSIGNED NOT NULL,
                ip_address VARCHAR(45),
                user_agent VARCHAR(255)
            )
        ");
    }

    /**
     * Initialize session
     */
    public function open($path, $name): bool {
        return true;
    }

    /**
     * Close session
     */
    public function close(): bool {
        return true;
    }

    /**
     * Read session data
     */
    public function read($id): string|false {
        try {
            $stmt = $this->pdo->prepare("
                SELECT data 
                FROM php_sessions 
                WHERE id = ?
            ");
            $stmt->execute([$this->prefix . $id]);
            
            if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                return $row['data'];
            }
            
            return '';
        } catch (PDOException $e) {
            error_log("Session Read Error: " . $e->getMessage());
            return '';
        }
    }

    /**
     * Write session data
     */
    public function write($id, $data): bool {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO php_sessions (
                    id, data, timestamp, ip_address, user_agent
                ) VALUES (
                    ?, ?, ?, ?, ?
                ) ON DUPLICATE KEY UPDATE 
                    data = VALUES(data),
                    timestamp = VALUES(timestamp),
                    ip_address = VALUES(ip_address),
                    user_agent = VALUES(user_agent)
            ");

            return $stmt->execute([
                $this->prefix . $id,
                $data,
                time(),
                $_SERVER['REMOTE_ADDR'] ?? null,
                $_SERVER['HTTP_USER_AGENT'] ?? null
            ]);
        } catch (PDOException $e) {
            error_log("Session Write Error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Destroy session
     */
    public function destroy($id): bool {
        try {
            $stmt = $this->pdo->prepare("
                DELETE FROM php_sessions 
                WHERE id = ?
            ");
            return $stmt->execute([$this->prefix . $id]);
        } catch (PDOException $e) {
            error_log("Session Destroy Error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Garbage collection
     */
    public function gc($max_lifetime): int|false {
        try {
            $stmt = $this->pdo->prepare("
                DELETE FROM php_sessions 
                WHERE timestamp < ?
            ");
            $stmt->execute([time() - $max_lifetime]);
            return $stmt->rowCount();
        } catch (PDOException $e) {
            error_log("Session GC Error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Validate session
     */
    public function validateSession($id): bool {
        try {
            $stmt = $this->pdo->prepare("
                SELECT timestamp, ip_address, user_agent 
                FROM php_sessions 
                WHERE id = ?
            ");
            $stmt->execute([$this->prefix . $id]);
            $session = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$session) {
                return false;
            }

            // Check session age
            if (time() - $session['timestamp'] > $this->ttl) {
                return false;
            }

            // Check for session hijacking
            if ($session['ip_address'] !== ($_SERVER['REMOTE_ADDR'] ?? null) ||
                $session['user_agent'] !== ($_SERVER['HTTP_USER_AGENT'] ?? null)) {
                return false;
            }

            return true;
        } catch (PDOException $e) {
            error_log("Session Validation Error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Regenerate session ID
     */
    public function regenerateId(): bool {
        $oldId = session_id();
        if (session_regenerate_id(true)) {
            $newId = session_id();
            try {
                $stmt = $this->pdo->prepare("
                    UPDATE php_sessions 
                    SET id = ? 
                    WHERE id = ?
                ");
                return $stmt->execute([
                    $this->prefix . $newId,
                    $this->prefix . $oldId
                ]);
            } catch (PDOException $e) {
                error_log("Session Regeneration Error: " . $e->getMessage());
                return false;
            }
        }
        return false;
    }

    /**
     * Get active sessions count
     */
    public function getActiveSessionsCount(): int {
        try {
            $stmt = $this->pdo->prepare("
                SELECT COUNT(*) 
                FROM php_sessions 
                WHERE timestamp > ?
            ");
            $stmt->execute([time() - $this->ttl]);
            return (int) $stmt->fetchColumn();
        } catch (PDOException $e) {
            error_log("Session Count Error: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Clean up expired sessions
     */
    public function cleanup(): bool {
        try {
            $stmt = $this->pdo->prepare("
                DELETE FROM php_sessions 
                WHERE timestamp < ?
            ");
            return $stmt->execute([time() - $this->ttl]);
        } catch (PDOException $e) {
            error_log("Session Cleanup Error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get session info
     */
    public function getSessionInfo($id): array {
        try {
            $stmt = $this->pdo->prepare("
                SELECT * 
                FROM php_sessions 
                WHERE id = ?
            ");
            $stmt->execute([$this->prefix . $id]);
            return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        } catch (PDOException $e) {
            error_log("Session Info Error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Kill all sessions for a user
     */
    public function killUserSessions($userId): bool {
        try {
            $stmt = $this->pdo->prepare("
                DELETE FROM php_sessions 
                WHERE data LIKE ?
            ");
            return $stmt->execute(['%' . $userId . '%']);
        } catch (PDOException $e) {
            error_log("Session Kill Error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Kill all sessions except current
     */
    public function killOtherSessions($currentId): bool {
        try {
            $stmt = $this->pdo->prepare("
                DELETE FROM php_sessions 
                WHERE id != ?
            ");
            return $stmt->execute([$this->prefix . $currentId]);
        } catch (PDOException $e) {
            error_log("Session Kill Others Error: " . $e->getMessage());
            return false;
        }
    }
}
?>
