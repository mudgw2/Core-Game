<?php
declare(strict_types=1);

namespace Core\Modules\Universe;

use Core\Storage\JsonStorage;
use RuntimeException;

/** Reversible lifecycle controls for a saved universe. */
final class UniverseLifecycleService
{
    private const ACTIONS = ['disable', 'enable', 'lock', 'unlock'];

    public function __construct(private JsonStorage $storage) {}

    public function apply(string $universeId, string $action, int $expectedRevision): array
    {
        $universeId = trim($universeId); $action = trim($action);
        if ($universeId === '' || $action === '') throw new RuntimeException('universe_id and action are required.');
        if (!in_array($action, self::ACTIONS, true)) throw new RuntimeException('Action must be disable, enable, lock, or unlock.');
        if ($expectedRevision < 0) throw new RuntimeException('Expected universe lifecycle revision must be zero or greater.');

        $changed = false;
        $universe = $this->storage->transact('universes', $universeId, static function (array $universe) use ($action, $expectedRevision, &$changed): array {
            $lifecycle = is_array($universe['lifecycle'] ?? null) ? $universe['lifecycle'] : [];
            $revision = (int)($lifecycle['revision'] ?? 0);
            if ($revision !== $expectedRevision) throw new RuntimeException('Universe lifecycle revision conflict: expected ' . $expectedRevision . ', current revision is ' . $revision . '.');
            $lifecycle['enabled'] = ($lifecycle['enabled'] ?? true) === true;
            $lifecycle['locked'] = ($lifecycle['locked'] ?? false) === true;
            $before = [$lifecycle['enabled'], $lifecycle['locked']];
            if ($action === 'disable') $lifecycle['enabled'] = false;
            if ($action === 'enable') $lifecycle['enabled'] = true;
            if ($action === 'lock') $lifecycle['locked'] = true;
            if ($action === 'unlock') $lifecycle['locked'] = false;
            $changed = $before !== [$lifecycle['enabled'], $lifecycle['locked']];
            if ($changed) {
                $lifecycle['revision'] = $revision + 1;
                $lifecycle['updated_at'] = gmdate('c');
                $lifecycle['last_action'] = $action;
            } else {
                $lifecycle['revision'] = $revision;
            }
            $universe['lifecycle'] = $lifecycle;
            return $universe;
        });
        return ['lifecycle_status' => $changed ? 'updated' : 'unchanged', 'action' => $action, 'universe' => $universe];
    }
}
