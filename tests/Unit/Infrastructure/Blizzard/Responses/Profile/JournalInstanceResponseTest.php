<?php

declare(strict_types=1);

use App\Infrastructure\Blizzard\Responses\Profile\JournalInstanceResponse;
use App\Infrastructure\Blizzard\Responses\ResponsePayload;

/**
 * @param  array<string, mixed>  $decoded
 */
function journalInstance(array $decoded): JournalInstanceResponse
{
    return JournalInstanceResponse::fromPayload(ResponsePayload::forEndpoint('data/wow/journal-instance/1307', $decoded));
}

test('it carries the localized instance and encounter names', function (): void {
    $journalInstanceResponse = journalInstance([
        'name' => 'La Flèche du Vide',
        'encounters' => [['id' => 2733, 'name' => 'Empereur Averzian'], ['id' => 2734, 'name' => 'Deuxième boss']],
    ]);

    expect($journalInstanceResponse->name)->toBe('La Flèche du Vide')
        ->and($journalInstanceResponse->encounterNames)->toBe([2733 => 'Empereur Averzian', 2734 => 'Deuxième boss']);
});

test('an encounter without id is skipped, one without name keeps an empty name', function (): void {
    expect(journalInstance(['encounters' => [['name' => 'orphan'], ['id' => 2733]]])->encounterNames)->toBe([2733 => '']);
});

test('an empty journal entry has neither name nor encounter', function (): void {
    $journalInstanceResponse = journalInstance([]);

    expect($journalInstanceResponse->name)->toBeNull()
        ->and($journalInstanceResponse->encounterNames)->toBe([]);
});
