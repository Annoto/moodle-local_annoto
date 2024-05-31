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
 * Annoto backup class.
 *
 * @package    local_annoto
 * @subpackage annoto
 * @copyright  Annoto Ltd.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class restore_local_annoto_plugin extends restore_plugin {
    /**
     * Define the paths to be handled by the plugin in the xml dom.
     *
     * @return array Array of restore_path_element objects
     */
    protected function define_module_plugin_structure() {
        $paths = [
            new restore_path_element('plugin_local_annoto', $this->get_pathfor('')),
        ];
        return $paths;
    }

    /**
     * Process the plugin data from xml backup.
     *
     * @param object $data
     */
    public function process_plugin_local_annoto($data) {
        global $DB;

        $data = (object)$data;

        // Get new course module id from the mapping and update data in the object.
        $data->cmid = $this->get_mappingid('course_module', $data->cmid);
        // Get new course id from the mapping and update data in the object.
        $cm = $DB->get_record('course_modules', ['id' => $data->cmid], 'course');
        $data->courseid = $cm->course;
        // Insert new annoto completion record to the database.
        $DB->insert_record('local_annoto_completion', $data);
    }
}
