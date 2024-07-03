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
 * Annoto completion class.
 *
 * @package    local_annoto
 * @subpackage annoto
 * @copyright  Annoto Ltd.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_annoto;

use core\persistent;
use local_annoto\annoto_completiondata;
use lang_string; 

/**
 * Class representing the Annoto completion.
 *
 * @package    local_annoto
 * @subpackage annoto
 */
class annoto_completion extends persistent {
    /**
     * Table name for this persistent class.
     */
    const TABLE = 'local_annoto_completion';

    /**
     * Completion tracking none constant.
     */
    const COMPLETION_TRACKING_NONE = COMPLETION_TRACKING_NONE;

    /**
     * Completion tracking automatic constant.
     * See moodle/lib/completionlib.php definitions of COMPLETION_TRACKING_AUTOMATIC we need it to be higher than any other value.
     */
    const COMPLETION_TRACKING_AUTOMATIC = 9;

    /**
     * Get enabled menu for completion tracking.
     *
     * @return array
     */
    public static function get_enabled_menu() {
        return [
            static::COMPLETION_TRACKING_NONE => new lang_string('completion_none', 'completion'),
            static::COMPLETION_TRACKING_AUTOMATIC => new lang_string('completion_automatic', 'completion'),
        ];
    }

    /**
     * Return the list of properties.
     *
     * @return array
     */
    protected static function define_properties() {
        return[
            'courseid' => [
                'type' => PARAM_INT,
                'default' => 0,
            ],
            'cmid' => [
                'type' => PARAM_INT,
            ],
            'enabled' => [
                'type' => PARAM_INT,
            ],
            'totalview' => [
                'type' => PARAM_INT,
            ],
            'comments' => [
                'type' => PARAM_INT,
            ],
            'replies' => [
                'type' => PARAM_INT,
            ],
            'completionexpected' => [
                'type' => PARAM_INT,
                'default' => null,
                'null' => NULL_ALLOWED,
            ],
        ];
    }

    /**
     * Action to perform after delete.
     *
     * @param bool $result Result of the delete action.
     * @return void
     */
    public function after_delete($result) {
        // FIXME: optimize this.
        if ($records = annoto_completiondata::get_records(['completionid' => $this->raw_get('id')])) {
            foreach ($records as $record) {
                $record->delete();
            }
        }
    }
}
