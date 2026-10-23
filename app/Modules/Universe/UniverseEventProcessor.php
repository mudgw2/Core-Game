<?php
declare(strict_types=1);

namespace Core\Modules\Universe;

use RuntimeException;

/** Applies supported event types to derived universe state inside the same storage transaction. */
final class UniverseEventProcessor
{
    public const HANDLER_VERSION = 1;

    public function process(array $event, array $state): array
    {
        $state['projections'] = is_array($state['projections'] ?? null) ? $state['projections'] : [];
        $state['event_processing'] = is_array($state['event_processing'] ?? null) ? $state['event_processing'] : [];

        $status = 'unhandled';
        if (($event['type'] ?? '') === 'note_added') {
            $payload = is_array($event['payload'] ?? null) ? $event['payload'] : [];
            $text = $payload['text'] ?? $payload['value'] ?? null;
            if (!is_string($text) || trim($text) === '') {
                throw new RuntimeException('note_added event requires a non-empty text or value string.');
            }
            $text = trim($text);
            $characterCount = preg_match_all('/./us', $text);
            if ($characterCount === false || $characterCount > 500) {
                throw new RuntimeException('note_added text cannot exceed 500 characters.');
            }

            $notes = $state['projections']['notes'] ?? [];
            if (!is_array($notes)) {
                throw new RuntimeException('Universe notes projection structure is invalid.');
            }
            $notes[] = [
                'id' => (string)$event['event_id'],
                'text' => $text,
                'revision' => (int)$event['revision'],
                'created_at' => (string)$event['recorded_at'],
            ];
            $state['projections']['notes'] = $notes;
            $status = 'applied';
        }

        $state['event_processing'][] = [
            'event_id' => (string)$event['event_id'],
            'type' => (string)$event['type'],
            'status' => $status,
            'handler_version' => self::HANDLER_VERSION,
        ];

        return $state;
    }
}
