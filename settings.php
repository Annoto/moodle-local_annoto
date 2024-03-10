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
    $name = get_string('pluginname', 'local_annoto').' (rel. '.$release.' ver. '.$version.')';

    $settings = new admin_settingpage('local_annoto', $name);
    $ADMIN->add('localplugins', $settings);

    /* Annoto setup. */
    $settings->add(new admin_setting_heading('local_annoto/setupheading', get_string('setupheading', 'local_annoto'), ''));

    // API key / clientID.
    $setting = new admin_setting_configtext('local_annoto/clientid', get_string('clientid', 'local_annoto'),
        get_string('clientiddesc', 'local_annoto'), null);
    $setting->set_updatedcallback('local_annoto_update_settings');
    $settings->add($setting);

    // SSO Secret.
    $setting = new admin_setting_configtext('local_annoto/ssosecret', get_string('ssosecret', 'local_annoto'),
        get_string('ssosecretdesc', 'local_annoto'), null);
    $setting->set_updatedcallback('local_annoto_update_settings');
    $settings->add($setting);

    // Annoto script url.
    $settings->add(new admin_setting_configtext('local_annoto/scripturl', get_string('scripturl', 'local_annoto'),
        get_string('scripturldesc', 'local_annoto'), 'https://cdn.annoto.net/widget/latest/bootstrap.js'));

    // Deployment domain.
    $settings->add(new admin_setting_configselect('local_annoto/deploymentdomain', get_string('deploymentdomain', 'local_annoto'),
        get_string('deploymentdomaindesc', 'local_annoto'), EUREGION,
            [
                EUREGION => get_string('eurregion', 'local_annoto'),
                USREGION => get_string('usregion', 'local_annoto'),
                CUSTOM => get_string('custom', 'local_annoto'),
            ]
        )
    );

    $settings->add(new admin_setting_configtext('local_annoto/customdomain', get_string('customdomain', 'local_annoto'),
        get_string('customdomaindesc', 'local_annoto'), null));
    $settings->hide_if('local_annoto/customdomain', 'local_annoto/deploymentdomain', 'neq', CUSTOM);


    /* Annoto dashboard (LTI) */
    $settings->add(new admin_setting_heading('local_annoto/externaltoolsettings',
        get_string('externaltoolsettings', 'local_annoto'), ''));

    // Auto launching.
    $setting = new admin_setting_configcheckbox('local_annoto/addingdashboard',
        get_string('addingdashboard', 'local_annoto'), get_string('addingdashboard_desc', 'local_annoto'), 0);
    $setting->set_updatedcallback('local_annoto_update_settings');
    $settings->add($setting);

    // LTI name.
    $settings->add(new admin_setting_configtext('local_annoto/toolname', get_string('toolname', 'local_annoto'),
        get_string('toolnamedesc', 'local_annoto'), TOOLNAME));

    // LTI url.
    $settings->add(new admin_setting_configtext('local_annoto/toolurl', get_string('toolurl', 'local_annoto'),
        get_string('toolurldesc', 'local_annoto'), TOOLURL));

    // LTI icon url.
    $settings->add(new admin_setting_configtext('local_annoto/tooliconurl', get_string('tooliconurl', 'local_annoto'),
        get_string('tooliconurldesc', 'local_annoto'), TOOLICONURL));

    // Management dashboard.
    list($roles, $defaultroles) = local_annoto_get_all_dashboard_roles();
    $settings->add(new admin_setting_configmulticheckbox('local_annoto/managementdashboard',
        get_string('managementdashboard', 'local_annoto'),
        get_string('managementdashboard_desc', 'local_annoto'),
        $defaultroles,
        $roles
    ));

    /* Annoto assignment (LTI) */
    $settings->add(new admin_setting_heading('local_annoto/externalgradetoolsettings',
        get_string('externalgradetoolsettings', 'local_annoto'), ''));

    // Enable / disable lti grading.
    $setting = new admin_setting_configcheckbox('local_annoto/gradetoggle',
        get_string('gradetoggle', 'local_annoto'), get_string('gradetoggle_desc', 'local_annoto'), 1);
    $setting->set_updatedcallback('local_annoto_update_settings');
    $settings->add($setting);

    // LTI grade name.
    $settings->add(new admin_setting_configtext('local_annoto/gradetoolname', get_string('gradetoolname', 'local_annoto'),
        get_string('gradetoolnamedesc', 'local_annoto'), LTIGRADEGNAME));

    // LTI grade url.
    $setting = new admin_setting_configtext('local_annoto/gradetoolurl', get_string('gradetoolurl', 'local_annoto'),
        get_string('gradetoolurldesc', 'local_annoto'), LTIGRADEURL);
    $setting->set_updatedcallback('local_annoto_update_settings');
    $settings->add($setting);

    // LTI grade icon url.
    $settings->add(new admin_setting_configtext('local_annoto/gradetooliconurl', get_string('gradetooliconurl', 'local_annoto'),
        get_string('gradetooliconurldesc', 'local_annoto'), LTIGRADEICONURL));

    /* Annoto settings */
    $settings->add(new admin_setting_heading('local_annoto/appsetingsheading', get_string('appsetingsheading', 'local_annoto'),
        ''));

    // Locale.
    $settings->add(new admin_setting_configcheckbox('local_annoto/locale',
        get_string('locale', 'local_annoto'), get_string('locale_desc', 'local_annoto'), 1));


    // Moderators Roles.
    $settings->add(new local_annoto_admin_setting_custompickroles('local_annoto/moderatorroles',
        get_string('moderatorroles', 'local_annoto'),
        get_string('moderatorrolesdesc', 'local_annoto'),
        [
            'manager',
            'editingteacher',
        ]));

    /* Annoto settings */
    $settings->add(new admin_setting_heading('local_annoto/mediaplayersettingheading',
        get_string('media_player_setting', 'local_annoto'), ''));

    $setting = new admin_setting_configselect('local_annoto/mediasettingsoverride',
        get_string('mediasettingsoverride', 'local_annoto'),
        get_string('mediasettingsoverridedesc', 'local_annoto'), 1, [
            0 => get_string('no'),
            1 => get_string('yes')]);
    $setting->set_updatedcallback('local_annoto_update_settings');
    $settings->add($setting);

    // Annoto default width.
    $setting = new admin_setting_configtext('local_annoto/defaultwidth', get_string('defaultwidth', 'local_annoto'),
        get_string('defaultwidthdesc', 'local_annoto'), DEFAULTWIDTH);
    $setting->set_updatedcallback('local_annoto_update_settings');
    $settings->add($setting);
    $settings->hide_if('local_annoto/defaultwidth', 'local_annoto/mediasettingsoverride', 'neq', 1);

    // Annoto default height.
    $setting = new admin_setting_configtext('local_annoto/defaultheight', get_string('defaultheight', 'local_annoto'),
        get_string('defaultheightdesc', 'local_annoto'), DEFAULTHEIGHT);
    $setting->set_updatedcallback('local_annoto_update_settings');
    $settings->add($setting);
    $settings->hide_if('local_annoto/defaultheight', 'local_annoto/mediasettingsoverride', 'neq', 1);



    // Activity completion.
    $settings->add(new admin_setting_heading('local_annoto/activitycompletionheading',
        get_string('activitycompletion_settings', 'local_annoto'), ''));
    $settings->add(new admin_setting_configselect('local_annoto/activitycompletion',
        get_string('activitycompletion_enable', 'local_annoto'),
        get_string('activitycompletion_enabledesc', 'local_annoto'), 0, [
            0 => get_string('no'),
            1 => get_string('yes')]
        ));
}
