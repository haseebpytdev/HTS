<?php

namespace App\Integrations\Sabre\Support;

final class SabreSoapResponseExtractor
{
    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function extractOperation(array $payload, string $operationKey): array
    {
        $result = $this->findByKey($payload, $operationKey);

        return is_array($result) ? $result : $payload;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return mixed
     */
    private function findByKey(array $payload, string $needle): mixed
    {
        foreach ($payload as $key => $value) {
            if (stripos((string) $key, $needle) !== false) {
                return $value;
            }
            if (is_array($value)) {
                $nested = $this->findByKey($value, $needle);
                if ($nested !== null) {
                    return $nested;
                }
            }
        }

        return null;
    }
}

