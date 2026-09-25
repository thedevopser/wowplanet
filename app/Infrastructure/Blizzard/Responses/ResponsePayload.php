<?php

declare(strict_types=1);

namespace App\Infrastructure\Blizzard\Responses;

use App\Infrastructure\Blizzard\Responses\Exceptions\MissingFieldException;
use App\Infrastructure\Blizzard\Responses\Exceptions\UnexpectedFieldTypeException;

/**
 * Lecture typée d'un JSON externe décodé : réponse de l'API Blizzard, export curé de SimpleArmory.
 *
 * C'est le seul endroit où le `mixed` sorti de `json_decode` est autorisé à vivre :
 * rien n'en ressort qui ne soit typé, et une réponse qui ne respecte pas le contrat
 * échoue ici plutôt que trois couches plus loin.
 */
final readonly class ResponsePayload
{
    /**
     * @param  array<array-key, mixed>  $decoded
     */
    private function __construct(
        private string $endpoint,
        private string $path,
        private array $decoded,
    ) {}

    /**
     * @param  array<array-key, mixed>  $decoded
     */
    public static function forEndpoint(string $endpoint, array $decoded): self
    {
        return new self($endpoint, '', $decoded);
    }

    public function requiredString(string $key): string
    {
        return $this->asString($key, $this->required($key));
    }

    public function optionalString(string $key): ?string
    {
        $value = $this->decoded[$key] ?? null;

        return $value === null ? null : $this->asString($key, $value);
    }

    public function requiredInt(string $key): int
    {
        return $this->asInt($key, $this->required($key));
    }

    public function optionalInt(string $key): ?int
    {
        $value = $this->decoded[$key] ?? null;

        return $value === null ? null : $this->asInt($key, $value);
    }

    public function requiredFloat(string $key): float
    {
        return $this->asFloat($key, $this->required($key));
    }

    /**
     * JSON ne distingue pas 280 de 280.0 : un entier est un nombre valide.
     */
    public function optionalFloat(string $key): ?float
    {
        $value = $this->decoded[$key] ?? null;

        return $value === null ? null : $this->asFloat($key, $value);
    }

    public function optionalBool(string $key): ?bool
    {
        $value = $this->decoded[$key] ?? null;
        if ($value === null) {
            return null;
        }

        if (! is_bool($value)) {
            throw UnexpectedFieldTypeException::at($this->pathTo($key), $this->endpoint, 'bool', $value);
        }

        return $value;
    }

    public function requiredObject(string $key): self
    {
        return $this->asObject($key, $this->required($key));
    }

    /**
     * Lecture tolérante, réservée aux champs que le code d'origine lisait par `is_numeric` :
     * une chaîne numérique ou un décimal passent, toute autre valeur est ignorée plutôt que rejetée.
     */
    public function lenientInt(string $key): ?int
    {
        $value = $this->decoded[$key] ?? null;

        return is_numeric($value) ? (int) $value : null;
    }

    /**
     * Lecture tolérante d'un libellé : ce qui n'est pas du texte est ignoré plutôt que rejeté.
     */
    public function lenientString(string $key): ?string
    {
        $value = $this->decoded[$key] ?? null;

        return is_string($value) ? $value : null;
    }

    public function optionalObject(string $key): ?self
    {
        $value = $this->decoded[$key] ?? null;

        return $value === null ? null : $this->asObject($key, $value);
    }

    /**
     * Un champ de liste absent rend une liste vide : l'API omet régulièrement les
     * collections vides plutôt que de renvoyer `[]`.
     *
     * @return list<self>
     */
    public function objectList(string $key): array
    {
        $value = $this->decoded[$key] ?? null;
        if ($value === null) {
            return [];
        }

        if (! is_array($value) || ! array_is_list($value)) {
            throw UnexpectedFieldTypeException::at($this->pathTo($key), $this->endpoint, 'list', $value);
        }

        $entries = [];
        foreach ($value as $index => $entry) {
            $entries[] = $this->asObject($key.'.'.$index, $entry);
        }

        return $entries;
    }

    /**
     * Un endpoint en 404 ou abandonné après ses tentatives rend une réponse vide.
     */
    public function isEmpty(): bool
    {
        return $this->decoded === [];
    }

    /**
     * @return list<int>
     */
    public function intList(string $key): array
    {
        $value = $this->decoded[$key] ?? null;
        if ($value === null) {
            return [];
        }

        if (! is_array($value) || ! array_is_list($value)) {
            throw UnexpectedFieldTypeException::at($this->pathTo($key), $this->endpoint, 'list', $value);
        }

        $integers = [];
        foreach ($value as $index => $entry) {
            $integers[] = $this->asInt($key.'.'.$index, $entry);
        }

        return $integers;
    }

    /**
     * Une table indexée par identifiant. Un objet JSON aux clés « 0 », « 1 »… revient en liste
     * une fois décodé : la liste est donc acceptée au même titre que l'objet.
     *
     * @return array<int, string>
     */
    public function stringMap(string $key): array
    {
        $strings = [];
        foreach ($this->mapEntries($key) as $identifier => $entry) {
            $strings[$identifier] = $this->asString($key.'.'.$identifier, $entry);
        }

        return $strings;
    }

    /**
     * @return array<int, self>
     */
    public function objectMap(string $key): array
    {
        $objects = [];
        foreach ($this->mapEntries($key) as $identifier => $entry) {
            $objects[$identifier] = $this->asMapEntry($key.'.'.$identifier, $entry);
        }

        return $objects;
    }

    /**
     * Les entrées du lecteur lui-même, pour une table imbriquée dans une autre.
     *
     * @return array<int, self>
     */
    public function entries(): array
    {
        $objects = [];
        foreach ($this->decoded as $identifier => $entry) {
            if (! is_int($identifier)) {
                throw UnexpectedFieldTypeException::at($this->path, $this->endpoint, 'map keyed by integer', $this->decoded);
            }

            $objects[$identifier] = $this->asMapEntry((string) $identifier, $entry);
        }

        return $objects;
    }

    /**
     * @return array<int, mixed>
     */
    private function mapEntries(string $key): array
    {
        $value = $this->decoded[$key] ?? null;
        if ($value === null) {
            return [];
        }

        if (! is_array($value)) {
            throw UnexpectedFieldTypeException::at($this->pathTo($key), $this->endpoint, 'map keyed by integer', $value);
        }

        $entries = [];
        foreach ($value as $identifier => $entry) {
            if (! is_int($identifier)) {
                throw UnexpectedFieldTypeException::at($this->pathTo($key), $this->endpoint, 'map keyed by integer', $value);
            }

            $entries[$identifier] = $entry;
        }

        return $entries;
    }

    private function asMapEntry(string $key, mixed $value): self
    {
        if (! is_array($value)) {
            throw UnexpectedFieldTypeException::at($this->pathTo($key), $this->endpoint, 'object', $value);
        }

        return new self($this->endpoint, $this->pathTo($key), $value);
    }

    private function required(string $key): mixed
    {
        $value = $this->decoded[$key] ?? null;

        if ($value === null) {
            throw MissingFieldException::at($this->pathTo($key), $this->endpoint);
        }

        return $value;
    }

    private function asString(string $key, mixed $value): string
    {
        if (! is_string($value)) {
            throw UnexpectedFieldTypeException::at($this->pathTo($key), $this->endpoint, 'string', $value);
        }

        return $value;
    }

    private function asInt(string $key, mixed $value): int
    {
        if (! is_int($value)) {
            throw UnexpectedFieldTypeException::at($this->pathTo($key), $this->endpoint, 'int', $value);
        }

        return $value;
    }

    private function asFloat(string $key, mixed $value): float
    {
        if (! is_int($value) && ! is_float($value)) {
            throw UnexpectedFieldTypeException::at($this->pathTo($key), $this->endpoint, 'float', $value);
        }

        return (float) $value;
    }

    private function asObject(string $key, mixed $value): self
    {
        if (! is_array($value) || (array_is_list($value) && $value !== [])) {
            throw UnexpectedFieldTypeException::at($this->pathTo($key), $this->endpoint, 'object', $value);
        }

        return new self($this->endpoint, $this->pathTo($key), $value);
    }

    private function pathTo(string $key): string
    {
        return $this->path === '' ? $key : $this->path.'.'.$key;
    }
}
