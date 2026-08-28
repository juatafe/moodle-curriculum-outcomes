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

use local_criteriaoutcomes\curriculum\normalized_curriculum;

/**
 * Deterministic parsers for explicitly structured AEBOE curriculum text.
 *
 * @package local_criteriaoutcomes
 * @copyright 2026 Juan Bautista Talens Felis
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class boe_parser {
    /**
     * Parser revisions stored in provenance.
     */
    public const FP_VERSION = 'boe-fp-v1';
    /**
     * Parser revision for ESO and Bachillerato structures.
     */
    public const ESO_VERSION = 'boe-eso-bach-v1';

    /**
     * Headings which structurally end the FP learning-result assessment section.
     */
    private const FP_SECTION_BOUNDARY =
        '(?:Duraci[oó]n\s*:\s*[^\n]+|Contenidos\s+b[aá]sicos|Orientaciones\s+pedag[oó]gicas)' .
        '\s*[.:]?';

    /**
     * Parse every clearly delimited FP professional module.
     */
    public function parse_fp(string $content, array $source): array {
        $sections = $this->fp_title_sections($content);
        if (!$sections) {
            $sections = [['qualification' => $source['qualification'] ?? null, 'text' => $this->plain_text($content)]];
        }
        $pattern = '/(?:M[oó]dulo\s+profesional\s*:\s*)(.+?)(?=(?:\n\s*M[oó]dulo\s+profesional\s*:)|\z)/isu';
        $result = [];
        foreach ($sections as $section) {
            if (!preg_match_all($pattern, $section['text'], $matches)) {
                continue;
            }
            foreach ($matches[1] as $moduletext) {
                $lines = preg_split('/\R+/u', trim($moduletext));
                $modulename = trim((string)array_shift($lines));
                $body = implode("\n", $lines);
                $modulecode = null;
                if (preg_match('/(?:^|\n)\s*C[oó]digo\s*:\s*([A-Z0-9.-]{2,20})\s*(?:\n|$)/iu', $body, $code)) {
                    $modulecode = $code[1];
                }
                $sectionpos = preg_match(
                    '/Resultados\s+de\s+aprendizaje\s+y\s+criterios\s+de\s+evaluaci[oó]n\s*[:.]?/iu',
                    $body,
                    $sectionmatch,
                    PREG_OFFSET_CAPTURE
                );
                if (!$sectionpos) {
                    continue;
                }
                $learning = substr($body, $sectionmatch[0][1] + strlen($sectionmatch[0][0]));
                $parents = $this->fp_learning_results($learning);
                if (!$parents) {
                    continue;
                }
                $metadata = $this->metadata($source, [
                    'curriculumtype' => 'fp',
                    'educationlevel' => 'formacion_profesional',
                    'qualification' => $section['qualification'],
                    'subjectmodule' => $modulename,
                    'modulecode' => $modulecode,
                    'curriculumkey' => $modulecode ?: $modulename,
                    'parserversion' => self::FP_VERSION,
                ]);
                try {
                    $result[] = normalized_curriculum::normalize(['metadata' => $metadata, 'parents' => $parents]);
                } catch (\invalid_parameter_exception $e) {
                    continue;
                }
            }
        }
        if (!$result) {
            throw new \UnexpectedValueException('The FP structure could not be recognized safely.');
        }
        return $this->disambiguate_fp_keys($result);
    }

    /**
     * Preserve AEBOE Annex/title boundaries while extracting module text.
     *
     * A title is carried only within its own Annex block. This avoids attaching
     * modules from a later qualification to an earlier heading.
     */
    private function fp_title_sections(string $content): array {
        $previous = libxml_use_internal_errors(true);
        $xml = simplexml_load_string($content, \SimpleXMLElement::class, LIBXML_NONET | LIBXML_NOCDATA);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        if (!$xml) {
            return [];
        }
        $sections = [];
        $qualification = null;
        foreach ($xml->xpath('//bloque') ?: [] as $block) {
            $versions = $block->xpath('./version') ?: [];
            $current = $versions ? $versions[array_key_last($versions)] : $block;
            $titles = $current->xpath('.//p[contains(concat(" ", normalize-space(@class), " "), " anexo_tit ")]') ?: [];
            foreach ($titles as $title) {
                $candidate = trim(preg_replace('/\s+/u', ' ', (string)$title));
                if ($candidate !== '') {
                    $qualification = $candidate;
                }
            }
            $text = $this->plain_text($current->asXML() ?: '');
            if (preg_match('/M[oó]dulo\s+profesional\s*:/iu', $text)) {
                $sections[] = ['qualification' => $qualification, 'text' => $text];
            }
        }
        return $sections;
    }

    /**
     * Retain historical module-code identity unless the source proves it ambiguous.
     */
    private function disambiguate_fp_keys(array $curricula): array {
        $qualifications = [];
        foreach ($curricula as $curriculum) {
            $code = (string)($curriculum['metadata']['modulecode'] ?? '');
            $qualification = (string)($curriculum['metadata']['qualification'] ?? '');
            if ($code !== '') {
                $qualifications[$code][$qualification] = true;
            }
        }
        foreach ($curricula as &$curriculum) {
            $code = (string)($curriculum['metadata']['modulecode'] ?? '');
            $qualification = (string)($curriculum['metadata']['qualification'] ?? '');
            if ($code !== '' && count($qualifications[$code] ?? []) > 1) {
                $curriculum['metadata']['curriculumkey'] = $code . ':' . sha1($qualification);
            }
        }
        unset($curriculum);
        return $curricula;
    }

    /**
     * Parse clearly grouped ESO/Bachillerato subject competence criteria.
     */
    public function parse_eso_bach(string $content, array $source, string $level): array {
        if (!in_array($level, ['eso', 'bach'], true)) {
            throw new \invalid_parameter_exception('Education level must be eso or bach.');
        }
        $structured = $this->structured_subjects($content, $source, $level);
        if ($structured) {
            return $structured;
        }
        $text = $this->plain_text($content);
        $pattern = '/(?:Materia\s*:\s*)(.+?)(?=(?:\n\s*Materia\s*:)|\z)/isu';
        if (!preg_match_all($pattern, $text, $matches)) {
            throw new \UnexpectedValueException('No safely delimited subject was found.');
        }
        $result = [];
        foreach ($matches[1] as $subjecttext) {
            $lines = preg_split('/\R+/u', trim($subjecttext));
            $subject = trim((string)array_shift($lines));
            $parents = $this->specific_competences(implode("\n", $lines));
            if (!$parents) {
                continue;
            }
            $metadata = $this->metadata($source, [
                'curriculumtype' => $level,
                'educationlevel' => $level,
                'subjectmodule' => $subject,
                'curriculumkey' => $level . ':' . $subject,
                'parserversion' => self::ESO_VERSION,
                'provenance' => trim(
                    ($source['provenance'] ?? '') .
                    ' Enseñanzas mínimas estatales; el currículo autonómico puede desarrollarlas.'
                ),
            ]);
            try {
                $result[] = normalized_curriculum::normalize(['metadata' => $metadata, 'parents' => $parents]);
            } catch (\invalid_parameter_exception $e) {
                continue;
            }
        }
        if (!$result) {
            throw new \UnexpectedValueException('The subject competence structure could not be recognized safely.');
        }
        return $result;
    }

    /**
     * Parse AEBOE's explicit Annex → subject heading → course band → criteria topology.
     */
    private function structured_subjects(string $content, array $source, string $level): array {
        $previous = libxml_use_internal_errors(true);
        $xml = simplexml_load_string($content, \SimpleXMLElement::class, LIBXML_NONET | LIBXML_NOCDATA);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        if (!$xml) {
            return [];
        }
        $officialbands = $this->official_course_bands($xml);
        $result = [];
        foreach ($xml->xpath('//bloque[starts-with(@titulo, "ANEXO")]') ?: [] as $block) {
            $paragraphs = [];
            foreach ($block->xpath('.//*[self::p or self::td]') ?: [] as $node) {
                $paragraphs[] = [
                    'class' => (string)$node['class'],
                    'text' => trim(preg_replace('/\s+/u', ' ', $this->plain_text($node->asXML() ?: ''))),
                ];
            }
            $subject = null;
            $band = null;
            $competencies = [];
            $incompetencies = false;
            $subjectstart = 0;
            $count = count($paragraphs);
            for ($i = 0; $i < $count; $i++) {
                $paragraph = $paragraphs[$i];
                if ($paragraph['class'] === 'centro_negrita' && $paragraph['text'] !== '') {
                    $subject = $paragraph['text'];
                    $subjectstart = $i;
                    $band = null;
                    $competencies = [];
                    $incompetencies = false;
                    continue;
                }
                if ($paragraph['class'] === 'centro_cursiva') {
                    $band = $paragraph['text'];
                    continue;
                }
                if (preg_match('/^Competencias\s+espec[ií]ficas\.?$/iu', $paragraph['text'])) {
                    $incompetencies = true;
                    continue;
                }
                if ($incompetencies && preg_match('/^(\d{1,2})\.\s+(.+)$/u', $paragraph['text'], $competency)) {
                    $competencies[(int)$competency[1]] = trim($competency[2]);
                    continue;
                }
                if (!$subject || !preg_match('/^Criterios\s+de\s+evaluaci[oó]n\.?$/iu', $paragraph['text'])) {
                    continue;
                }
                $incompetencies = false;
                $lines = [];
                for ($j = $i + 1; $j < $count; $j++) {
                    $next = $paragraphs[$j];
                    if (
                        $next['class'] === 'centro_negrita' || $next['class'] === 'centro_cursiva' ||
                        preg_match('/^(?:Criterios\s+de\s+evaluaci[oó]n|Saberes\s+b[aá]sicos)\.?$/iu', $next['text'])
                    ) {
                        break;
                    }
                    if (
                        preg_match('/^Competencia\s+espec[ií]fica\s+\d{1,2}\.?$/iu', $next['text']) ||
                            preg_match('/^\d{1,2}\.\d{1,2}\s+.+/u', $next['text'])
                    ) {
                        $lines[] = $next['text'];
                    }
                }
                $parents = $this->specific_competences(implode("\n", $lines), $competencies);
                if (!$parents) {
                    continue;
                }
                $effectiveband = $band ?: $this->infer_course_band($paragraphs, $subjectstart);
                if (!$effectiveband && count($officialbands[$subject] ?? []) === 1) {
                    $effectiveband = array_key_first($officialbands[$subject]);
                }
                $label = $effectiveband ?: 'all_courses';
                $metadata = $this->metadata($source, [
                    'curriculumtype' => $level,
                    'educationlevel' => $level,
                    'subjectmodule' => $subject . ($effectiveband ? ' — ' . $effectiveband : ''),
                    'curriculumkey' => $level . ':' . $subject . ':' . $label,
                    'parserversion' => self::ESO_VERSION,
                    'provenance' => trim(($source['provenance'] ?? '') .
                        ' Enseñanzas mínimas estatales; el currículo autonómico puede desarrollarlas.'),
                ]);
                try {
                    $result[] = normalized_curriculum::normalize(['metadata' => $metadata, 'parents' => $parents]);
                } catch (\invalid_parameter_exception $e) {
                    continue;
                }
            }
        }
        return $result;
    }

    /**
     * Recover a course band only when the official subject section says it unequivocally.
     */
    private function infer_course_band(array $paragraphs, int $subjectstart): ?string {
        $texts = [];
        $count = count($paragraphs);
        for ($i = $subjectstart + 1; $i < $count; $i++) {
            if ($paragraphs[$i]['class'] === 'centro_negrita') {
                break;
            }
            $texts[] = $paragraphs[$i]['text'];
        }
        $text = implode(' ', $texts);
        $firstthree = preg_match('/(?:tres\s+primeros\s+cursos|cursos?\s+de\s+primero\s+a\s+tercero)/iu', $text);
        $fourth = preg_match('/(?:en|para|de)\s+(?:el\s+)?cuarto\s+curso/iu', $text);
        if ($firstthree && !$fourth) {
            return 'Cursos de primero a tercero';
        }
        if ($fourth && !$firstthree) {
            return 'Cuarto curso';
        }
        return null;
    }

    /**
     * Read explicit course-band tables included in the same official source.
     */
    private function official_course_bands(\SimpleXMLElement $xml): array {
        $bands = [];
        $currentband = null;
        foreach ($xml->xpath('//tr') ?: [] as $row) {
            $text = $this->plain_text($row->asXML() ?: '');
            if (preg_match('/Para\s+los\s+tres\s+primeros\s+cursos/iu', $text)) {
                $currentband = 'Cursos de primero a tercero';
                continue;
            }
            if (preg_match('/Para\s+el\s+cuarto\s+curso/iu', $text)) {
                $currentband = 'Cuarto curso';
                continue;
            }
            if (!$currentband) {
                continue;
            }
            $cells = $row->xpath('./td') ?: [];
            if (!$cells) {
                continue;
            }
            $subject = trim(preg_replace('/[.*\s]+$/u', '', $this->plain_text($cells[0]->asXML() ?: '')));
            if ($subject !== '' && !is_numeric($subject)) {
                $bands[$subject][$currentband] = true;
            }
        }
        return $bands;
    }

    /**
     * Extract numbered FP learning results and lettered criteria.
     */
    private function fp_learning_results(string $text): array {
        $text = preg_split('/(?:^|\n)\s*' . self::FP_SECTION_BOUNDARY . '\s*(?:\n|$)/iu', $text, 2)[0];
        $pattern = '/(?:^|\n)\s*(\d{1,2})[.)]\s+(.+?)(?=(?:\n\s*\d{1,2}[.)]\s+)|\z)/su';
        preg_match_all($pattern, $text, $matches, PREG_SET_ORDER);
        $parents = [];
        foreach ($matches as $index => $match) {
            if (
                !preg_match_all(
                    '/(?:^|\n)\s*([a-z])\)\s+(.+?)(?=(?:\n\s*[a-z]\)\s+)|\z)/isu',
                    $match[2],
                    $criteria,
                    PREG_SET_ORDER
                )
            ) {
                return [];
            }
            $parentname = trim(preg_split(
                '/\n\s*(?:Criterios\s+de\s+evaluaci[oó]n\s*[:.]?\s*)?(?=[a-z]\)\s+)/iu',
                $match[2],
                2
            )[0]);
            $parentname = trim(preg_replace(
                '/\s+Criterios\s+de\s+evaluaci[oó]n\s*[:.]?\s*$/iu',
                '',
                $parentname
            ));
            if ($parentname === '') {
                return [];
            }
            $parentcode = 'RA' . (int)$match[1];
            $normalcriteria = [];
            foreach ($criteria as $ci => $criterion) {
                $normalcriteria[] = [
                    'code' => $parentcode . '.' . strtolower($criterion[1]),
                    'name' => trim(preg_replace('/\s+/u', ' ', $criterion[2])),
                    'weight' => null,
                    'sortorder' => $ci,
                ];
            }
            $parents[] = [
                'code' => $parentcode, 'name' => trim(preg_replace('/\s+/u', ' ', $parentname)),
                'type' => 'ra', 'weight' => null, 'sortorder' => $index, 'criteria' => $normalcriteria,
            ];
        }
        return $parents;
    }

    /**
     * Extract numbered specific competences and decimal criteria.
     */
    private function specific_competences(string $text, array $competencynames = []): array {
        $pattern = '/(?:^|\n)\s*Competencia\s+espec[ií]fica\s+(\d{1,2})\s*[.:—-]?\s*' .
            '(.+?)(?=(?:\n\s*Competencia\s+espec[ií]fica\s+\d{1,2})|\z)/isu';
        preg_match_all($pattern, $text, $matches, PREG_SET_ORDER);
        $parents = [];
        foreach ($matches as $index => $match) {
            $number = (int)$match[1];
            $criterionpattern = '/(?:^|\n)\s*(' . $number . '\.\d{1,2})[.)]?\s+(.+?)' .
                '(?=(?:\n\s*' . $number . '\.\d{1,2}[.)]?\s+)|\z)/su';
            if (!preg_match_all($criterionpattern, $match[2], $criteria, PREG_SET_ORDER)) {
                return [];
            }
            $parentname = trim(preg_split('/\n\s*' . $number . '\.\d{1,2}[.)]?\s+/u', $match[2], 2)[0]);
            if ($parentname === '' || preg_match('/^' . $number . '\.\d{1,2}\s+/u', $parentname)) {
                $parentname = trim((string)($competencynames[$number] ?? ''));
            }
            if ($parentname === '') {
                return [];
            }
            $normalcriteria = [];
            foreach ($criteria as $ci => $criterion) {
                $normalcriteria[] = [
                    'code' => $criterion[1], 'name' => trim(preg_replace('/\s+/u', ' ', $criterion[2])),
                    'weight' => null, 'sortorder' => $ci,
                ];
            }
            $parents[] = [
                'code' => 'CE' . $number, 'name' => trim(preg_replace('/\s+/u', ' ', $parentname)),
                'type' => 'ce', 'weight' => null, 'sortorder' => $index, 'criteria' => $normalcriteria,
            ];
        }
        return $parents;
    }

    /**
     * Convert AEBOE XML/HTML-like content to stable line-oriented text.
     */
    private function plain_text(string $content): string {
        $content = preg_replace('/<\/?(?:p|div|h[1-6]|li|br|blockquote|bloque|version)[^>]*>/iu', "\n", $content);
        $content = html_entity_decode(strip_tags($content), ENT_QUOTES | ENT_XML1, 'UTF-8');
        $content = preg_replace('/[\t ]+/u', ' ', $content);
        $content = preg_replace('/\R\s*\R+/u', "\n", $content);
        return trim($content);
    }

    /**
     * Merge common official-source metadata.
     */
    private function metadata(array $source, array $specific): array {
        return array_merge([
            'provider' => 'boe',
            'sourceid' => $source['sourceid'] ?? null,
            'sourcename' => $source['sourcename'] ?? 'AEBOE consolidated legislation',
            'sourceref' => $source['sourceref'] ?? null,
            'sourceversion' => $source['sourceversion'] ?? null,
            'sourcelastupdate' => $source['sourcelastupdate'] ?? null,
            'retrievedat' => $source['retrievedat'] ?? time(),
            'qualification' => $source['qualification'] ?? null,
            'language' => $source['language'] ?? 'es',
            'provenance' => $source['provenance'] ?? 'Texto consolidado de carácter informativo.',
        ], $specific);
    }
}
