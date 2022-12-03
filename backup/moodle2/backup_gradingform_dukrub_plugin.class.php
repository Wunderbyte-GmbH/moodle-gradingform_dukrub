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
 * Support for backup API
 *
 * @package    gradingform_dukrub
 * @copyright  2011 David Mudrak <david@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Defines dukrub backup structures
 *
 * @package    gradingform_dukrub
 * @copyright  2011 David Mudrak <david@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class backup_gradingform_dukrub_plugin extends backup_gradingform_plugin {

    /**
     * Declares dukrub structures to append to the grading form definition
     */
    protected function define_definition_plugin_structure() {
        // Append data only if the grand-parent element has 'method' set to 'dukrub'
        $plugin = $this->get_plugin_element(null, '../../method', 'dukrub');

        // Create a visible container for our data
        $pluginwrapper = new backup_nested_element($this->get_recommended_name());

        // Connect our visible container to the parent
        $plugin->add_child($pluginwrapper);

        // Define our elements
        // Elements must not be named like elements in other activities!

        $criteria2 = new backup_nested_element('criteria2');

        $criterion2 = new backup_nested_element('criterion2', array('id'), array(
            'sortorder', 'description', 'descriptionformat'));

        $levels2 = new backup_nested_element('levels2');

        $level2 = new backup_nested_element('level2', array('id'), array(
            'score', 'definition', 'definitionformat'));

        // Build elements hierarchy

        $pluginwrapper->add_child($criteria2);
        $criteria2->add_child($criterion2);
        $criterion2->add_child($levels2);
        $levels2->add_child($level2);

        // Set sources to populate the data

        $criterion2->set_source_table('gradingform_dukrub_criteria',
                array('definitionid' => backup::VAR_PARENTID));

        $level2->set_source_table('gradingform_dukrub_levels',
                array('criterionid' => backup::VAR_PARENTID));

        // no need to annotate ids or files yet (one day when criterion definition supports
        // embedded files, they must be annotated here)

        return $plugin;
    }

    /**
     * Declares dukrub structures to append to the grading form instances
     */
    protected function define_instance_plugin_structure() {

        // Append data only if the ancestor 'definition' element has 'method' set to 'dukrub'
        $plugin = $this->get_plugin_element(null, '../../../../method', 'dukrub');

        // Create a visible container for our data
        $pluginwrapper = new backup_nested_element($this->get_recommended_name());

        // Connect our visible container to the parent
        $plugin->add_child($pluginwrapper);

        // Define our elements

        $fillings = new backup_nested_element('fillings');

        $filling = new backup_nested_element('filling', array('id'), array(
            'criterionid', 'levelid', 'leveloverwrite', 'remark', 'remarkformat'));

        // Build elements hierarchy

        $pluginwrapper->add_child($fillings);
        $fillings->add_child($filling);

        // Set sources to populate the data

        // Binding criterionid to ensure it's existence
        $filling->set_source_sql('SELECT rf.*
                FROM {gradingform_dukrub_fillings} rf
                JOIN {grading_instances} gi ON gi.id = rf.instanceid
                JOIN {gradingform_dukrub_criteria} rc ON rc.id = rf.criterionid AND gi.definitionid = rc.definitionid
                WHERE rf.instanceid = :instanceid',
                array('instanceid' => backup::VAR_PARENTID));

        // no need to annotate ids or files yet (one day when remark field supports
        // embedded fileds, they must be annotated here)

        return $plugin;
    }
}
