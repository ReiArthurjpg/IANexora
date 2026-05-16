<?php

declare(strict_types=1);

namespace App\Services;

use App\Database\Database;
use PDO;

class DocumentSearchService
{
    public function __construct(private readonly PDO $db)
    {
    }

    public static function make(): self
    {
        return new self(Database::connection());
    }

    public function search(string $query, int $limit = 5): array
    {
        $keywords = array_values(array_filter(preg_split('/\s+/', trim($query)) ?: []));

        if ($keywords === []) {
            return [];
        }

        $clauses = [];
        $params = [];

        foreach ($keywords as $index => $keyword) {
            $paramTitle = ':term_t_' . $index;
            $paramContent = ':term_c_' . $index;
            $paramTags = ':term_tags_' . $index;
            
            $clauses[] = "(title LIKE {$paramTitle} OR content LIKE {$paramContent} OR tags LIKE {$paramTags})";
            
            $params[$paramTitle] = '%' . $keyword . '%';
            $params[$paramContent] = '%' . $keyword . '%';
            $params[$paramTags] = '%' . $keyword . '%';
        }

        $sql = 'SELECT id, title, content, tags, created_at FROM documents WHERE ' . implode(' OR ', $clauses) . ' ORDER BY updated_at DESC LIMIT :limit';
        $stmt = $this->db->prepare($sql);

        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value, PDO::PARAM_STR);
        }

        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }
}
