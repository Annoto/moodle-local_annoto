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

namespace local_annoto\local\hook\output;

/**
 * Hook to allow local_annoto to add elements to the top of the body.
 *
 * @package    local_annoto
 * @copyright  2024 Alexander Bias <bias@alexanderbias.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class before_standard_top_of_body_html_generation {
    /**
     * Callback to add top of body html elements.
     *
     * @param \core\hook\output\before_standard_top_of_body_html_generation $hook
     */
    public static function callback(\core\hook\output\before_standard_top_of_body_html_generation $hook): void {
        global $CFG;

        // Require local library.
        require_once($CFG->dirroot.'/local/annoto/locallib.php');

        // Call callback implementation.
        local_annoto_callbackimpl_before_standard_top_of_body_html($hook);
    }
}
