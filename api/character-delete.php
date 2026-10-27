<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
http_response_code(410);
echo json_encode(['ok' => false, 'error' => ['code' => 'character_deletion_retired', 'message' => 'Permanent character deletion is disabled. Disable a character to archive it; restore it later from the Archive view.']], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
