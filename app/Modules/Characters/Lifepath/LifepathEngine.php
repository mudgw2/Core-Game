<?php
declare(strict_types=1);

namespace Core\Modules\Characters\Lifepath;


class LifepathEngine {
    private array $races = [];

    public function __construct() {
        $this->races = $this->loadJsonData('races.json');
    }

    public function getRaces(): array {
        return $this->races;
    }

    public function renderRaceSelector(string $selectedKey = 'human'): string {
        $selectedKey = !empty($selectedKey) ? strtolower(trim($selectedKey)) : 'human';
        
        $html = '<select name="race" id="race-select" class="form-select bg-dark text-white border-secondary">';
        foreach ($this->races as $key => $race) {
            $name = htmlspecialchars($race['name'] ?? ucfirst($key));
            $selected = (strcasecmp($key, $selectedKey) === 0) ? ' selected' : '';
            $html .= sprintf(
                '<option value="%s"%s>%s (Starts: %d, Grad: %d)</option>',
                htmlspecialchars($key),
                $selected,
                $name,
                (int)($race['starting_age'] ?? 0),
                (int)($race['graduation_age'] ?? 0)
            );
        }
        $html .= '</select>';
        return $html;
    }

    private function loadJsonData(string $relativePath): array {
        static $cache = [];
        if (isset($cache[$relativePath])) {
            return $cache[$relativePath];
        }

        $filePath = CORE_ROOT . '/data/lifepath/' . $relativePath;
        $data = [];

        if (file_exists($filePath)) {
            $content = file_get_contents($filePath);
            $content = preg_replace('/^\xEF\xBB\xBF/', '', $content);
            $decoded = json_decode($content, true);
            if (is_array($decoded)) {
                $data = $decoded;
            }
        }
        return $cache[$relativePath] = $data;
    }

    private function getWeightedRandom(array $items, string $key): ?array {
        $totalWeight = 0;
        foreach ($items as $item) {
            $totalWeight += (int)($item['weight'] ?? 1);
        }

        if ($totalWeight <= 0) {
            return $items[array_rand($items)] ?? null;
        }

        $roll = mt_rand(1, $totalWeight);
        $currentWeight = 0;

        foreach ($items as $item) {
            $currentWeight += (int)($item['weight'] ?? 1);
            if ($roll <= $currentWeight) {
                return $item;
            }
        }

        return $items[array_rand($items)] ?? null;
    }

    private function generateSiblings(array $raceData): array {
        $raceName = strtolower($raceData['name'] ?? '');
        
        if ($raceName === 'android' || !empty($raceData['no_siblings'])) {
            return [];
        }

        $maxSiblings = (int)($raceData['max_siblings'] ?? 4);
        $onlyChildChance = (int)($raceData['only_child_chance'] ?? 6);

        if ($onlyChildChance > 0 && mt_rand(1, $onlyChildChance) === $onlyChildChance) {
            return [];
        }

        $namesData = $this->loadJsonData('names.json');
        $friendData = $this->loadJsonData('friend.json');
        $enemyData = $this->loadJsonData('enemy.json');

        $numSiblings = mt_rand(1, max(1, $maxSiblings));
        $siblings = [];

        $relationTypes = ['Older', 'Younger', 'Twin / Same Age'];

        $defaultDispositions = [
            'Supportive and protective.',
            'Distant and indifferent.',
            'Competitive and driven.',
            'Troubled and unpredictable.'
        ];

        for ($s = 0; $s < $numSiblings; $s++) {
            $isMale = (mt_rand(1, 2) === 1);
            $genderLabel = $isMale ? 'Male' : 'Female';

            $pool = $namesData[$isMale ? 'male' : 'female'] ?? [];
            $name = 'Unknown';
            if (!empty($pool)) {
                $selected = $pool[array_rand($pool)];
                $name = is_array($selected) ? ($selected['name'] ?? reset($selected)) : $selected;
            }

            $relation = $relationTypes[array_rand($relationTypes)];

            $dispositionRoll = mt_rand(1, 3);
            $disposition = 'Supportive and close.';

            if ($dispositionRoll === 1 && !empty($friendData)) {
                $friendPool = $friendData['friend'] ?? $friendData;
                if (is_array($friendPool) && !empty($friendPool)) {
                    $selectedFriend = $friendPool[array_rand($friendPool)];
                    $disposition = is_array($selectedFriend) ? ($selectedFriend['event'] ?? reset($selectedFriend)) : $selectedFriend;
                }
            } elseif ($dispositionRoll === 2 && !empty($enemyData)) {
                $enemyPool = $enemyData['enemy'] ?? $enemyData;
                if (is_array($enemyPool) && !empty($enemyPool)) {
                    $selectedEnemy = $enemyPool[array_rand($enemyPool)];
                    $disposition = is_array($selectedEnemy) ? ($selectedEnemy['event'] ?? reset($selectedEnemy)) : $selectedEnemy;
                }
            } else {
                $disposition = $defaultDispositions[array_rand($defaultDispositions)];
            }

            $siblings[] = [
                'name'        => trim((string)$name),
                'gender'      => $genderLabel,
                'relation'    => $relation,
                'disposition' => is_string($disposition) ? $disposition : 'Connected.'
            ];
        }

        return $siblings;
    }

private function generateYearlyEvent(): array {
        $eventCategories = [
            ['file' => 'windfall.json', 'key' => 'windfall', 'weight' => 20],
            ['file' => 'enlightenment.json', 'key' => 'enlightenment', 'weight' => 20],
            ['file' => 'friend.json', 'key' => 'friend', 'weight' => 20],
            ['file' => 'romance.json', 'key' => 'romance', 'weight' => 15],
            ['file' => 'enemy.json', 'key' => 'enemy', 'weight' => 10],
            ['file' => 'misfortune.json', 'key' => 'misfortune', 'weight' => 10],
            ['file' => 'tragedy.json', 'key' => 'tragedy', 'weight' => 5],
        ];

        $totalCategoryWeight = array_sum(array_column($eventCategories, 'weight'));
        $roll = mt_rand(1, $totalCategoryWeight);
        $currentWeight = 0;
        $chosenCategory = $eventCategories[0];

        foreach ($eventCategories as $cat) {
            $currentWeight += $cat['weight'];
            if ($roll <= $currentWeight) {
                $chosenCategory = $cat;
                break;
            }
        }

        $fileData = $this->loadJsonData($chosenCategory['file']);
        $pool = $fileData[$chosenCategory['key']] ?? [];
        
        $eventTitle = ucfirst($chosenCategory['key']);
        $eventDesc = "A relatively quiet year.";

        if (!empty($pool) && is_array($pool)) {
                    $selectedEvent = $pool[array_rand($pool)];
                    if (is_array($selectedEvent)) {
                        // Check for the new title/description format first
                        if (isset($selectedEvent['title']) || isset($selectedEvent['description'])) {
                            $eventTitle = $selectedEvent['title'] ?? ucfirst($chosenCategory['key']);
                            $eventDesc = $selectedEvent['description'] ?? '';
                        } 
                        // Fallback for old single 'event' key format
                        elseif (isset($selectedEvent['event'])) {
                            $eventDesc = $selectedEvent['event'];
                        }
                    } elseif (is_string($selectedEvent)) {
                        $eventDesc = $selectedEvent;
                    }
                }

                return [
                    'type' => $chosenCategory['key'],
                    'title' => $eventTitle,
                    'description' => $eventDesc
                ];
    }

    public function generateLifepath(?string $raceKey = null, ?string $seed = null, string $action = 'generate', int $targetAge = 0): array {
        // 1. Resolve and validate race key safely (strict fallback to 'human')
        $raceKey = !empty($raceKey) ? strtolower(trim($raceKey)) : 'human';
        if (!isset($this->races[$raceKey])) {
            $resolved = 'human';
            foreach ($this->races as $k => $v) {
                if (strcasecmp($k, $raceKey) === 0) {
                    $resolved = $k;
                    break;
                }
            }
            $raceKey = $resolved;
        }

        $raceData = $this->races[$raceKey];
        $startingAge = max(0, (int)($raceData['starting_age'] ?? 13));
        $maximumAge = max($startingAge, (int)($raceData['max_allowable_age'] ?? 120));

        // 2. Unpack seed string format: baseSeed:currentAge:raceKey
        $baseSeed = null;
        $savedAge = 0;
        $savedRace = null;

        if (!empty($seed) && strpos($seed, ':') !== false) {
            $parts = explode(':', $seed);
            $baseSeed = trim($parts[0] ?? '');
            $savedAge = (int)($parts[1] ?? 0);
            $savedRace = strtolower(trim($parts[2] ?? ''));
        }

        // 3. Determine whether to start fresh or advance
        // Fresh start if: action is 'generate', no base seed exists, or the race was changed in the dropdown.
        $isNewLifepath = ($action === 'generate') || empty($baseSeed) || ($savedRace !== $raceKey);

        if ($isNewLifepath) {
            $baseSeed = bin2hex(random_bytes(4));
            $targetAgeToBuild = $startingAge;
        } else {
            // Advancing existing lifepath
            if ($action === 'advance') {
                $targetAgeToBuild = min($maximumAge, max($startingAge, $savedAge + 1));
            } elseif ($targetAge > 0) {
                $targetAgeToBuild = min($maximumAge, max($startingAge, $targetAge));
            } else {
                $targetAgeToBuild = min($maximumAge, max($startingAge, $savedAge));
            }
        }

        // Seed the random number generator consistently
        mt_srand(crc32($baseSeed));

        $birthData = $this->loadJsonData('birth.json');
        $caretakersData = $this->loadJsonData('caretakers.json');
        $caretakersStatusData = $this->loadJsonData('caretakers_status.json');
        $backgroundData = $this->loadJsonData('background.json');
        $clothingData = $this->loadJsonData('clothing.json');
        $environmentData = $this->loadJsonData('environment.json');
        $markData = $this->loadJsonData('mark.json');

        $siblings = $this->generateSiblings($raceData);

        $history = [];
        $loopAge = $startingAge;

while ($loopAge <= $targetAgeToBuild) {
            if ($loopAge === $startingAge) {
                $birthPlace = 'Unknown Location';
                if (!empty($birthData)) {
                    $selectedBirth = $this->getWeightedRandom($birthData, 'birth');
                    if ($selectedBirth && isset($selectedBirth['birth'])) {
                        $birthPlace = $selectedBirth['birth'];
                    }
                }

                $caretakers = 'Natural Parents';
                if (!empty($caretakersData)) {
                    $selectedCaretakers = $this->getWeightedRandom($caretakersData, 'caretakers');
                    if ($selectedCaretakers && isset($selectedCaretakers['caretakers'])) {
                        $caretakers = $selectedCaretakers['caretakers'];
                    }
                }

                $caretakersStatus = 'Alive';
                if (!empty($caretakersStatusData)) {
                    $selectedStatus = $this->getWeightedRandom($caretakersStatusData, 'caretakers_status');
                    if ($selectedStatus && isset($selectedStatus['caretakers_status'])) {
                        $caretakersStatus = $selectedStatus['caretakers_status'];
                    }
                }

                $background = 'Working Class';
                if (!empty($backgroundData)) {
                    $selectedBg = $backgroundData[array_rand($backgroundData)];
                    if (is_array($selectedBg) && isset($selectedBg['background'])) {
                        $background = $selectedBg['background'];
                    } elseif (is_string($selectedBg)) {
                        $background = $selectedBg;
                    }
                }

                $clothing = 'Clothing suitable to the region of origin';
                if (!empty($clothingData)) {
                    $selectedCloth = $clothingData[array_rand($clothingData)];
                    if (is_array($selectedCloth) && isset($selectedCloth['clothing'])) {
                        $clothing = $selectedCloth['clothing'];
                    } elseif (is_string($selectedCloth)) {
                        $clothing = $selectedCloth;
                    }
                }

                $environment = 'Provided guidance and care and allowed room to grow and explore';
                if (!empty($environmentData)) {
                    $selectedEnv = $this->getWeightedRandom($environmentData, 'environment');
                    if ($selectedEnv && isset($selectedEnv['environment'])) {
                        $environment = $selectedEnv['environment'];
                    }
                }

                $mark = 'No distinguishing marks';
                if (!empty($markData)) {
                    $selectedMark = $markData[array_rand($markData)];
                    if (is_array($selectedMark) && isset($selectedMark['mark'])) {
                        $mark = $selectedMark['mark'];
                    } elseif (is_string($selectedMark)) {
                        $mark = $selectedMark;
                    }
                }

                $siblingsHtml = '';
                if (!empty($siblings)) {
                    $siblingsHtml .= '<p class="mb-1 text-white"><strong>Siblings:</strong></p><ul class="mb-1 text-white ps-3">';
                    foreach ($siblings as $sib) {
                        $siblingsHtml .= sprintf(
                            '<li>%s (%s, %s) — Disposition: %s</li>',
                            htmlspecialchars($sib['name']),
                            htmlspecialchars($sib['gender']),
                            htmlspecialchars($sib['relation']),
                            htmlspecialchars($sib['disposition'])
                        );
                    }
                    $siblingsHtml .= '</ul>';
                } else {
                    $siblingsHtml = '<p class="mb-1 text-white"><strong>Siblings:</strong> None (Only child or species condition).</p>';
                }

                // Upbringing background text mapped to description
                $upbringingDescription = sprintf(
                    'Born into a %s background at %s.<br>' .
                    'Raised by %s (Status: %s).<br>' .
                    '%s' .
                    'Social Class & Style: %s background with preferred clothing: %s.<br>' .
                    'Environment: %s.<br>' .
                    'Distinguishing Mark: %s.',
                    strtolower($raceData['name'] ?? $loopAge),
                    $birthPlace,
                    $caretakers,
                    $caretakersStatus,
                    $siblingsHtml,
                    $background,
                    $clothing,
                    $environment,
                    $mark
                );

                $history[] = [
                    'age' => $loopAge,
                    'type' => 'upbringing',
                    'title' => 'Background & Upbringing',
                    'description' => $upbringingDescription,
                    'is_graduation' => false
                ];

                // First year yearly event
                $yearlyEvent = $this->generateYearlyEvent();
                $history[] = [
                    'age' => $loopAge,
                    'type' => $yearlyEvent['type'],
                    'title' => ucfirst($yearlyEvent['type']),
                    'description' => $yearlyEvent['description'],
                    'is_graduation' => false
                ];
            } else {
                $yearlyEvent = $this->generateYearlyEvent();
                $isGrad = ($loopAge === (int)($raceData['graduation_age'] ?? 0));

                $history[] = [
                    'age' => $loopAge,
                    'type' => $yearlyEvent['type'],
                    'title' => ucfirst($yearlyEvent['type']),
                    'description' => $yearlyEvent['description'],
                    'is_graduation' => $isGrad
                ];
            }

            $loopAge++;
        }

        // Reset global mt_srand state so other scripts aren't affected
        mt_srand();

        // Pack the new composite seed state (baseSeed:currentAge:raceKey)
        $finalAge = $targetAgeToBuild;
        $compositeSeed = $baseSeed . ':' . $finalAge . ':' . $raceKey;

        return [
            'race' => [
                'key' => $raceKey,
                'name' => $raceData['name'] ?? ucfirst($raceKey),
                $startingAge,
                'starting_age' => $startingAge,
                'graduation_age' => (int)($raceData['graduation_age'] ?? 0),
                'max_allowable_age' => $maximumAge
            ],
            'seed' => $compositeSeed,
            'current_age' => $finalAge,
            'maturation_age' => $startingAge,
            'max_allowable_age' => $maximumAge,
            'at_maximum_age' => $finalAge >= $maximumAge,
            'siblings' => $siblings,
            'history' => $history
        ];
    }
}?>