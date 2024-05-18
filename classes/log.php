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
 * Log class for Annoto plugin.
 *
 * @package    local_annoto
 * @subpackage annoto
 * @copyright  Annoto Ltd.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_annoto;

/**
 * Provides logging functionality for the Annoto plugin.
 */
class log {
    /**
     * Logs a debug message.
     *
     * @param string $message The message to log.
     */
    public static function debug($message = '') {
        if (!defined('BEHAT_SITE_RUNNING')
            && !(defined('PHPUNIT_TEST') && PHPUNIT_TEST)
        ) {
            debugging('Annoto: ' . $message, DEBUG_DEVELOPER);
        }
    }

    /**
     * Logs an informational message.
     *
     * @param string $message The message to log.
     */
    public static function info($message = '') {
        if (!defined('BEHAT_SITE_RUNNING')
            && !(defined('PHPUNIT_TEST') && PHPUNIT_TEST)
        ) {
            debugging('Annoto: ' . $message, DEBUG_NORMAL);
        }
    }

    /**
     * Logs a warning message.
     *
     * @param string $message The message to log.
     */
    public static function warning($message = '') {
        if (!defined('BEHAT_SITE_RUNNING')
            && !(defined('PHPUNIT_TEST') && PHPUNIT_TEST)
        ) {
            debugging('Annoto: ' . $message, DEBUG_NORMAL);
        }
    }

    /**
     * Logs an error message.
     *
     * @param string $message The message to log.
     */
    public static function error($message = '') {
        if (!defined('BEHAT_SITE_RUNNING')
            && !(defined('PHPUNIT_TEST') && PHPUNIT_TEST)
        ) {
            debugging('Annoto: ' . $message, DEBUG_NORMAL);
        }
    }
}
