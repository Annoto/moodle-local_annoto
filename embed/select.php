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
 * Opens the "Embed Annoto Media" picker (LTI 1.3 Deep Linking) inside the editor dialog.
 *
 * Called by tiny_annoto and atto_annoto with the editor's context id.
 *
 * @package    local_annoto
 * @copyright  Annoto Ltd.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');
require_once($CFG->dirroot . '/mod/lti/locallib.php');

use local_annoto\embed\helper;

$contextid = optional_param('contextid', 0, PARAM_INT);
$courseid = optional_param('course', 0, PARAM_INT);

if ($contextid) {
    $context = context::instance_by_id($contextid, MUST_EXIST);
    $course = helper::get_course_for_context($context);
} else {
    $course = get_course($courseid ?: SITEID);
}
require_login($course, false);
$coursecontext = context_course::instance($course->id);

$PAGE->set_url(new moodle_url('/local/annoto/embed/select.php', ['contextid' => $contextid, 'course' => $courseid]));
$PAGE->set_context($coursecontext);
$PAGE->set_pagelayout('popup');
$PAGE->set_title(get_string('embed_dialogtitle', 'local_annoto'));

require_capability('local/annoto:embedmedia', $coursecontext);

$type = helper::get_tool_type();
if (!$type) {
    echo $OUTPUT->header();
    echo $OUTPUT->notification(get_string('embed_notconfigured', 'local_annoto'), 'error');
    echo $OUTPUT->footer();
    exit;
}

$config = lti_get_type_type_config($type->id);
$target = !empty($config->lti_toolurl_ContentItemSelectionRequest) ? $config->lti_toolurl_ContentItemSelectionRequest
    : $config->lti_toolurl;

helper::output_raw_page(
    html_writer::tag('p', get_string('embed_loading', 'local_annoto')) .
    helper::initiate_login($type, $course->id, helper::KIND_DEEPLINK, trim($target))
);
