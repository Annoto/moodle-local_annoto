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
 * Hook callbacks to allow Annoto to respond to hooks.
 *
 * @package    local_annoto
 * @subpackage annoto
 * @copyright  Annoto Ltd.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_annoto;

/**
 * Provides hooks and callbacks functionality for the Annoto plugin.
 */
class hook_callbacks {
    /**
     * Callback to allow Annoto to add HTML content to the footer.
     *
     * @param \core\hook\output\before_footer_html_generation $hook
     */
    public static function before_footer_html_generation(\core\hook\output\before_footer_html_generation $hook): void {
        local_annoto_init();
    }

    /**
     * Callback to allow Annoto to add HTML content to the top of the page body.
     * Utilized only for select LOCAL_ANNOTO_TOP_OF_BODY_THEMES.
     *
     * @param \core\hook\output\before_standard_top_of_body_html_generation $hook
     */
    public static function before_standard_top_of_body_html_generation(
        \core\hook\output\before_standard_top_of_body_html_generation $hook
    ): void {
        global $PAGE;
        // The Kaltura embed hook must be in the page ahead of every player embed, so it is emitted
        // here rather than queued for the footer by local_annoto_init().
        $html = local_annoto_kaltura_hook_html();
        if ($html !== '') {
            $hook->add_html($html);
            local_annoto_kaltura_hook_emitted(true);
        }
        // Prevent callback loading for all themes except those.
        $themes = explode(',', LOCAL_ANNOTO_TOP_OF_BODY_THEMES);
        if (in_array($PAGE->theme->name, $themes)) {
            local_annoto_init();
        }
    }
}
