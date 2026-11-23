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
 * LTI 1.3 platform authorization endpoint for the Annoto embed tool.
 *
 * Register this URL as the platform "Authentication request URL" (OIDC authorization endpoint) of the
 * Annoto media_embed tool. Requests started by local_annoto (editor Deep Linking and per-viewer embed
 * launches, which have no mod_lti course module) are answered here; every other request is handed to
 * Moodle's own /mod/lti/auth.php unchanged, so the same registration keeps working for activities.
 *
 * @package    local_annoto
 * @copyright  Annoto Ltd.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// phpcs:ignore moodle.Files.RequireLogin.Missing -- require_login() is called once the launch is identified.
require_once(__DIR__ . '/../../../config.php');
require_once($CFG->dirroot . '/mod/lti/locallib.php');

use local_annoto\embed\helper;

$ltimessagehintenc = optional_param('lti_message_hint', '', PARAM_RAW);

if (!helper::is_own_hint($ltimessagehintenc)) {
    // Not ours: run core's endpoint in this same request (it reads the same GET/POST parameters and
    // performs its own cross-site repost). Including it avoids an extra redirect that could drop the
    // session cookie inside third-party iframes.
    require($CFG->dirroot . '/mod/lti/auth.php');
    exit;
}

if (helper::repost_if_needed(new moodle_url('/local/annoto/lti/auth.php'))) {
    exit;
}

$scope = optional_param('scope', '', PARAM_TEXT);
$responsetype = optional_param('response_type', '', PARAM_TEXT);
$clientid = optional_param('client_id', '', PARAM_TEXT);
$redirecturi = optional_param('redirect_uri', '', PARAM_URL);
$loginhint = optional_param('login_hint', '', PARAM_TEXT);
$state = optional_param('state', null, PARAM_TEXT);
$responsemode = optional_param('response_mode', '', PARAM_TEXT);
$nonce = optional_param('nonce', '', PARAM_TEXT);
$prompt = optional_param('prompt', '', PARAM_TEXT);

$error = '';
$desc = '';
$launch = helper::consume_launch($ltimessagehintenc);
if (empty($launch)) {
    $error = 'invalid_request';
    $desc = 'Unknown or expired launch';
} else if (
    empty($scope) || empty($responsetype) || empty($clientid) || empty($redirecturi) || empty($loginhint)
        || empty($nonce)
) {
    $error = 'invalid_request';
} else if ($scope !== 'openid') {
    $error = 'invalid_scope';
} else if ($responsetype !== 'id_token') {
    $error = 'unsupported_response_type';
} else if ($responsemode !== 'form_post') {
    $error = 'invalid_request';
    $desc = 'Invalid response_mode';
} else if (!empty($prompt) && $prompt !== 'none') {
    $error = 'invalid_request';
    $desc = 'Invalid prompt';
}

// Without a tool type we cannot trust the redirect URI to POST anything to.
$type = !empty($launch['typeid']) ? lti_get_type($launch['typeid']) : null;
if (!$type || $type->ltiversion !== LTI_VERSION_1P3) {
    throw new moodle_exception('invalidrequest', 'error');
}
$config = lti_get_type_type_config($type->id);
$uris = array_map('trim', explode("\n", (string) $config->lti_redirectionuris));
if (!in_array($redirecturi, $uris, true)) {
    throw new moodle_exception('invalidrequest', 'error');
}

if (!$error && $clientid !== $config->lti_clientid) {
    $error = 'unauthorized_client';
}
if (!$error && (string) $loginhint !== (string) $USER->id) {
    $error = 'access_denied';
}

$params = [];
if (!$error) {
    $course = get_course((int) $launch['courseid']);
    require_login($course, false);
    $context = context_course::instance($course->id);
    $PAGE->set_context($context);

    if ($launch['kind'] === helper::KIND_DEEPLINK) {
        require_capability('local/annoto:embedmedia', $context);
        [$endpoint, $params] = helper::build_deep_linking_request($type, $course, $nonce);
    } else if ($launch['kind'] === helper::KIND_LAUNCH) {
        require_capability('local/annoto:viewembed', $context);
        $endpoint = $launch['targetlinkuri'];
        if (!helper::is_valid_launch_url($endpoint, $type)) {
            throw new moodle_exception('embed_invalidurl', 'local_annoto');
        }
        [$endpoint, $params] = helper::build_resource_link_request(
            $type,
            $course,
            $endpoint,
            $launch['data'] ?? [],
            $nonce
        );
    } else {
        $error = 'invalid_request';
    }
}

if ($error) {
    $params = ['error' => $error];
    if ($desc) {
        $params['error_description'] = $desc;
    }
}
if ($state !== null) {
    $params['state'] = $state;
}

helper::output_raw_page(helper::auto_post_form($redirecturi, $params, 'ltiAuthForm'));
