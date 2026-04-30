<?php

namespace App\Services\Integrations;

/**
 * Placeholder for JSON Schema validation of normalized payloads (wire JSON Schema / league/json-schema later).
 */
final class JsonSchemaValidator
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function validate(array $data, string $normalizedSchemaVersion): bool
    {
        return $normalizedSchemaVersion !== '';
    }
}
