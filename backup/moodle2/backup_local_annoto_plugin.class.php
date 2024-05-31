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
class backup_local_annoto_plugin extends backup_local_plugin {
    /**
     * Returns the backup configration of annoto data for particular module.
     */
    public function define_module_plugin_structure() {
        $plugin = $this->get_plugin_element(null, null, null);
        $pluginwrapper = new backup_nested_element(
            $this->get_recommended_name(),
            ['id'],
            [
                'courseid',
                'cmid',
                'enabled',
                'totalview',
                'comments',
                'replies',
                'completionexpected',
                'usermodified',
                'timecreated',
                'timemodified',
            ]
        );
        $plugin->add_child($pluginwrapper);
        $pluginwrapper->set_source_sql(
            'SELECT * FROM {local_annoto_completion} WHERE cmid = ?',
            ['cmid' => backup::VAR_MODID]
        );
        return $plugin;
    }
}
