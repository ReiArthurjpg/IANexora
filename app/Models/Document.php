<?php

declare(strict_types=1);

namespace App\Models;

use App\Database\Database;
use PDO;

class Document
{
    public function __construct(private readonly PDO $db)
    {
    }

    public static function make(): self
    {
        return new self(Database::connection());
    }

    public function create(string $title, string $path, string $content, ?string $tags = null): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO documents (title, path, content, tags, created_at, updated_at)
             VALUES (:title, :path, :content, :tags, NOW(), NOW())'
        );

        $stmt->execute([
            ':title' => $title,
            ':path' => $path,
            ':content' => $content,
            ':tags' => $tags,
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function all(): array
    {
        $stmt = $this->db->query('SELECT id, title, path, tags, created_at, updated_at FROM documents ORDER BY created_at DESC');
        return $stmt->fetchAll();
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM documents WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        $doc = $stmt->fetch();

        return $doc ?: null;
    }

    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare('DELETE FROM documents WHERE id = :id');
        return $stmt->execute([':id' => $id]);
    }
}
