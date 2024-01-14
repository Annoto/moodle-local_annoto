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
 * Event observers supported by this module
 *
 * @package    local
 * @subpackage annoto
 * @copyright 2024 annoto.net
 * @author  Genadi sokolov
 * @license  http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

 namespace local_annoto;

 defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/completion.php');
require_once(__DIR__ . '/completiondata.php');

use \local_annoto\annoto_completion;
use \local_annoto\annoto_completiondata;

class observer {

    /**
     * Observer for \core\event\course_module_deleted event.
     *
     * @param \core\event\course_module_deleted $event
     * @return void
     */
    public static function course_module_deleted(\core\event\course_module_deleted $event) {
        if ($records = annoto_completion::get_records(['cmid' => $event->objectid])) {
            foreach ($records as $record) {
                $record->delete();
            }
        }
    }

    /**
     * Triggered via user_enrolment_deleted event.
     *
     * @param \core\event\user_enrolment_deleted $event
     * @return bool true on success.
     */
    public static function user_enrolment_deleted(\core\event\user_enrolment_deleted $event) {
        $comprecords = annoto_completion::get_records(['courseid' => $event->courseid]);

        // TODO: optimize this
        foreach ($comprecords as $record) {
            foreach (annoto_completiondata::get_records(['completionid' => $record->get('id'), 'userid' => $event->relateduserid]) as $data) {
                $data->delete();
            }
        }

        return true;
    }
}
