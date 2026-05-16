<?php

declare(strict_types=1);

use App\Controllers\ChatController;
use App\Controllers\DocumentController;
use App\Helpers\Response;

return [
    ['GET', '/api/health', fn () => Response::success('API online.', ['status' => 'ok'])],
    ['POST', '/api/documents/upload', [new DocumentController(), 'upload']],
    ['GET', '/api/documents', [new DocumentController(), 'index']],
    ['GET', '/api/documents/{id}', [new DocumentController(), 'show']],
    ['DELETE', '/api/documents/{id}', [new DocumentController(), 'delete']],
    ['POST', '/api/chat', [new ChatController(), 'chat']],
];
