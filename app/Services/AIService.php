<?php

declare(strict_types=1);

namespace App\Services;

interface AIService
{
    public function sendMessage(string $message, string $context): array;
}
