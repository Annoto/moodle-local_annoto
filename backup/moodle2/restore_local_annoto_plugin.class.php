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
        // Path to the annoto completion data in the backup xml.
        $paths[] = new restore_path_element('local_annoto_completion', '/module/local_annoto_completion');
        $userinfo = $this->get_setting_value('userinfo');
        if ($userinfo) {
            $paths[] = new restore_path_element(
                'local_annoto_completiondata',
                '/module/local_annoto_completion/completiondataset/local_annoto_completiondata'
            );
        }

        return $paths;
    }

    /**
     * Process the completion from xml backup.
     * Will not be called if xml element not found.
     *
     * @param object $data
     */
    public function process_local_annoto_completion($data) {
        global $DB, $USER;

        // Cast to object.
        $data = (object)$data;
        $userinfo = $this->get_setting_value('userinfo');
        // Used /moodle/mod/quiz/accessrule/seb/backup/moodle2/backup_quizaccess_seb_subplugin.class.php.
        // As a reference for case when userdata is false to use user who perform action as a modifier.
        if (!$userinfo) {
            // Add user who performed the restore to the data.
            $data->usermodified = $USER->id;
        } else {
            // Get new user id from the mapping and update data in the object.
            $data->usermodified = $this->get_mappingid('user', $data->usermodified);
        }
        // Get new course module id from the mapping and update data in the object.
        $data->cmid = $this->get_mappingid('course_module', $data->cmid);

        // Get new course id from the mapping and update data in the object.
        $cm = $DB->get_record('course_modules', ['id' => $data->cmid], 'course');

        // Set timecreated and timemodified to the current time.
        $data->timecreated = time();
        $data->timemodified = time();

        $data->courseid = $cm->course;
        // Insert new annoto completion record to the database.
        $newid = $DB->insert_record('local_annoto_completion', $data);
        // Set mapping for the annoto completion for feature usage.
        $this->set_mapping('local_annoto_completion', $data->id, $newid);
    }

    /**
     * Process the completion data from xml backup.
     * Will not be called if xml element not found.
     *
     * @param object $data
     */
    public function process_local_annoto_completiondata($data) {
        global $DB;
        $data = (object)$data;

        $data->completionid = $this->get_new_parentid('local_annoto_completion');
        $data->userid = $this->get_mappingid('user', $data->userid);
        $data->timecreated = time();
        $data->timemodified = time();

        $DB->insert_record('local_annoto_completiondata', $data);
    }
}
