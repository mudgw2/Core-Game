<?php
declare(strict_types=1);

namespace Core\Modules\Universe;

use RuntimeException;

/** Applies supported event types to derived universe state inside the same storage transaction. */
final class UniverseEventProcessor
{
    public const HANDLER_VERSION = 1;

    public function process(array $event, array $state, array $baseline = []): array
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
        } elseif (($event['type'] ?? '') === 'system_explored') {
            $payload = is_array($event['payload'] ?? null) ? $event['payload'] : [];
            $systemId = $payload['system_id'] ?? null;
            if (!is_string($systemId) || trim($systemId) === '') {
                throw new RuntimeException('system_explored event requires a non-empty system_id string.');
            }
            $systemId = trim($systemId);
            $systems = $baseline['systems'] ?? null;
            if (!is_array($systems)) {
                throw new RuntimeException('Universe baseline systems are unavailable for exploration validation.');
            }
            $target = null;
            foreach ($systems as $system) {
                if (is_array($system) && ($system['id'] ?? null) === $systemId) {
                    $target = $system;
                    break;
                }
            }
            if ($target === null) {
                throw new RuntimeException('system_explored system_id does not belong to this universe.');
            }

            $explored = $state['projections']['explored_systems'] ?? [];
            if (!is_array($explored)) {
                throw new RuntimeException('Universe explored_systems projection structure is invalid.');
            }
            $alreadyExplored = false;
            foreach ($explored as $entry) {
                if (is_array($entry) && ($entry['system_id'] ?? null) === $systemId) {
                    $alreadyExplored = true;
                    break;
                }
            }
            if ($alreadyExplored) {
                $status = 'already_explored';
            } else {
                $explored[] = [
                    'system_id' => $systemId,
                    'name' => (string)($target['name'] ?? $systemId),
                    'classification' => (string)($target['classification'] ?? 'unknown'),
                    'x' => (int)($target['x'] ?? 0),
                    'y' => (int)($target['y'] ?? 0),
                    'first_explored_revision' => (int)$event['revision'],
                    'first_explored_at' => (string)$event['recorded_at'],
                ];
                $state['projections']['explored_systems'] = $explored;
                $status = 'applied';
            }
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
