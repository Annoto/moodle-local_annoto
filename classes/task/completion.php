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
 * @package    local
 * @subpackage annoto
 * @copyright  2021 Devlion.co
 * @author  Evgeniy Voevodin
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

namespace local_annoto\task;

use \local_annoto\annoto_completion;
use \local_annoto\annoto_completiondata;

/**
 * The local_annoto cache task class.
 *
 * @package    local_annoto
 * @copyright  2021 Devlion <info@devlion.co>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class completion extends \core\task\scheduled_task {

    /**
     * Return localised task name.
     *
     * @return string
     */
    public function get_name() {
        return get_string('completiontask', 'local_annoto');
    }

    /**
     * Execute scheduled task
     *
     * @return boolean
     */
    public function execute() {

        global $CFG, $DB;
        require_once($CFG->libdir . "/completionlib.php");

        // TODO: add logic for cleaning up in case $settings->activitycompletion is set to false or true. what should we do in this case?
        $records = annoto_completion::get_records(['enabled' => annoto_completion::COMPLETION_TRACKING_AUTOMATIC]);
        foreach ($records as $record) {
            if ($record->get('cmid') > 0) {
                list($course, $cm) = get_course_and_cm_from_cmid($record->get('cmid'));
                $completion = new \completion_info($course);
                foreach ($completiondatas = annoto_completiondata::get_records(['completionid' => $record->get('id')]) as $completiondata) {
                    $currentdata = $completion->get_data($cm, 0, $completiondata->get('userid'));
                    if (!$currentdata->completionstate) {
                        $data = json_decode($completiondata->get('data'));
                        $completed = true;
                        if($record->get('totalview') <= 0 && $record->get('comments') <= 0 && $record->get('replies') <= 0){
                            $completed = false;
                        }
                        if ($record->get('totalview') > 0 && $completed) {
                            $completed = $record->get('totalview') <= $data->completion;
                        }
                        if ($record->get('comments') > 0 && $completed) {
                            $completed = $record->get('comments') <= $data->comments;
                        }
                        if ($record->get('replies') > 0 && $completed) {
                            $completed = $record->get('replies') <= $data->replies;
                        }
                        if ($completed) {
                            $completion->update_state($cm, COMPLETION_COMPLETE, $completiondata->get('userid'));
                        }
                    }
                }
            }
        }
    }
}
