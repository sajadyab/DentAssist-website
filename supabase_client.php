<?php
declare(strict_types=1);

final class SupabaseAPI
{
    private string $url;
    private string $key;
    /** @var array<int,string> */
    private array $headers;

    public function __construct(string $url, string $key)
    {
        $this->url = rtrim($url, '/');
        $this->key = trim($key);
        if ($this->url === '' || $this->key === '') {
            throw new InvalidArgumentException('Supabase URL and key are required.');
        }

        $this->headers = [
            'apikey: ' . $this->key,
            'Authorization: Bearer ' . $this->key,
            'Content-Type: application/json',
            'Prefer: return=representation',
        ];
    }

    public function select(string $table, array $query = []): array
    {
        $url = $this->buildTableUrl($table, $query);

        return $this->request('GET', $url);
    }

    public function insert(string $table, array $data): array
    {
        $url = $this->buildTableUrl($table);

        return $this->request('POST', $url, $data);
    }

    public function update(string $table, array $data, array $filter): array
    {
        $query = $this->buildFilterQuery($filter);
        $url = $this->buildTableUrl($table, $query);

        return $this->request('PATCH', $url, $data);
    }

    public function delete(string $table, array $filter): array
    {
        $query = $this->buildFilterQuery($filter);
        $url = $this->buildTableUrl($table, $query);

        return $this->request('DELETE', $url);
    }

    public function upsert(string $table, array $data, string $onConflict = 'id'): array
    {
        $headers = $this->headers;
        $headers[] = 'Prefer: resolution=merge-duplicates,return=representation';
        $url = $this->buildTableUrl($table, ['on_conflict' => $onConflict]);

        return $this->request('POST', $url, $data, $headers);
    }

    private function buildTableUrl(string $table, array $query = []): string
    {
        $table = trim($table);
        if ($table === '' || !preg_match('/^[a-zA-Z0-9_.]+$/', $table)) {
            throw new InvalidArgumentException('Invalid Supabase table name.');
        }
        $url = $this->url . '/rest/v1/' . rawurlencode($table);
        if (!empty($query)) {
            $url .= '?' . http_build_query($query);
        }

        return $url;
    }

    private function buildFilterQuery(array $filter): array
    {
        $query = [];
        foreach ($filter as $col => $val) {
            if (!is_string($col) || !preg_match('/^[a-zA-Z0-9_]+$/', $col)) {
                throw new InvalidArgumentException('Invalid filter column for Supabase request.');
            }
            $query[$col] = 'eq.' . (string) $val;
        }

        return $query;
    }

    private function request(string $method, string $url, ?array $data = null, ?array $headers = null): array
    {
        $ch = curl_init();
        if ($ch === false) {
            throw new RuntimeException('Failed to initialize cURL.');
        }

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers ?? $this->headers);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        // Force direct outbound requests to Supabase (ignore broken global proxy env).
        curl_setopt($ch, CURLOPT_PROXY, '');
        curl_setopt($ch, CURLOPT_NOPROXY, '*');

        if ($data !== null) {
            $normalized = $this->normalizePayloadForKnownLimits($data);
            $jsonData = json_encode($normalized, JSON_UNESCAPED_UNICODE);
            if ($jsonData === false) {
                throw new RuntimeException('Failed to JSON-encode request payload.');
            }
            curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);
        }

        $response = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error !== '') {
            throw new RuntimeException('cURL error: ' . $error);
        }
        if ($response === false) {
            throw new RuntimeException('Supabase request failed with empty response.');
        }

        $decoded = json_decode($response, true);
        if ($httpCode >= 400) {
            $msg = $response;
            if (is_array($decoded)) {
                $parts = [];
                if (!empty($decoded['message'])) {
                    $parts[] = (string) $decoded['message'];
                }
                if (!empty($decoded['details'])) {
                    $parts[] = 'details: ' . (string) $decoded['details'];
                }
                if (!empty($decoded['hint'])) {
                    $parts[] = 'hint: ' . (string) $decoded['hint'];
                }
                if (!empty($decoded['code'])) {
                    $parts[] = 'code: ' . (string) $decoded['code'];
                }
                $msg = !empty($parts) ? implode(' | ', $parts) : json_encode($decoded);
            }
            throw new RuntimeException("Supabase API error ({$httpCode}): {$msg}");
        }

        if ($decoded === null && trim($response) !== '' && strtolower(trim($response)) !== 'null') {
            throw new RuntimeException('Invalid JSON response from Supabase.');
        }

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Normalize known payload fields that are frequently narrower on legacy cloud schemas.
     */
    private function normalizePayloadForKnownLimits(array $payload): array
    {
        foreach ($payload as $key => $value) {
            if (is_array($value)) {
                $payload[$key] = $this->normalizePayloadForKnownLimits($value);
                continue;
            }

            if (is_string($key) && strtolower($key) === 'source' && is_string($value)) {
                $payload[$key] = substr($value, 0, 10);
            }
        }

        return $payload;
    }
}
