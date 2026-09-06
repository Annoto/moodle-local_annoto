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
 * Scheduled task that sends Annoto usage telemetry.
 *
 * @package    local_annoto
 * @subpackage annoto
 * @copyright  Annoto Ltd.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_annoto\task;

defined('MOODLE_INTERNAL') || die();

/**
 * The local_annoto usage telemetry task.
 *
 * Runs daily but only actually sends at most once per week (throttled inside
 * \local_annoto\telemetry::maybe_send_heartbeat()).
 *
 * @package    local_annoto
 * @copyright  Annoto Ltd.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class telemetry extends \core\task\scheduled_task {
    /**
     * Return localised task name.
     *
     * @return string
     */
    public function get_name() {
        return get_string('telemetrytask', 'local_annoto');
    }

    /**
     * Execute scheduled task.
     *
     * @return void
     */
    public function execute() {
        if (!\local_annoto\telemetry::is_enabled()) {
            mtrace('AnnotoTelemetryTask: telemetry disabled by admin, skipping.');
            return;
        }
        mtrace('AnnotoTelemetryTask: checking whether a heartbeat is due.');
        \local_annoto\telemetry::maybe_send_heartbeat();
        mtrace('AnnotoTelemetryTask: done.');
    }
}
