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

/**
 * Privacy class for requesting user data.
 *
 * @package    local_annoto
 * @copyright  Annoto Ltd.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
        \core_privacy\local\metadata\provider,
        \core_privacy\local\request\plugin\provider {
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

        $collection->add_database_table(
            'local_annoto_completiondata',
            [
                'userid' => 'privacy:metadata:local_annoto_completiondata:userid',
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
        // phpcs:ignore Squiz.PHP.CommentedOutCode.Found, moodle.Commenting.InlineComment.NotCapital
        // https://moodledev.io/docs/4.4/apis/subsystems/privacy/faq#my-plugin-only-sends-data-to-an-external-location-but-doesnt-store-it-locally-in-moodle---what-should-i-do.
    }

    /**
     * Export all user data for the specified user, in the specified contexts.
     *
     * @param approved_contextlist $contextlist The approved contexts to export information for.
     */
    public static function export_user_data(approved_contextlist $contextlist) {
        // phpcs:ignore Squiz.PHP.CommentedOutCode.Found, moodle.Commenting.InlineComment.NotCapital
        // https://moodledev.io/docs/4.4/apis/subsystems/privacy/faq#my-plugin-only-sends-data-to-an-external-location-but-doesnt-store-it-locally-in-moodle---what-should-i-do.
    }

    /**
     * Delete all data for all users in the specified context.
     *
     * @param \context $context The specific context to delete data for.
     */
    public static function delete_data_for_all_users_in_context(\context $context) {
        // phpcs:ignore Squiz.PHP.CommentedOutCode.Found, moodle.Commenting.InlineComment.NotCapital
        // https://moodledev.io/docs/4.4/apis/subsystems/privacy/faq#my-plugin-only-sends-data-to-an-external-location-but-doesnt-store-it-locally-in-moodle---what-should-i-do.
    }

    /**
     * Delete all user data for the specified user, in the specified contexts.
     *
     * @param approved_contextlist $contextlist The approved contexts and user information to delete information for.
     */
    public static function delete_data_for_user(approved_contextlist $contextlist) {
        // phpcs:ignore Squiz.PHP.CommentedOutCode.Found, moodle.Commenting.InlineComment.NotCapital
        // https://moodledev.io/docs/4.4/apis/subsystems/privacy/faq#my-plugin-only-sends-data-to-an-external-location-but-doesnt-store-it-locally-in-moodle---what-should-i-do.
    }
}
