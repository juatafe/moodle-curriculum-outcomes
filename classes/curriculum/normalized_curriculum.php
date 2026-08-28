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

namespace local_criteriaoutcomes\curriculum;

/**
 * Canonical curriculum DTO validation, identity and checksums.
 *
 * @package local_criteriaoutcomes
 * @copyright 2026 Juan Bautista Talens Felis
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class normalized_curriculum {
    /**
     * Current DTO format version.
     */
    public const VERSION = 1;

    /**
     * Complete provider output with stable defaults and entity keys.
     */
    public static function normalize(array $curriculum): array {
        if (empty($curriculum['metadata']) || empty($curriculum['parents'])) {
            throw new \invalid_parameter_exception('A normalized curriculum requires metadata and parents.');
        }
        $metadata = $curriculum['metadata'];
        $provider = self::clean_key($metadata['provider'] ?? $metadata['source'] ?? 'json', 30);
        $sourceid = self::nullable($metadata['sourceid'] ?? $metadata['source_id'] ?? null, 255);
        $name = self::required($metadata['sourcename'] ?? $metadata['name'] ?? null, 255);
        $type = self::clean_key($metadata['curriculumtype'] ?? $metadata['type'] ?? 'other', 20);
        $curriculumkey = self::required(
            $metadata['curriculumkey'] ?? $metadata['modulecode'] ?? $metadata['subject'] ?? $name,
            255
        );
        $retrievedat = isset($metadata['retrievedat']) ? (int)$metadata['retrievedat'] : time();
        $normal = ['metadata' => [
            'dtoversion' => self::VERSION,
            'provider' => $provider,
            'sourceid' => $sourceid,
            'sourcename' => $name,
            'sourceref' => self::nullable($metadata['sourceref'] ?? $metadata['sourceurl'] ?? $metadata['source_url'] ?? null),
            'sourceversion' => self::nullable($metadata['sourceversion'] ?? $metadata['version'] ?? null, 100),
            'sourcelastupdate' => self::nullable($metadata['sourcelastupdate'] ?? null, 40),
            'retrievedat' => $retrievedat,
            'curriculumtype' => $type,
            'educationlevel' => self::nullable($metadata['educationlevel'] ?? null, 50),
            'qualification' => self::nullable($metadata['qualification'] ?? null),
            'subjectmodule' => self::nullable($metadata['subjectmodule'] ?? $metadata['subject'] ?? null),
            'modulecode' => self::nullable($metadata['modulecode'] ?? null, 100),
            'language' => self::nullable($metadata['language'] ?? null, 20),
            'provenance' => self::nullable($metadata['provenance'] ?? null),
            'parserversion' => self::nullable($metadata['parserversion'] ?? null, 50),
            'curriculumkey' => $curriculumkey,
        ], 'parents' => []];

        $criterionkeys = [];
        foreach (array_values($curriculum['parents']) as $pi => $parent) {
            $parentcode = self::required($parent['code'] ?? null, 100);
            $parentkey = self::entity_key($provider, $sourceid, $curriculumkey, 'parent', $parentcode);
            $normalparent = [
                'code' => $parentcode,
                'name' => self::required($parent['name'] ?? null),
                'type' => self::clean_key($parent['type'] ?? ($type === 'fp' ? 'ra' : 'ce'), 20),
                'weight' => self::weight($parent['weight'] ?? null),
                'sortorder' => isset($parent['sortorder']) ? (int)$parent['sortorder'] : $pi,
                'sourcekey' => $parentkey,
                'criteria' => [],
            ];
            foreach (array_values($parent['criteria'] ?? []) as $ci => $criterion) {
                $code = self::required($criterion['code'] ?? null, 100);
                $sourcekey = self::entity_key($provider, $sourceid, $curriculumkey, 'criterion', $code);
                if (isset($criterionkeys[$sourcekey])) {
                    throw new \invalid_parameter_exception('Duplicate normalized criterion identity: ' . $code);
                }
                $criterionkeys[$sourcekey] = true;
                $normalparent['criteria'][] = [
                    'code' => $code,
                    'name' => self::required($criterion['name'] ?? null),
                    'parentcode' => $parentcode,
                    'weight' => self::weight($criterion['weight'] ?? null),
                    'sortorder' => isset($criterion['sortorder']) ? (int)$criterion['sortorder'] : $ci,
                    'sourcekey' => $sourcekey,
                ];
            }
            if (!$normalparent['criteria']) {
                throw new \invalid_parameter_exception('A normalized parent must contain criteria.');
            }
            $normal['parents'][] = $normalparent;
        }
        $normal['metadata']['checksum'] = self::checksum($normal);
        return $normal;
    }

    /**
     * Stable SHA-256 over semantically relevant normalized content.
     */
    public static function checksum(array $curriculum): string {
        $copy = $curriculum;
        unset($copy['metadata']['retrievedat'], $copy['metadata']['checksum']);
        return hash('sha256', json_encode(self::canonicalize($copy), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    /**
     * Build a provider-scoped entity identity.
     */
    public static function entity_key(
        string $provider,
        ?string $sourceid,
        string $curriculumkey,
        string $entitytype,
        string $code
    ): string {
        $parts = [$provider, $sourceid ?? '', $curriculumkey, $entitytype, $code];
        $parts = array_map(fn(string $value): string => \core_text::strtolower(trim($value)), $parts);
        return hash('sha256', implode('|', $parts));
    }

    /**
     * Recursively sort associative keys without changing list ordering.
     */
    private static function canonicalize(array $value): array {
        if (!array_is_list($value)) {
            ksort($value, SORT_STRING);
        }
        foreach ($value as &$item) {
            if (is_array($item)) {
                $item = self::canonicalize($item);
            }
        }
        return $value;
    }

    /**
     * Clean a required display value.
     */
    private static function required(mixed $value, int $maxlength = 10000): string {
        $result = trim(clean_param((string)$value, PARAM_TEXT));
        if ($result === '') {
            throw new \invalid_parameter_exception('A normalized curriculum value is empty.');
        }
        return \core_text::substr($result, 0, $maxlength);
    }

    /**
     * Clean an optional display value.
     */
    private static function nullable(mixed $value, int $maxlength = 10000): ?string {
        if ($value === null || trim((string)$value) === '') {
            return null;
        }
        return \core_text::substr(trim(clean_param((string)$value, PARAM_TEXT)), 0, $maxlength);
    }

    /**
     * Clean a machine key.
     */
    private static function clean_key(mixed $value, int $maxlength): string {
        $result = strtolower(trim(clean_param((string)$value, PARAM_ALPHANUMEXT)));
        return $result === '' ? 'other' : substr($result, 0, $maxlength);
    }

    /**
     * Validate an optional non-negative weight.
     */
    private static function weight(mixed $value): ?float {
        if ($value === null || $value === '') {
            return null;
        }
        if (!is_numeric($value) || (float)$value < 0) {
            throw new \invalid_parameter_exception('Invalid normalized curriculum weight.');
        }
        return (float)$value;
    }
}
