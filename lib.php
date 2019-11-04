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
 * Allow plugins to injecting JS across the site, like analytics.
 *
 */

function local_annoto_before_footer() {
    global $PAGE, $COURSE, $OUTPUT;

    // Start local_annoto only on the course page or at course module pages.
    if ((strpos($PAGE->pagetype, 'mod-') !== false) ||
        (strpos($PAGE->pagetype, 'course-view-') !== false)) {

        $courseid = $COURSE->id;
        $pageurl = $PAGE->url->out();
        $modid = 0;
        if (isset($PAGE->cm->id)) {
            $modid = (int)$PAGE->cm->id;
        }

        $PAGE->requires->js('/local/annoto/initkaltura.js');
        $PAGE->requires->js_call_amd('local_annoto/annoto', 'init', array($courseid, $pageurl, $modid));
        // SETTINGS FOR DEVELOPMENT SERVERS - not intended for production use!!!
        echo $OUTPUT->notification('Annoto: The plugin inits scripts to handling video content on this page', 'info');
    }else {
        // SETTINGS FOR DEVELOPMENT SERVERS - not intended for production use!!!
        echo $OUTPUT->notification('Annoto: The plugin not configured for this page', 'warning');
    }
}


/**
 * Function gets user token for Annoto script.
 * @param stdClass $settings the plugin global settings.
 * @param int $courseid the id of the course.
 * @return string
 */
function local_annoto_get_user_token($settings, $courseid) {
    global $USER, $PAGE;

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
 * @param stdClass $course Course object
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
 * @param int $courseid the id of the course.
 * @return bolean
 */
function local_annoto_is_moderator($settings, $courseid) {
    global  $USER;
    $ismoderator = false;
    $coursecontext = context_course::instance($courseid);
    $capabilities = 'local/annoto:moderatediscussion';

    // Check if user has a role as defined in settings.
    $userroles = get_user_roles($coursecontext, $USER->id, true);
    $allowedroles = explode(',', $settings->moderatorroles);
    foreach ($userroles as $role) {
        if (in_array($role->roleid, $allowedroles) && has_capability($capabilities, $coursecontext)) {
            $ismoderator = true;
        }
    }
    return $ismoderator;
}

/**
 * Get parameters for Anooto's JS script
 * @param int $courseid the id of the course.
 * @param string $pageurl url of the current page.
 * @param int $modid mod id.
 * @return array
 */
function get_jsparam($courseid, $pageurl, $modid) {
    global $CFG;
    $course = get_course($courseid);

    // Get plugin global settings.
    $settings = get_config('local_annoto');

    // Set id of the video frame where script should be attached.
    $defaultplayerid = 'annoto_default_player_id';
    $isglobalscope = filter_var($settings->scope, FILTER_VALIDATE_BOOLEAN);

    // If scope is not Global - check if url is in access list.
    if (!$isglobalscope) {
        // ACL.
        $acltext = ($settings->acl) ? $settings->acl : null;
        $aclarr = preg_split("/\R/", $acltext);
        $iscourseinacl = false;
        $isurlinacl = false;

        $iscourseinacl = in_array($courseid, $aclarr);

        if (!$iscourseinacl) {
            $isurlinacl = in_array($pageurl, $aclarr);
        }
        $isaclmatch = ($iscourseinacl || $isurlinacl);
    }

    // Get login, logout urls.
    $loginurl = $CFG->wwwroot . "/login/index.php";
    $logouturl = $CFG->wwwroot . "/login/logout.php?sesskey=" . sesskey();

    // Get activity data for mediaDetails.
    $cmtitle = '';
    $cmintro = '';
    if ($modid) {
        $modinfo = get_fast_modinfo($course);
        $cm = $modinfo->get_cm($modid);
        $cmtitle = $cm->name;
        $cmintro = $cm->content;
    }

    // Locale settings.
    if ($settings->locale == "auto") {
        $lang = local_annoto_get_lang($course);
    } else {
        $lang = $settings->locale;
    }
    $widgetposition = 'right';
    $widgetverticalalign = 'center';
    if (stripos($settings->widgetposition, 'left') !== false) {
        $widgetposition = 'left';
    }
    if (stripos($settings->widgetposition, 'top') !== false) {
        $widgetverticalalign = 'top';
    }
    if (stripos($settings->widgetposition, 'bottom') !== false) {
        $widgetverticalalign = 'bottom';
    }

    $jsparams = array(
        'bootstrapUrl' => $settings->scripturl,
        'clientId' => $settings->clientid,
        'userToken' => local_annoto_get_user_token($settings, $courseid),
        'position' => $widgetposition,
        'alignVertical' => $widgetverticalalign,
        'widgetOverlay' => $settings->widgetoverlay,
        'featureTab' => !empty($settings->tabs) ? filter_var($settings->tabs, FILTER_VALIDATE_BOOLEAN) : true,
        'featureCTA' => !empty($settings->cta) ? filter_var($settings->cta, FILTER_VALIDATE_BOOLEAN) : false,
        'loginUrl' => $loginurl,
        'logoutUrl' => $logouturl,
        'mediaTitle' => $cmtitle,
        'mediaDescription' => $cmintro,
        'mediaGroupId' => $courseid,
        'mediaGroupTitle' => $course->fullname,
        'mediaGroupDescription' => $course->summary,
        'privateThread' => filter_var($settings->discussionscope, FILTER_VALIDATE_BOOLEAN),
        'locale' => $lang,
        'rtl' => filter_var((substr($lang, 0, 2) === "he"), FILTER_VALIDATE_BOOLEAN),
        'demoMode' => filter_var($settings->demomode, FILTER_VALIDATE_BOOLEAN),
        'defaultPlayerId' => $defaultplayerid,
        'zIndex' => !empty($settings->zindex) ? filter_var($settings->zindex, FILTER_VALIDATE_INT) : 100,
        'isGlobalScope' => $isglobalscope,
        'isACLmatch' => !empty($isaclmatch) ? filter_var($isaclmatch, FILTER_VALIDATE_BOOLEAN) : false,
    );

    return $jsparams;
}
