<?php
declare(strict_types=1);

namespace Core\Modules\Universe;

use RuntimeException;

/** Normalizes client-supplied changes into versioned, append-only universe events. */
final class UniverseEvent
{
    /** @return array{schema_version:int,event_id:string,type:string,payload:array,revision:int,recorded_at:string} */
    public static function fromChange(array $change, int $revision): array
    {
        $type = isset($change['type']) && is_string($change['type']) ? trim($change['type']) : '';
        if ($type === '' || preg_match('/^[a-z][a-z0-9_.-]{0,63}$/', $type) !== 1) {
            throw new RuntimeException('Event type must start with a lowercase letter and contain only lowercase letters, numbers, dots, underscores, or hyphens (maximum 64 characters).');
        }

        $payload = $change;
        unset($payload['type']);
        try {
            json_encode($payload, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new RuntimeException('Event payload must contain valid JSON data.', 0, $e);
        }

        return [
            'schema_version' => 1,
            'event_id' => bin2hex(random_bytes(16)),
            'type' => $type,
            'payload' => $payload,
            'revision' => $revision,
            'recorded_at' => gmdate('c'),
        ];
    }
}
