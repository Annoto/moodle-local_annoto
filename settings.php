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

if ($hassiteconfig) {

    require_once($CFG->dirroot. '/local/annoto/classes/admin_setting_custompickroles.php');
    require_once($CFG->dirroot . '/local/annoto/lib.php');

    $pluginmanager = core_plugin_manager::instance();
    $plugininfo = $pluginmanager->get_plugin_info('local_annoto');
    $version = $plugininfo->versiondb;
    $release = $plugininfo->release;
    $name = new lang_string('pluginname', 'local_annoto').' (rel. '.$release.' ver. '.$version.')';

    $settings = new admin_settingpage('local_annoto', $name);
    $ADMIN->add('localplugins', $settings);

    /* Annoto setup. */
    $settings->add(new admin_setting_heading(
        'local_annoto/setupheading',
        new lang_string('setupheading', 'local_annoto'),
        ''
    ));

    // API key / clientID.
    $settings->add(new admin_setting_configtext(
        'local_annoto/clientid',
        new lang_string('clientid', 'local_annoto'),
        new lang_string('clientiddesc', 'local_annoto'),
        null
    ));

    // SSO Secret.
    $settings->add(new admin_setting_configtext(
        'local_annoto/ssosecret',
        new lang_string('ssosecret', 'local_annoto'),
        new lang_string('ssosecretdesc', 'local_annoto'),
        null
    ));

    // Annoto script url.
    $settings->add(new admin_setting_configtext(
        'local_annoto/scripturl',
        new lang_string('scripturl', 'local_annoto'),
        new lang_string('scripturldesc', 'local_annoto'),
        'https://cdn.annoto.net/widget/latest/bootstrap.js'
    ));

    // Deployment domain.
    $settings->add(new admin_setting_configselect(
        'local_annoto/deploymentdomain',
        new lang_string('deploymentdomain', 'local_annoto'),
        new lang_string('deploymentdomaindesc', 'local_annoto'),
        EUREGION,
        [
            EUREGION => new lang_string('eurregion', 'local_annoto'),
            USREGION => new lang_string('usregion', 'local_annoto'),
            CUSTOM => new lang_string('custom', 'local_annoto'),
        ]
    ));

    $settings->add(new admin_setting_configtext(
        'local_annoto/customdomain',
        new lang_string('customdomain', 'local_annoto'),
        new lang_string('customdomaindesc', 'local_annoto'),
        null
    ));
    $settings->hide_if('local_annoto/customdomain', 'local_annoto/deploymentdomain', 'neq', CUSTOM);


    /* Annoto dashboard (LTI) */
    $settings->add(new admin_setting_heading(
        'local_annoto/externaltoolsettings',
        new lang_string('externaltoolsettings', 'local_annoto'),
        ''
    ));

    // Enable auto add dashboard to navigation.
    $settings->add(new admin_setting_configcheckbox(
        'local_annoto/addingdashboard',
        new lang_string('addingdashboard', 'local_annoto'),
        new lang_string('addingdashboard_desc', 'local_annoto'),
        0
    ));

    // Dashboard access roles.
    list($roles, $defaultroles) = local_annoto_get_all_dashboard_roles();
    $settings->add(new admin_setting_configmulticheckbox(
        'local_annoto/managementdashboard',
        new lang_string('managementdashboard', 'local_annoto'),
        new lang_string('managementdashboard_desc', 'local_annoto'),
        $defaultroles,
        $roles
    ));

    /* Annoto settings */
    $settings->add(new admin_setting_heading(
        'local_annoto/appsetingsheading',
        new lang_string('appsetingsheading', 'local_annoto'),
        ''
    ));

    // Locale.
    $settings->add(new admin_setting_configcheckbox(
        'local_annoto/locale',
        new lang_string('locale', 'local_annoto'),
        new lang_string('locale_desc', 'local_annoto'),
        1
    ));

    // Moderators Roles.
    $settings->add(new local_annoto_admin_setting_custompickroles(
        'local_annoto/moderatorroles',
        new lang_string('moderatorroles', 'local_annoto'),
        new lang_string('moderatorrolesdesc', 'local_annoto'),
        [
            'manager',
            'editingteacher',
        ]
    ));

    /* Media player settings */
    $settings->add(new admin_setting_heading(
        'local_annoto/mediaplayersettingheading',
        new lang_string('media_player_setting',
        'local_annoto'),
        ''
    ));

    $settings->add(new admin_setting_configselect(
        'local_annoto/mediasettingsoverride',
        new lang_string('mediasettingsoverride', 'local_annoto'),
        new lang_string('mediasettingsoverridedesc', 'local_annoto'),
        1,
        [
            0 => new lang_string('no'),
            1 => new lang_string('yes'),
        ]
    ));

    // Default player width.
    $settings->add(new admin_setting_configtext(
        'local_annoto/defaultwidth',
        new lang_string('defaultwidth', 'local_annoto'),
        new lang_string('defaultwidthdesc', 'local_annoto'),
        DEFAULTWIDTH
    ));
    $settings->hide_if('local_annoto/defaultwidth', 'local_annoto/mediasettingsoverride', 'neq', 1);

    // Default player height.
    $settings->add(new admin_setting_configtext(
        'local_annoto/defaultheight',
        new lang_string('defaultheight', 'local_annoto'),
        new lang_string('defaultheightdesc', 'local_annoto'),
        DEFAULTHEIGHT
    ));
    $settings->hide_if('local_annoto/defaultheight', 'local_annoto/mediasettingsoverride', 'neq', 1);



    // Activity completion.
    $settings->add(new admin_setting_heading(
        'local_annoto/activitycompletionheading',
        new lang_string('activitycompletion_settings', 'local_annoto'),
        ''
    ));
    $settings->add(new admin_setting_configselect(
        'local_annoto/activitycompletion',
        new lang_string('activitycompletion_enable', 'local_annoto'),
        new lang_string('activitycompletion_enabledesc', 'local_annoto'),
        0,
        [
            0 => new lang_string('no'),
            1 => new lang_string('yes'),
        ]
    ));
}
