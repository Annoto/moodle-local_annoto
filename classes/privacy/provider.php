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

use core_privacy\local\metadata\collection;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;
use core_privacy\local\request\transform;

/**
 * Privacy class for requesting user data.
 *
 * @package    local_annoto
 * @copyright  Annoto Ltd.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
        // This plugin has data.
    \core_privacy\local\metadata\provider,

    // This plugin currently implements the original plugin_provider interface.
    \core_privacy\local\request\plugin\provider,

    // This plugin is capable of determining which users have data within it.
    \core_privacy\local\request\core_userlist_provider {
    /**
     * Returns meta data about this plugin.
     *
     * @param collection $collection The initialized collection to add items to.
     * @return collection A listing of user data stored through this plugin.
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_external_location_link('annoto', [
            'userid' => 'privacy:metadata:annoto:userid',
            'fullname' => 'privacy:metadata:annoto:fullname',
            'email' => 'privacy:metadata:annoto:email',
        ], 'privacy:metadata:annoto');

        // Table local_annoto_completion to store completion settings for particular course module.
        $collection->add_database_table(
            'local_annoto_completion',
            [
                'courseid' => 'privacy:metadata:local_annoto_completion:courseid',
                'cmid' => 'privacy:metadata:local_annoto_completion:cmid',
                'enabled' => 'privacy:metadata:local_annoto_completion:enabled',
                'totalview' => 'privacy:metadata:local_annoto_completion:totalview',
                'comments' => 'privacy:metadata:local_annoto_completion:comments',
                'replies' => 'privacy:metadata:local_annoto_completion:replies',
                'completionexpected' => 'privacy:metadata:local_annoto_completion:completionexpected',
                'usermodified' => 'privacy:metadata:local_annoto_completion:usermodified',
                'timecreated' => 'privacy:metadata:local_annoto_completion:timecreated',
                'timemodified' => 'privacy:metadata:local_annoto_completion:timemodified',
            ],
            'privacy:metadata:local_annoto_completion'
        );

        // Table local_annoto_completiondata to store completion data for particular user.
        $collection->add_database_table(
            'local_annoto_completiondata',
            [
                'userid' => 'privacy:metadata:local_annoto_completiondata:userid',
                'completionid' => 'privacy:metadata:local_annoto_completiondata:completionid',
                'data' => 'privacy:metadata:local_annoto_completiondata:data',
                'timecreated' => 'privacy:metadata:local_annoto_completiondata:timecreated',
                'timemodified' => 'privacy:metadata:local_annoto_completiondata:timemodified',
            ],
            'privacy:metadata:local_annoto_completiondata'
        );

        return $collection;
    }

    /**
     * Get the list of contexts that contain user information for the specified user.
     *
     * @param int $userid The user to search.
     * @return contextlist The context list containing the list of contexts used in this plugin.
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();

        $sql = "SELECT ctx.id
                    FROM {context} ctx
                    JOIN {course_modules} cm ON cm.id = ctx.instanceid AND ctx.contextlevel = :contextlevel
                    LEFT JOIN {local_annoto_completion} lac ON lac.cmid = cm.id
                    LEFT JOIN {local_annoto_completiondata} lacd ON lacd.completionid = lac.id
                WHERE lac.usermodified = :usermodified
                    OR lacd.userid = :userid";

        $params = [
            'contextlevel' => CONTEXT_MODULE,
            'usermodified' => $userid,
            'userid' => $userid,
        ];
        $contextlist->add_from_sql($sql, $params);
        return $contextlist;
    }

    /**
     * Get the list of users who have data within a context.
     *
     * @param   userlist    $userlist   The userlist containing the list of users who have data in this context/plugin combination.
     */
    public static function get_users_in_context(userlist $userlist) {
        $context = $userlist->get_context();

        if (!$context instanceof \context_module) {
            return;
        }

        $params = [
            'contextid' => $context->id,
            'contextlevel' => CONTEXT_MODULE,
        ];

        // Get all users who modified completion records.
        $sql = "SELECT lac.usermodified AS userid
                  FROM {context} ctx
                  JOIN {course_modules} cm ON cm.id = ctx.instanceid
                  JOIN {local_annoto_completion} lac ON lac.cmid = cm.id
                 WHERE ctx.id = :contextid AND ctx.contextlevel = :contextlevel";
        $userlist->add_from_sql('userid', $sql, $params);

        // Get all users who have data in completiondata.
        $sql = "SELECT lacd.userid
                  FROM {context} ctx
                  JOIN {course_modules} cm ON cm.id = ctx.instanceid
                  JOIN {local_annoto_completion} lac ON lac.cmid = cm.id
                  JOIN {local_annoto_completiondata} lacd ON lacd.completionid = lac.id
                 WHERE ctx.id = :contextid AND ctx.contextlevel = :contextlevel";
        $userlist->add_from_sql('userid', $sql, $params);
    }

    /**
     * Export all user data for the specified user, in the specified contexts.
     *
     * @param approved_contextlist $contextlist The approved contexts to export information for.
     */
    public static function export_user_data(approved_contextlist $contextlist) {
        global $DB;

        if (!count($contextlist)) {
            return;
        }

        $user = $contextlist->get_user();
        $userid = $user->id;
        list($contextsql, $contextparams) = $DB->get_in_or_equal($contextlist->get_contextids(), SQL_PARAMS_NAMED);

        $sql = "SELECT
                    ctx.id AS contextid,
                    lac.*,
                    cm.id AS cmid
                FROM {context} ctx
                JOIN {course_modules} cm ON cm.id = ctx.instanceid
                JOIN {local_annoto_completion} lac ON lac.cmid = cm.id
                WHERE ctx.id {$contextsql}";

        $params = $contextparams;

        $completions = $DB->get_recordset_sql($sql, $params);

        foreach ($completions as $completion) {
            $context = \context_module::instance($completion->cmid);

            \core_privacy\local\request\helper::export_context_files($context, $user);

            if (!empty($completion->timecreated)) {
                $completion->timecreated = transform::datetime($completion->timecreated);
            }
            if (!empty($completion->timemodified)) {
                $completion->timemodified = transform::datetime($completion->timemodified);
            }
            // Export general completion data.
            writer::with_context($context)->export_data([], $completion);

            $completiondatarecords = $DB->get_records('local_annoto_completiondata', ['completionid' => $completion->id]);
            foreach ($completiondatarecords as $record) {
                $record = (object) $record;

                if (!empty($record->timecreated)) {
                    $record->timecreated = transform::datetime($record->timecreated);
                }
                if (!empty($record->timemodified)) {
                    $record->timemodified = transform::datetime($record->timemodified);
                }

                writer::with_context($context)->export_data(['completiondata'], $record);
            }
        }
        $completions->close();
    }

    /**
     * Delete all data for all users in the specified context.
     *
     * @param \context $context The specific context to delete data for.
     */
    public static function delete_data_for_all_users_in_context(\context $context) {
        global $DB;
        if ($context->contextlevel != CONTEXT_MODULE) {
            return;
        }
        // Retrieve the ID of the annoto_completion record associated with this context.
        $sql = "SELECT lac.id
                FROM {local_annoto_completion} lac
                JOIN {course_modules} cm ON lac.cmid = cm.id
                JOIN {context} ctx ON ctx.instanceid = cm.id AND ctx.contextlevel = :contextmodule
                WHERE ctx.id = :contextid";
        $params = ['contextmodule' => CONTEXT_MODULE, 'contextid' => $context->id];
        $completionid = $DB->get_field_sql($sql, $params);
        // If a record ID is found, proceed with deletion.
        if ($completionid) {
            // Delete all related records from the completiondata table.
            $DB->delete_records('local_annoto_completiondata', ['completionid' => $completionid]);

            // Delete the record from the completion table.
            $DB->delete_records('local_annoto_completion', ['id' => $completionid]);
        }
    }

    /**
     * Delete all user data for the specified user, in the specified contexts.
     *
     * @param approved_contextlist $contextlist The approved contexts and user information to delete information for.
     */
    public static function delete_data_for_user(approved_contextlist $contextlist) {
        global $DB;

        $user = $contextlist->get_user();

        foreach ($contextlist as $context) {
            if ($context->contextlevel != CONTEXT_MODULE) {
                // Only handle module context level.
                continue;
            }

            $cm = get_coursemodule_from_id('', $context->instanceid);
            if (!$cm) {
                // Only handle valid course modules.
                continue;
            }

            // Fetch the completion ID for the context.
            $sql = "SELECT lac.id
                    FROM {local_annoto_completion} lac
                    JOIN {course_modules} cm ON lac.cmid = cm.id
                    JOIN {context} ctx ON ctx.instanceid = cm.id AND ctx.contextlevel = :contextmodule
                    WHERE ctx.id = :contextid";
            $params = ['contextmodule' => CONTEXT_MODULE, 'contextid' => $context->id];

            $completionid = $DB->get_field_sql($sql, $params);
            if (!$completionid) {
                continue;
            }

            // Remove completion data for this user.
            $DB->delete_records('local_annoto_completiondata', [
                'completionid' => $completionid,
                'userid' => $user->id,
            ]);

            // If the user was the one who modified the completion, remove the completion record.
            $DB->delete_records('local_annoto_completion', [
                'id' => $completionid,
                'usermodified' => $user->id,
            ]);
        }
    }

    /**
     * Delete multiple users within a single context.
     *
     * @param   approved_userlist       $userlist The approved context and user information to delete information for.
     */
    public static function delete_data_for_users(approved_userlist $userlist) {
        global $DB;

        $context = $userlist->get_context();
        $userids = $userlist->get_userids();

        if (empty($userids) || $context->contextlevel !== CONTEXT_MODULE) {
            return;
        }

        $sql = "SELECT ctx.id AS ctxid, lac.id AS completionid
                    FROM {local_annoto_completion} lac
                    JOIN {course_modules} cm ON lac.cmid = cm.id
                    JOIN {context} ctx ON ctx.instanceid = cm.id AND ctx.contextlevel = :contextmodule
                    WHERE ctx.id = :contextid";

        $params = [
            'contextmodule' => CONTEXT_MODULE,
            'contextid' => $context->id,
        ];

        if (!$records = $DB->get_records_sql($sql, $params)) {
            return;
        }

        $completionids = [];

        foreach ($records as $record) {
            $completionids[] = $record->completionid;
        }
        if (count($completionids) > 0) {
            list($usersql, $userparams) = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'usr');
            list($csql, $cparams) = $DB->get_in_or_equal($completionids, SQL_PARAMS_NAMED, 'completionid');

            $params = $userparams + $cparams;

            $DB->delete_records_select('local_annoto_completiondata', 'userid ' . $usersql . ' AND completionid ' . $csql, $params);
            $DB->delete_records_select('local_annoto_completion', 'usermodified ' . $usersql . ' AND id ' . $csql, $params);
        }
    }
}
