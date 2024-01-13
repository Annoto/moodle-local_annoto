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

require_once($CFG->libdir . "/externallib.php");
require_once($CFG->dirroot . "/local/annoto/lib.php");

use \local_annoto\annoto_completion;
use \local_annoto\annoto_completiondata;

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
     * @return array result of submittion
     * @throws \core\invalid_persistent_exception
     * @throws coding_exception
     * @throws dml_exception
     * @throws invalid_parameter_exception
     * @throws moodle_exception
     */
    public static function set_completion($jsondata) {
        global $DB,$CFG, $USER;
        require_once($CFG->libdir . "/completionlib.php");
        $params = self::validate_parameters(self::set_completion_parameters(),
            array(
                'data' => $jsondata,
            )
        );

        $data = json_decode($jsondata);
        $status = false;
        $message = 'Completion not defined';

        if (isset($data->cmid) && !empty($data->cmid)) {
            list($course, $cm) = get_course_and_cm_from_cmid($data->cmid);
            $enrolled = static::get_enrolled_userids($course->id);

            if (in_array($USER->id, $enrolled)) {
                $record = annoto_completion::get_record(['cmid' => $data->cmid]);
                if ($record !== false && $record->get('enabled') == annoto_completion::COMPLETION_TRACKING_AUTOMATIC) {
                    $status = true;
                    if ($completiondata = annoto_completion::get_record(['completionid' => $record->get('id'), 'userid' => $USER->id])) {
                        $jsondata2 = json_encode($data);
                        $completiondata->set('data', $jsondata2);
                        $completiondata->update();
                        $message = "Update completion for user {$USER->id} modid {$data->cmid} completion {$data->completion}";
                    } else {
                        $record = [
                            'userid' => $USER->id,
                            'completionid' => $record->get('id'),
                            'data' => $jsondata
                        ];
                        $completiondata = new annoto_completiondata(0, (object) $record);
                        $completiondata->create();
                        $message = "Set completion for user {$USER->id} modid {$data->cmid} completion {$data->completion}";
                    }
                }
            }
        }

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

    /**
     * Returns array of user ids enrolled into this course with gradebook roles
     * @param $courseid
     * @return array
     * @throws coding_exception
     * @throws dml_exception
     */
    public static function get_enrolled_userids($courseid) {
        global $DB, $CFG;

        $context = \context_course::instance($courseid);

        list($gradebookroles_sql, $params) = $DB->get_in_or_equal(explode(',', $CFG->gradebookroles), SQL_PARAMS_NAMED, 'grbr');
        $params['contextid'] = $context->id;
        $sql = "SELECT DISTINCT ra.userid FROM {role_assignments} ra WHERE ra.roleid $gradebookroles_sql AND contextid = :contextid";

        return $DB->get_fieldset_sql($sql, $params);
    }
}
