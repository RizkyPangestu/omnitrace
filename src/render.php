<?php
declare(strict_types=1);

function redirect(string $path): never
{
    header('Location: ' . $path, true, 303);
    exit;
}

function flash(string $message): void
{
    $_SESSION['alerts'][] = $message;
}

function flash_messages(): array
{
    $messages = $_SESSION['alerts'] ?? [];
    unset($_SESSION['alerts']);
    return $messages;
}

function render(string $template, array $values = []): void
{
    extract($values, EXTR_SKIP);
    require PROJECT_DIR . '/views/' . $template . '.html';
}
