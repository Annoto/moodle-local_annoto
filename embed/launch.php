<?php
// This file is part of Moodle - http://moodle.org/
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
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Per-viewer launch of an embedded Annoto media item (LTI 1.3 resource link launch).
 *
 * filter_annoto renders an iframe to this page; without the filter, the placeholder link opens it in a
 * new tab. All parameters are re-validated: the launch URL must be on the configured Annoto tool host.
 *
 * @package    local_annoto
 * @copyright  Annoto Ltd.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');
require_once($CFG->dirroot . '/mod/lti/locallib.php');

use local_annoto\embed\helper;

$url = required_param('url', PARAM_URL);
$rl = required_param('rl', PARAM_ALPHANUMEXT);
$customjson = optional_param('custom', '', PARAM_RAW);
$title = optional_param('title', '', PARAM_TEXT);
$courseid = optional_param('course', SITEID, PARAM_INT);

$course = get_course($courseid);
require_login($course, true);
$context = context_course::instance($course->id);

$PAGE->set_url(new moodle_url(helper::LAUNCH_PATH, ['url' => $url, 'rl' => $rl, 'course' => $course->id]));
$PAGE->set_context($context);
$PAGE->set_pagelayout('embedded');

require_capability('local/annoto:viewembed', $context);

$type = helper::get_tool_type();
if (!$type) {
    throw new moodle_exception('embed_notconfigured', 'local_annoto');
}
if (!helper::is_valid_launch_url($url, $type)) {
    throw new moodle_exception('embed_invalidurl', 'local_annoto');
}

$custom = helper::clean_custom(json_decode($customjson, true) ?: []);

helper::output_raw_page(helper::initiate_login($type, $course->id, helper::KIND_LAUNCH, $url, [
    'rl' => $rl,
    'custom' => $custom,
    'title' => $title,
]));
