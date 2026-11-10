<?php
declare(strict_types=1);

namespace Core\Http;

final class Response
{
    public function __construct(
        private string $body,
        private int $status = 200,
        private array $headers = ['Content-Type' => 'text/plain; charset=utf-8']
    ) {}

    public static function json(array $data, int $status = 200): self
    {
        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
        return new self($json === false ? '{"ok":false,"error":{"code":"encoding_error"}}' : $json, $status, [
            'Content-Type' => 'application/json; charset=utf-8',
            'Cache-Control' => 'no-store',
        ]);
    }

    public function statusCode(): int { return $this->status; }

    public function send(): void
    {
        http_response_code($this->status);
        foreach ($this->headers as $name => $value) header($name . ': ' . $value);
        echo $this->body;
    }
}
