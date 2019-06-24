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
 * Local plugin "Annoto" - Library
 * @package    local_annoto
 * @copyright  Annoto Ltd.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Callback function - injects Annoto's JS into every page.
 */
function local_annoto_before_footer() {
    global $PAGE, $COURSE, $USER;

    // Start local_annoto only on the course page or at course module pages.
    if ((strpos($PAGE->pagetype, 'mod-') !== false) ||
        (strpos($PAGE->pagetype, 'course-view-') !== false)) {

        $courseid = $COURSE->id;
        $pageurl = $PAGE->url->out();
        $modid = $PAGE->cm->id ?? 0;

        $PAGE->requires->js_call_amd('local_annoto/annoto', 'init', array($courseid, $pageurl, $modid));
    }
}

/**
 * Function gets user token for Annoto script.
 * @param stdClass $settings the plugin global settings.
 * @return string
 */
function local_annoto_get_user_token($settings, $courseid) {
    global $USER, $PAGE;

    $context = context_course::instance($courseid);
    $PAGE->set_context($context);

    // Is user logged in or is guest.
    $userloggined = isloggedin();
    if (!$userloggined) {
        return '';
    }
    $guestuser = isguestuser();

    // Provide page and js with data.
    // Get user's avatar.
    $userpicture = new user_picture($USER);
    $userpicture->size = 150;
    $userpictureurl = $userpicture->get_url($PAGE);

    // Create and encode JWT for Annoto script.
    require_once('JWT.php');                   // Load JWT lib.

    $issuedat = time();                        // Get current time.
    $expire = $issuedat + 60 * 20;             // Adding 20 minutes.

    // Check if user is a moderator.
    $moderator = local_annoto_is_moderator($settings, $courseid);

    $payload = array(
        "jti" => $USER->id,                     // User's id in Moodle.
        "name" => fullname($USER),              // User's fullname in Moodle.
        "email" => $USER->email,                // User's email.
        "photoUrl" => is_object($userpictureurl) ? $userpictureurl->out() : '',  // User's avatar in Moodle.
        "iss" => $settings->clientid,           // ClientID from global settings.
        "exp" => $expire,                       // JWT token expiration time.
        "scope" => ($moderator ? 'super-mod' : 'user'),
    );

    return JWT::encode($payload, $settings->ssosecret);
}

/**
 * Function gets current language for Annoto script.
 * @return string
 */
function local_annoto_get_lang($course) {
    global $SESSION, $USER;

    if (isset($course->lang) and !empty($course->lang)) {
        return $course->lang;
    }
    if (isset($SESSION->lang) and !empty($SESSION->lang)) {
        return $SESSION->lang;
    }
    if (isset($USER->lang) and !empty($USER->lang)) {
        return $USER->lang;
    }
    return current_language();
}

/**
 * Function defines either is current user a 'moderator' or not (in the context of Annoto script).
 * @param stdClass $settings the plugin global settings.
 * @return bolean
 */
function local_annoto_is_moderator($settings, $courseid) {
    global $USER;

    $reqcapabilities = array(
        'local/annoto:moderatediscussion'
    );

    $context = context_course::instance($courseid);

    // Check the minimum required capabilities.
    foreach ($reqcapabilities as $cap) {
        if (!has_capability($cap, $context)) {
            return false;
        }
    }

    // Check if user has a role as defined in settings.
    $userroles = get_user_roles($context, $USER->id, true);
    $allowedroles = explode(',', $settings->moderatorroles);

    foreach ($userroles as $role) {
        if (in_array($role->roleid, $allowedroles)) {
            return true;
        }
    }

    return false;
}
