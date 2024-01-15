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
 * External interface library for customfields component
 *
 * @package   local_annoto
 * @copyright Annoto Ltd.
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die;

require_once($CFG->libdir . '/externallib.php');
require_once(__DIR__ . '/lib.php');
require_once(__DIR__ . '/classes/completion.php');
require_once(__DIR__ . '/classes/completiondata.php');
require_once(__DIR__ . '/classes/log.php');

use \local_annoto\annoto_completion;
use \local_annoto\annoto_completiondata;
use \local_annoto\log;

/**
 * Class local_annoto_external
 *
 * @copyright Annoto Ltd.
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class local_annoto_external extends external_api {

    /**
     * Returns description of method parameters
     * @return external_function_parameters
     */
    public static function get_jsparams_parameters() {
        return new external_function_parameters(
                array(
                  'courseid' => new external_value(PARAM_INT, 'Course id', VALUE_DEFAULT, null),
                  'modid' => new external_value(PARAM_INT, 'Mod id', VALUE_DEFAULT, 0)
                )
        );
    }

    /**
     * Returns description of method result value
     * @return external_value
     */
    public static function get_jsparams_returns() {
        //return new external_value(PARAM_TEXT, 'json jsparams');
        return new external_single_structure([
            'result' => new external_value(PARAM_BOOL, 'True if the params was successfully sended'),
            'params'    => new external_value(PARAM_TEXT, 'json jsparams'),
        ]);
    }

    /**
     * Get parameters for Anooto's JS script
     * @param int $courseid the id of the course.
     * @param int $modid mod id.
     * @return array
     */
    public static function get_jsparams($courseid, $modid) {
        global $USER;
        $params = self::validate_parameters(
            self::get_jsparams_parameters(),
            array(
                'courseid' => $courseid,
                'modid' => $modid
            )
        );
        $context = context_course::instance($courseid);
        self::validate_context(context_course::instance($courseid));

        list($result, $response) = !is_guest($context) ? [true, local_annoto_get_jsparam($courseid, $modid)] : [false, null];

        return ['result' => $result, 'params' => json_encode($response, JSON_HEX_TAG)];
    }


    /**
     * Returns description of method parameters
     * @return external_function_parameters
     */
    public static function set_completion_parameters() {
        return new external_function_parameters(
            array(
                'data' => new external_value(PARAM_RAW, 'JSON encoded data'),
            )
        );
    }

    /**
     * @param $jsondata
     * @return array result of set_completion
     */
    public static function set_completion($jsondata) {
        global $USER;
        $settings = get_config('local_annoto');
        
        self::validate_parameters(self::set_completion_parameters(),
            array(
                'data' => $jsondata,
            )
        );

        $data = json_decode($jsondata);
        $status = false;
        $message = 'user completion not saved';
        $userid = $USER->id;

        if (!$settings->activitycompletion) {
            $message = 'activity completion is disabled';
            log::info('set_completion - ' . $message);
            
            return ['status' => $status, 'message' => $message];
        }

        if (isset($data->cmid) && !empty($data->cmid)) {
            $cmid = $data->cmid;
            $cleandata = new stdClass();
            $cleandata->completion = isset($data->completion) ? $data->completion : 0;
            $cleandata->comments = isset($data->comments) ? $data->comments : 0;
            $cleandata->replies = isset($data->replies) ? $data->replies : 0;
            $cleandata->heatmap = isset($data->heatmap) ? $data->heatmap : null;
            $cleandata->watch_time = isset($data->watch_time) ? $data->watch_time : null;
            $cleandata->media_src = isset($data->media_src) ? $data->media_src : null;
            $cleandata->session_id = isset($data->session_id) ? $data->session_id : null;
            $cleandata->group_id = isset($data->group_id) ? $data->group_id : null;
            $cleandata->sso_id = isset($data->sso_id) ? $data->sso_id : null;
            $cleandata->widget_index = isset($data->widget_index) ? $data->widget_index : null;

            list($course, $cm) = get_course_and_cm_from_cmid($cmid);
            $context = \context_course::instance($course->id);

            if (is_enrolled($context, $USER, '', true)) {
                $completionrecord = annoto_completion::get_record(['cmid' => $cmid]);
                if ($completionrecord && $completionrecord->get('enabled') === annoto_completion::COMPLETION_TRACKING_AUTOMATIC) {
                    $completionid = $completionrecord->get('id');
                    
                    if ($completiondata = annoto_completiondata::get_record(['completionid' => $completionid, 'userid' => $userid])) {
                        $completiondata->set('data', json_encode($cleandata));
                        $completiondata->update();
                        $message = 'Updated completion for user '. $userid . ' cmid ' . $cmid;
                    } else {
                        $record = (object) [
                            'userid' => $userid,
                            'completionid' => $completionid,
                            'data' => json_encode($cleandata),
                        ];
                        $completiondata = new annoto_completiondata(0, $record);
                        $completiondata->create();
                        $message = 'Set completion for user ' . $userid . ' cmid ' . $cmid;
                    }
                    $status = true;
                }
            }
        }

        log::debug('set_completion - ' . $message . ($status ? ' data ' . print_r($cleandata, true) : ''));
        
        return ['status' => $status, 'message' => $message];
    }

    /**
     * Returns description of method result value
     * @return external_description
     */
    public static function set_completion_returns() {
        return new external_single_structure([
            'status' => new external_value(PARAM_BOOL, 'The processing result'),
            'message' => new external_value(PARAM_TEXT, 'Message'),
        ]);
    }
}
