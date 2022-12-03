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
 * Support for restore API
 *
 * @package    gradingform_dukrub
 * @copyright  2011 David Mudrak <david@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Restores the dukrub specific data from grading.xml file
 *
 * @package    gradingform_dukrub
 * @copyright  2011 David Mudrak <david@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class restore_gradingform_dukrub_plugin extends restore_gradingform_plugin {

    /**
     * Declares the dukrub XML paths attached to the form definition element
     *
     * @return array of {@link restore_path_element}
     */
    protected function define_definition_plugin_structure() {

        $paths = array();

        $paths[] = new restore_path_element('gradingform_dukrub_criterion',
            $this->get_pathfor('/criteria2/criterion2'));

        $paths[] = new restore_path_element('gradingform_dukrub_level',
            $this->get_pathfor('/criteria2/criterion2/levels2/level2'));

        return $paths;
    }

    /**
     * Declares the dukrub XML paths attached to the form instance element
     *
     * @return array of {@link restore_path_element}
     */
    protected function define_instance_plugin_structure() {

        $paths = array();

        $paths[] = new restore_path_element('gradinform_dukrub_filling',
            $this->get_pathfor('/fillings2/filling2'));

        return $paths;
    }

    /**
     * Processes criterion element data
     *
     * Sets the mapping 'gradingform_dukrub_criterion' to be used later by
     * {@link self::process_gradinform_dukrub_filling()}
     *
     * @param stdClass|array $data
     */
    public function process_gradingform_dukrub_criterion($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;
        $data->definitionid = $this->get_new_parentid('grading_definition');

        $newid = $DB->insert_record('gradingform_dukrub_criteria', $data);
        $this->set_mapping('gradingform_dukrub_criterion', $oldid, $newid);
    }

    /**
     * Processes level element data
     *
     * Sets the mapping 'gradingform_dukrub_level' to be used later by
     * {@link self::process_gradinform_dukrub_filling()}
     *
     * @param stdClass|array $data
     */
    public function process_gradingform_dukrub_level($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;
        $data->criterionid = $this->get_new_parentid('gradingform_dukrub_criterion');

        $newid = $DB->insert_record('gradingform_dukrub_levels', $data);
        $this->set_mapping('gradingform_dukrub_level', $oldid, $newid);
    }

    /**
     * Processes filling element data
     *
     * @param stdClass|array $data
     */
    public function process_gradinform_dukrub_filling($data) {
        global $DB;

        $data = (object)$data;
        $data->instanceid = $this->get_new_parentid('grading_instance');
        $data->criterionid = $this->get_mappingid('gradingform_dukrub_criterion', $data->criterionid);
        $data->levelid = $this->get_mappingid('gradingform_dukrub_level', $data->levelid);

        if (!empty($data->criterionid)) {
            $DB->insert_record('gradingform_dukrub_fillings', $data);
        }

    }
}
