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
 * Task definition for the Annoto plugin.
 *
 * @package    local_annoto
 * @subpackage annoto
 * @copyright  Annoto Ltd.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();

$tasks = [
    [
         'classname' => 'local_annoto\task\completion',
         'blocking'  => 0,
         // Runs every 5 minutes rather than every minute: the task full-scans
         // all active completion records and every user's completion data, so a
         // 1-minute cadence is needlessly heavy at scale.
         'minute'    => '*/5',
         'hour'      => '*',
         'day'       => '*',
         'dayofweek' => '*',
         'month'     => '*',
     ],
];
