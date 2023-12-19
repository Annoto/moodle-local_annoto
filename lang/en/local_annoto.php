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
 * Strings for component 'local_annoto', language 'en'.
 *
 * @package    local_annoto
 * @copyright  Annoto Ltd.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['pluginname'] = 'Annoto';

// Capabilities
$string['annoto:moderatediscussion'] = 'Moderate discussions in Annoto';
$string['annoto:managementdashboard'] = 'Access Annoto dashboard';

// Annoto Setup.
$string['setupheading'] = 'Annoto setup';
$string['clientid'] = 'API key';
$string['clientiddesc'] = 'ClientID is provided by Annoto (keep in secret)';
$string['ssosecret'] = 'SSO secret';
$string['ssosecretdesc'] = 'SSO secret is provided by Annoto (keep in secret)';
$string['scripturl'] = 'Annoto\'s script URL';
$string['scripturldesc'] = 'Provide Annoto\'s script URL here';
$string['deploymentdomain'] = 'Deployment domain';
$string['deploymentdomaindesc'] = 'Provide the region for the widgets. Please note that the widget data is bound to a specific region.';
$string['customdomain'] = 'Custom deployment domain';
$string['customdomaindesc'] = 'Specify a custom deployment domain. Please note that the widget data is bound to a specific region.';
$string['eurregion'] = 'EU region';
$string['usregion'] = 'US region';
$string['custom'] = 'Custom';

// Annoto dashboard (LTI)
$string['addingdashboard'] = 'Add to all the courses';
$string['addingdashboard_desc'] = 'If enabled, Annoto dashboard will be automatically added to all the courses. If disabled the Annoto dashboard can be added manually from the “+ Add an activity or resource” on the desired course';
$string['externaltoolsettings'] = 'Annoto dashboard (LTI)';
$string['toolurl'] = 'URL';
$string['toolurldesc'] = 'External tool URL';
$string['tooliconurl'] = 'Icon';
$string['tooliconurldesc'] = 'External tool icon';
$string['toolname'] = 'Name';
$string['toolnamedesc'] = 'External tool name';
$string['annoto_dashboard_description'] = "The Annoto Dashboard was designed in order to shed light upon learners' video consumption and behavior within Annoto in-video collaboration widget, providing you with analytics and insights regarding their activity in the course.";
$string['lti_activity_name'] = 'Annoto Dashboard';
$string['managementdashboard'] = 'Dashboard manager roles';
$string['managementdashboard_desc'] = 'Specify who is allowed to access Annoto dashboard';

// Annoto grading (LTI)
$string['gradetoggle'] = 'Enable annoto assignment';
$string['gradetoggle_desc'] = 'If disabled, Annoto assignment is not available from activity chooser.';
$string['externalgradetoolsettings'] = 'Annoto assignment (LTI)';
$string['gradetoolname'] = 'Name';
$string['gradetoolnamedesc'] = 'External grade tool name';
$string['gradetoolurl'] = 'URL';
$string['gradetoolurldesc'] = 'External grade tool URL';
$string['gradetooliconurl'] = 'Icon';
$string['gradetooliconurldesc'] = 'External grade tool icon';
$string['annoto_grade_description'] = 'Annoto grading LTI';

// Annoto settings.
$string['appsetingsheading'] = 'Annoto settings';
$string['locale'] = 'Locale';
$string['locale_desc'] = 'If enable will set per page and course based on Course and User preferences';
$string['moderatorroles'] = 'Moderator roles';
$string['moderatorrolesdesc'] = 'Specify who is allowed to moderate discussions (only roles that at least include the following capabilities are available: local/annoto:moderatediscussion).';

// Media player settings
$string['media_player_setting'] = 'Media Player Setting';
$string['mediasettingsoverride'] = 'Override Moodle media settings';
$string['mediasettingsoverridedesc'] = 'Enable overriding the Moodle Media Player settings';
$string['defaultwidth'] = 'Media width';
$string['defaultwidthdesc'] = 'Media player width if a width is not specified and the actual media file width cannot be determined by the player';
$string['defaultheight'] = 'Media height';
$string['defaultheightdesc'] = 'Media player height if a height is not specified and the actual media file height cannot be determined by the player';

// Privacy API.
$string['privacy:metadata:annoto'] = 'In order to integrate with a remote service, user data needs to be exchanged with that service.';
$string['privacy:metadata:annoto:userid'] = 'The userid is sent from Moodle to allow you to access your data on the remote system.';
$string['privacy:metadata:annoto:fullname'] = 'Your full name is sent to the remote system to allow a better user experience.';
$string['privacy:metadata:annoto:email'] = 'Your e-mail name is sent to the remote system to allow a better user experience.';

