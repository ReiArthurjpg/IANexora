<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\Response;
use App\Models\Document;
use App\Services\MarkdownParserService;

class DocumentController
{
    public function upload(): void
    {
        if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
            Response::error('Arquivo não enviado.', ['file' => 'Upload inválido.'], 422);
            return;
        }

        $file = $_FILES['file'];
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

        if ($extension !== 'md') {
            Response::error('Extensão inválida.', ['file' => 'Apenas arquivos .md são permitidos.'], 422);
            return;
        }

        $maxSizeStr = $_ENV['MAX_UPLOAD_SIZE'] ?? '';
        $uploadPath = $_ENV['UPLOAD_PATH'] ?? '';

        if (empty($maxSizeStr) || empty($uploadPath)) {
            throw new \RuntimeException('As configurações de upload (MAX_UPLOAD_SIZE, UPLOAD_PATH) não estão configuradas no arquivo .env.');
        }

        $maxSize = (int) $maxSizeStr;
        if ($file['size'] > $maxSize) {
            Response::error('Arquivo excede o tamanho máximo permitido.', ['file' => 'Limite de upload excedido.'], 422);
            return;
        }

        $safeName = preg_replace('/[^a-zA-Z0-9._-]/', '_', basename($file['name']));
        $targetDir = dirname(__DIR__, 2) . '/' . $uploadPath;

        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0775, true);
        }

        $targetPath = $targetDir . '/' . uniqid('doc_', true) . '_' . $safeName;

        if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
            Response::error('Falha ao mover arquivo enviado.', [], 500);
            return;
        }

        $content = (string) file_get_contents($targetPath);
        $title = (new MarkdownParserService())->extractTitle($content, pathinfo($safeName, PATHINFO_FILENAME));
        $id = Document::make()->create($title, $targetPath, $content, null);

        Response::success('Documento enviado com sucesso.', ['id' => $id, 'title' => $title], 201);
    }

    public function index(): void
    {
        Response::success('Lista de documentos.', Document::make()->all());
    }

    public function show(int $id): void
    {
        $doc = Document::make()->findById($id);
        if ($doc === null) {
            Response::error('Documento não encontrado.', [], 404);
            return;
        }

        Response::success('Documento encontrado.', $doc);
    }

    public function delete(int $id): void
    {
        $doc = Document::make()->findById($id);
        if ($doc === null) {
            Response::error('Documento não encontrado.', [], 404);
            return;
        }

        Document::make()->delete($id);

        if (isset($doc['path']) && is_string($doc['path']) && is_file($doc['path'])) {
            @unlink($doc['path']);
        }

        Response::success('Documento removido com sucesso.', ['id' => $id]);
    }
}
