<?php

declare(strict_types=1);

namespace App\Services;

use App\Database\Database;
use PDO;

class ChatHistoryService
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::connection();
    }

    /**
     * Salva uma mensagem no histórico
     */
    public function save(string $sessionId, string $role, string $content): void
    {
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
        $this->db->exec('DELETE FROM chat_history WHERE created_at < NOW() - INTERVAL 24 HOUR');
    }

    /**
     * Recupera o histórico de uma sessão (última 1 hora)
     */
    public function getHistory(string $sessionId, int $limit = 10): array
    {
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
