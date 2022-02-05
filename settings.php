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

if (!defined('USREGION')) define('USREGION', 'us.annoto.net');
if (!defined('EUREGION')) define('EUREGION', 'annoto.net');
if (!defined('CUSTOM')) define('CUSTOM', 'custom');

if ($hassiteconfig) {

    require_once($CFG->dirroot. '/local/annoto/classes/admin_setting_custompickroles.php');
    require_once($CFG->dirroot . '/local/annoto/lib.php');

    $pluginmanager = core_plugin_manager::instance();
    $plugininfo = $pluginmanager->get_plugin_info('local_annoto');
    $version = $plugininfo->versiondb;
    $name = get_string('pluginname', 'local_annoto').' (v. '.$version. ')';

    $settings = new admin_settingpage('local_annoto',$name);
    $ADMIN->add('localplugins', $settings);

    /* Annoto setup. */
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

    // Deployment domain.
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


    /* Annoto dashboard (LTI) */
    $settings->add(new admin_setting_heading('local_annoto/externaltoolsettings', get_string('externaltoolsettings', 'local_annoto'), ''));

    // LTI name.
    $settings->add(new admin_setting_configtext('local_annoto/toolname', get_string('toolname', 'local_annoto'),
        get_string('toolnamedesc', 'local_annoto'), 'Annoto Dashboard'));

    // LTI url.
    $settings->add(new admin_setting_configtext('local_annoto/toolurl', get_string('toolurl', 'local_annoto'),
        get_string('toolurldesc', 'local_annoto'), 'https://auth.annoto.net/lti/course-insights'));

    // LTI icon url.
    $settings->add(new admin_setting_configtext('local_annoto/tooliconurl', get_string('tooliconurl', 'local_annoto'),
        get_string('tooliconurldesc', 'local_annoto'), 'https://assets.annoto.net/images/logo_icon.png'));

    // Auto launchig.

    $setting = new admin_setting_configcheckbox('local_annoto/addingdashboard',
        get_string('addingdashboard', 'local_annoto'), get_string('addingdashboard_desc', 'local_annoto'), 1);
    $setting->set_updatedcallback('local_annoto_update_lti_type');
    $settings->add($setting);
    
    /* Annoto settings */
    $settings->add(new admin_setting_heading('local_annoto/appsetingsheading', get_string('appsetingsheading', 'local_annoto'),
        ''));

    // Locale.
    $settings->add(new admin_setting_configselect('local_annoto/locale', get_string('locale', 'local_annoto'),
        get_string('localedesc', 'local_annoto'), 'auto', array(  'auto' => get_string('localeauto', 'local_annoto'),
                                                                'en' => get_string('localeen', 'local_annoto'),
                                                                'he' => get_string('localehe', 'local_annoto'))));

    // Moderators Roles.
    $settings->add(new local_annoto_admin_setting_custompickroles('local_annoto/moderatorroles',
        get_string('moderatorroles', 'local_annoto'),
        get_string('moderatorrolesdesc', 'local_annoto'),
        array(
            'manager',
            'editingteacher',
        )));

}
