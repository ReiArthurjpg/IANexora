<?php

declare(strict_types=1);

use App\Controllers\ChatController;
use App\Helpers\Response;

$chatController = new ChatController();

return [
    ['GET', '/api/health', fn () => Response::success('API online.', ['status' => 'ok'])],
    ['POST', '/api/chat', [$chatController, 'chat']],
];
