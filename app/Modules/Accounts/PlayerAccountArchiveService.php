<?php
declare(strict_types=1);

namespace Core\Modules\Accounts;

use InvalidArgumentException;

/**
 * Account archival preserves identity and ownership references while disabling sign-in.
 * The operation is idempotent: the first archive timestamp is retained.
 */
final class PlayerAccountArchiveService
{
    public static function archive(array $accounts, string $accountId, ?string $archivedAt = null): array
    {
        if ($accountId === '' || !isset($accounts[$accountId]) || !is_array($accounts[$accountId])) {
            throw new InvalidArgumentException('Account not found.');
        }

        $account = $accounts[$accountId];
        if (($account['status'] ?? null) !== 'disabled' || empty($account['archived_at'])) {
            $account['archived_at'] = $archivedAt ?? gmdate('c');
        }

        $account['status'] = 'disabled';
        $accounts[$accountId] = $account;

        return $accounts;
    }
}
