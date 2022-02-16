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
 * Function allows plugins to injecting JS across the site, like analytics.
 *
 */
function local_annoto_before_footer() {
    local_annoto_init();
    return '';
}

/**
 * Function Insert a chunk of html at the start of the html document.
 * @return string HTML fragment.
 */
function local_annoto_before_standard_top_of_body_html() {
    global $PAGE;
    // Prevent callback loading for all themes except theme_lambda.
    if ($PAGE->theme->name != 'lambda') {
        return '';
    }
    local_annoto_init();
    return '';
}

/**
 * Function init plugin according to the proper environment conditions.
 * @return boolean
 */
function local_annoto_init() {
    global $PAGE, $COURSE;

    $istargetpage = false;
    $possiblepages = [
        'mod-',
        'course-view-',
        'blocks-'
    ];

    foreach ($possiblepages as $possiblepage) {
        if ((strpos($PAGE->pagetype, $possiblepage) !== false)){
            $istargetpage = true;
            break;
        }
    }
    // Start local_annoto on a specific pages only.
    if ($istargetpage) {
        $courseid = $COURSE->id;
        $modid = 0;
        if (isset($PAGE->cm->id)) {
            $modid = (int)$PAGE->cm->id;
        }

        $PAGE->requires->js('/local/annoto/initkaltura.js');
        $PAGE->requires->js_call_amd('local_annoto/annoto', 'init', array($courseid, $modid));
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
    $enctoken = \Firebase\JWT\JWT::encode($payload, $settings->ssosecret);

    return $enctoken;
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
function local_annoto_get_jsparam($courseid, $modid) {
    global $CFG;
    $course = get_course($courseid);

    // Get plugin global settings.
    $settings = get_config('local_annoto');

    // Get login, logout urls.
    $loginurl = $CFG->wwwroot . "/login/index.php";
    $logouturl = $CFG->wwwroot . "/login/logout.php?sesskey=" . sesskey();

    // Get activity data for mediaDetails.
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

    $jsparams = array(
        'deploymentDomain' => $settings->deploymentdomain != 'custom' ? $settings->deploymentdomain : $settings->customdomain,
        'bootstrapUrl' => $settings->scripturl,
        'clientId' => $settings->clientid,
        'userToken' => local_annoto_get_user_token($settings, $courseid),
        'loginUrl' => $loginurl,
        'logoutUrl' => $logouturl,
        'mediaId' => $modid ?? '',
        'mediaTitle' => $cmtitle ?? '',
        'mediaDescription' => $cmintro ?? '',
        'mediaGroupId' => $courseid,
        'mediaGroupTitle' => $course->fullname,
        'mediaGroupDescription' => $course->summary,
        'locale' => $lang,
    );

    return $jsparams;
}


/**
 * Adds module specific settings to the settings block
 *
 * @param settings_navigation $settings The settings navigation object
 * @param context_course $context The node to add module settings to
 */
function local_annoto_extend_settings_navigation(settings_navigation $settingsnav, context  $context) {
    global $CFG, $PAGE, $COURSE;

    if ((strpos($PAGE->pagetype, 'mod-') === false) &&
        (strpos($PAGE->pagetype, 'course-view-') === false)) {
        return;
    }

    // Get plugin global settings.
    $settings = get_config('local_annoto');

    // Only let users with the appropriate capability see this settings item.
    if (!local_annoto_is_moderator($settings, $COURSE->id)) {
        return;
    }

    if(!$settingnode = $settingsnav->find('courseadmin', navigation_node::TYPE_COURSE)){
        return;
    }

    // Check and create LTI external tool
   require_once($CFG->dirroot . '/mod/lti/locallib.php');
    $lti = lti_get_tool_by_url_match($settings->toolurl);
    if (!$lti){
        $lti = new stdClass();
        $lti->id = local_annoto_lti_add_type();
    }

    // Create a dashboard instance if not available
    if(!$cm = local_annoto_get_lti_course_module()){
        if (!$settings->addingdashboard) {
            return;
        }
        if (!$cm = local_annoto_create_lti_course_module($lti)) {
            return;
        }
    }

    $text = get_string('lti_activity_name', 'local_annoto');
    $type = navigation_node::TYPE_SETTING;
    $icon = new pix_icon('icon', '','local_annoto');
    $url  = $cm->url->out();

    // Add nav button to Annoto dashboard
    $annotodashboard = navigation_node::create($text, $url, $type , null, 'annotodashboard', $icon);
    if ($settingnode->find('coursereports', navigation_node::TYPE_CONTAINER)) {
        $settingnode->add_node($annotodashboard,'coursereports');
    } else {
        // The nodes next to the course report node have no keys,
        // So the target node is calculated as a delta from the closest know key.
        $keys = array_flip($settingnode->get_children_key_list());
        $key = isset($keys['users']) ? $keys['users'] : 0;
        // Insert the new node two places after the users node.
        $settingnode->add_node($annotodashboard, $settingnode->children->get_key_list()[$key+2]);
    }

}

/**
 * returns annoto dashboard's lti course module of current course
 *
 * @return cm_info|null $cm
 */
function local_annoto_get_lti_course_module(){

    GLOBAL $PAGE;

    $modinfo = get_fast_modinfo($PAGE->course);

    foreach($modinfo->get_instances_of('lti') as $cm){

        $domain = $cm->get_icon_url()->get_host();
        if(strpos($domain, 'annoto') !== false){
            return $cm;
        }
    }

    return null;
}

/**
 * creates annoto dashboard's lti course module for current course
 * @param stdClass $lti LTI extrnall tool for specific mode
 * @return cm_info|null $cm
 */
function local_annoto_create_lti_course_module($lti){
    GLOBAL $CFG, $PAGE;

    $context = context_course::instance($PAGE->course->id);
    if(!has_capability('local/annoto:managementdashboard', $context)){
        return null;
    }

    if (!course_allowed_module($PAGE->course, 'lti')) {
        return null;
    }

    require_once($CFG->dirroot . '/mod/lti/locallib.php');

    $toolconfig = lti_get_type_config($lti->id);

    $new_dashboard = new stdClass;
    $new_dashboard->modulename = 'lti';
    $new_dashboard->name = get_string('lti_activity_name', 'local_annoto');
    $new_dashboard->course = $PAGE->course->id;
    $new_dashboard->introeditor = [
        'itemid' => 0,
        'format' => FORMAT_PLAIN,
        'text'   => get_string('pluginname', 'local_annoto')
    ];
    $new_dashboard->section = 0;
    $new_dashboard->visible = 0;
    $new_dashboard->typeid = $lti->id;
    $new_dashboard->servicesalt = $toolconfig['servicesalt'];
    $new_dashboard->instructorchoicesendname = 1;
    $new_dashboard->instructorchoicesendemailaddr = 1;
    $new_dashboard->showtitlelaunch = 1;
    $new_dashboard->timecreated = time();
    $new_dashboard->timemodified = time();

    if(!create_module($new_dashboard)){
        return null;
    }

    return local_annoto_get_lti_course_module();
}

/**
 * creates Annoto LTI type
 *
 * @return integer LTI type id
 */
function local_annoto_lti_add_type() {

    // Get plugin global settings.
    $settings = get_config('local_annoto');
    
    $type = new stdClass;
    $type->name = $settings->toolname;
    $type->baseurl = $settings->toolurl;
    $type->tooldomain = parse_url($settings->toolurl, PHP_URL_HOST);
    $type->state = 1;
    $type->coursevisible = LTI_COURSEVISIBLE_NO;

    $type->icon = $settings->tooliconurl;
    $type->secureicon = $settings->tooliconurl;
    $type->description = get_string('annoto_dashboard_description', 'local_annoto');

    $config = new stdClass;
    $config->lti_resourcekey = $settings->clientid;
    $config->lti_password = $settings->ssosecret;
    $config->lti_coursevisible = LTI_COURSEVISIBLE_NO;
    $config->lti_launchcontainer = 3;

    //Privacy setting
    $config->lti_forcessl = 1;
    $config->lti_sendname = 1;
    $config->lti_sendemailaddr = 1;
    $config->lti_acceptgrades = 2;

    return lti_add_type($type, $config);
}

function local_annoto_update_lti_type() {

    $settings = get_config('local_annoto');
    $lti = lti_get_tool_by_url_match($settings->toolurl);

    if (!$lti){
        $lti = new stdClass;
        $lti->id = local_annoto_lti_add_type();
    }

    $coursevisible = $settings->addingdashboard ? LTI_COURSEVISIBLE_NO : LTI_COURSEVISIBLE_ACTIVITYCHOOSER;
    $lti->coursevisible = $coursevisible;

    $config = new stdClass;
    $config->lti_resourcekey = $settings->clientid;
    $config->lti_password = $settings->ssosecret;
    $config->lti_coursevisible = $coursevisible;

    lti_update_type($lti, $config);
}
