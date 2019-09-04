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
                  'pageurl' => new external_value(PARAM_URL, 'Page URL', VALUE_DEFAULT, null),
                  'modid' => new external_value(PARAM_INT, 'Mod id', VALUE_OPTIONAL)
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
     * @param string $pageurl url of the current page.
     * @param int $modid mod id.
     * @return array
     */
    public static function get_jsparams($courseid, $pageurl, $modid) {
        global $PAGE;
        $params = self::validate_parameters(self::get_jsparams_parameters(),
                          array(
                              'courseid' => $courseid,
                              'pageurl' => $pageurl,
                              'modid' => $modid
                          ));

        $context = context_course::instance($courseid);
        self::validate_context($context);
        return json_encode(get_jsparam($courseid, $pageurl, $modid), JSON_HEX_TAG);
    }

}
