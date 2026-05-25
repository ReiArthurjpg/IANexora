<?php

declare(strict_types=1);

namespace App\Services;

use App\Database\Database;
use PDO;
use Throwable;

class ChatHistoryService
{
    private ?PDO $db = null;

    public function __construct()
    {
        try {
            $this->db = Database::connection();
        } catch (Throwable) {
            // Histórico opcional: se o banco estiver indisponível,
            // a API de chat continua funcionando sem persistência.
            $this->db = null;
        }
    }

    /**
     * Salva uma mensagem no histórico
     */
    public function save(string $sessionId, string $role, string $content): void
    {
        if (!$this->db instanceof PDO) {
            return;
        }

        // Limpa mensagens com mais de 24 horas antes de salvar a nova
        $this->clearOldHistory();

        $stmt = $this->db->prepare('INSERT INTO chat_history (session_id, role, content) VALUES (:session_id, :role, :content)');
        $stmt->execute([
            ':session_id' => $sessionId,
            ':role' => $role,
            ':content' => $content
        ]);
    }

    /**
     * Apaga do banco mensagens com mais de 24 horas
     */
    private function clearOldHistory(): void
    {
        if (!$this->db instanceof PDO) {
            return;
        }

        $this->db->exec('DELETE FROM chat_history WHERE created_at < NOW() - INTERVAL 24 HOUR');
    }

    /**
     * Recupera o histórico de uma sessão (última 1 hora)
     */
    public function getHistory(string $sessionId, int $limit = 10): array
    {
        if (!$this->db instanceof PDO) {
            return [];
        }

        $stmt = $this->db->prepare('
            SELECT role, content 
            FROM chat_history 
            WHERE session_id = :session_id 
            AND created_at >= NOW() - INTERVAL 1 HOUR
            ORDER BY created_at ASC 
            LIMIT :limit
        ');
        
        $stmt->bindValue(':session_id', $sessionId, PDO::PARAM_STR);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
