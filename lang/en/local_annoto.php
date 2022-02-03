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

$string['filtername'] = 'Annoto';
$string['pluginname'] = 'Annoto';
$string['annoto:moderatediscussion'] = 'Moderate discussions in Annoto';

// Application Setup.
$string['setupheading'] = 'Annoto setup';
$string['clientid'] = 'API key';
$string['clientiddesc'] = 'ClientID is provided by Annoto (keep in secret)';
$string['ssosecret'] = 'SSO secret';
$string['ssosecretdesc'] = 'SSO secret is provided by Annoto (keep in secret)';
$string['scripturl'] = 'Annoto\'s script URL';
$string['scripturldesc'] = 'Provide Annoto\'s script URL here';

$string['deploymentdomain'] = 'Deployment domain';
$string['deploymentdomaindesc'] = 'Provide the region for the widgets. Please note that the widget data is bound to a specific region.';
$string['eurregion'] = 'EU region';
$string['usregion'] = 'US region';

$string['customdomain'] = 'Custom deployment domain';
$string['customdomaindesc'] = 'Specify a custom deployment domain. Please note that the widget data is bound to a specific region.';
$string['custom'] = 'Custom';

// Application settings.
$string['appsetingsheading'] = 'Annoto settings';
$string['cta'] = 'Call to action';
$string['ctadesc'] = 'Toggle this if you want to use call to actions';
$string['locale'] = 'Locale';
$string['localedesc'] = 'Choose language (Auto will set per page and course based on Course and User preferences)';
$string['localeauto'] = 'Auto detect';
$string['localeen'] = 'English';
$string['localehe'] = 'Hebrew';
$string['moderatorroles'] = 'Moderator roles';
$string['moderatorrolesdesc'] = 'Specify who is allowed to moderate discussions (only roles that at least include the following capabilities are available: local/annoto:moderatediscussion).';
$string['addingdashboard'] = 'Add Annoto dashboard';
$string['addingdashboard_desc'] = 'Add Annoto dashboard automatically to each course';

// UX preferences.
$string['appuxheading'] = 'Annoto UX Preferences';
$string['positionright'] = 'Right';
$string['positionleft'] = 'Left';
$string['positiontopright'] = 'Top right';
$string['positiontopleft'] = 'Top left';
$string['positionbottomright'] = 'Bottom right';
$string['positionbottomleft'] = 'Bottom left';
$string['overlayauto'] = 'Auto';
$string['overlayinner'] = 'On top of player';
$string['overlayouter'] = 'Next to player';


// LTI
$string['externaltoolsettings'] = 'Annoto dashboard (LTI)';
$string['toolurl'] = 'External tool URL';
$string['toolurldesc'] = 'Provide external tool URL';
$string['tooliconurl'] = 'External tool icon';
$string['tooliconurldesc'] = 'External tool icon URL';
$string['toolname'] = 'External tool name';
$string['toolnamedesc'] = 'External tool name';
$string['annoto_dashboard:view'] = 'Annoto dashboard view';
$string['section:media'] = 'media';
$string['section:video'] = 'video';
$string['annoto_dashboard_description'] = 'Annoto’s dashboard was designed in order to shed a light upon students’ behavior within Annoto in-video collaboration widget, providing you with data regarding their engagement with the video.';
$string['lti_activity_name'] = 'Annoto Dashboard';


// Privacy API.
$string['privacy:metadata:annoto'] = 'In order to integrate with a remote service, user data needs to be exchanged with that service.';
$string['privacy:metadata:annoto:userid'] = 'The userid is sent from Moodle to allow you to access your data on the remote system.';
$string['privacy:metadata:annoto:fullname'] = 'Your full name is sent to the remote system to allow a better user experience.';
$string['privacy:metadata:annoto:email'] = 'Your e-mail name is sent to the remote system to allow a better user experience.';

