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

// phpcs:disable moodle.Files.LangFilesOrdering.UnexpectedComment
// phpcs:disable moodle.Files.LangFilesOrdering.IncorrectOrder

$string['pluginname'] = 'Annoto';

// Capabilities.
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

// Annoto dashboard (LTI).
$string['addingdashboard'] = 'Add to all the courses';
$string['addingdashboard_desc'] = <<<EOD
If enabled, Annoto dashboard will be automatically added to all the courses.
If disabled the Annoto dashboard can be added manually from the “+ Add an activity or resource” on the desired course'\n
Note: Annoto Dashboard LTI external tool must be configured in the Site administration.
EOD;
$string['externaltoolsettings'] = 'Annoto dashboard (LTI)';
$string['lti_activity_name'] = 'Annoto Dashboard';
$string['managementdashboard'] = 'Dashboard manager roles';
$string['managementdashboard_desc'] = 'Specify who is allowed to access Annoto dashboard';

// Annoto settings.
$string['appsetingsheading'] = 'Annoto settings';
$string['locale'] = 'Locale';
$string['locale_desc'] = 'If enable will set per page and course based on Course and User preferences';
$string['moderatorroles'] = 'Moderator roles';
$string['moderatorrolesdesc'] = 'Specify who is allowed to moderate discussions (only roles that at least include the following capabilities are available: local/annoto:moderatediscussion).';
$string['debuglogging'] = 'Enable debug logging';
$string['debuglogging_desc'] = 'If enabled, debug-level log messages from the Annoto plugin will be output. This is useful for troubleshooting and development, but should be disabled in production.';

// Media player settings.
$string['media_player_setting'] = 'Media Player Setting';
$string['mediasettingsoverride'] = 'Override Moodle media settings';
$string['mediasettingsoverridedesc'] = 'Enable overriding the Moodle Media Player settings';
$string['defaultwidth'] = 'Media width';
$string['defaultwidthdesc'] = 'Media player width if a width is not specified and the actual media file width cannot be determined by the player';
$string['defaultheight'] = 'Media height';
$string['defaultheightdesc'] = 'Media player height if a height is not specified and the actual media file height cannot be determined by the player';

// Activities completion.

$string['activitycompletion_settings'] = 'Activity completion (Beta)';
$string['activitycompletion_enable'] = 'Enable Annoto activity completion';
$string['activitycompletion_enabledesc'] = 'If enabled, Annoto activity completion will be available in page, label, Annoto LTI, h5p, hvp and Kaltura activity settings';

$string['annotocompletion'] = 'Annoto completion conditions';
$string['completiontask'] = 'Annoto Completion Task';
$string['numericrule'] = 'This field should be numeric';

$string['completionenabled'] = 'Completion tracking';
$string['completionenableddesc'] = 'Select whether annoto completion should be enabled by default for new activities';

$string['annotocompletionview'] = 'Require video completion';
$string['annotocompletionviewhelp'] = 'How much of a video duration should be viewed by student to consider this activity completed (coverage)';
$string['annotocompletionviewprefix'] = 'Minimal percent of video (coverage) that must be watched by the learner:';
$string['annotocompletionviewsuffix'] = '%';

$string['annotocompletioncomments'] = 'Require comments';
$string['annotocompletioncommentshelp'] = 'Minimal amount of comments (including replies) that should be posted by the learner to consider this activity completed';
$string['annotocompletioncommentsprefix'] = 'Minimal number of comments the leaner must post: ';

$string['annotocompletionreplies'] = 'Require replies';
$string['annotocompletionreplieshelp'] = 'Minimal amount of replies to comments that should be posted by the learner to consider this activity completed';
$string['annotocompletionrepliesprefix'] = 'Minimal number of replies the learner must post: ';

$string['annotocompletionexpected'] = 'Set reminder in Timeline';
$string['annotocompletionexpectedhelp'] = 'Set reminder for student to work on this activity';


// Privacy API.
$string['privacy:metadata:annoto'] = 'In order to integrate with a remote service, user data needs to be exchanged with that service.';
$string['privacy:metadata:annoto:userid'] = 'The userid is sent from Moodle to allow you to access your data on the remote system.';
$string['privacy:metadata:annoto:fullname'] = 'Your full name is sent to the remote system to allow a better user experience.';
$string['privacy:metadata:annoto:email'] = 'Your e-mail name is sent to the remote system to allow a better user experience.';
