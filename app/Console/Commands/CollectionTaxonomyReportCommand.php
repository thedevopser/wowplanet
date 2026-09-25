<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Application\Taxonomy\PendingTaxonomyEntries;
use App\Infrastructure\Taxonomy\CollectionEntity;
use Illuminate\Console\Command;
use InvalidArgumentException;

/**
 * Liste les entrées de catalogue que la taxonomie ne range pas encore.
 *
 * Le rapport n'est pas stocké : l'absence de ligne de taxonomie pour une ligne de catalogue
 * *est* le rapport, et une jointure gauche le reconstitue à tout moment. Une entrée rangée
 * nulle part **en connaissance de cause** porte, elle, une ligne de taxonomie aux deux
 * libellés nuls : elle est curée, donc hors de ce rapport.
 */
class CollectionTaxonomyReportCommand extends Command
{
    protected $signature = 'app:collection-taxonomy-report
        {--entity= : Ne rapporter qu\'une seule collection}
        {--limit=20 : Nombre d\'entrées détaillées par collection}';

    protected $description = 'Liste les entrées de collection à arbitrer dans la taxonomie';

    public function __construct(private readonly PendingTaxonomyEntries $pendingTaxonomyEntries)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        try {
            $entities = $this->entitiesToReport();
        } catch (InvalidArgumentException $invalidArgumentException) {
            $this->error($invalidArgumentException->getMessage());

            return self::FAILURE;
        }

        $limit = max(0, (int) $this->option('limit'));

        $this->info('Entrées à arbitrer');
        $this->newLine();

        foreach ($entities as $entity) {
            $this->reportEntity($entity, $limit);
        }

        return self::SUCCESS;
    }

    /**
     * @return list<CollectionEntity>
     */
    private function entitiesToReport(): array
    {
        /** @var string|null $requested */
        $requested = $this->option('entity');

        if ($requested === null) {
            return CollectionEntity::cases();
        }

        return [CollectionEntity::fromOption($requested)];
    }

    private function reportEntity(CollectionEntity $collectionEntity, int $limit): void
    {
        $pending = $this->pendingTaxonomyEntries->forEntity($collectionEntity);

        if ($pending === []) {
            $this->line(sprintf('  %-8s Rien à arbitrer', $collectionEntity->value));

            return;
        }

        $this->line(sprintf(
            '  %-8s %d à arbitrer sur %d au catalogue',
            $collectionEntity->value,
            count($pending),
            $this->pendingTaxonomyEntries->counts()[$collectionEntity->value]['catalogue'],
        ));

        foreach (array_slice($pending, 0, $limit) as $entry) {
            $this->line(sprintf('    %8d  %s', $entry['id'], $entry['name']));
        }

        $omitted = count($pending) - $limit;
        if ($omitted > 0) {
            $this->line(sprintf('    … et %d autre(s).', $omitted));
        }

        $this->newLine();
    }
}
