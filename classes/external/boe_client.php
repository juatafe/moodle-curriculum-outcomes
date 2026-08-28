<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

namespace local_criteriaoutcomes\external;

use GuzzleHttp\ClientInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Strict client for AEBOE's official consolidated-legislation API.
 *
 * The caller supplies search text or an official identifier, never a URL.
 * Redirects are disabled so a response cannot escape the fixed allowlisted host.
 *
 * @package local_criteriaoutcomes
 * @copyright 2026 Juan Bautista Talens Felis
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class boe_client {
    /**
     * Official API origin.
     */
    public const ORIGIN = 'https://www.boe.es';
    /**
     * Official API base path.
     */
    public const BASEPATH = '/datosabiertos/api/legislacion-consolidada';
    /**
     * Result limit enforced by the plugin.
     */
    public const MAX_RESULTS = 25;

    /**
     * @var ClientInterface Moodle HTTP client or a test double.
     */
    private ClientInterface $http;

    /**
     * @var \cache Public response cache.
     */
    private \cache $cache;

    /**
     * Create the client.
     */
    public function __construct(?ClientInterface $http = null, ?\cache $cache = null) {
        $this->http = $http ?? new \core\http_client();
        $this->cache = $cache ?? \cache::make('local_criteriaoutcomes', 'boe_public');
    }

    /**
     * Search consolidated legislation by safe server-built query.
     */
    public function search(string $query, int $offset = 0, int $limit = 20, bool $force = false): array {
        $query = trim(clean_param($query, PARAM_TEXT));
        if ($query === '' || \core_text::strlen($query) > 250) {
            throw new \invalid_parameter_exception('Invalid BOE search text.');
        }
        $offset = max(0, $offset);
        $limit = min(self::MAX_RESULTS, max(1, $limit));
        if (preg_match('/^BOE-A-\d{4}-\d{1,8}$/', strtoupper($query))) {
            $metadata = $this->metadata(strtoupper($query), $force);
            return [$metadata['metadatos'] ?? $metadata];
        }
        preg_match_all('/[\pL\pN]{2,}/u', $query, $tokens);
        $tokens = array_slice(array_unique($tokens[0]), 0, 10);
        if (!$tokens) {
            throw new \invalid_parameter_exception('Invalid BOE search text.');
        }
        $expression = implode(' AND ', array_map(
            static fn(string $token): string => '(titulo:' . $token . ' OR texto:' . $token . ')',
            $tokens
        ));
        $search = json_encode([
            'query' => ['query_string' => ['query' => $expression], 'range' => (object)[]],
            'sort' => [['fecha_publicacion' => 'desc']],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $path = self::BASEPATH . '?' . http_build_query([
            'query' => $search,
            'offset' => $offset,
            'limit' => $limit,
        ], '', '&', PHP_QUERY_RFC3986);
        $data = $this->json_request($path, $force);
        return array_values($data['data'] ?? []);
    }

    /**
     * Get official metadata for one BOE identifier.
     */
    public function metadata(string $identifier, bool $force = false): array {
        $data = $this->json_request($this->document_path($identifier) . '/metadatos', $force);
        $metadata = $data['data'] ?? [];
        if (array_is_list($metadata) && isset($metadata[0]) && is_array($metadata[0])) {
            return $metadata[0];
        }
        return $metadata;
    }

    /**
     * Get the latest consolidated block index.
     */
    public function index(string $identifier, bool $force = false): array {
        $data = $this->json_request($this->document_path($identifier) . '/texto/indice', $force);
        return array_values($data['data'] ?? []);
    }

    /**
     * Get one block as validated XML.
     */
    public function block(string $identifier, string $blockid, bool $force = false): string {
        if (!preg_match('/^[a-zA-Z0-9._-]{1,80}$/', $blockid)) {
            throw new \invalid_parameter_exception('Invalid BOE block identifier.');
        }
        return $this->xml_request($this->document_path($identifier) . '/texto/bloque/' . rawurlencode($blockid), $force);
    }

    /**
     * Get the complete consolidated text as validated XML.
     */
    public function text(string $identifier, bool $force = false): string {
        return $this->xml_request($this->document_path($identifier) . '/texto', $force);
    }

    /**
     * Build an allowlisted document path.
     */
    private function document_path(string $identifier): string {
        $identifier = strtoupper(trim($identifier));
        if (!preg_match('/^BOE-A-\d{4}-\d{1,8}$/', $identifier)) {
            throw new \invalid_parameter_exception('Invalid BOE identifier.');
        }
        return self::BASEPATH . '/id/' . rawurlencode($identifier);
    }

    /**
     * Request and decode JSON.
     */
    private function json_request(string $path, bool $force): array {
        $body = $this->request($path, 'application/json', $force);
        try {
            $data = json_decode($body, true, 64, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new \RuntimeException('AEBOE returned invalid JSON.', 0, $e);
        }
        if (!is_array($data) || !isset($data['status'])) {
            throw new \RuntimeException('AEBOE returned an unexpected JSON schema.');
        }
        return $data;
    }

    /**
     * Request and validate XML without expanding external entities.
     */
    private function xml_request(string $path, bool $force): string {
        $body = $this->request($path, 'application/xml', $force);
        $previous = libxml_use_internal_errors(true);
        $xml = simplexml_load_string($body, \SimpleXMLElement::class, LIBXML_NONET | LIBXML_NOCDATA);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        if (!$xml || $xml->getName() !== 'response' || !isset($xml->status)) {
            throw new \RuntimeException('AEBOE returned invalid XML.');
        }
        return $body;
    }

    /**
     * Execute one fixed-origin GET with Moodle proxy/TLS/security middleware.
     */
    private function request(string $path, string $accept, bool $force): string {
        if (!str_starts_with($path, self::BASEPATH)) {
            throw new \coding_exception('Attempt to access a non-allowlisted BOE path.');
        }
        $cachekey = hash('sha256', $accept . '|' . $path);
        if (!$force && ($cached = $this->cache->get($cachekey)) !== false) {
            return $cached;
        }
        try {
            $response = $this->http->request('GET', self::ORIGIN . $path, [
                'headers' => ['Accept' => $accept],
                'timeout' => 15,
                'connect_timeout' => 5,
                'allow_redirects' => false,
                'http_errors' => false,
            ]);
        } catch (\Throwable $e) {
            throw new \RuntimeException('AEBOE is temporarily unavailable.', 0, $e);
        }
        $this->assert_response($response);
        $body = (string)$response->getBody();
        if (trim($body) === '') {
            throw new \RuntimeException('AEBOE returned an empty response.');
        }
        $this->cache->set($cachekey, $body);
        return $body;
    }

    /**
     * Convert HTTP failure into a stable provider exception.
     */
    private function assert_response(ResponseInterface $response): void {
        $status = $response->getStatusCode();
        if ($status === 200) {
            return;
        }
        if ($status === 400 || $status === 404) {
            throw new \invalid_parameter_exception('AEBOE document or query was not found.');
        }
        throw new \RuntimeException('AEBOE service error (HTTP ' . $status . ').');
    }
}
