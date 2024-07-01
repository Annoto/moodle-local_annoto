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

if (!defined('USREGION')) {
    define('USREGION', 'us.annoto.net');
}
if (!defined('EUREGION')) {
    define('EUREGION', 'eu.annoto.net');
}
if (!defined('CUSTOM')) {
    define('CUSTOM', 'custom');
}

if (!defined('TOOLNAME')) {
    define('TOOLNAME', 'Annoto Dashboard');
}
if (!defined('TOOLURL')) {
    define('TOOLURL', 'https://auth.eu.annoto.net/lti/course-insights');
}
if (!defined('TOOLICONURL')) {
    define('TOOLICONURL', 'https://assets.annoto.net/images/logo_icon.png');
}

if (!defined('LTIGRADEGNAME')) {
    define('LTIGRADEGNAME', 'Annoto Assignment');
}
if (!defined('LTIGRADEURL')) {
    define('LTIGRADEURL', 'https://auth.eu.annoto.net');
}
if (!defined('LTIGRADEICONURL')) {
    define('LTIGRADEICONURL', 'https://cdn.annoto.net/assets/latest/images/icon.svg');
}
if (!defined('LTIGRADECONTENTITEM')) {
    define('LTIGRADECONTENTITEM', '/lti/item-embed');
}

if (!defined('DEFAULTWIDTH')) {
    define('DEFAULTWIDTH', 854);
}
if (!defined('DEFAULTHEIGHT')) {
    define('DEFAULTHEIGHT', 480);
}


require_once($CFG->libdir . '/completionlib.php');
require_once(__DIR__ . '/classes/completion.php');
require_once(__DIR__ . '/classes/completiondata.php');
require_once(__DIR__ . '/classes/log.php');

use local_annoto\log;
use local_annoto\annoto_completion;
use local_annoto\annoto_completiondata;

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
    // Prevent callback loading for all themes except those:.
    $themes = ['lambda', 'adaptable', 'academi']; // Added academi theme.
    if (in_array($PAGE->theme->name, $themes)) {
        local_annoto_init();
    }
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
        'blocks-',
    ];

    foreach ($possiblepages as $possiblepage) {
        if ((strpos($PAGE->pagetype, $possiblepage) !== false)) {
            $istargetpage = true;
            break;
        }
    }
    // Start local_annoto on a specific pages only.
    local_annoto_set_jslog('Page ' . $istargetpage);

    if ($istargetpage) {
        $courseid = $COURSE->id;
        $modid = 0;
        if (isset($PAGE->cm->id)) {
            $modid = (int)$PAGE->cm->id;
        }

        $PAGE->requires->js('/local/annoto/initkaltura.js');
        $PAGE->requires->js_call_amd('local_annoto/annoto', 'init', [$courseid, $modid]);
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

    // Provide page and js with data.
    // Get user's avatar.
    $userpicture = new user_picture($USER);
    $userpicture->size = 150;
    $userpictureurl = $userpicture->get_url($PAGE);

    $issuedat = time();                        // Get current time.
    $expire = $issuedat + 60 * 20;             // Adding 20 minutes.

    $payload = [
        "jti" => $USER->id,                     // User's id in Moodle.
        "name" => fullname($USER),              // User's fullname in Moodle.
        "email" => $USER->email,                // User's email.
        "photoUrl" => is_object($userpictureurl) ? $userpictureurl->out() : '',  // User's avatar in Moodle.
        "iss" => $settings->clientid,           // ClientID from global settings.
        "exp" => $expire,                       // JWT token expiration time.
        "scope" => local_annoto_get_user_scope($settings, $courseid),
    ];
    $enctoken = \Firebase\JWT\JWT::encode($payload, $settings->ssosecret, 'HS256');

    return $enctoken;
}

/**
 * Function gets user scope for Annoto permissions.
 * @param stdClass $settings the plugin global settings.
 * @param int $courseid the id of the course.
 * @return 'super-mod'|'user'
 */
function local_annoto_get_user_scope($settings, $courseid) {
    $userloggedin = isloggedin();
    if (!$userloggedin) {
        return 'user';
    }
    $capability = 'local/annoto:moderatediscussion';
    $ismoderator = local_annoto_has_capability($settings->moderatorroles, $courseid, $capability);
    return $ismoderator ? 'super-mod' : 'user';
}

/**
 * Function gets current language for Annoto script.
 * @param stdClass $course Course object
 * @return string
 */
function local_annoto_get_lang($course) {
    global $SESSION, $USER;

    if (isset($course->lang) && !empty($course->lang)) {
        return $course->lang;
    }
    if (isset($SESSION->lang) && !empty($SESSION->lang)) {
        return $SESSION->lang;
    }
    if (isset($USER->lang) && !empty($USER->lang)) {
        return $USER->lang;
    }
    return current_language();
}

/**
 * Function defines either is current user a 'moderator' or not (in the context of Annoto script).
 * @param string $allowedroles comma separated string of roles.
 * @param int $courseid the id of the course.
 * @param string $capability the name of the capability to check
 * @return bolean
 */
function local_annoto_has_capability($allowedroles, $courseid, $capability) {
    global  $USER;
    $hascapability = false;
    $coursecontext = context_course::instance($courseid);

    // Check if user has a role as defined in settings.
    $userroles = get_user_roles($coursecontext, $USER->id, true);
    $allowedroles = explode(',', $allowedroles);
    foreach ($userroles as $role) {
        if (in_array($role->roleid, $allowedroles) && has_capability($capability, $coursecontext)) {
            $hascapability = true;
        }
    }
    return $hascapability;
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
    global $USER;
    $course = get_course($courseid);
    $userloggedin = isloggedin();

    // Get plugin global settings.
    $settings = get_config('local_annoto');

    // Get login, logout urls.
    $loginurl = $CFG->wwwroot . '/login/index.php';
    $logouturl = $CFG->wwwroot . '/login/logout.php?sesskey=' . sesskey();

    $activitycompletionenabled = false;
    $activitycompletionreq = null;
    $userscope = local_annoto_get_user_scope($settings, $courseid);
    $context = \context_course::instance($courseid);
    $userisenrolled = is_enrolled($context, $USER, '', true);
    // Get activity data for mediaDetails.
    if ($modid) {
        $modinfo = get_fast_modinfo($course);
        $cm = $modinfo->get_cm($modid);
        $cmtitle = $cm->name;
        $cmintro = $cm->content;
        if ($settings->activitycompletion && $userloggedin) {
            $completionrecord = annoto_completion::get_record(['cmid' => $modid]);
            if ($completionrecord) {
                $activitycompletionreq = $completionrecord->to_record();
                // Moodle v3 do not have clean_param and returns type string.
                if ((int)$completionrecord->get('enabled') == annoto_completion::COMPLETION_TRACKING_AUTOMATIC) {
                    $activitycompletionenabled = true;
                    if ($userscope === 'user') {
                        if ($completiondata = annoto_completiondata::get_record(
                            ['completionid' => $completionrecord->get('id'), 'userid' => $USER->id])
                        ) {
                            $activitycompletionreq->user_data = $completiondata->to_record();
                        }
                    }
                }
            }
        }
    }

    $jsparams = [
        'deploymentDomain' => $settings->deploymentdomain != 'custom' ? $settings->deploymentdomain : $settings->customdomain,
        'bootstrapUrl' => $settings->scripturl,
        'clientId' => $settings->clientid,
        'userToken' => local_annoto_get_user_token($settings, $courseid),
        'loginUrl' => $loginurl,
        'logoutUrl' => $logouturl,
        'mediaTitle' => $cmtitle ?? '',
        'mediaDescription' => $cmintro ?? '',
        'mediaGroupId' => $courseid,
        'mediaGroupTitle' => $course->fullname,
        'mediaGroupDescription' => $course->summary,
        'locale' => $settings->locale ? local_annoto_get_lang($course) : false,
        'cmid' => $modid ?? null,
        'moodleVersion' => $CFG->version,
        'moodleRelease' => $CFG->release,
        'activityCompletionEnabled' => $activitycompletionenabled,
        'activityCompletionReq' => $activitycompletionreq,
        'userScope' => $userscope,
        'userIsEnrolled' => $userisenrolled,
    ];

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
        (strpos($PAGE->pagetype, 'course-view-') === false)
    ) {
        return;
    }

    // Get plugin global settings.
    $settings = get_config('local_annoto');

    // Only let users with the appropriate capability see this settings item.
    $capability = 'local/annoto:managementdashboard';
    if (!local_annoto_has_capability($settings->managementdashboard, $COURSE->id, $capability)) {
        return;
    }

    if (!$settingnode = $settingsnav->find('courseadmin', navigation_node::TYPE_COURSE)) {
        return;
    }

    // Check and create LTI external tool.
    require_once($CFG->dirroot . '/mod/lti/locallib.php');
    $lti = lti_get_tool_by_url_match($settings->toolurl);
    if (!$lti) {
        $lti = new stdClass();
        $lti->id = local_annoto_lti_add_type('dashboard');
    }

    // Create a dashboard instance if not available.
    if (!$cm = local_annoto_get_lti_course_module()) {
        if (!$settings->addingdashboard) {
            return;
        }
        if (!$cm = local_annoto_create_lti_course_module($lti)) {
            return;
        }
    }

    $text = get_string('lti_activity_name', 'local_annoto');
    $type = navigation_node::TYPE_SETTING;
    $icon = new pix_icon('icon', '', 'local_annoto');
    $url = new moodle_url($cm->url->out());

    // Add nav button to Annoto dashboard.
    $annotodashboard = navigation_node::create($text, $url, $type, null, 'annotodashboard', $icon);
    if ($settingnode->find('coursereports', navigation_node::TYPE_CONTAINER)) {
        $settingnode->add_node($annotodashboard, 'coursereports');
    } else {
        // The nodes next to the course report node have no keys,
        // So the target node is calculated as a delta from the closest know key.
        $keys = array_flip($settingnode->get_children_key_list());
        $key = isset($keys['users']) ? $keys['users'] : 0;
        // Insert the new node two places after the users node.
        $settingnode->add_node($annotodashboard, $settingnode->children->get_key_list()[$key + 2]);
    }
}

/**
 * Returns annoto dashboard's lti course module of current course.
 *
 * @return cm_info|null $cm
 */
function local_annoto_get_lti_course_module() {

    global $PAGE;

    $modinfo = get_fast_modinfo($PAGE->course);

    foreach ($modinfo->get_instances_of('lti') as $cm) {

        $domain = $cm->get_icon_url()->get_host();
        if (strpos($domain, 'annoto') !== false) {
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
function local_annoto_create_lti_course_module($lti) {
    global $CFG, $PAGE;

    $context = context_course::instance($PAGE->course->id);
    if (!has_capability('local/annoto:managementdashboard', $context)) {
        return null;
    }

    if (!course_allowed_module($PAGE->course, 'lti')) {
        return null;
    }

    require_once($CFG->dirroot . '/mod/lti/locallib.php');

    $toolconfig = lti_get_type_config($lti->id);

    $newdashboard = new stdClass;
    $newdashboard->modulename = 'lti';
    $newdashboard->name = get_string('lti_activity_name', 'local_annoto');
    $newdashboard->course = $PAGE->course->id;
    $newdashboard->introeditor = [
        'itemid' => 0,
        'format' => FORMAT_PLAIN,
        'text'   => get_string('pluginname', 'local_annoto'),
    ];
    $newdashboard->section = 0;
    $newdashboard->visible = 0;
    $newdashboard->typeid = $lti->id;
    $newdashboard->servicesalt = $toolconfig['servicesalt'];
    $newdashboard->instructorchoicesendname = 1;
    $newdashboard->instructorchoicesendemailaddr = 1;
    $newdashboard->showtitlelaunch = 1;
    $newdashboard->timecreated = time();
    $newdashboard->timemodified = time();

    if (!create_module($newdashboard)) {
        return null;
    }

    return local_annoto_get_lti_course_module();
}

/**
 * creates Annoto LTI type
 *
 * @param string $ltitype LTI type for adding
 * @return integer|null LTI type id or null

 */
function local_annoto_lti_add_type($ltitype) {

    // Get plugin global settings.
    $settings = get_config('local_annoto');

    switch ($ltitype) {
        case 'dashboard':
            $toolname = $settings->toolname ?: TOOLNAME;
            $toolurl = $settings->toolurl ?: TOOLURL;
            $tooliconurl = $settings->tooliconurl ?: TOOLICONURL;
            $description = get_string('annoto_dashboard_description', 'local_annoto');
            $contentitem = 0;
            $gradecontentitem = '';
            $servicegradesynchronization = 0;
            $servicememberships = 0;
            $servicetoolsettings = 0;
            $coursevisible = LTI_COURSEVISIBLE_NO;
            break;
        case 'grade':
            $toolname = $settings->gradetoolname ?: LTIGRADEGNAME;
            $toolurl = $settings->gradetoolurl ?: LTIGRADEGNAME;
            $tooliconurl = $settings->gradetooliconurl ?: LTIGRADEICONURL;
            $description = get_string('annoto_grade_description', 'local_annoto');
            $contentitem = 1;
            $gradecontentitem = $toolurl . LTIGRADECONTENTITEM;
            $servicegradesynchronization = 2; // Use this service for grade sync and column management.
            $servicememberships = 1; // Use this service to retrieve members' information as per privacy settings.
            $servicetoolsettings = 1; // Use this service.
            $coursevisible = LTI_COURSEVISIBLE_ACTIVITYCHOOSER;
            break;
        default:
            return null;
    }

    $settings->toolname = $toolname;
    $settings->toolurl = $toolurl;
    $settings->tooliconurl = $tooliconurl;

    $type = new stdClass;
    $type->name = $toolname;
    $type->baseurl = $toolurl;
    $type->tooldomain = parse_url($toolurl, PHP_URL_HOST);
    $type->state = 1;
    $type->coursevisible = $coursevisible;

    $type->icon = $tooliconurl;
    $type->secureicon = $tooliconurl;
    $type->description = $description;

    $config = new stdClass;
    $config->lti_resourcekey = $settings->clientid;
    $config->lti_password = $settings->ssosecret;
    $config->lti_coursevisible = $coursevisible;
    $config->lti_launchcontainer = LTI_LAUNCH_CONTAINER_EMBED_NO_BLOCKS;

    // Services settings.
    $config->lti_contentitem = $contentitem;
    $config->lti_toolurl_ContentItemSelectionRequest = $gradecontentitem;
    $config->ltiservice_gradesynchronization = $servicegradesynchronization;
    $config->ltiservice_memberships = $servicememberships;
    $config->ltiservice_toolsettings = $servicetoolsettings;

    // Privacy setting.
    $config->lti_forcessl = LTI_SETTING_ALWAYS;
    $config->lti_sendname = LTI_SETTING_ALWAYS;
    $config->lti_sendemailaddr = LTI_SETTING_ALWAYS;
    $config->lti_acceptgrades = LTI_SETTING_ALWAYS;

    return lti_add_type($type, $config);
}
/**
 * update moodle settings by hook
 * @param string $settingname
 *
 */
function local_annoto_update_settings($settingname) {
    global $DB;

    $settings = get_config('local_annoto');
    $updateltitype = [
        's_local_annoto_addingdashboard',
        's_local_annoto_clientid',
        's_local_annoto_ssosecret',
    ];
    $updategradeltitype = [
        's_local_annoto_gradetoggle',
        's_local_annoto_gradetoolurl',
        's_local_annoto_clientid',
        's_local_annoto_ssosecret',
    ];

    // Update dashboard LTI core settings.
    if (in_array($settingname, $updateltitype)) {
        if (!isset($settings->toolname) || !isset($settings->toolurl)) {
            return;
        }
        $lti = lti_get_tool_by_url_match($settings->toolurl);

        if (!$lti) {
            $lti = new stdClass;
            $lti->id = local_annoto_lti_add_type('dashboard');
        }

        $coursevisible = $settings->addingdashboard ? LTI_COURSEVISIBLE_NO : LTI_COURSEVISIBLE_ACTIVITYCHOOSER;
        $lti->coursevisible = $coursevisible;

        $config = new stdClass;
        $config->lti_resourcekey = $settings->clientid ?: '';
        $config->lti_password = $settings->ssosecret ?: '';
        $config->lti_coursevisible = $coursevisible;

        lti_update_type($lti, $config);
    }

    // Update grade LTI core settings.
    if (in_array($settingname, $updategradeltitype)) {
        if (!isset($settings->gradetoolname) || !isset($settings->gradetoolname)) {
            return;
        }
        $lti = lti_get_tool_by_url_match($settings->gradetoolurl);

        if (!$lti) {
            $lti = new stdClass;
            $lti->id = local_annoto_lti_add_type('grade');
        }

        $coursevisible = $settings->gradetoggle ? LTI_COURSEVISIBLE_ACTIVITYCHOOSER : LTI_COURSEVISIBLE_NO;
        $toolurl = $settings->gradetoolurl ?: LTIGRADEGNAME;
        $gradecontentitem = $toolurl . LTIGRADECONTENTITEM;

        $lti->coursevisible = $coursevisible;

        $config = new stdClass;
        $config->lti_forcessl = LTI_SETTING_ALWAYS;
        $config->lti_resourcekey = $settings->clientid ?: '';
        $config->lti_password = $settings->ssosecret ?: '';
        $config->lti_coursevisible = $coursevisible;
        $config->lti_toolurl_ContentItemSelectionRequest = $gradecontentitem;

        lti_update_type($lti, $config);
    }

    // Update media details.
    if (!isset($settings->mediasettingsoverride) || !$settings->mediasettingsoverride) {
        return;
    }

    $defaultwidth = $DB->get_record('config', ['name' => 'media_default_width']);
    $defaultwidth->value = $settings->defaultwidth ?? DEFAULTWIDTH;
    $DB->update_record('config', $defaultwidth);

    $defaultheight = $DB->get_record('config', ['name' => 'media_default_height']);
    $defaultheight->value = $settings->defaultheight ?? DEFAULTHEIGHT;
    $DB->update_record('config', $defaultheight);

    purge_caches();
}

/**
 * get all Annoto dashboard's roles with default archetypes
 *
 * @return array
 */
function local_annoto_get_all_dashboard_roles() {
    $capabilitiy = 'local/annoto:managementdashboard';
    $choices = $defaultchoices = [];

    if ($roles = get_roles_with_capability($capabilitiy, CAP_ALLOW)) {
        $choices = role_fix_names($roles, null, ROLENAME_ORIGINAL, true);

        foreach ($choices as $key => $val) {
            $defaultchoices[$key] = 1;
        }
    }

    return [$choices, $defaultchoices];
}

/**
 * Provide log into console browser
 * @param string $log the id of the course.
 * @return void
 */
function local_annoto_set_jslog($log = '') {
    global $PAGE;
    global $CFG;
    $themename = $PAGE->theme->get_theme_name();
    $pluginmanager = core_plugin_manager::instance();
    $plugininfo = $pluginmanager->get_plugin_info('local_annoto');
    $version = $plugininfo->versiondb;
    $release = $plugininfo->release;

    $jscode = "(function () {
        console.dir('AnnotoBackend: Moodle version " . $CFG->release . "');
        console.dir('AnnotoBackend: Plugin version " . $release . ' - ' . $version . "');
        console.dir('AnnotoBackend: theme " . $themename . "');
        console.dir('AnnotoBackend: " . $log . "');
    }());";
    $PAGE->requires->js_amd_inline($jscode);
}

/**
 * Inject the annoto completion elements into moodle module settings forms.
 *
 * @param moodleform_mod $formwrapper The moodle quickforms wrapper object.
 * @param MoodleQuickForm $mform The actual form object (required to modify the form).
 *
 * Note: original completion form is located in moodle/completion/classes/form/form_trait.php
 * The extension is triggered by standard_coursemodule_elements() function in moodle/course/moodleform_mod.php
 */
function local_annoto_coursemodule_standard_elements($formwrapper, $mform) {
    log::debug('local_annoto_coursemodule_standard_elements');

    $settings = get_config('local_annoto');

    $supportedmodtypes = [
        'page',
        'label',
        'h5p',
        'hvp',
        'h5pactivity',
        'kalvidres',
        'lti',
    ];

    // phpcs:ignore Squiz.PHP.CommentedOutCode.Found
    /* $completionel = 'completion';
    $conditionsgroupel = 'conditionsgroup';

    $nnautomatic = $mform->createElement(
        'radio',
        $completionel,
        '',
        'Annoto completion',
        9,
        ['class' => 'left-indented']
    );
    $mform->insertElementBefore($nnautomatic, $conditionsgroupel);

    log::debug('coursemodule_standard_elements: ' . print_r($mform->getElement($conditionsgroupel), true)); */

    $modulename = $formwrapper->get_current()->modulename;
    if (!$settings->activitycompletion || !in_array($modulename, $supportedmodtypes)) {
        return;
    }

    /*
     *  tooldomain - parsed from lti_toolurl of the plugin that launched page
     *  deploymentdomain, customdomain - domain recieved from the local
     *  annoto plagin integration
     *  domain
     */
    if ($modulename === 'lti') {
        $typeid = $mform->getElementValue('typeid');
        $lticonfig = lti_get_type_config($typeid);
        $tooldomain = $lticonfig['tooldomain'];
        $deploymentdomain = $settings->deploymentdomain;
        if ($deploymentdomain === 'custom') {
            $deploymentdomain = $settings->customdomain;
        }

        if (empty($deploymentdomain) || !isset($deploymentdomain)) {
            $deploymentdomain = 'annoto.net';
        }
        if (strpos($tooldomain, $deploymentdomain) === false) {
            return;
        }
    }

    $mform->addElement('header', 'annotocompletion', get_string('annotocompletion', 'local_annoto'));

    $cmid = null;
    $cm = $formwrapper->get_coursemodule();
    $completionoptions = (object) [
        'enabled' => annoto_completion::COMPLETION_TRACKING_NONE,
        'totalview' => 0,
        'comments' => 0,
        'replies' => 0,
    ];
    if (!empty($cm) && isset($cm)) {
        $cmid = $cm->id;
        if ($completionsettings = annoto_completion::get_record(['cmid' => $cmid])) {
            $completionoptions = $completionsettings->to_record();
        }
    }

    $completionenabledel = 'annotocompletionenabled';
    $completionview = 'annotocompletionview';
    $completioncomments = 'annotocompletioncomments';
    $completionreplies = 'annotocompletionreplies';
    // phpcs:ignore Squiz.PHP.CommentedOutCode.Found
    // FIXME: $completionexpected = 'annotocompletionexpected';.

    $completionmenu = annoto_completion::get_enabled_menu();
    $mform->addElement('select', $completionenabledel, get_string('completionenabled', 'local_annoto'), $completionmenu);
    $mform->setDefault($completionenabledel, $completionoptions->enabled);
    $mform->disabledIf($completionenabledel, 'completion', 'noteq', 0);
    $mform->disabledIf('completion', $completionenabledel, 'noteq', annoto_completion::COMPLETION_TRACKING_NONE);

    $addgroup = function ($groupname, $value, $valuerules, $info) use ($mform, $completionenabledel) {
        $enabledel = 'enabled';
        $valueel = 'value';
        $getvalueingroup = function ($groupname) use ($valueel) {
            return $groupname . '[' . $valueel . ']';
        };

        $getenabledingroup = function ($groupname) use ($enabledel) {
            return $groupname . '[' . $enabledel . ']';
        };

        $group = [$mform->createElement('advcheckbox', $enabledel)];
        if (isset($info) && $info->prefix) {
            $group[] = &$mform->createElement('static', 'prefix', null, get_string($groupname . 'prefix', 'local_annoto'));
        }
        $group[] = &$mform->createElement('text', $valueel);
        if (isset($info) && $info->suffix) {
            $group[] = &$mform->createElement('static', 'suffix', null, get_string($groupname . 'suffix', 'local_annoto'));
        }

        $mform->addGroup($group, $groupname, get_string($groupname, 'local_annoto'));
        $mform->disabledIf($getvalueingroup($groupname), $getenabledingroup($groupname), 'notchecked');
        $mform->setType($getvalueingroup($groupname), PARAM_INT);
        $mform->setDefault($getvalueingroup($groupname), $value);
        $mform->setDefault($getenabledingroup($groupname), $value > 0);
        if (isset($valurules)) {
            $grouprule = [];
            $grouprule[$valueel] = $valuerules;
            $mform->addGroupRule($groupname, $grouprule);
        }
        $mform->hideIf($groupname, $completionenabledel, 'ne', annoto_completion::COMPLETION_TRACKING_AUTOMATIC);
    };

    $addgroup(
        $completionview,
        $completionoptions->totalview,
        [
            [get_string('numericrule', 'local_annoto'), 'numeric'],
        ],
        (object) [
            'prefix' => true,
            'suffix' => true,
        ]
    );
    $addgroup(
        $completioncomments,
        $completionoptions->comments,
        [
            [get_string('numericrule', 'local_annoto'), 'numeric'],
        ],
        (object) [
            'prefix' => true,
            'suffix' => false,
        ]
    );
    $addgroup(
        $completionreplies,
        $completionoptions->replies,
        [
            [get_string('numericrule', 'local_annoto'), 'numeric'],
        ],
        (object) [
            'prefix' => true,
            'suffix' => false,
        ]
    );
    // phpcs:ignore Squiz.PHP.CommentedOutCode.Found 
    // FIXME: $mform->addElement('date_time_selector', $completionexpected,
    // get_string($completionexpected, 'local_annoto'), ['optional' => true]);.
}

/**
 * Lock completion form if annotocompletion is used.
 * 
 * The update_moduleinfo() at moodle/course/modlib.php calls reset_all_state() if completion is unlocked.
 * The reset can cause all users completion state to be set to invalid "completed" state for following sequence of events:
 * 1. New mod created with completion not setup.
 * 2. Edit mod settings and setup Annoto completion.
 * 3. Save mod settings. (if completion is unlocked, reset_all_state() will be called and set all users to completed)
 *
 * @param moodleform_mod $formwrapper The moodle quickforms wrapper object.
 * @param MoodleQuickForm $mform The actual form object (required to modify the form).
 */
function local_annoto_coursemodule_definition_after_data($formwrapper, $mform) {
    log::debug('local_annoto_coursemodule_definition_after_data');
    $settings = get_config('local_annoto');
    $completionenabledel = 'annotocompletionenabled';

    if (
        $settings->activitycompletion &&
        $mform->elementExists($completionenabledel) &&
        $mform->elementExists('completionunlocked')
    ) {
        $enval = $mform->getElementValue($completionenabledel);
        $isEnabled = is_array($enval) ?
            in_array(annoto_completion::COMPLETION_TRACKING_AUTOMATIC, $enval) :
            $enval == annoto_completion::COMPLETION_TRACKING_AUTOMATIC;

        if (!$isEnabled) {
            return;
        }
        
        // Lock completion form, so that reset_all_state() is not called. (this is currently not working)
        $mform->getElement('completionunlocked')->setValue(0);
    }
}

/**
 * Hook to process data from submitted form of coursemodule
 *
 * @param stdClass $data moduleinfo data from the form submission.
 * @param stdClass $course The course.
 * @return stdClass updated moduleinfo
 *
 * See plugin_extend_coursemodule_edit_post_actions in
 * https://github.com/moodle/moodle/blob/master/course/modlib.php
 *
 */
function local_annoto_coursemodule_edit_post_actions($data, $course) {
    global $DB;
    $settings = get_config('local_annoto');
    $cmid = $data->coursemodule;

    log::debug('local_annoto_coursemodule_edit_post_actions');

    if (isset($data->annotocompletionenabled) && $settings->activitycompletion) {
        $completionenabled = $data->annotocompletionenabled;
        $completionview = $data->annotocompletionview;
        $completioncomments = $data->annotocompletioncomments;
        $completionreplies = $data->annotocompletionreplies;
        // phpcs:ignore Squiz.PHP.CommentedOutCode.Found 
        // FIXME: $completionexpected = $data->annotocompletionexpected;.
        $completionrecord = new stdClass();
        $completionrecord->courseid = $course->id;
        $completionrecord->cmid = $cmid;
        $completionrecord->enabled  = $completionenabled;
        $completionrecord->totalview  = $completionview['enabled'] ? $completionview['value'] : 0;
        $completionrecord->comments  = $completioncomments['enabled'] ? $completioncomments['value'] : 0;
        $completionrecord->replies  = $completionreplies['enabled'] ? $completionreplies['value'] : 0;
        // phpcs:ignore Squiz.PHP.CommentedOutCode.Found 
        // FIXME: $completiondata->completionexpected  = $completionexpected;.

        log::debug('coursemodule_edit_post_actions - completiondata: ' . json_encode($completionrecord));

        if (!$record = annoto_completion::get_record(['cmid' => $cmid])) {
            $record = new annoto_completion(0, $completionrecord);
            $record->create();
        } else {
            $record->from_record($completionrecord);
            $record->update();
        }

        $completiontracking = $data->completion;
        if ($completionenabled == annoto_completion::COMPLETION_TRACKING_AUTOMATIC) {
            $completiontracking = annoto_completion::COMPLETION_TRACKING_AUTOMATIC;
        }
        $cm = $DB->get_record('course_modules', ['id' => $cmid]);

        if (!$cm) {
            return $data;
        }

        if ($completionenabled == annoto_completion::COMPLETION_TRACKING_AUTOMATIC) {
            // Fix to prevent reset_all_state() from being called in moodle/course/modlib.php @see local_annoto_coursemodule_definition_after_data
            unset($data->completionunlocked);
            $completion = new completion_info($course);
            $completion->delete_all_state($cm);
        }

        // FIXME: need to cleanup this override if $settings->activitycompletion changes to false for all the courses.
        $cm->completion = $completiontracking;
        $DB->update_record('course_modules', $cm);

        // See edit_module_post_actions() in moodle/course/modlib.php
        // coursemodule_edit_post_actions is called after edit_module_post_actions() clears the cache
        // because we change the cm we need to clear it again.
        if (method_exists(\course_modinfo::class, 'purge_course_module_cache')) {
            log::debug('coursemodule_edit_post_actions - purge_course_module_cache and partial rebuild_course_cache v4');
            // Moodle v4 and up.
            \course_modinfo::purge_course_module_cache($cm->course, $cmid);
            rebuild_course_cache($cm->course, true, true);
        } else {
            // Moodle v3.
            log::debug('coursemodule_edit_post_actions - rebuild_course_cache v3');
            rebuild_course_cache($cm->course, true);
        }
    }

    return $data;
}
