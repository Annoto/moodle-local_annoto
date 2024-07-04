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
        // Flag that determines that user data is included in the backup.
        $userinfo = $this->get_setting_value('userinfo');
        $userscompletion = $this->get_setting_value('userscompletion');

        $plugin = $this->get_plugin_element(null, null, null);

        $completion = new backup_nested_element(
            'local_annoto_completion',
            ['id'],
            [
                'cmid',
                'enabled',
                'totalview',
                'comments',
                'replies',
                'completionexpected',
            ]
        );
        $completiondataset = new backup_nested_element('completiondataset');
        $completiondata = new backup_nested_element(
            'local_annoto_completiondata',
            ['id'],
            [
                'completionid',
                'userid',
                'data',
            ]
        );
        $completion->add_child($completiondataset);
        $completiondataset->add_child($completiondata);

        $completiondata->annotate_ids('user', 'userid');

        $completion->set_source_sql(
            'SELECT * FROM {local_annoto_completion} WHERE cmid = ?',
            ['cmid' => backup::VAR_MODID]
        );

        if ($userinfo && $userscompletion) {
            $completiondata->set_source_sql(
                'SELECT * FROM {local_annoto_completiondata} WHERE completionid = ?',
                ['completionid' => backup::VAR_PARENTID]
            );
        }
        $plugin->add_child($completion);
        return $plugin;
    }
}
