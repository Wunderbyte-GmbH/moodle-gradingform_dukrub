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
 * Grading method controller for the Dukrub plugin
 *
 * @package    gradingform_dukrub
 * @copyright  2018 michael pollak <moodle@michaelpollak.org>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot.'/grade/grading/form/lib.php');

/**
 * This controller encapsulates the dukrub grading logic
 *
 * @package    gradingform_dukrub
 * @copyright  2011 David Mudrak <david@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class gradingform_dukrub_controller extends gradingform_controller {
    // Modes of displaying the dukrub (used in gradingform_dukrub_renderer)
    /** Dukrub display mode: For editing (moderator or teacher creates a dukrub) */
    const DISPLAY_EDIT_FULL     = 1;
    /** Dukrub display mode: Preview the dukrub design with hidden fields */
    const DISPLAY_EDIT_FROZEN   = 2;
    /** Dukrub display mode: Preview the dukrub design (for person with manage permission) */
    const DISPLAY_PREVIEW       = 3;
    /** Dukrub display mode: Preview the dukrub (for people being graded) */
    const DISPLAY_PREVIEW_GRADED= 8;
    /** Dukrub display mode: For evaluation, enabled (teacher grades a student) */
    const DISPLAY_EVAL          = 4;
    /** Dukrub display mode: For evaluation, with hidden fields */
    const DISPLAY_EVAL_FROZEN   = 5;
    /** Dukrub display mode: Teacher reviews filled dukrub */
    const DISPLAY_REVIEW        = 6;
    /** Dukrub display mode: Dispaly filled dukrub (i.e. students see their grades) */
    const DISPLAY_VIEW          = 7;

    /**
     * Extends the module settings navigation with the dukrub grading settings
     *
     * This function is called when the context for the page is an activity module with the
     * FEATURE_ADVANCED_GRADING, the user has the permission moodle/grade:managegradingforms
     * and there is an area with the active grading method set to 'dukrub'.
     *
     * @param settings_navigation $settingsnav {@link settings_navigation}
     * @param navigation_node $node {@link navigation_node}
     */
    public function extend_settings_navigation(settings_navigation $settingsnav, navigation_node $node=null) {
        $node->add(get_string('definedukrub', 'gradingform_dukrub'),
            $this->get_editor_url(), settings_navigation::TYPE_CUSTOM,
            null, null, new pix_icon('icon', '', 'gradingform_dukrub'));
    }

    /**
     * Extends the module navigation
     *
     * This function is called when the context for the page is an activity module with the
     * FEATURE_ADVANCED_GRADING and there is an area with the active grading method set to the given plugin.
     *
     * @param global_navigation $navigation {@link global_navigation}
     * @param navigation_node $node {@link navigation_node}
     */
    public function extend_navigation(global_navigation $navigation, navigation_node $node=null) {
        if (has_capability('moodle/grade:managegradingforms', $this->get_context())) {
            // no need for preview if user can manage forms, he will have link to manage.php in settings instead
            return;
        }
        if ($this->is_form_defined() && ($options = $this->get_options()) && !empty($options['alwaysshowdefinition'])) {
            $node->add(get_string('gradingof', 'gradingform_dukrub', get_grading_manager($this->get_areaid())->get_area_title()),
                    new moodle_url('/grade/grading/form/'.$this->get_method_name().'/preview.php', array('areaid' => $this->get_areaid())),
                    settings_navigation::TYPE_CUSTOM);
        }
    }

    /**
     * Saves the dukrub definition into the database
     *
     * @see parent::update_definition()
     * @param stdClass $newdefinition dukrub definition data as coming from gradingform_dukrub_editdukrub::get_data()
     * @param int|null $usermodified optional userid of the author of the definition, defaults to the current user
     */
    public function update_definition(stdClass $newdefinition, $usermodified = null) {
        $this->update_or_check_dukrub($newdefinition, $usermodified, true);
        if (isset($newdefinition->dukrub['regrade']) && $newdefinition->dukrub['regrade']) {
            $this->mark_for_regrade();
        }
    }

    /**
     * Either saves the dukrub definition into the database or check if it has been changed.
     * Returns the level of changes:
     * 0 - no changes
     * 1 - only texts or criteria sortorders are changed, students probably do not require re-grading
     * 2 - added levels but maximum score on dukrub is the same, students still may not require re-grading
     * 3 - removed criteria or added levels or changed number of points, students require re-grading but may be re-graded automatically
     * 4 - removed levels - students require re-grading and not all students may be re-graded automatically
     * 5 - added criteria - all students require manual re-grading
     *
     * @param stdClass $newdefinition dukrub definition data as coming from gradingform_dukrub_editdukrub::get_data()
     * @param int|null $usermodified optional userid of the author of the definition, defaults to the current user
     * @param boolean $doupdate if true actually updates DB, otherwise performs a check
     *
     */
    public function update_or_check_dukrub(stdClass $newdefinition, $usermodified = null, $doupdate = false) {
        global $DB;

        // firstly update the common definition data in the {grading_definition} table
        if ($this->definition === false) {
            if (!$doupdate) {
                // if we create the new definition there is no such thing as re-grading anyway
                return 5;
            }
            // if definition does not exist yet, create a blank one
            // (we need id to save files embedded in description)
            parent::update_definition(new stdClass(), $usermodified);
            parent::load_definition();
        }
        if (!isset($newdefinition->dukrub['options'])) {
            $newdefinition->dukrub['options'] = self::get_default_options();
        }
        $newdefinition->options = json_encode($newdefinition->dukrub['options']);
        $editoroptions = self::description_form_field_options($this->get_context());
        $newdefinition = file_postupdate_standard_editor($newdefinition, 'description', $editoroptions, $this->get_context(),
            'grading', 'description', $this->definition->id);

        // Reload the definition from the database.
        $currentdefinition = $this->get_definition(true);

        $haschanges = array();

        // Check if 'lockzeropoints' option has changed.
        $newlockzeropoints = $newdefinition->dukrub['options']['lockzeropoints'];
        $currentoptions = $this->get_options();
        if ((bool)$newlockzeropoints != (bool)$currentoptions['lockzeropoints']) {
            $haschanges[3] = true;
        }

        // Update dukrub data.
        if (empty($newdefinition->dukrub['criteria'])) {
            $newcriteria = array();
        } else {
            $newcriteria = $newdefinition->dukrub['criteria']; // New ones to be saved.
        }
        $currentcriteria = $currentdefinition->dukrub_criteria;
        $criteriafields = array('sortorder', 'description', 'descriptionformat');
        $levelfields = array('score', 'definition', 'definitionformat');
        foreach ($newcriteria as $id => $criterion) {
            // Get list of submitted levels.
            $levelsdata = array();
            if (array_key_exists('levels', $criterion)) {
                $levelsdata = $criterion['levels'];
            }
            $criterionmaxscore = null;
            if (preg_match('/^NEWID\d+$/', $id)) {
                // Insert criterion into DB.
                $data = array('definitionid' => $this->definition->id, 'descriptionformat' => FORMAT_MOODLE); // TODO MDL-31235 format is not supported yet
                foreach ($criteriafields as $key) {
                    if (array_key_exists($key, $criterion)) {
                        $data[$key] = $criterion[$key];
                    }
                }
                if ($doupdate) {
                    $id = $DB->insert_record('gradingform_dukrub_criteria', $data);
                }
                $haschanges[5] = true;
            } else {
                // Update criterion in DB.
                $data = array();
                foreach ($criteriafields as $key) {
                    if (array_key_exists($key, $criterion) && $criterion[$key] != $currentcriteria[$id][$key]) {
                        $data[$key] = $criterion[$key];
                    }
                }
                if (!empty($data)) {
                    // Update only if something is changed.
                    $data['id'] = $id;
                    if ($doupdate) {
                        $DB->update_record('gradingform_dukrub_criteria', $data);
                    }
                    $haschanges[1] = true;
                }
                // Remove deleted levels from DB and calculate the maximum score for this criteria.
                foreach ($currentcriteria[$id]['levels'] as $levelid => $currentlevel) {
                    if ($criterionmaxscore === null || $criterionmaxscore < $currentlevel['score']) {
                        $criterionmaxscore = $currentlevel['score'];
                    }
                    if (!array_key_exists($levelid, $levelsdata)) {
                        if ($doupdate) {
                            $DB->delete_records('gradingform_dukrub_levels', array('id' => $levelid));
                        }
                        $haschanges[4] = true;
                    }
                }
            }
            foreach ($levelsdata as $levelid => $level) {
                if (isset($level['score'])) {
                    $level['score'] = unformat_float($level['score']);
                }
                if (preg_match('/^NEWID\d+$/', $levelid)) {
                    // Insert level into DB.
                    $data = array('criterionid' => $id, 'definitionformat' => FORMAT_MOODLE); // TODO MDL-31235 format is not supported yet
                    foreach ($levelfields as $key) {
                        if (array_key_exists($key, $level)) {
                            $data[$key] = $level[$key];
                        }
                    }
                    if ($doupdate) {
                        $levelid = $DB->insert_record('gradingform_dukrub_levels', $data);
                    }
                    if ($criterionmaxscore !== null && $criterionmaxscore >= $level['score']) {
                        // New level is added but the maximum score for this criteria did not change, re-grading may not be necessary.
                        $haschanges[2] = true;
                    } else {
                        $haschanges[3] = true;
                    }
                } else {
                    // Update level in DB.
                    $data = array();
                    foreach ($levelfields as $key) {
                        if (array_key_exists($key, $level) && $level[$key] != $currentcriteria[$id]['levels'][$levelid][$key]) {
                            $data[$key] = $level[$key];
                        }
                    }
                    if (!empty($data)) {
                        // Update only if something is changed.
                        $data['id'] = $levelid;
                        if ($doupdate) {
                            $DB->update_record('gradingform_dukrub_levels', $data);
                        }
                        if (isset($data['score'])) {
                            $haschanges[3] = true;
                        }
                        $haschanges[1] = true;
                    }
                }
            }
        }
        // Remove deleted criteria from DB.
        foreach (array_keys($currentcriteria) as $id) {
            if (!array_key_exists($id, $newcriteria)) {
                if ($doupdate) {
                    $DB->delete_records('gradingform_dukrub_criteria', array('id' => $id));
                    $DB->delete_records('gradingform_dukrub_levels', array('criterionid' => $id));
                }
                $haschanges[3] = true;
            }
        }
        foreach (array('status', 'description', 'descriptionformat', 'name', 'options') as $key) {
            if (isset($newdefinition->$key) && $newdefinition->$key != $this->definition->$key) {
                $haschanges[1] = true;
            }
        }
        if ($usermodified && $usermodified != $this->definition->usermodified) {
            $haschanges[1] = true;
        }
        if (!count($haschanges)) {
            return 0;
        }
        if ($doupdate) {
            parent::update_definition($newdefinition, $usermodified);
            $this->load_definition();
        }
        // Return the maximum level of changes.
        $changelevels = array_keys($haschanges);
        sort($changelevels);
        return array_pop($changelevels);
    }

    /**
     * Marks all instances filled with this dukrub with the status INSTANCE_STATUS_NEEDUPDATE
     */
    public function mark_for_regrade() {
        global $DB;
        if ($this->has_active_instances()) {
            $conditions = array('definitionid'  => $this->definition->id,
                        'status'  => gradingform_instance::INSTANCE_STATUS_ACTIVE);
            $DB->set_field('grading_instances', 'status', gradingform_instance::INSTANCE_STATUS_NEEDUPDATE, $conditions);
        }
    }

    /**
     * Loads the dukrub form definition if it exists
     *
     * There is a new array called 'dukrub_criteria' appended to the list of parent's definition properties.
     */
    protected function load_definition() {
        global $DB;
        $sql = "SELECT gd.*,
                       rc.id AS rcid, rc.sortorder AS rcsortorder, rc.description AS rcdescription, rc.descriptionformat AS rcdescriptionformat,
                       rl.id AS rlid, rl.score AS rlscore, rl.definition AS rldefinition, rl.definitionformat AS rldefinitionformat
                  FROM {grading_definitions} gd
             LEFT JOIN {gradingform_dukrub_criteria} rc ON (rc.definitionid = gd.id)
             LEFT JOIN {gradingform_dukrub_levels} rl ON (rl.criterionid = rc.id)
                 WHERE gd.areaid = :areaid AND gd.method = :method
              ORDER BY rc.sortorder,rl.score";
        $params = array('areaid' => $this->areaid, 'method' => $this->get_method_name());

        $rs = $DB->get_recordset_sql($sql, $params);
        $this->definition = false;
        foreach ($rs as $record) {
            // Pick the common definition data.
            if ($this->definition === false) {
                $this->definition = new stdClass();
                foreach (array('id', 'name', 'description', 'descriptionformat', 'status', 'copiedfromid',
                        'timecreated', 'usercreated', 'timemodified', 'usermodified', 'timecopied', 'options') as $fieldname) {
                    $this->definition->$fieldname = $record->$fieldname;
                }
                $this->definition->dukrub_criteria = array();
            }
            // Pick the criterion data.
            if (!empty($record->rcid) and empty($this->definition->dukrub_criteria[$record->rcid])) {
                foreach (array('id', 'sortorder', 'description', 'descriptionformat') as $fieldname) {
                    $this->definition->dukrub_criteria[$record->rcid][$fieldname] = $record->{'rc'.$fieldname};
                }
                $this->definition->dukrub_criteria[$record->rcid]['levels'] = array();
            }
            // Pick the level data.
            if (!empty($record->rlid)) {
                foreach (array('id', 'score', 'definition', 'definitionformat') as $fieldname) {
                    $value = $record->{'rl'.$fieldname};
                    if ($fieldname == 'score') {
                        $value = (float)$value; // To prevent display like 1.00000
                    }
                    $this->definition->dukrub_criteria[$record->rcid]['levels'][$record->rlid][$fieldname] = $value;
                }
            }
        }
        $rs->close();
        $options = $this->get_options();
        if (!$options['sortlevelsasc']) {
            foreach (array_keys($this->definition->dukrub_criteria) as $rcid) {
                $this->definition->dukrub_criteria[$rcid]['levels'] = array_reverse($this->definition->dukrub_criteria[$rcid]['levels'], true);
            }
        }
    }

    /**
     * Returns the default options for the dukrub display
     *
     * @return array
     */
    public static function get_default_options() {
        $options = array(
            'sortlevelsasc' => 1,
            'lockzeropoints' => 1,
            'alwaysshowdefinition' => 1,
            'showdescriptionteacher' => 1,
            'showdescriptionstudent' => 1,
            'showscoreteacher' => 1,
            'showscorestudent' => 1,
            'enableremarks' => 1,
            'showremarksstudent' => 1
        );
        return $options;
    }

    /**
     * Gets the options of this dukrub definition, fills the missing options with default values
     *
     * The only exception is 'lockzeropoints' - if other options are present in the json string but this
     * one is absent, this means that the dukrub was created before Moodle 3.2 and the 0 value should be used.
     *
     * @return array
     */
    public function get_options() {
        $options = self::get_default_options();
        if (!empty($this->definition->options)) {
            $thisoptions = json_decode($this->definition->options);
            foreach ($thisoptions as $option => $value) {
                $options[$option] = $value;
            }
            if (!array_key_exists('lockzeropoints', $thisoptions)) {
                // Dukrubs created before Moodle 3.2 don't have 'lockzeropoints' option. In this case they should not
                // assume default value 1 but use "legacy" value 0.
                $options['lockzeropoints'] = 0;
            }
        }
        return $options;
    }

    /**
     * Converts the current definition into an object suitable for the editor form's set_data()
     *
     * @param boolean $addemptycriterion whether to add an empty criterion if the dukrub is completely empty (just being created)
     * @return stdClass
     */
    public function get_definition_for_editing($addemptycriterion = false) {

        $definition = $this->get_definition();
        $properties = new stdClass();
        $properties->areaid = $this->areaid;
        if ($definition) {
            foreach (array('id', 'name', 'description', 'descriptionformat', 'status') as $key) {
                $properties->$key = $definition->$key;
            }
            $options = self::description_form_field_options($this->get_context());
            $properties = file_prepare_standard_editor($properties, 'description', $options, $this->get_context(),
                'grading', 'description', $definition->id);
        }
        $properties->dukrub = array('criteria' => array(), 'options' => $this->get_options());
        if (!empty($definition->dukrub_criteria)) {
            $properties->dukrub['criteria'] = $definition->dukrub_criteria;
        } else if (!$definition && $addemptycriterion) {
            $properties->dukrub['criteria'] = array('addcriterion' => 1);
        }

        return $properties;
    }

    /**
     * Returns the form definition suitable for cloning into another area
     *
     * @see parent::get_definition_copy()
     * @param gradingform_controller $target the controller of the new copy
     * @return stdClass definition structure to pass to the target's {@link update_definition()}
     */
    public function get_definition_copy(gradingform_controller $target) {

        $new = parent::get_definition_copy($target);
        $old = $this->get_definition_for_editing();
        $new->description_editor = $old->description_editor;
        $new->dukrub = array('criteria' => array(), 'options' => $old->dukrub['options']);
        $newcritid = 1;
        $newlevid = 1;
        foreach ($old->dukrub['criteria'] as $oldcritid => $oldcrit) {
            unset($oldcrit['id']);
            if (isset($oldcrit['levels'])) {
                foreach ($oldcrit['levels'] as $oldlevid => $oldlev) {
                    unset($oldlev['id']);
                    $oldcrit['levels']['NEWID'.$newlevid] = $oldlev;
                    unset($oldcrit['levels'][$oldlevid]);
                    $newlevid++;
                }
            } else {
                $oldcrit['levels'] = array();
            }
            $new->dukrub['criteria']['NEWID'.$newcritid] = $oldcrit;
            $newcritid++;
        }

        return $new;
    }

    /**
     * Options for displaying the dukrub description field in the form
     *
     * @param object $context
     * @return array options for the form description field
     */
    public static function description_form_field_options($context) {
        global $CFG;
        return array(
            'maxfiles' => -1,
            'maxbytes' => get_user_max_upload_file_size($context, $CFG->maxbytes),
            'context'  => $context,
        );
    }

    /**
     * Formats the definition description for display on page
     *
     * @return string
     */
    public function get_formatted_description() {
        if (!isset($this->definition->description)) {
            return '';
        }
        $context = $this->get_context();

        $options = self::description_form_field_options($this->get_context());
        $description = file_rewrite_pluginfile_urls($this->definition->description, 'pluginfile.php', $context->id,
            'grading', 'description', $this->definition->id, $options);

        $formatoptions = array(
            'noclean' => false,
            'trusted' => false,
            'filter' => true,
            'context' => $context
        );
        return format_text($description, $this->definition->descriptionformat, $formatoptions);
    }

    /**
     * Returns the dukrub plugin renderer
     *
     * @param moodle_page $page the target page
     * @return gradingform_dukrub_renderer
     */
    public function get_renderer(moodle_page $page) {
        return $page->get_renderer('gradingform_'. $this->get_method_name());
    }

    /**
     * Returns the HTML code displaying the preview of the grading form
     *
     * @param moodle_page $page the target page
     * @return string
     */
    public function render_preview(moodle_page $page) {

        if (!$this->is_form_defined()) {
            throw new coding_exception('It is the caller\'s responsibility to make sure that the form is actually defined');
        }

        $criteria = $this->definition->dukrub_criteria;
        $options = $this->get_options();
        $dukrub = '';
        if (has_capability('moodle/grade:managegradingforms', $page->context)) {
            $showdescription = true;
        } else {
            if (empty($options['alwaysshowdefinition']))  {
                // Ensure we don't display unless show dukrub option enabled.
                return '';
            }
            $showdescription = $options['showdescriptionstudent'];
        }
        $output = $this->get_renderer($page);
        if ($showdescription) {
            $dukrub .= $output->box($this->get_formatted_description(), 'gradingform_dukrub-description');
        }
        if (has_capability('moodle/grade:managegradingforms', $page->context)) {
            if (!$options['lockzeropoints']) {
                // Warn about using grade calculation method where minimum number of points is flexible.
                $dukrub .= $output->display_dukrub_mapping_explained($this->get_min_max_score());
            }
            $dukrub .= $output->display_dukrub($criteria, $options, self::DISPLAY_PREVIEW, 'dukrub');
        } else {
            $dukrub .= $output->display_dukrub($criteria, $options, self::DISPLAY_PREVIEW_GRADED, 'dukrub');
        }

        return $dukrub;
    }

    /**
     * Deletes the dukrub definition and all the associated information
     */
    protected function delete_plugin_definition() {
        global $DB;

        // get the list of instances
        $instances = array_keys($DB->get_records('grading_instances', array('definitionid' => $this->definition->id), '', 'id'));
        // delete all fillings
        $DB->delete_records_list('gradingform_dukrub_fillings', 'instanceid', $instances);
        // delete instances
        $DB->delete_records_list('grading_instances', 'id', $instances);
        // get the list of criteria records
        $criteria = array_keys($DB->get_records('gradingform_dukrub_criteria', array('definitionid' => $this->definition->id), '', 'id'));
        // delete levels
        $DB->delete_records_list('gradingform_dukrub_levels', 'criterionid', $criteria);
        // delete critera
        $DB->delete_records_list('gradingform_dukrub_criteria', 'id', $criteria);
    }

    /**
     * If instanceid is specified and grading instance exists and it is created by this rater for
     * this item, this instance is returned.
     * If there exists a draft for this raterid+itemid, take this draft (this is the change from parent)
     * Otherwise new instance is created for the specified rater and itemid
     *
     * @param int $instanceid
     * @param int $raterid
     * @param int $itemid
     * @return gradingform_instance
     */
    public function get_or_create_instance($instanceid, $raterid, $itemid) {
        global $DB;
        if ($instanceid &&
                $instance = $DB->get_record('grading_instances', array('id'  => $instanceid, 'raterid' => $raterid, 'itemid' => $itemid), '*', IGNORE_MISSING)) {
            return $this->get_instance($instance);
        }
        if ($itemid && $raterid) {
            $params = array('definitionid' => $this->definition->id, 'raterid' => $raterid, 'itemid' => $itemid);
            if ($rs = $DB->get_records('grading_instances', $params, 'timemodified DESC', '*', 0, 1)) {
                $record = reset($rs);
                $currentinstance = $this->get_current_instance($raterid, $itemid);
                if ($record->status == gradingform_dukrub_instance::INSTANCE_STATUS_INCOMPLETE &&
                        (!$currentinstance || $record->timemodified > $currentinstance->get_data('timemodified'))) {
                    $record->isrestored = true;
                    return $this->get_instance($record);
                }
            }
        }
        return $this->create_instance($raterid, $itemid);
    }

    /**
     * Returns html code to be included in student's feedback.
     *
     * @param moodle_page $page
     * @param int $itemid
     * @param array $gradinginfo result of function grade_get_grades
     * @param string $defaultcontent default string to be returned if no active grading is found
     * @param boolean $cangrade whether current user has capability to grade in this context
     * @return string
     */
    public function render_grade($page, $itemid, $gradinginfo, $defaultcontent, $cangrade) {
        return $this->get_renderer($page)->display_instances($this->get_active_instances($itemid), $defaultcontent, $cangrade);
    }

    // ///// full-text search support /////////////////////////////////////////////

    /**
     * Prepare the part of the search query to append to the FROM statement
     *
     * @param string $gdid the alias of grading_definitions.id column used by the caller
     * @return string
     */
    public static function sql_search_from_tables($gdid) {
        return " LEFT JOIN {gradingform_dukrub_criteria} rc ON (rc.definitionid = $gdid)
                 LEFT JOIN {gradingform_dukrub_levels} rl ON (rl.criterionid = rc.id)";
    }

    /**
     * Prepare the parts of the SQL WHERE statement to search for the given token
     *
     * The returned array cosists of the list of SQL comparions and the list of
     * respective parameters for the comparisons. The returned chunks will be joined
     * with other conditions using the OR operator.
     *
     * @param string $token token to search for
     * @return array
     */
    public static function sql_search_where($token) {
        global $DB;

        $subsql = array();
        $params = array();

        // search in dukrub criteria description
        $subsql[] = $DB->sql_like('rc.description', '?', false, false);
        $params[] = '%'.$DB->sql_like_escape($token).'%';

        // search in dukrub levels definition
        $subsql[] = $DB->sql_like('rl.definition', '?', false, false);
        $params[] = '%'.$DB->sql_like_escape($token).'%';

        return array($subsql, $params);
    }

    /**
     * Calculates and returns the possible minimum and maximum score (in points) for this dukrub
     *
     * @return array
     */
    public function get_min_max_score() {
        if (!$this->is_form_available()) {
            return null;
        }
        $returnvalue = array('minscore' => 0, 'maxscore' => 0);
        foreach ($this->get_definition()->dukrub_criteria as $id => $criterion) {
            $scores = array();
            foreach ($criterion['levels'] as $level) {
                $scores[] = $level['score'];
            }
            sort($scores);
            $returnvalue['minscore'] += $scores[0];
            $returnvalue['maxscore'] += $scores[sizeof($scores)-1];
        }
        return $returnvalue;
    }

    /**
     * @return array An array containing a single key/value pair with the 'dukrub_criteria' external_multiple_structure.
     * @see gradingform_controller::get_external_definition_details()
     * @since Moodle 2.5
     */
    public static function get_external_definition_details() {
        $dukrub_criteria = new external_multiple_structure(
            new external_single_structure(
                array(
                   'id'   => new external_value(PARAM_INT, 'criterion id', VALUE_OPTIONAL),
                   'sortorder' => new external_value(PARAM_INT, 'sortorder', VALUE_OPTIONAL),
                   'description' => new external_value(PARAM_RAW, 'description', VALUE_OPTIONAL),
                   'descriptionformat' => new external_format_value('description', VALUE_OPTIONAL),
                   'levels' => new external_multiple_structure(
                                   new external_single_structure(
                                       array(
                                        'id' => new external_value(PARAM_INT, 'level id', VALUE_OPTIONAL),
                                        'score' => new external_value(PARAM_FLOAT, 'score', VALUE_OPTIONAL),
                                        'definition' => new external_value(PARAM_RAW, 'definition', VALUE_OPTIONAL),
                                        'definitionformat' => new external_format_value('definition', VALUE_OPTIONAL)
                                       )
                                  ), 'levels', VALUE_OPTIONAL
                              )
                   )
              ), 'definition details', VALUE_OPTIONAL
        );
        return array('dukrub_criteria' => $dukrub_criteria);
    }

    /**
     * Returns an array that defines the structure of the dukrub's filling. This function is used by
     * the web service function core_grading_external::get_gradingform_instances().
     *
     * @return An array containing a single key/value pair with the 'criteria' external_multiple_structure
     * @see gradingform_controller::get_external_instance_filling_details()
     * @since Moodle 2.6
     */
    public static function get_external_instance_filling_details() {
        $criteria = new external_multiple_structure(
            new external_single_structure(
                array(
                    'id' => new external_value(PARAM_INT, 'filling id'),
                    'criterionid' => new external_value(PARAM_INT, 'criterion id'),
                    'levelid' => new external_value(PARAM_INT, 'level id', VALUE_OPTIONAL),
                    'leveloverwrite' => new external_value(PARAM_INT, 'leveloverwrite'),
                    'remark' => new external_value(PARAM_RAW, 'remark', VALUE_OPTIONAL),
                    'remarkformat' => new external_format_value('remark', VALUE_OPTIONAL)
                )
            ), 'filling', VALUE_OPTIONAL
        );
        return array ('criteria' => $criteria);
    }

}

/**
 * Class to manage one dukrub grading instance.
 *
 * Stores information and performs actions like update, copy, validate, submit, etc.
 *
 * @package    gradingform_dukrub
 * @copyright  2011 Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class gradingform_dukrub_instance extends gradingform_instance {

    /** @var array stores the dukrub, has two keys: 'criteria' and 'options' */
    protected $dukrub;

    /**
     * Deletes this (INCOMPLETE) instance from database.
     */
    public function cancel() {
        global $DB;
        parent::cancel();
        $DB->delete_records('gradingform_dukrub_fillings', array('instanceid' => $this->get_id()));
    }

    /**
     * Duplicates the instance before editing (optionally substitutes raterid and/or itemid with
     * the specified values)
     *
     * @param int $raterid value for raterid in the duplicate
     * @param int $itemid value for itemid in the duplicate
     * @return int id of the new instance
     */
    public function copy($raterid, $itemid) {
        global $DB;
        $instanceid = parent::copy($raterid, $itemid);
        $currentgrade = $this->get_dukrub_filling();
        foreach ($currentgrade['criteria'] as $criterionid => $record) {
            $params = array('instanceid' => $instanceid, 'criterionid' => $criterionid,
                'levelid' => $record['levelid'], 'remark' => $record['remark'], 'leveloverwrite' => $record['leveloverwrite'], 'remarkformat' => $record['remarkformat']);
            $DB->insert_record('gradingform_dukrub_fillings', $params);
        }
        return $instanceid;
    }

    /**
     * Determines whether the submitted form was empty.
     *
     * @param array $elementvalue value of element submitted from the form
     * @return boolean true if the form is empty
     */
    public function is_empty_form($elementvalue) {
        $criteria = $this->get_controller()->get_definition()->dukrub_criteria;

        foreach ($criteria as $id => $criterion) {
            if (isset($elementvalue['criteria'][$id]['levelid'])
                    || !empty($elementvalue['criteria'][$id]['remark'])) {
                return false;
            }
        }
        return true;
    }

    /**
     * Removes the attempt from the gradingform_guide_fillings table
     * @param array $data the attempt data
     */
    public function clear_attempt($data) {
        global $DB;

        foreach ($data['criteria'] as $criterionid => $record) {
            $DB->delete_records('gradingform_dukrub_fillings',
                array('criterionid' => $criterionid, 'instanceid' => $this->get_id()));
        }
    }

    /**
     * Validates that dukrub is fully completed and contains valid grade on each criterion
     *
     * @param array $elementvalue value of element as came in form submit
     * @return boolean true if the form data is validated and contains no errors
     */
    public function validate_grading_element($elementvalue) {
        $criteria = $this->get_controller()->get_definition()->dukrub_criteria;
        
        if (!isset($elementvalue['criteria']) || !is_array($elementvalue['criteria']) || sizeof($elementvalue['criteria']) < sizeof($criteria)) {
            return false;
        }

        foreach ($criteria as $id => $criterion) {
            
            $levelid = $elementvalue['criteria'][$id]['levelid'];
            if (!isset($levelid) || !array_key_exists($levelid, $criterion['levels'])) return false;

            $value = $elementvalue['criteria'][$id]['leveloverwrite'];
            if (is_array($value)) $value = $value[$levelid];

            // Validate all inputs are numeric.
            if (!is_numeric($value)) return false;

            // Check if selected levelid is smallest, if so we can't go smaller.
            if ($levelid == min(array_keys($criterion['levels']))) $minscore = $criterion['levels'][$levelid]['score'];
            else $minscore = $criterion['levels'][$levelid-1]['score'];

            // Check if selected levelid is biggest, if so we can't go bigger.
            if ($levelid == max(array_keys($criterion['levels']))) $maxscore = $criterion['levels'][$levelid]['score'];
            else $maxscore = $criterion['levels'][$levelid+1]['score'];

            // Check if value is between prior levelscore and next levelscore.
            if ($value < $minscore || $value > $maxscore) return false;
        }
        return true;
    }


   /**
     * Validates that, if leveloverwrites are set, they are within a specified range.
     *
     * @param array $elementvalue value of element as came in form submit
     * @return boolean true if leveloverwrites are ok.
     */
    public function validate_leveloverwrite($elementvalue) {
        $criteria = $this->get_controller()->get_definition()->dukrub_criteria;
        foreach ($criteria as $id => $criterion) {
            
            $levelid = $elementvalue['criteria'][$id]['levelid'];
            if (!isset($levelid) || !array_key_exists($levelid, $criterion['levels'])) return false;

            $value = $elementvalue['criteria'][$id]['leveloverwrite'];
            if (is_array($value)) $value = $value[$levelid]; // Special case if faulty values.

            // Check if selected levelid is smallest, if so we can't go smaller.
            if ($levelid == min(array_keys($criterion['levels']))) $minscore = $criterion['levels'][$levelid]['score'];
            else $minscore = $criterion['levels'][$levelid-1]['score'];

            // Check if selected levelid is biggest, if so we can't go bigger.
            if ($levelid == max(array_keys($criterion['levels']))) $maxscore = $criterion['levels'][$levelid]['score'];
            else $maxscore = $criterion['levels'][$levelid+1]['score'];

            // Check if value is between prior levelscore and next levelscore.
            if ($value < $minscore || $value > $maxscore) return false;
        }
        return true;
    }
    
    
    /**
     * Retrieves from DB and returns the data how this dukrub was filled
     *
     * @param boolean $force whether to force DB query even if the data is cached
     * @return array
     */
    public function get_dukrub_filling($force = false) {
        global $DB;
        if ($this->dukrub === null || $force) {
            $records = $DB->get_records('gradingform_dukrub_fillings', array('instanceid' => $this->get_id()));
            $this->dukrub = array('criteria' => array());
            foreach ($records as $record) {
                $this->dukrub['criteria'][$record->criterionid] = (array)$record;
            }
        }
        return $this->dukrub;
    }

    /**
     * Updates the instance with the data received from grading form. This function may be
     * called via AJAX when grading is not yet completed, so it does not change the
     * status of the instance.
     *
     * @param array $data
     */
    public function update($data) {
        global $DB;
        $currentgrade = $this->get_dukrub_filling();
        parent::update($data);
        foreach ($data['criteria'] as $criterionid => $record) {

            if (!array_key_exists($criterionid, $currentgrade['criteria'])) {
				
                $newrecord = array('instanceid' => $this->get_id(), 'criterionid' => $criterionid,
                    'levelid' => $record['levelid'], 'remarkformat' => FORMAT_MOODLE);
                if (isset($record['remark'])) {
                    $newrecord['remark'] = $record['remark'];
                }
                
                // DUKrub always safes leveloverwrites, even if default values are picked.
                $newrecord['leveloverwrite'] = $record['leveloverwrite'][$record['levelid']];
                    
                $DB->insert_record('gradingform_dukrub_fillings', $newrecord);
            } else {
                $newrecord = array('id' => $currentgrade['criteria'][$criterionid]['id']);
                
                foreach (array('levelid', 'remark'/*, 'remarkformat' */) as $key) {
                    // TODO MDL-31235 format is not supported yet
                    if (isset($record[$key]) && $currentgrade['criteria'][$criterionid][$key] != $record[$key]) {
                        $newrecord[$key] = $record[$key];
                    }
                }

                // DUKrub always safes leveloverwrites, even if default values are picked.
                $newrecord['leveloverwrite'] = $record['leveloverwrite'][$record['levelid']];
                
                if (count($newrecord) > 1) {
                    $DB->update_record('gradingform_dukrub_fillings', $newrecord);
                }
            }
        }
        foreach ($currentgrade['criteria'] as $criterionid => $record) {
            if (!array_key_exists($criterionid, $data['criteria'])) {
                $DB->delete_records('gradingform_dukrub_fillings', array('id' => $record['id']));
            }
        }
        $this->get_dukrub_filling(true);
    }

    /**
     * Calculates the grade to be pushed to the gradebook
     *
     * @return float|int the valid grade from $this->get_controller()->get_grade_range()
     */
    public function get_grade() {
        $grade = $this->get_dukrub_filling();

        if (!($scores = $this->get_controller()->get_min_max_score()) || $scores['maxscore'] <= $scores['minscore']) {
            return -1;
        }

        $graderange = array_keys($this->get_controller()->get_grade_range());
        if (empty($graderange)) {
            return -1;
        }
        sort($graderange);
        $mingrade = $graderange[0];
        $maxgrade = $graderange[sizeof($graderange) - 1];

        $curscore = 0;
        foreach ($grade['criteria'] as $id => $record) {
            // DUKrub uses only the value of leveloverwrite to calculate scores.
            $curscore += $record['leveloverwrite'];
        }

        $allowdecimals = $this->get_controller()->get_allow_grade_decimals();
        $options = $this->get_controller()->get_options();

        if ($options['lockzeropoints']) {
            // Grade calculation method when 0-level is locked.
            $grade = max($mingrade, $curscore / $scores['maxscore'] * $maxgrade);
            return $allowdecimals ? $grade : round($grade, 0);
        } else {
            // Alternative grade calculation method.
            $gradeoffset = ($curscore - $scores['minscore']) / ($scores['maxscore'] - $scores['minscore']) * ($maxgrade - $mingrade);
            return ($allowdecimals ? $gradeoffset : round($gradeoffset, 0)) + $mingrade;
        }
    }

    /**
     * Returns html for form element of type 'grading'.
     *
     * @param moodle_page $page
     * @param MoodleQuickForm_grading $gradingformelement
     * @return string
     */
    public function render_grading_element($page, $gradingformelement) {
        global $USER;
        if (!$gradingformelement->_flagFrozen) {
            $module = array('name'=>'gradingform_dukrub', 'fullpath'=>'/grade/grading/form/dukrub/js/dukrub.js');
            $page->requires->js_init_call('M.gradingform_dukrub.init', array(array('name' => $gradingformelement->getName())), true, $module);
            $mode = gradingform_dukrub_controller::DISPLAY_EVAL;
        } else {
            if ($gradingformelement->_persistantFreeze) {
                $mode = gradingform_dukrub_controller::DISPLAY_EVAL_FROZEN;
            } else {
                $mode = gradingform_dukrub_controller::DISPLAY_REVIEW;
            }
        }
        $criteria = $this->get_controller()->get_definition()->dukrub_criteria;
        $options = $this->get_controller()->get_options();
        $value = $gradingformelement->getValue(); // Get value of previews submit.
        $html = '';

        if ($value === null) {
            $value = $this->get_dukrub_filling(); // We get fillings from db
        } else {
            if (!$this->validate_grading_element($value)) {
                $html .= html_writer::tag('div', get_string('dukrubnotcompleted', 'gradingform_dukrub'), array('class' => 'gradingform_dukrub-error'));
            }
            // DUKrub validates if score is set properly.
            if (!$this->validate_leveloverwrite($value)) {
                $html .= html_writer::tag('div', get_string('dukrubfailedleveloverwrite', 'gradingform_dukrub'), array('class' => 'gradingform_dukrub-error'));
            }
        }
        

        $currentinstance = $this->get_current_instance();
        if ($currentinstance && $currentinstance->get_status() == gradingform_instance::INSTANCE_STATUS_NEEDUPDATE) {
            $html .= html_writer::div(get_string('needregrademessage', 'gradingform_dukrub'), 'gradingform_dukrub-regrade',
                                      array('role' => 'alert'));
        }
        $haschanges = false;
        if ($currentinstance) {
            $curfilling = $currentinstance->get_dukrub_filling();
            foreach ($curfilling['criteria'] as $criterionid => $curvalues) {
                $value['criteria'][$criterionid]['savedlevelid'] = $curvalues['levelid'];
                $newremark = null;
                $newlevelid = null;
                if (isset($value['criteria'][$criterionid]['remark'])) $newremark = $value['criteria'][$criterionid]['remark'];
                if (isset($value['criteria'][$criterionid]['levelid'])) $newlevelid = $value['criteria'][$criterionid]['levelid'];
                // if (isset($value['criteria'][$criterionid]['leveloverwrite'])) $newlevelid = $value['leveloverwrite'][$criterionid]['leveloverwrite'];
                if ($newlevelid != $curvalues['levelid'] || $newremark != $curvalues['remark']) {
                    $haschanges = true;
                }
            }
        }
        if ($this->get_data('isrestored') && $haschanges) {
            $html .= html_writer::tag('div', get_string('restoredfromdraft', 'gradingform_dukrub'), array('class' => 'gradingform_dukrub-restored'));
        }
        if (!empty($options['showdescriptionteacher'])) {
            $html .= html_writer::tag('div', $this->get_controller()->get_formatted_description(), array('class' => 'gradingform_dukrub-description'));
        }
        $html .= $this->get_controller()->get_renderer($page)->display_dukrub($criteria, $options, $mode, $gradingformelement->getName(), $value);
        return $html;
    }
}
