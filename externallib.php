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
     * Returns result
     * @return result
     */
    public static function get_jsparams_returns() {
        return new external_value(PARAM_TEXT, 'json jsparams');
    }

    /**
     * Get parameters for Anooto's JS script
     * @param int $courseid the id of the course.
     * @param int $modid mod id.
     * @return array
     */
    public static function get_jsparams($courseid, $modid) {
        global $PAGE;
        $params = self::validate_parameters(
            self::get_jsparams_parameters(),
            array(
                'courseid' => $courseid,
                'modid' => $modid
            )
        );

        $context = context_course::instance($courseid);
        $capabilities = 'moodle/search:query'; // Checking permission to prevent unauthorized access.
        $response = '';
        if (has_capability($capabilities, $context)) {
            self::validate_context($context);
            $response = json_encode(local_annoto_get_jsparam($courseid, $modid), JSON_HEX_TAG);
        } else {
            $response = json_encode(false);
        }

        return $response;
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
     * @return string result of submittion
     * @throws \core\invalid_persistent_exception
     * @throws coding_exception
     * @throws dml_exception
     * @throws invalid_parameter_exception
     * @throws moodle_exception
     */
    public static function set_completion($jsondata) {
        global $DB, $CFG, $USER;
        require_once($CFG->libdir . "/completionlib.php");
        $params = self::validate_parameters(self::set_completion_parameters(),
            array(
                'data' => $jsondata,
            )
        );

        $data = json_decode($jsondata);

        if (isset($data->cmid) && !empty($data->cmid)) {
            list($course, $cm) = get_course_and_cm_from_cmid($data->cmid);
            $enrolled = static::get_enrolled_userids($course->id);

            if (in_array($USER->id, $enrolled)) {
                $record = \local_annoto\completion::get_record(['cmid' => $data->cmid]);
                if ($record !== false && $record->get('enabled') == \local_annoto\completion::COMPLETION_TRACKING_AUTOMATIC) {
                    if ($completiondata = \local_annoto\completiondata::get_record(['completionid' => $record->get('id'), 'userid' => $USER->id])) {
                        $completiondata->set('data', $jsondata);
                        $completiondata->update();
                    } else {
                        $record = [
                            'userid' => $USER->id,
                            'completionid' => $record->get('id'),
                            'data' => $jsondata
                        ];
                        $completiondata = new \local_annoto\completiondata(0, (object) $record);
                        $completiondata->create();
                    }
                }
            }

        }

        return true;
    }

    /**
     * Returns description of method result value
     * @return external_description
     */
    public static function set_completion_returns() {
        return new external_value(PARAM_BOOL, 'Return status');
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
