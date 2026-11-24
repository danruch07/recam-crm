<?php
declare(strict_types=1);

namespace Recam\Service;

use Recam\Core\Config;
use DateTimeImmutable;

/**
 *API externa: tipo de cambio USD -> MXN.
 *
 * ¿Por qué esta API? En un taller de corte y grabado láser muchos insumos
 * se cotizan en dólares y el precio varia a lo largo del dia
 *
 * Fuentes: Banxico (FIX) y ExchangeRate-API
 *
 *   - Caché en archivo (1 h): no se llama a la API en cada visita
 *   - Timeouts cortos
 *   - Si todas las fuentes fallan se usa el último valor guardado (marcado como
 *     "desactualizado") y, si no existe, la página sigue funcionando sin él
 *   - Se valida el JSON recibido antes de usarlo
 */
final class ExchangeRateService
{
    private const BANXICO_SOURCE = 'Banxico (FIX)';
    private const BACKUP_SOURCE = 'ExchangeRate-API';

    private string $cacheFile;

    public function __construct(?string $cacheFile = null)
    {
        $this->cacheFile = $cacheFile ?? BASE_PATH . '/storage/cache/usd_mxn.json';
    }

    /**
     * Devuelve el tipo de cambio o null si no hay ningún dato disponible.
     */
    public function usdToMxn(): ?array
    {
        $cached = $this->readCache();
        $ttl    = (int) Config::get('api.cache_ttl', 3600);

        // 1) Caché vigente: no se consulta la API
        if ($cached !== null && (time() - $cached['fetched_at']) < $ttl) {
            return $cached + ['stale' => false];
        }

        // 2) Caché vencida o inexistente: consultar la API (principal, luego respaldo)
        $fresh = $this->fetchBanxico() ?? $this->fetchOpenErApi();
        if ($fresh !== null) {
            $this->writeCache($fresh);
            return $fresh + ['stale' => false];
        }

        // 3) Sin red / API caída: usar el último dato conocido, avisando que es viejo
        return $cached !== null ? $cached + ['stale' => true] : null;
    }

    public static function convert(float $usd, float $rate): float
    {
        return round($usd * $rate, 2);
    }

    // ------------------------------------------------------------------
    // Fuentes
    // ------------------------------------------------------------------

    private function fetchBanxico(): ?array
    {
        $token = trim((string) Config::get('api.banxico_token', ''));
        if (!preg_match('/\A[A-Za-z0-9]{64}\z/', $token)) {
            return null;
        }

        $data = $this->httpGetJson(
            'https://www.banxico.org.mx/SieAPIRest/service/v1/series/SF43718/datos/oportuno',
            ['Bmx-Token: ' . $token]
        );
        $observation = $data['bmx']['series'][0]['datos'][0] ?? null;
        $rate = $observation['dato'] ?? null;
        $date = $observation['fecha'] ?? null;
        if (!$this->isValidRate($rate) || !is_string($date) || !preg_match('/\A\d{2}\/\d{2}\/\d{4}\z/', $date)) {
            return null;
        }

        $parsed = DateTimeImmutable::createFromFormat('!d/m/Y', $date);
        if ($parsed === false || $parsed->format('d/m/Y') !== $date) {
            return null;
        }

        return [
            'rate'       => (float) $rate,
            'date'       => $parsed->format('Y-m-d'),
            'source'     => self::BANXICO_SOURCE,
            'fetched_at' => time(),
        ];
    }

    private function fetchOpenErApi(): ?array
    {
        $data = $this->httpGetJson((string) Config::get('api.fx_secondary'));
        $rate = $data['rates']['MXN'] ?? null;

        if (($data['result'] ?? '') !== 'success' || !$this->isValidRate($rate)) {
            return null;
        }
        $ts = isset($data['time_last_update_unix']) ? (int) $data['time_last_update_unix'] : time();

        return [
            'rate'       => (float) $rate,
            'date'       => date('Y-m-d', $ts),
            'source'     => self::BACKUP_SOURCE,
            'fetched_at' => time(),
        ];
    }

    /** Una tasa válida es numérica y está en un rango razonable */
    private function isValidRate(mixed $rate): bool
    {
        return is_numeric($rate) && (float) $rate > 1 && (float) $rate < 1000;
    }

    // ------------------------------------------------------------------
    // HTTP y caché
    // ------------------------------------------------------------------

    /** GET con cURL. Devuelve el JSON decodificado o null ante cualquier fallo */
    private function httpGetJson(string $url, array $headers = []): ?array
    {
        if ($url === '' || !function_exists('curl_init')) {
            return null;
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => (int) Config::get('api.timeout', 5),
            CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_FOLLOWLOCATION => $headers === [],
            CURLOPT_MAXREDIRS      => 2,
            CURLOPT_PROTOCOLS      => CURLPROTO_HTTPS | CURLPROTO_HTTP,
            CURLOPT_SSL_VERIFYPEER => true,  
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_HTTPHEADER     => ['Accept: application/json', ...$headers],
            CURLOPT_USERAGENT      => 'recam-crm/1.0',
        ]);
        $body   = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);

        if ($body === false || $status !== 200) {
            return null;
        }
        $json = json_decode((string) $body, true);
        return is_array($json) ? $json : null;
    }

    private function readCache(): ?array
    {
        if (!is_file($this->cacheFile)) {
            return null;
        }
        $data = json_decode((string) @file_get_contents($this->cacheFile), true);
        if (!is_array($data) || !isset($data['rate'], $data['date'], $data['source'], $data['fetched_at'])) {
            return null;
        }
        if (!in_array($data['source'], [self::BANXICO_SOURCE, self::BACKUP_SOURCE], true)) {
            return null;
        }
        return [
            'rate'       => (float) $data['rate'],
            'date'       => (string) $data['date'],
            'source'     => (string) $data['source'],
            'fetched_at' => (int) $data['fetched_at'],
        ];
    }

    private function writeCache(array $data): void
    {
        $dir = dirname($this->cacheFile);
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $tmp = $this->cacheFile . '.' . bin2hex(random_bytes(4)) . '.tmp';
        if (@file_put_contents($tmp, json_encode($data)) !== false) {
            @rename($tmp, $this->cacheFile);
        }
    }
}
