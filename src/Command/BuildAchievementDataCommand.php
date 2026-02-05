<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\BlizzardOAuthService;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Finder\Finder;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[AsCommand(
    name: 'app:build-achievement-data',
    description: 'Build achievement data files using Krowi IDs directly (no name matching)',
)]
final class BuildAchievementDataCommand extends Command
{
    private const string API_BASE_URL = 'https://%s.api.blizzard.com';
    private const string KROWI_PATH = 'krowi/DataAddons/Retail';
    private const string OUTPUT_PATH = 'src/Data';

    /** @var array<string, int> Folder prefix => expansion order */
    private const array FOLDER_TO_ORDER = [
        '01_Vanilla' => 1,
        '02_TheBurningCrusade' => 2,
        '03_WrathOfTheLichKing' => 3,
        '04_Cataclysm' => 4,
        '05_MistsOfPandaria' => 5,
        '06_WarlordsOfDaenor' => 6,
        '07_Legion' => 7,
        '08_BattleForAzeroth' => 8,
        '09_Shadowlands' => 9,
        '10_Dragonflight' => 10,
        '11_TheWarWithin' => 11,
        '12_Midnight' => 12,
    ];

    /** @var list<string> Subcategory type names */
    private const array SUBCATEGORY_TYPES = [
        'Quests',
        'Exploration',
        'Player vs. Player',
        'PvP',
        'Reputation',
        'Dragonriding Races',
        'Skyriding Races',
        'Dragon Glyphs',
        'Races',
    ];

    public function __construct(
        private readonly BlizzardOAuthService $oauthService,
        private readonly HttpClientInterface $httpClient,
        private readonly LoggerInterface $logger,
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDir,
        #[Autowire('%env(BLIZZARD_REGION)%')]
        private readonly string $region
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Building Achievement Data - Using Krowi IDs Directly');

        $accessToken = $this->oauthService->fetchClientCredentialsToken();
        $io->info('Access token obtained');

        $io->section('Step 1: Fetching French achievement names from Blizzard API');
        $frenchAchievements = $this->fetchAchievementIndex($accessToken, 'fr_FR');
        $io->info(sprintf('Fetched %d French achievements', count($frenchAchievements)));

        $io->section('Step 2: Parsing Krowi structure with achievement IDs');
        $krowiData = $this->parseKrowiStructure($io);
        $io->info(sprintf(
            'Parsed %d categories across %d expansions with %d unique achievements',
            array_sum(array_map(count(...), $krowiData['categories'])),
            count($krowiData['categories']),
            count($krowiData['all_achievement_ids'])
        ));

        $io->section('Step 3: Building data structures');
        $result = $this->buildDataStructures($krowiData, $frenchAchievements, $io);

        $io->section('Step 4: Writing data files');
        $outputPath = $this->projectDir . '/' . self::OUTPUT_PATH;

        $this->writePhpFile(
            $outputPath . '/achievement_expansion_map.php',
            $result['expansion_map'],
            'array<int, int>'
        );

        $this->writePhpFile(
            $outputPath . '/expansion_categories.php',
            $result['expansion_categories'],
            'array<int, list<array{name: string, krowi_category_id: int}>>'
        );

        $this->writePhpFile(
            $outputPath . '/category_achievements.php',
            $result['category_achievements'],
            'array<int, list<int>>'
        );

        $this->writePhpFile(
            $outputPath . '/achievement_names.php',
            $result['achievement_names'],
            'array<int, string>'
        );

        $io->success(sprintf(
            'Generated data files: %d achievements, %d categories, %d with French names, %d without names',
            count($result['achievement_names']),
            count($result['category_achievements']),
            $result['stats']['with_names'],
            $result['stats']['without_names']
        ));

        if ($result['stats']['without_names'] > 0) {
            $io->warning(sprintf(
                '%d achievements have no French name in Blizzard API (might be removed/outdated)',
                $result['stats']['without_names']
            ));
        }

        $this->logger->info('Achievement data built using direct IDs', [
            'total_achievements' => count($result['achievement_names']),
            'with_names' => $result['stats']['with_names'],
            'without_names' => $result['stats']['without_names'],
        ]);

        return Command::SUCCESS;
    }

    /**
     * Fetch achievement index from Blizzard API.
     *
     * @return array<int, string> achievement_id => name
     */
    private function fetchAchievementIndex(string $accessToken, string $locale): array
    {
        $url = sprintf(
            self::API_BASE_URL . '/data/wow/achievement/index?namespace=static-%s&locale=%s',
            $this->region,
            $this->region,
            $locale
        );

        try {
            $response = $this->httpClient->request('GET', $url, [
                'headers' => ['Authorization' => sprintf('Bearer %s', $accessToken)],
            ]);

            $data = $response->toArray();

            if (!isset($data['achievements']) || !is_array($data['achievements'])) {
                return [];
            }

            $achievements = [];
            foreach ($data['achievements'] as $achievement) {
                if (!is_array($achievement)) {
                    continue;
                }

                if (
                    isset($achievement['id'], $achievement['name'])
                    && is_int($achievement['id'])
                    && is_string($achievement['name'])
                    && $achievement['name'] !== ''
                ) {
                    $achievements[$achievement['id']] = $achievement['name'];
                }
            }

            return $achievements;
        } catch (\Throwable $e) {
            $this->logger->error('Failed to fetch achievement index', [
                'locale' => $locale,
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * Parse Krowi structure to extract categories and achievement IDs directly.
     *
     * @return array{
     *     categories: array<int, list<array{id: int, name: string, achievements: list<int>}>>,
     *     all_achievement_ids: array<int, bool>
     * }
     */
    private function parseKrowiStructure(SymfonyStyle $io): array
    {
        $krowiPath = $this->projectDir . '/' . self::KROWI_PATH;
        $categories = [];
        $allAchievementIds = [];

        $finder = new Finder();
        $finder->directories()->in($krowiPath)->depth(0)->sortByName();

        foreach ($finder as $expansionDir) {
            $folderName = $expansionDir->getFilename();
            $expansionOrder = self::FOLDER_TO_ORDER[$folderName] ?? null;

            if ($expansionOrder === null) {
                continue;
            }

            $categoryFile = $expansionDir->getRealPath() . '/CategoryData.lua';

            if (!file_exists($categoryFile)) {
                continue;
            }

            $content = file_get_contents($categoryFile);

            if ($content === false) {
                continue;
            }

            $content = str_replace(["\r\n", "\r"], "\n", $content);

            $parsedCategories = $this->parseCategoryFile($content, $expansionOrder);
            $categories[$expansionOrder] = $parsedCategories;

            $achievementCount = 0;
            foreach ($parsedCategories as $category) {
                foreach ($category['achievements'] as $achId) {
                    $allAchievementIds[$achId] = true;
                    $achievementCount++;
                }
            }

            $io->text(sprintf(
                '  %s: %d categories, %d achievements',
                $folderName,
                count($parsedCategories),
                $achievementCount
            ));
        }

        return [
            'categories' => $categories,
            'all_achievement_ids' => $allAchievementIds,
        ];
    }

    /**
     * Parse a single CategoryData.lua file.
     *
     * @return list<array{id: int, name: string, achievements: list<int>}>
     */
    private function parseCategoryFile(string $content, int $expansionOrder): array
    {
        $categories = [];
        $assignedAchievements = [];
        $lines = explode("\n", $content);
        $totalLines = count($lines);
        $currentZoneName = '';
        $categoryIdCounter = $expansionOrder * 100000;

        for ($i = 0; $i < $totalLines; $i++) {
            $line = $lines[$i];

            if ($this->isSubcategoryDeclaration($line)) {
                $typeName = $this->extractSubcategoryType($line);

                if ($typeName === null) {
                    continue;
                }

                $categoryId = $this->findCategoryIdAfterLine($lines, $i, $totalLines);

                if ($categoryId === null) {
                    $categoryIdCounter++;
                    $categoryId = $categoryIdCounter;
                }

                $achievementIds = $this->extractAchievementIdsFromSubcategory($lines, $i, $totalLines);

                if ($achievementIds === []) {
                    continue;
                }

                $categoryName = $currentZoneName !== ''
                    ? "{$currentZoneName} - {$this->normalizeTypeName($typeName)}"
                    : $this->normalizeTypeName($typeName);

                $categories[] = [
                    'id' => $categoryId,
                    'name' => $categoryName,
                    'achievements' => $achievementIds,
                ];

                foreach ($achievementIds as $achId) {
                    $assignedAchievements[$achId] = true;
                }

                continue;
            }

            if ($this->isZoneDeclaration($line)) {
                $zoneName = $this->extractZoneName($line);

                if ($zoneName !== null && !$this->isSubcategoryType($zoneName)) {
                    $currentZoneName = $zoneName;
                }
            }
        }

        $directCategories = $this->parseDirectAchievementLists($content, $categoryIdCounter, $assignedAchievements);
        $categories = array_merge($categories, $directCategories);

        return $categories;
    }

    /**
     * Build final data structures using Krowi IDs directly.
     *
     * @param array{
     *     categories: array<int, list<array{id: int, name: string, achievements: list<int>}>>,
     *     all_achievement_ids: array<int, bool>
     * } $krowiData
     * @param array<int, string> $frenchAchievements
     * @return array{
     *     expansion_map: array<int, int>,
     *     expansion_categories: array<int, list<array{name: string, krowi_category_id: int}>>,
     *     category_achievements: array<int, list<int>>,
     *     achievement_names: array<int, string>,
     *     stats: array{with_names: int, without_names: int}
     * }
     */
    private function buildDataStructures(
        array $krowiData,
        array $frenchAchievements,
        SymfonyStyle $io
    ): array {
        $expansionMap = [];
        $expansionCategories = [];
        $categoryAchievements = [];
        $achievementNames = [];
        $withNames = 0;
        $withoutNames = 0;
        $missingIds = [];

        foreach ($krowiData['categories'] as $expansionOrder => $categories) {
            $expansionCategories[$expansionOrder] = [];

            foreach ($categories as $category) {
                $categoryId = $category['id'];
                $validIds = [];

                foreach ($category['achievements'] as $achId) {
                    $expansionMap[$achId] = $expansionOrder;

                    if (isset($frenchAchievements[$achId])) {
                        $achievementNames[$achId] = $frenchAchievements[$achId];
                        $validIds[] = $achId;
                        $withNames++;
                    } else {
                        $withoutNames++;
                        $missingIds[] = $achId;
                    }
                }

                if ($validIds !== []) {
                    $expansionCategories[$expansionOrder][] = [
                        'name' => $category['name'],
                        'krowi_category_id' => $categoryId,
                    ];
                    $categoryAchievements[$categoryId] = $validIds;
                }
            }
        }

        if ($withoutNames > 0 && $withoutNames <= 30) {
            $io->note('Missing IDs from Blizzard API: ' . implode(', ', array_slice($missingIds, 0, 30)));
        }

        return [
            'expansion_map' => $expansionMap,
            'expansion_categories' => $expansionCategories,
            'category_achievements' => $categoryAchievements,
            'achievement_names' => $achievementNames,
            'stats' => ['with_names' => $withNames, 'without_names' => $withoutNames],
        ];
    }

    private function isSubcategoryDeclaration(string $line): bool
    {
        $pattern = '/\{\s*--\s*(Quests|Exploration|Player vs\. Player|PvP|Reputation|'
            . 'Dragonriding Races|Skyriding Races|Dragon Glyphs|Races)/';

        return (bool) preg_match($pattern, $line);
    }

    private function extractSubcategoryType(string $line): ?string
    {
        $pattern = '/\{\s*--\s*(Quests|Exploration|Player vs\. Player|PvP|Reputation|'
            . 'Dragonriding Races|Skyriding Races|Dragon Glyphs|Races)/';

        if (preg_match($pattern, $line, $match)) {
            return $match[1];
        }

        return null;
    }

    private function isZoneDeclaration(string $line): bool
    {
        return (bool) preg_match('/\{\s*--\s*[A-Za-z]/', $line);
    }

    private function extractZoneName(string $line): ?string
    {
        if (preg_match('/\{\s*--\s*([^,\n]+)/', $line, $match)) {
            $name = trim($match[1]);

            if ($name !== 'Zones' && $name !== 'Character' && $name !== '') {
                return $name;
            }
        }

        return null;
    }

    private function isSubcategoryType(string $name): bool
    {
        return in_array($name, self::SUBCATEGORY_TYPES, true) || $name === 'Zones' || $name === 'Character';
    }

    /**
     * @param list<string> $lines
     */
    private function findCategoryIdAfterLine(array $lines, int $currentLine, int $totalLines): ?int
    {
        for ($i = $currentLine + 1; $i < min($currentLine + 3, $totalLines); $i++) {
            $line = trim($lines[$i]);

            if (preg_match('/^(\d+),/', $line, $match)) {
                return (int) $match[1];
            }
        }

        return null;
    }

    /**
     * Extract achievement IDs directly from the subcategory block.
     *
     * @param list<string> $lines
     * @return list<int>
     */
    private function extractAchievementIdsFromSubcategory(array $lines, int $startLine, int $totalLines): array
    {
        $achievements = [];
        $foundTrue = false;
        $inAchievementBlock = false;
        $braceDepth = 0;

        for ($i = $startLine; $i < $totalLines; $i++) {
            $line = $lines[$i];
            $trimmedLine = trim($line);

            if ($trimmedLine === 'true,') {
                $foundTrue = true;
                continue;
            }

            if ($foundTrue && !$inAchievementBlock && str_contains($trimmedLine, '{')) {
                $inAchievementBlock = true;
                $braceDepth = 1;
                continue;
            }

            if ($inAchievementBlock) {
                $braceDepth += substr_count($line, '{') - substr_count($line, '}');

                if ($braceDepth <= 0) {
                    break;
                }

                if (preg_match('/^\s*(\d+),?\s*--/', $line, $match)) {
                    $achievements[] = (int) $match[1];
                }
            }

            if (!$foundTrue && $i > $startLine + 5) {
                break;
            }
        }

        return $achievements;
    }

    /**
     * Parse direct achievement lists (zones with achievements but no subcategory type).
     * Skips zones that have subcategories and achievements already assigned.
     *
     * @param array<int, bool> $assignedAchievements
     * @return list<array{id: int, name: string, achievements: list<int>}>
     */
    private function parseDirectAchievementLists(
        string $content,
        int $categoryIdCounter,
        array $assignedAchievements
    ): array {
        $categories = [];

        $pattern = '/\{\s*--\s*([A-Za-z][^\n,]+)\n\s*(\d+),\n\s*'
            . 'addon\.Get(?:Map|Category)(?:Name|InfoTitle)\(\d+\),\n\s*\{([^}]+)\}/s';

        if (preg_match_all($pattern, $content, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $zoneName = trim($match[1]);
                $categoryId = (int) $match[2];
                $achievementBlock = $match[3];

                if ($this->isSubcategoryType($zoneName)) {
                    continue;
                }

                if ($this->hasSubcategories($achievementBlock)) {
                    continue;
                }

                $achievements = [];
                preg_match_all('/(\d+),?\s*--/', $achievementBlock, $achMatches);

                foreach ($achMatches[1] as $idStr) {
                    $achId = (int) $idStr;

                    if (isset($assignedAchievements[$achId])) {
                        continue;
                    }

                    $achievements[] = $achId;
                }

                if ($achievements !== []) {
                    $categories[] = [
                        'id' => $categoryId,
                        'name' => $zoneName,
                        'achievements' => $achievements,
                    ];
                }
            }
        }

        return $categories;
    }

    /**
     * Check if the block contains subcategory declarations.
     */
    private function hasSubcategories(string $block): bool
    {
        $subcategoryPattern = '/--\s*(Quests|Exploration|Player vs\. Player|PvP|Reputation|'
            . 'Dragonriding Races|Skyriding Races|Dragon Glyphs|Races)\s*\n/';

        return (bool) preg_match($subcategoryPattern, $block);
    }

    private function normalizeTypeName(string $type): string
    {
        return match ($type) {
            'Player vs. Player', 'PvP' => 'PvP',
            'Dragonriding Races', 'Skyriding Races' => 'Races',
            default => $type,
        };
    }

    /**
     * @param array<int|string, mixed> $data
     */
    private function writePhpFile(string $path, array $data, string $phpDocType): void
    {
        ksort($data);
        $export = var_export($data, true);

        $export = (string) preg_replace('/array \(/', '[', $export);
        $export = (string) preg_replace('/\)$/', ']', $export);
        $export = (string) preg_replace('/\),/', '],', $export);
        $export = str_replace('  ', '    ', $export);

        $content = <<<PHP
<?php

declare(strict_types=1);

/**
 * Auto-generated from Blizzard API + Krowi addon data.
 * Do not edit manually.
 *
 * @var {$phpDocType}
 */
return {$export};

PHP;

        file_put_contents($path, $content);
    }
}
