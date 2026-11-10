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
 * Print, validate or send one usage telemetry event, for testing the telemetry on a site.
 *
 * Examples:
 *   php local/annoto/cli/telemetry.php --event=annoto_heartbeat
 *   php local/annoto/cli/telemetry.php --event=annoto_heartbeat --validate
 *   php local/annoto/cli/telemetry.php --event=annoto_heartbeat --send
 *
 * --send ignores the weekly throttle but still respects the opt-out setting; it does not move
 * the heartbeat schedule. To see sends in GA4 DebugView, first run
 *   php admin/cli/cfg.php --component=local_annoto --name=telemetry_debugmode --set=1
 *
 * @package    local_annoto
 * @copyright  Annoto Ltd.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');

use local_annoto\telemetry;

[$options, $unrecognised] = cli_get_params([
    'event' => 'annoto_heartbeat',
    'validate' => false,
    'send' => false,
    'help' => false,
], ['h' => 'help']);

$events = ['annoto_install', 'annoto_heartbeat', 'annoto_environment', 'annoto_config_change',
    'annoto_uninstall', 'annoto_error'];

if ($unrecognised || $options['help'] || !in_array($options['event'], $events, true)) {
    cli_writeln("Print, validate or send one Annoto telemetry event.

Options:
--event=NAME   One of: " . implode(', ', $events) . " (default annoto_heartbeat)
--validate     Post to the GA4 validation endpoint and print its messages (records nothing)
--send         Send the event to GA4 (respects the opt-out setting)
-h, --help     Print this help");
    exit($unrecognised ? 1 : 0);
}

$extra = $options['event'] === 'annoto_error'
    ? ['error_area' => 'client', 'error_code' => 'cli_test', 'error_count' => 1]
    : [];
$payload = telemetry::build_payload($options['event'], $extra);
$count = count($payload['events'][0]['params']);

cli_writeln(json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
cli_writeln("Event parameters: {$count} (GA4 limit " . telemetry::MAX_EVENT_PARAMS . ')');

if ($options['validate']) {
    $res = telemetry::post($payload, true);
    cli_writeln("Validation: http={$res['httpcode']} errno={$res['errno']} {$res['error']}");
    cli_writeln($res['body']);
}

if ($options['send']) {
    $sent = telemetry::send_event($options['event'], $extra);
    cli_writeln($sent ? 'Sent.' : 'Not sent: telemetry is off, not configured, or the request failed.');
    exit($sent ? 0 : 1);
}
