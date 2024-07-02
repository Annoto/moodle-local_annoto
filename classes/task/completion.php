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
 * Scheduled task for managing Annoto completion data.
 *
 * @package    local_annoto
 * @subpackage annoto
 * @copyright  Annoto Ltd.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_annoto\task;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/completionlib.php');
require_once(__DIR__ . '/../completion.php');
require_once(__DIR__ . '/../completiondata.php');

use local_annoto\annoto_completion;
use local_annoto\annoto_completiondata;

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
     * @return void
     */
    public function execute() {
        global $CFG;
        $settings = get_config('local_annoto');
        mtrace(
            'AnnotoCompletionTask: Running annoto completion task, activitycompletion enabled: : ' . $settings->activitycompletion
        );
        if (!$settings->activitycompletion) {
            return;
        }

        // FIXME: add logic for cleaning up in case
        // $settings->activitycompletion is set to false or true. what should we do in this case?
        // FIXME: implementing update completed state on
        // extenal call of set_completion. and make this task to run less frequently.

        $activecompletionrecords = annoto_completion::get_records(['enabled' => annoto_completion::COMPLETION_TRACKING_AUTOMATIC]);
        mtrace('AnnotoCompletionTask: Found ' . count($activecompletionrecords) . ' active completion records');

        foreach ($activecompletionrecords as $record) {
            $cmid = $record->get('cmid');
            if (empty($cmid) || $cmid <= 0) {
                continue;
            }

            try {
                list($course, $cm) = get_course_and_cm_from_cmid($cmid);
            } catch (\Exception $e) {
                mtrace('AnnotoCompletionTask: Error fetching course and cm for cmid: ' . $cmid . ' - ' . $e->getMessage());
                continue;
            }
            $completion = new \completion_info($course);
            $completiondatarecords = annoto_completiondata::get_records(['completionid' => $record->get('id')]);
            // Moodle v3 do not have clean_param and returns type string.
            $totalview = (int)$record->get('totalview');
            $comments = (int)$record->get('comments');
            $replies = (int)$record->get('replies');

            // TRACE: mtrace('AnnotoCompletionTask: Found ' . count($completiondatarecords) .
            // ' completion data records for cmid: ' . $cmid);.

            foreach ($completiondatarecords as $completiondata) {
                $userid = $completiondata->get('userid');
                $currentdata = $completion->get_data($cm, 0, $userid);
                $completionstate = $currentdata->completionstate;
                $useractivity = json_decode($completiondata->get('data'));

                // Determine if user has completed the activity
                // Activity completion is enalbed, but no completion requirements set.
                // Mark as completed to prevent blocking of other activities.
                $emptycompletionrequirement = $totalview <= 0 && $comments <= 0 && $replies <= 0;

                $totalviewcompleted = $totalview == 0 ||
                    (isset($useractivity->completion) && $totalview <= $useractivity->completion);
                $commentscompleted = $comments == 0 ||
                    (isset($useractivity->comments) && $comments <= $useractivity->comments);
                $repliescompleted = $replies == 0 ||
                    (isset($useractivity->replies) && $replies <= $useractivity->replies);

                $completed = $emptycompletionrequirement || ($totalviewcompleted && $commentscompleted && $repliescompleted);

                // TRACE: mtrace('AnnotoCompletionTask: User ' . $userid . ' completionstate: '  .
                // $completionstate . ' completed: ' . var_export($completed, true) . ' totalview: ' .
                // var_export($totalviewcompleted, true) . ' comments: ' . var_export($commentscompleted, true) .
                // ' replies: ' . var_export($repliescompleted, true));.

                if ($completed && $completionstate <= COMPLETION_INCOMPLETE) {
                    // TRACE: mtrace('AnnotoCompletionTask: Updating completion state for user '
                    // . $userid . ' to COMPLETION_COMPLETE');.
                    $completion->update_state($cm, COMPLETION_COMPLETE, $userid);
                } else if (!$completed && $completionstate > COMPLETION_INCOMPLETE) {
                    // TRACE: mtrace('AnnotoCompletionTask: Updating completion
                    // state for user ' . $userid . ' to COMPLETION_INCOMPLETE');.
                    // Need to set override param to true, otherwise completion will not be updated.
                    $completion->update_state($cm, COMPLETION_INCOMPLETE, $userid, true);
                }
            }
        }
    }
}
