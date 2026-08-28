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

/**
 * JSON curriculum provider.
 *
 *  local_criteriaoutcomes
 * @copyright 2026 Juan Bautista Talens Felis
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_criteriaoutcomes\provider;

use local_criteriaoutcomes\curriculum\normalized_curriculum;

/**
 * Validates and normalizes current and legacy curriculum JSON.
 */
class json_provider implements curriculum_provider_interface {
    /**
     * Maximum accepted input size.
     */
    public const MAX_BYTES = 2097152;

    /**
     * Parse and normalize JSON content.
     */
    public function parse(string $content): array {
        if ($content === '' || strlen($content) > self::MAX_BYTES) {
            throw new \InvalidArgumentException(get_string('errorjsonsize', 'local_criteriaoutcomes'));
        }
        try {
            $raw = json_decode($content, true, 64, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new \InvalidArgumentException(get_string('errorinvalidjson', 'local_criteriaoutcomes', $e->getMessage()));
        }
        if (!is_array($raw) || empty($raw['resultados']) || !is_array($raw['resultados'])) {
            throw new \InvalidArgumentException(get_string('errornoresults', 'local_criteriaoutcomes'));
        }
        $metadata = is_array($raw['metadata'] ?? null) ? $raw['metadata'] : [];
        $type = strtolower(trim((string)($metadata['type'] ?? 'fp')));
        if (!in_array($type, ['fp', 'eso', 'bach', 'other'], true)) {
            $type = 'other';
        }
        $result = ['metadata' => [
            'name' => $this->text($metadata['name'] ?? get_string('unnamedcurriculum', 'local_criteriaoutcomes'), 255),
            'type' => $type, 'source' => $this->nullable($metadata['source'] ?? null, 255),
            'source_id' => $this->nullable($metadata['source_id'] ?? null, 255),
            'source_url' => $this->nullable($metadata['source_url'] ?? null, 2048),
            'version' => $this->nullable($metadata['version'] ?? null, 100),
            'language' => $this->nullable($metadata['language'] ?? null, 20),
            'provider' => $this->nullable($metadata['provider'] ?? $metadata['source'] ?? 'json', 30),
            'curriculumkey' => $this->nullable($metadata['curriculumkey'] ?? null, 255),
            'educationlevel' => $this->nullable($metadata['educationlevel'] ?? null, 50),
            'qualification' => $this->nullable($metadata['qualification'] ?? null, 10000),
            'subjectmodule' => $this->nullable($metadata['subjectmodule'] ?? null, 10000),
            'modulecode' => $this->nullable($metadata['modulecode'] ?? null, 100),
            'provenance' => $this->nullable($metadata['provenance'] ?? null, 10000),
            'parserversion' => $this->nullable($metadata['parserversion'] ?? 'json-v1', 50),
            'sourcelastupdate' => $this->nullable($metadata['sourcelastupdate'] ?? null, 40),
            'retrievedat' => isset($metadata['retrievedat']) ? (int)$metadata['retrievedat'] : time(),
        ], 'parents' => []];
        $codes = [];
        foreach (array_values($raw['resultados']) as $pi => $parent) {
            if (!is_array($parent) || empty($parent['nombre']) || empty($parent['criterios']) || !is_array($parent['criterios'])) {
                throw new \InvalidArgumentException(get_string('errorparentstructure', 'local_criteriaoutcomes', $pi + 1));
            }
            [$inferred, $parentname] = $this->split_code((string)$parent['nombre']);
            $parentcode = $this->text($parent['codigo'] ?? $inferred ?: 'P' . ($pi + 1), 100);
            $normalparent = ['code' => $parentcode, 'name' => $parentname,
                'type' => $type === 'fp' ? 'ra' : ($type === 'other' ? 'other' : 'ce'),
                'weight' => $this->weight($parent['peso'] ?? null), 'sortorder' => $pi, 'criteria' => []];
            foreach (array_values($parent['criterios']) as $ci => $criterion) {
                if (!is_array($criterion) || empty($criterion['nombre'])) {
                    throw new \InvalidArgumentException(
                        get_string('errorcriterionstructure', 'local_criteriaoutcomes', $parentcode)
                    );
                }
                [$cinferred, $criterionname] = $this->split_code((string)$criterion['nombre']);
                $code = $this->text($criterion['codigo'] ?? $cinferred ?: $parentcode . '.' . ($ci + 1), 100);
                $key = \core_text::strtolower($code);
                if (isset($codes[$key])) {
                    throw new \InvalidArgumentException(get_string('errorduplicatecode', 'local_criteriaoutcomes', $code));
                }
                $codes[$key] = true;
                $normalparent['criteria'][] = ['code' => $code, 'name' => $criterionname,
                    'weight' => $this->weight($criterion['peso'] ?? null), 'sortorder' => $ci];
            }
            $result['parents'][] = $normalparent;
        }
        return normalized_curriculum::normalize($result);
    }

    /**
     * Split the legacy "code: name" representation.
     */
    private function split_code(string $value): array {
        $value = trim(clean_param($value, PARAM_TEXT));
        if (preg_match('/^([\pL\pN]+(?:[.\-_][\pL\pN]+)*)\s*:\s*(.+)$/u', $value, $m)) {
            return [$m[1], trim($m[2])];
        }
        return ['', $value];
    }
    /**
     * Clean a required text value.
     */
    private function text(mixed $value, int $maxlength): string {
        $value = trim(clean_param((string)$value, PARAM_TEXT));
        if ($value === '') {
            throw new \InvalidArgumentException(get_string('erroremptytext', 'local_criteriaoutcomes'));
        }
        return \core_text::substr($value, 0, $maxlength);
    }
    /**
     * Clean an optional text value.
     */
    private function nullable(mixed $value, int $maxlength): ?string {
        if ($value === null || trim((string)$value) === '') {
            return null;
        }
        return \core_text::substr(trim(clean_param((string)$value, PARAM_TEXT)), 0, $maxlength);
    }
    /**
     * Validate an optional weight.
     */
    private function weight(mixed $value): ?float {
        if ($value === null || $value === '') {
            return null;
        }
        if (!is_numeric($value) || (float)$value < 0) {
            throw new \InvalidArgumentException(get_string('errorweight', 'local_criteriaoutcomes'));
        }
        return (float)$value;
    }
}
