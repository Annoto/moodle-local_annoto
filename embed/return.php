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
 * Deep Linking return endpoint: validates the Annoto response and hands the placeholder to the editor.
 *
 * The page runs inside the editor dialog iframe and posts a message to its parent window:
 *  {source: 'local_annoto_embed', mceAction: 'annotoInsert', action: 'insert', html: '...'} or
 *  {source: 'local_annoto_embed', mceAction: 'annotoCancel', action: 'cancel'}.
 *
 * @package    local_annoto
 * @copyright  Annoto Ltd.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// phpcs:ignore moodle.Files.RequireLogin.Missing -- require_login() follows the cross-site repost check.
require_once(__DIR__ . '/../../../config.php');
require_once($CFG->dirroot . '/mod/lti/locallib.php');

use local_annoto\embed\helper;

$courseid = required_param('course', PARAM_INT);
$pageurl = new moodle_url('/local/annoto/embed/return.php', ['course' => $courseid]);

// The tool POSTs here cross-site; SameSite cookies are not sent, so re-post from our own origin first.
if (helper::repost_if_needed($pageurl)) {
    exit;
}

$jwt = optional_param('JWT', '', PARAM_RAW);

$course = get_course($courseid);
require_login($course, false);
require_sesskey();
$context = context_course::instance($course->id);
require_capability('local/annoto:embedmedia', $context);

$PAGE->set_url($pageurl);
$PAGE->set_context($context);
$PAGE->set_pagelayout('popup');
$PAGE->set_title(get_string('embed_dialogtitle', 'local_annoto'));

$type = helper::get_tool_type();
$message = ['source' => 'local_annoto_embed', 'mceAction' => 'annotoCancel', 'action' => 'cancel'];
$errormsg = '';

try {
    if (!$type) {
        throw new moodle_exception('embed_notconfigured', 'local_annoto');
    }
    if ($jwt === '') {
        throw new moodle_exception('embed_invalidresponse', 'local_annoto');
    }
    // Verifies the signature against the tool's key set / public key and the issuer (= client id).
    $params = lti_convert_from_jwt($type->id, $jwt);
    if (($params['oauth_consumer_key'] ?? '') !== $type->clientid) {
        throw new moodle_exception('embed_invalidresponse', 'local_annoto');
    }
    if (($params['lti_message_type'] ?? '') !== 'ContentItemSelection') {
        throw new moodle_exception('embed_invalidresponse', 'local_annoto');
    }
    if (!empty($params['lti_errormsg'])) {
        $errormsg = s($params['lti_errormsg']);
    } else if (!empty($params['content_items'])) {
        $html = helper::placeholder_from_content_items($params['content_items'], $type);
        if ($html !== null) {
            $message = ['source' => 'local_annoto_embed', 'mceAction' => 'annotoInsert', 'action' => 'insert',
                'html' => $html];
        }
    }
} catch (moodle_exception $e) {
    $errormsg = $e->getMessage();
}

echo $OUTPUT->header();
if ($errormsg) {
    echo $OUTPUT->notification($errormsg, 'error');
} else {
    echo html_writer::tag('p', get_string(
        $message['action'] === 'insert' ? 'embed_inserted' : 'embed_cancelled',
        'local_annoto'
    ));
    // Only this site's own windows (the editor page) may receive the message.
    $origin = preg_replace('#^(https?://[^/]+).*$#i', '$1', $CFG->wwwroot);
    $js = 'var m = ' . json_encode($message) . ';' .
        'var t = (window.parent && window.parent !== window) ? window.parent : window.opener;' .
        'if (t) { t.postMessage(m, ' . json_encode($origin) . '); }' .
        'if (window.opener && window.parent === window) { window.close(); }';
    $PAGE->requires->js_init_code($js);
}
echo $OUTPUT->footer();
