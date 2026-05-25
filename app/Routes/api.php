<?php

declare(strict_types=1);

use App\Controllers\ChatController;
use App\Controllers\DocumentController;
use App\Helpers\Response;

$documentController = new DocumentController();
$chatController = new ChatController();

return [
    ['GET', '/api/health', fn () => Response::success('API online.', ['status' => 'ok'])],
    ['POST', '/api/documents/upload', [$documentController, 'upload']],
    ['GET', '/api/documents', [$documentController, 'index']],
    ['GET', '/api/documents/{id}', [$documentController, 'show']],
    ['DELETE', '/api/documents/{id}', [$documentController, 'delete']],
    ['POST', '/api/chat', [$chatController, 'chat']],
];
