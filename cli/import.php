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
 * Administrative curriculum importer.
 *
 *  local_criteriaoutcomes
 * @copyright 2026 Juan Bautista Talens Felis
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);
// phpcs:ignore moodle.Files.MoodleInternal.MoodleInternalGlobalState -- Supports both Moodle code layouts.
$configpaths = [dirname(__DIR__, 3) . '/config.php', dirname(__DIR__, 4) . '/config.php'];
foreach ($configpaths as $configpath) {
    if (is_readable($configpath)) {
        require_once($configpath);
        break;
    }
}
if (!defined('MOODLE_INTERNAL')) {
    throw new RuntimeException('Moodle config.php was not found.');
}
require_once($CFG->libdir . '/clilib.php');
require_once($CFG->libdir . '/gradelib.php');
require_once($CFG->libdir . '/grade/grade_outcome.php');
require_once($CFG->libdir . '/grade/grade_scale.php');

[$options, $unrecognised] = cli_get_params([
    'help' => false, 'courseid' => null, 'file' => null, 'scaleid' => null,
], ['h' => 'help', 'c' => 'courseid', 'f' => 'file', 's' => 'scaleid']);
if ($options['help'] || !$options['courseid'] || !$options['file'] || !$options['scaleid']) {
    echo "Import curriculum criteria as native Moodle outcomes.\n\n";
    echo "--courseid=ID --file=/path/curriculum.json --scaleid=ID\n";
    exit($options['help'] ? 0 : 1);
}
if ($unrecognised) {
    cli_error('Unknown options: ' . implode(', ', $unrecognised));
}
$course = get_course((int)$options['courseid']);
$content = file_get_contents($options['file']);
if ($content === false) {
    cli_error('Cannot read the JSON file.');
}
$admin = get_admin();
\core\session\manager::set_user($admin);
try {
    $data = (new \local_criteriaoutcomes\provider\json_provider())->parse($content);
    $counts = (new \local_criteriaoutcomes\service\import_service())->import($course->id, (int)$options['scaleid'], $data);
    cli_writeln("Imported: {$counts['new']} new, {$counts['existing']} unchanged, " .
        "{$counts['textchanged']} text updates, {$counts['scalechanged']} safe scale updates, " .
        "{$counts['metadatachanged']} metadata updates, {$counts['scaleblocked']} scale changes blocked, " .
        "{$counts['conflict']} conflicts skipped.");
} catch (Throwable $e) {
    cli_error($e->getMessage());
}
