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
 * @copyright  Annoto Ltd.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

 namespace local_annoto;

 defined('MOODLE_INTERNAL') || die();

use core\persistent;
use \local_annoto\annoto_completiondata;

class annoto_completion extends persistent
{
    const TABLE = 'local_annoto_completion';
    const COMPLETION_TRACKING_NONE = COMPLETION_TRACKING_NONE;
    // see moodle/lib/completionlib.php definitions of COMPLETION_TRACKING_AUTOMATIC we need it to be higher than any other value
    const COMPLETION_TRACKING_AUTOMATIC = 9;

    static function get_enabled_menu() {
        return [
            static::COMPLETION_TRACKING_NONE => get_string('completion_none', 'completion'),
            static::COMPLETION_TRACKING_AUTOMATIC => get_string('completion_automatic', 'completion'),
        ];
    }

    /**
     * Return the list of properties.
     *
     * @return array
     */
    protected static function define_properties()
    {
        return[
            'courseid' => array(
                'type' => PARAM_INT,
                'default' => 0,
            ),
            'cmid' => array(
                'type' => PARAM_INT,
            ),
            'enabled' => array(
                'type' => PARAM_INT,
            ),
            'totalview' => array(
                'type' => PARAM_INT,
            ),
            'comments' => array(
                'type' => PARAM_INT,
            ),
            'replies' => array(
                'type' => PARAM_INT,
            ),
            'completionexpected' => array(
                'type' => PARAM_INT,
                'default' => null,
                'null' => NULL_ALLOWED,
            ),
        ];
    }

    public function after_delete($result)
    {
        // TODO: oprimize this
        if ($records = annoto_completiondata::get_records(['completionid' => $this->raw_get('id')])) {
            foreach ($records as $record) {
                $record->delete();
            }
        }
    }
}


