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
 * Settings.
 *
 * @package    local_annoto
 * @copyright  Annoto Ltd.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

require_once($CFG->dirroot. '/local/annoto/classes/admin_setting_custompickroles.php');

if (!defined('USREGION')) define('USREGION', 'us.annoto.net');
if (!defined('EUREGION')) define('EUREGION', 'annoto.net');
if (!defined('CUSTOM')) define('CUSTOM', 'custom');

if ($hassiteconfig) {

    //$version = get_config('local_annoto');

    $pluginmanager = core_plugin_manager::instance();
    $plugininfo = $pluginmanager->get_plugin_info('local_annoto');
    $version = $plugininfo->versiondb;
    $name = get_string('pluginname', 'local_annoto').' (v. '.$version. ')';

    $settings = new admin_settingpage('local_annoto',$name);
    $ADMIN->add('localplugins', $settings);

    /* Application setup. */
    $settings->add(new admin_setting_heading('local_annoto/setupheading', get_string('setupheading', 'local_annoto'), ''));

    // API key / clientID.
    $settings->add(new admin_setting_configtext('local_annoto/clientid', get_string('clientid', 'local_annoto'),
        get_string('clientiddesc', 'local_annoto'), null));

    // SSO Secret.
    $settings->add(new admin_setting_configtext('local_annoto/ssosecret', get_string('ssosecret', 'local_annoto'),
        get_string('ssosecretdesc', 'local_annoto'), null));

    // Annoto script url.
    $settings->add(new admin_setting_configtext('local_annoto/scripturl', get_string('scripturl', 'local_annoto'),
        get_string('scripturldesc', 'local_annoto'), 'https://app.annoto.net/annoto-bootstrap.js'));

    // deploymentDomain.
    $settings->add(new admin_setting_configselect('local_annoto/deploymentdomain', get_string('deploymentdomain', 'local_annoto'),
        get_string('deploymentdomaindesc', 'local_annoto'), EUREGION,
            array(
                EUREGION => get_string('eurregion', 'local_annoto'),
                USREGION => get_string('usregion', 'local_annoto'),
                CUSTOM => get_string('custom', 'local_annoto'),
            )
        )
    );

    $settings->add(new admin_setting_configtext('local_annoto/customdomain', get_string('customdomain', 'local_annoto'),
        get_string('customdomaindesc', 'local_annoto'), null));
    $settings->hide_if('local_annoto/customdomain', 'local_annoto/deploymentdomain', 'neq', CUSTOM);


    // Demo checkbox.
    $settings->add(new admin_setting_configcheckbox('local_annoto/demomode', get_string('demomode', 'local_annoto'),
        get_string('demomodedesc', 'local_annoto'), 'true', 'true', 'false'));

    /* External tool (LTI) settings. */
    $settings->add(new admin_setting_heading('local_annoto/externaltoolsettings', get_string('externaltoolsettings', 'local_annoto'), ''));

    // LTI name.
    $settings->add(new admin_setting_configtext('local_annoto/toolname', get_string('toolname', 'local_annoto'),
        get_string('toolnamedesc', 'local_annoto'), 'AnnotoDashboard'));

    // LTI url.
    $settings->add(new admin_setting_configtext('local_annoto/toolurl', get_string('toolurl', 'local_annoto'),
        get_string('toolurldesc', 'local_annoto'), 'https://auth.annoto.net/lti/course-insights'));

    // LTI icon url.
    $settings->add(new admin_setting_configtext('local_annoto/tooliconurl', get_string('tooliconurl', 'local_annoto'),
        get_string('tooliconurldesc', 'local_annoto'), 'https://assets.annoto.net/images/logo_icon.png'));



    $settings->add(new admin_setting_heading('local_annoto/appsetingsheading', get_string('appsetingsheading', 'local_annoto'),
        ''));

    // Locale.
    $settings->add(new admin_setting_configselect('local_annoto/locale', get_string('locale', 'local_annoto'),
        get_string('localedesc', 'local_annoto'), 'auto', array(  'auto' => get_string('localeauto', 'local_annoto'),
                                                                'en' => get_string('localeen', 'local_annoto'),
                                                                'he' => get_string('localehe', 'local_annoto'))));

    // Discussions Scope.
    $settings->add(new admin_setting_configselect('local_annoto/discussionscope',
        get_string('discussionscope', 'local_annoto'),
        get_string('discussionscopedesc', 'local_annoto'),
        'true',
        array('false' => get_string('discussionscopesitewide', 'local_annoto'),
                'true' => get_string('discussionscopeprivate', 'local_annoto'))));

    // Moderators Roles.
    $settings->add(new local_annoto_admin_setting_custompickroles('local_annoto/moderatorroles',
        get_string('moderatorroles', 'local_annoto'),
        get_string('moderatorrolesdesc', 'local_annoto'),
        array(
            'manager',
            'editingteacher',
        )));

    /* UX preferences. */
    $settings->add(new admin_setting_heading('local_annoto/appuxheading', get_string('appuxheading', 'local_annoto'), ''));

    // Widget position.
    $settings->add(new admin_setting_configselect('local_annoto/widgetposition',
        get_string('widgetposition', 'local_annoto'),
        get_string('widgetpositiondesc', 'local_annoto'),
        'topright',
        array('right' => get_string('positionright', 'local_annoto'),
                'left' => get_string('positionleft', 'local_annoto'),
                'topright' => get_string('positiontopright', 'local_annoto'),
                'topleft' => get_string('positiontopleft', 'local_annoto'),
                'bottomright' => get_string('positionbottomright', 'local_annoto'),
                'bottomleft' => get_string('positionbottomleft', 'local_annoto'))));
    // Widget overlay mode.
    $settings->add(new admin_setting_configselect('local_annoto/widgetoverlay',
        get_string('widgetoverlay', 'local_annoto'),
        get_string('widgetoverlaydesc', 'local_annoto'),
        'auto',
        array('auto' => get_string('overlayauto', 'local_annoto'),
                'inner' => get_string('overlayinner', 'local_annoto'),
                'outer' => get_string('overlayouter', 'local_annoto'))));
    // Tabs.
    $settings->add(new admin_setting_configcheckbox('local_annoto/tabs', get_string('tabs', 'local_annoto'),
        get_string('tabsdesc', 'local_annoto'), 'true', 'true', 'false'));

    // Annoto zindex.
    $settings->add(new admin_setting_configtext('local_annoto/zindex', get_string('zindex', 'local_annoto'),
        get_string('zindexdesc', 'local_annoto'), 100, PARAM_INT));

    // Initial State.
    $settings->add(new admin_setting_configcheckbox('local_annoto/openonload', get_string('openonload', 'local_annoto'),
        get_string('openonloaddesc', 'local_annoto'), 1));

    /* UX Wistia player preferences. */
    $settings->add(new admin_setting_heading('local_annoto/appuxheadingwistia', get_string('appuxheadingwistia', 'local_annoto'), ''));

    // sidePanelLayout.
    $settings->add(new admin_setting_configcheckbox('local_annoto/sidepanellayout', get_string('sidepanellayout', 'local_annoto'),
        get_string('sidepanellayoutdesc', 'local_annoto'), 1));

    // sidePanelFullScreen.
    $settings->add(new admin_setting_configcheckbox('local_annoto/sidepanelfullscreen', get_string('sidepanelfullscreen', 'local_annoto'),
        get_string('sidepanelfullscreendesc', 'local_annoto'), 0));

    /* ACL and scope. */
    $settings->add(new admin_setting_heading('local_annoto/aclheading', get_string('aclheading', 'local_annoto'), ''));

    // Global Scope.
    $settings->add(new admin_setting_configcheckbox('local_annoto/scope', get_string('scope', 'local_annoto'),
        get_string('scopedesc', 'local_annoto'), 'false', 'true', 'false'));

    // URL ACL.
    $settings->add(new admin_setting_configtextarea('local_annoto/acl', get_string('acl', 'local_annoto'),
        get_string('acldesc', 'local_annoto'), null));
}
