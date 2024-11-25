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
 * Local plugin "Annoto" - Local library
 *
 * @package    local_annoto
 * @copyright  Annoto Ltd.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use core\di;
use core\hook\manager as hook_manager;

/**
 * Function Insert a chunk of html at the start of the html document.
 * 
 * @return string HTML fragment.
 */
function local_annoto_callbackimpl_before_standard_top_of_body_html() {
    global $PAGE;
    // Prevent callback loading for all themes except those:.
    $themes = ['lambda', 'adaptable', 'academi']; // Added academi theme.
    if (in_array($PAGE->theme->name, $themes)) {
        local_annoto_init();
    }
}

/**
 * Callback to add before footers elements.
 * Allows plugins to injecting JS across the site, like analytics.
 *
 * @return void
 */
function local_annoto_callbackimpl_before_footer_html_generation() {
    local_annoto_init();
}
