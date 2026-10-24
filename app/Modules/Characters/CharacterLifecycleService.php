<?php
declare(strict_types=1);

namespace Core\Modules\Characters;

use Core\Storage\JsonStorage;
use RuntimeException;

/** Reversible enable/disable and lock/unlock controls for a character record. */
final class CharacterLifecycleService
{
    private const ACTIONS = ['disable', 'enable', 'lock', 'unlock'];

    public function __construct(private JsonStorage $storage) {}

    public function apply(string $universeId, string $characterId, string $action, int $expectedRevision): array
    {
        $universeId = trim($universeId); $characterId = trim($characterId); $action = trim($action);
        if ($universeId === '' || $characterId === '' || $action === '') throw new RuntimeException('universe_id, character_id, and action are required.');
        if (!in_array($action, self::ACTIONS, true)) throw new RuntimeException('Action must be disable, enable, lock, or unlock.');
        if ($expectedRevision < 0) throw new RuntimeException('Expected character revision must be zero or greater.');
        $universe = $this->storage->read('universes', $universeId);
        if ($universe === null || ($universe['id'] ?? null) !== $universeId) throw new RuntimeException('The selected universe does not exist.');

        $changed = false;
        $character = $this->storage->transact('characters', $characterId, static function (array $character) use ($universeId, $action, $expectedRevision, &$changed): array {
            if (($character['universe_id'] ?? null) !== $universeId) throw new RuntimeException('Character does not belong to the selected universe.');
            $revision = (int)($character['revision'] ?? 0);
            if ($revision !== $expectedRevision) throw new RuntimeException('Character revision conflict: expected ' . $expectedRevision . ', current revision is ' . $revision . '.');
            $lifecycle = is_array($character['lifecycle'] ?? null) ? $character['lifecycle'] : [];
            $lifecycle['enabled'] = ($lifecycle['enabled'] ?? true) === true;
            $lifecycle['locked'] = ($lifecycle['locked'] ?? false) === true;
            $before = $lifecycle;
            if ($action === 'disable') $lifecycle['enabled'] = false;
            if ($action === 'enable') $lifecycle['enabled'] = true;
            if ($action === 'lock') $lifecycle['locked'] = true;
            if ($action === 'unlock') $lifecycle['locked'] = false;
            $changed = $before['enabled'] !== $lifecycle['enabled'] || $before['locked'] !== $lifecycle['locked'];
            if ($changed) {
                $lifecycle['updated_at'] = gmdate('c');
                $lifecycle['last_action'] = $action;
                $character['revision'] = $revision + 1;
            }
            $character['lifecycle'] = $lifecycle;
            return $character;
        });
        return ['lifecycle_status' => $changed ? 'updated' : 'unchanged', 'action' => $action, 'character' => $character];
    }
}
