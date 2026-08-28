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

namespace local_criteriaoutcomes\provider;

use local_criteriaoutcomes\external\boe_client;

/**
 * AEBOE provider orchestration, separate from HTTP and curriculum parsing.
 *
 * @package local_criteriaoutcomes
 * @copyright 2026 Juan Bautista Talens Felis
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class boe_provider implements curriculum_provider_interface {
    /**
     * @var boe_client
     */
    private boe_client $client;
    /**
     * @var boe_parser
     */
    private boe_parser $parser;

    /**
     * Create provider.
     */
    public function __construct(?boe_client $client = null, ?boe_parser $parser = null) {
        $this->client = $client ?? new boe_client();
        $this->parser = $parser ?? new boe_parser();
    }

    /**
     * Parse a deterministic FP text fixture through the normalized DTO contract.
     */
    public function parse(string $content): array {
        $curricula = $this->parser->parse_fp($content, [
            'sourceid' => 'BOE-A-0000-0',
            'sourcename' => 'BOE fixture',
            'provenance' => 'Texto consolidado de carácter informativo.',
        ]);
        return reset($curricula);
    }

    /**
     * Search the official collection.
     */
    public function search(string $query, int $offset = 0, int $limit = 20, bool $force = false): array {
        return $this->client->search($query, $offset, $limit, $force);
    }

    /**
     * Load and parse selectable curricula from one official norm.
     */
    public function curricula(string $identifier, string $family, bool $force = false): array {
        $metadata = $this->client->metadata($identifier, $force);
        $text = $this->client->text($identifier, $force);
        $source = $this->source_metadata($identifier, $metadata);
        return match ($family) {
            'fp' => $this->parser->parse_fp($text, $source),
            'eso', 'bach' => $this->parser->parse_eso_bach($text, $source, $family),
            default => throw new \invalid_parameter_exception('Unsupported BOE curriculum family.'),
        };
    }

    /**
     * Detect an education family only when official metadata is unambiguous.
     */
    public static function detect_family(array $metadata): ?string {
        $title = $metadata['titulo'] ?? $metadata['title'] ?? '';
        if (is_array($title)) {
            $title = $title['texto'] ?? $title['value'] ?? '';
        }
        $title = trim((string)$title);
        if (preg_match('/Educaci[oó]n\s+Secundaria\s+Obligatoria/iu', $title)) {
            return 'eso';
        }
        if (preg_match('/Bachillerato/iu', $title)) {
            return 'bach';
        }
        if (preg_match('/Formaci[oó]n\s+Profesional|t[ií]tulo\s+de\s+T[eé]cnico|ciclo\s+formativo/iu', $title)) {
            return 'fp';
        }
        return null;
    }

    /**
     * Normalize the varying JSON metadata wrapper returned by AEBOE.
     */
    private function source_metadata(string $identifier, array $response): array {
        $metadata = $response['metadatos'] ?? $response;
        return [
            'sourceid' => strtoupper($identifier),
            'sourcename' => $this->value($metadata['titulo'] ?? null) ?: strtoupper($identifier),
            'sourceref' => boe_client::ORIGIN . boe_client::BASEPATH . '/id/' . rawurlencode(strtoupper($identifier)),
            'sourceversion' => $this->value($metadata['numero_oficial'] ?? null),
            'sourcelastupdate' => $this->value($metadata['fecha_actualizacion'] ?? null),
            'retrievedat' => time(),
            'provenance' => 'Texto consolidado de carácter informativo.',
            'language' => 'es',
        ];
    }

    /**
     * Extract text from AEBOE scalar or {codigo,texto} values.
     */
    private function value(mixed $value): ?string {
        if (is_array($value)) {
            $value = $value['texto'] ?? $value['value'] ?? null;
        }
        return $value === null ? null : trim((string)$value);
    }
}
