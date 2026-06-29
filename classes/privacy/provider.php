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
 * Privacy class for requesting user data.
 *
 * @package    local_annoto
 * @copyright  Annoto Ltd.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace local_annoto\privacy;

use context;
use context_course;
use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\transform;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * Privacy class for requesting user data.
 *
 * The plugin stores per-user Annoto activity in {local_annoto_completiondata}
 * (keyed by completion config, which belongs to a course) and also sends the
 * user's identity to the remote Annoto service.
 *
 * @package    local_annoto
 * @copyright  Annoto Ltd.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\plugin\provider,
    \core_privacy\local\request\core_userlist_provider {

    /**
     * Returns meta data about this plugin.
     *
     * @param   collection $collection The initialised collection to add items to.
     * @return  collection A listing of user data stored through this plugin.
     */
    public static function get_metadata(collection $collection): collection {

        $collection->add_database_table('local_annoto_completiondata', [
            'userid' => 'privacy:metadata:local_annoto_completiondata:userid',
            'completionid' => 'privacy:metadata:local_annoto_completiondata:completionid',
            'data' => 'privacy:metadata:local_annoto_completiondata:data',
            'timecreated' => 'privacy:metadata:local_annoto_completiondata:timecreated',
            'timemodified' => 'privacy:metadata:local_annoto_completiondata:timemodified',
        ], 'privacy:metadata:local_annoto_completiondata');

        $collection->add_external_location_link('annoto', [
            'userid' => 'privacy:metadata:annoto:userid',
            'fullname' => 'privacy:metadata:annoto:fullname',
            'email' => 'privacy:metadata:annoto:email',
        ], 'privacy:metadata:annoto');

        return $collection;
    }

    /**
     * Get the list of contexts that contain user information for the specified user.
     *
     * @param int $userid The user to search.
     * @return contextlist The contextlist containing the list of contexts used in this plugin.
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();

        $sql = "SELECT ctx.id
                  FROM {local_annoto_completiondata} cd
                  JOIN {local_annoto_completion} c ON c.id = cd.completionid
                  JOIN {context} ctx ON ctx.instanceid = c.courseid AND ctx.contextlevel = :contextlevel
                 WHERE cd.userid = :userid";

        $contextlist->add_from_sql($sql, [
            'contextlevel' => CONTEXT_COURSE,
            'userid' => $userid,
        ]);

        return $contextlist;
    }

    /**
     * Get the list of users who have data within a context.
     *
     * @param userlist $userlist The userlist containing the list of users who have data in this context/plugin combination.
     */
    public static function get_users_in_context(userlist $userlist): void {
        $context = $userlist->get_context();
        if (!$context instanceof context_course) {
            return;
        }

        $sql = "SELECT cd.userid
                  FROM {local_annoto_completiondata} cd
                  JOIN {local_annoto_completion} c ON c.id = cd.completionid
                 WHERE c.courseid = :courseid";

        $userlist->add_from_sql('userid', $sql, ['courseid' => $context->instanceid]);
    }

    /**
     * Export all user data for the specified user, in the specified contexts.
     *
     * @param approved_contextlist $contextlist The approved contexts to export information for.
     */
    public static function export_user_data(approved_contextlist $contextlist): void {
        global $DB;

        $userid = $contextlist->get_user()->id;

        foreach ($contextlist->get_contexts() as $context) {
            if (!$context instanceof context_course) {
                continue;
            }

            $sql = "SELECT cd.id, cd.data, cd.timecreated, cd.timemodified, c.cmid
                      FROM {local_annoto_completiondata} cd
                      JOIN {local_annoto_completion} c ON c.id = cd.completionid
                     WHERE c.courseid = :courseid AND cd.userid = :userid";
            $records = $DB->get_records_sql($sql, [
                'courseid' => $context->instanceid,
                'userid' => $userid,
            ]);

            foreach ($records as $record) {
                $data = (object) [
                    'cmid' => $record->cmid,
                    'data' => $record->data,
                    'timecreated' => transform::datetime($record->timecreated),
                    'timemodified' => transform::datetime($record->timemodified),
                ];
                writer::with_context($context)->export_data(
                    [get_string('pluginname', 'local_annoto'), (string) $record->cmid],
                    $data
                );
            }
        }
    }

    /**
     * Delete all data for all users in the specified context.
     *
     * @param context $context The specific context to delete data for.
     */
    public static function delete_data_for_all_users_in_context(context $context): void {
        global $DB;

        if (!$context instanceof context_course) {
            return;
        }

        $completionids = $DB->get_fieldset_select('local_annoto_completion', 'id', 'courseid = ?', [$context->instanceid]);
        if (empty($completionids)) {
            return;
        }

        [$insql, $inparams] = $DB->get_in_or_equal($completionids);
        $DB->delete_records_select('local_annoto_completiondata', "completionid $insql", $inparams);
    }

    /**
     * Delete all user data for the specified user, in the specified contexts.
     *
     * @param approved_contextlist $contextlist The approved contexts and user information to delete information for.
     */
    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        global $DB;

        $userid = $contextlist->get_user()->id;

        foreach ($contextlist->get_contexts() as $context) {
            if (!$context instanceof context_course) {
                continue;
            }

            $completionids = $DB->get_fieldset_select(
                'local_annoto_completion',
                'id',
                'courseid = ?',
                [$context->instanceid]
            );
            if (empty($completionids)) {
                continue;
            }

            [$insql, $inparams] = $DB->get_in_or_equal($completionids);
            $params = array_merge($inparams, [$userid]);
            $DB->delete_records_select('local_annoto_completiondata', "completionid $insql AND userid = ?", $params);
        }
    }

    /**
     * Delete multiple users within a single context.
     *
     * @param approved_userlist $userlist The approved context and user information to delete information for.
     */
    public static function delete_data_for_users(approved_userlist $userlist): void {
        global $DB;

        $context = $userlist->get_context();
        if (!$context instanceof context_course) {
            return;
        }

        $userids = $userlist->get_userids();
        if (empty($userids)) {
            return;
        }

        $completionids = $DB->get_fieldset_select('local_annoto_completion', 'id', 'courseid = ?', [$context->instanceid]);
        if (empty($completionids)) {
            return;
        }

        [$compsql, $compparams] = $DB->get_in_or_equal($completionids, SQL_PARAMS_NAMED, 'comp');
        [$usersql, $userparams] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'usr');
        $params = array_merge($compparams, $userparams);
        $DB->delete_records_select('local_annoto_completiondata', "completionid $compsql AND userid $usersql", $params);
    }
}
