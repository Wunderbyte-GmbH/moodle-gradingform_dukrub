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
 * Language file for plugin gradingform_dukrub
 *
 * @package    gradingform_dukrub
 * @copyright  2018 michael pollak <moodle@michaelpollak.org>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['addcriterion'] = 'Add criterion';
$string['alwaysshowdefinition'] = 'Allow users to preview dukrub (otherwise it will only be displayed after grading)';
$string['backtoediting'] = 'Back to editing';
$string['confirmdeletecriterion'] = 'Are you sure you want to delete this criterion?';
$string['confirmdeletelevel'] = 'Are you sure you want to delete this level?';
$string['criterion'] = 'Criterion {$a}';
$string['criterionaddlevel'] = 'Add level';
$string['criteriondelete'] = 'Delete criterion';
$string['criterionduplicate'] = 'Duplicate criterion';
$string['criterionempty'] = 'Click to edit criterion';
$string['criterionmovedown'] = 'Move down';
$string['criterionmoveup'] = 'Move up';
$string['criterionremark'] = 'Remark for criterion {$a->description}: {$a->remark}';
$string['definedukrub'] = 'Define dukrub';
$string['description'] = 'Description';
$string['enableremarks'] = 'Allow grader to add text remarks for each criterion';
$string['err_mintwolevels'] = 'Each criterion must have at least two levels';
$string['err_nocriteria'] = 'Dukrub must contain at least one criterion';
$string['err_nodefinition'] = 'Level definition can not be empty';
$string['err_nodescription'] = 'Criterion description can not be empty';
$string['err_novariations'] = 'Criterion levels cannot all be worth the same number of points';
$string['err_scoreformat'] = 'Number of points for each level must be a valid number';
$string['err_totalscore'] = 'Maximum number of points possible when graded by the dukrub must be more than zero';
$string['gradingof'] = '{$a} grading';
$string['level'] = 'Level {$a->definition}, {$a->score} points.';
$string['leveldelete'] = 'Delete level {$a}';
$string['leveldefinition'] = 'Level {$a} definition';
$string['levelempty'] = 'Click to edit level';
$string['levelsgroup'] = 'Levels group';
$string['lockzeropoints'] = 'Calculate grade based on the dukrub having a minimum score of 0';
$string['lockzeropoints_help'] = 'This setting only applies if the sum of the minimum number of points for each criterion is greater than 0. If ticked, the minimum achievable grade for the dukrub will be greater than 0. If unticked, the minimum possible score for the dukrub will be mapped to the minimum grade available for the activity (which is 0 unless a scale is used).';
$string['name'] = 'Name';
$string['needregrademessage'] = 'The dukrub definition was changed after this student had been graded. The student can not see this dukrub until you check the dukrub and update the grade.';
$string['pluginname'] = 'Dukrub';
$string['previewdukrub'] = 'Preview dukrub';
$string['regrademessage1'] = 'You are about to save changes to a dukrub that has already been used for grading. Please indicate if existing grades need to be reviewed. If you set this then the dukrub will be hidden from students until their item is regraded.';
$string['regrademessage5'] = 'You are about to save significant changes to a dukrub that has already been used for grading. The gradebook value will be unchanged, but the dukrub will be hidden from students until their item is regraded.';
$string['regradeoption0'] = 'Do not mark for regrade';
$string['regradeoption1'] = 'Mark for regrade';
$string['restoredfromdraft'] = 'NOTE: The last attempt to grade this person was not saved properly so draft grades have been restored. If you want to cancel these changes use the \'Cancel\' button below.';
$string['dukrub'] = 'Dukrub';
$string['dukrubmapping'] = 'Score to grade mapping rules';
$string['dukrubmappingexplained'] = 'The minimum possible score for this dukrub is <b>{$a->minscore} points</b>. It will be converted to the minimum grade available for the activity (which is 0 unless a scale is used). The maximum score of <b>{$a->maxscore} points</b> will be converted to the maximum grade. Intermediate scores will be converted respectively.

If a scale is used for grading, the score will be rounded and converted to the scale elements as if they were consecutive integers.

This grade calculation may be changed by editing the form and ticking the box \'Calculate grade based on the dukrub having a minimum score of 0\'.';
// TODO: Translate this to german.
$string['dukrubnotcompleted'] = 'Please choose something for each criterion<br>We validate that you only input numeric values.<br>Each criterion must have one selected (green) level.';
$string['dukrubfailedleveloverwrite'] = 'The score you entered is not valid. No level can have a score that is less then the previous level or more then the next.';
$string['dukruboptions'] = 'Dukrub options';
$string['dukrubstatus'] = 'Current dukrub status';
$string['save'] = 'Save';
$string['savedukrub'] = 'Save dukrub and make it ready';
$string['savedukrubdraft'] = 'Save as draft';
$string['scoreinputforlevel'] = 'Score input for level {$a}';
$string['scorepostfix'] = '{$a}points';
$string['showdescriptionstudent'] = 'Display dukrub description to those being graded';
$string['showdescriptionteacher'] = 'Display dukrub description during evaluation';
$string['showremarksstudent'] = 'Show remarks to those being graded';
$string['showscorestudent'] = 'Display points for each level to those being graded';
$string['showscoreteacher'] = 'Display points for each level during evaluation';
$string['sortlevelsasc'] = 'Sort order for levels:';
$string['sortlevelsasc0'] = 'Descending by number of points';
$string['sortlevelsasc1'] = 'Ascending by number of points';
$string['zerolevelsabsent'] = 'Warning: The minimum possible score for this dukrub is not 0; this can result in unexpected grades for the activity. To avoid this, each criterion should have a level with 0 points.<br>
This warning may be ignored if a scale is used for grading, and the minimum levels in the dukrub correspond to the minimum value of the scale.';
