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
                array()
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
     * @return array
     */
    public static function get_jsparams() {
        return json_encode(local_annoto_get_jsparams());
    }

}
