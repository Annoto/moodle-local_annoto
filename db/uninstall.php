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
 * Plugin uninstall steps.
 *
 * @package    local_annoto
 * @copyright  Annoto Ltd.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Report the removal to Annoto's telemetry, best effort.
 *
 * Moodle runs this before it drops the plugin's tables and config, so the telemetry
 * opt-out setting is still readable here. A failed send never blocks the uninstall.
 *
 * @return bool always true
 */
function xmldb_local_annoto_uninstall() {
    try {
        \local_annoto\telemetry::send_uninstall();
    } catch (\Throwable $e) {
        debugging('local_annoto: uninstall telemetry failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
    }
    return true;
}
