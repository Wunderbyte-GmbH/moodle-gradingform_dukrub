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
 * This file keeps track of upgrades to plugin gradingform_dukrub
 *
 * @package    gradingform_dukrub
 * @copyright  2011 David Mudrak <david@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Keeps track or dukrub plugin upgrade path
 *
 * @param int $oldversion the DB version of currently installed plugin
 * @return bool true
 */
function xmldb_gradingform_dukrub_upgrade($oldversion) {
    global $DB;
    $dbman = $DB->get_manager();

    if ($oldversion < 2018050702) {

        // We allow decimal values too.
        // Changing type of field leveloverwrite on table gradingform_dukrub_fillings to number.
        $table = new xmldb_table('gradingform_dukrub_fillings');
        $field = new xmldb_field('leveloverwrite', XMLDB_TYPE_NUMBER, '10, 5', null, null, null, null, 'levelid');

        // Launch change of type for field leveloverwrite.
        $dbman->change_field_type($table, $field);

        // Dukrub savepoint reached.
        upgrade_plugin_savepoint(true, 2018050702, 'gradingform', 'dukrub');
    }

    return true;
}

