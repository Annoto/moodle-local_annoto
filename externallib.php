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

}
