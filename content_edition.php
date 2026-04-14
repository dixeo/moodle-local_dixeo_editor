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
 * Content edition page for AI-powered module editing.
 *
 * @package    local_dixeo_editor
 * @copyright  2025 Edunao SAS (contact@edunao.com)
 * @author     Pierre FACQ <pierre.facq@edunao.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');
require_once($CFG->libdir . '/formslib.php');
require_once($CFG->libdir . '/filelib.php');
require_once($CFG->dirroot.'/mod/page/locallib.php');

// Get the course module id from parameters.
$cmid = required_param('cmid', PARAM_INT);
// Optional child record id for composite modules (e.g. slideshow → slide id).
$subid = optional_param('slideid', 0, PARAM_INT) ?: null;

// Retrieve the course module record using Moodle API.
$cm = get_coursemodule_from_id('', $cmid);
$modname = $cm->modname;
$context = context_module::instance($cm->id);
require_capability('moodle/course:manageactivities', $context);

$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
$PAGE->set_cm($cm, $course);
$urlparams = ['cmid' => $cmid] + ($subid !== null ? ['slideid' => $subid] : []);
$PAGE->set_url('/local/dixeo_editor/content_edition.php', $urlparams);
$PAGE->set_context($context);
$PAGE->set_title($cm->name);
$PAGE->add_body_class('limitedwidth');

// Instantiate the appropriate activity adapter.
$factory = new \local_dixeo_editor\activity\activity_adapter_factory($DB);
$activityadapter = $factory->create($cmid, $subid);

// Get editor options
$editoroptions = page_get_editor_options($context);

// Set the editor field name.
$fieldname = 'modulecontent';
// Instantiate the form passing the proper custom data.
$mform = new \local_dixeo_editor\form\mod_editor_form(null, [
    'cmid' => $cm->id,
    'editoroptions' => $editoroptions,
    'fieldname' => $fieldname,
]);

if ($mform->is_cancelled()) {
    redirect($activityadapter->get_redirect_url($course->id, $cmid));
} else if ($data = $mform->get_data()) {
    $content = $data->{$fieldname}['text'];
    $format  = $data->{$fieldname}['format'];
    $itemid  = $data->{$fieldname}['itemid'];

    // Save using the adapter.
    $activityadapter->save_content($content, $format, $itemid, $editoroptions);
    // Trigger update event.
    \core\event\course_module_updated::create_from_cm($cm)->trigger();
    // Rebuild course cache.
    rebuild_course_cache($course->id, true);
    // Redirect.
    redirect($activityadapter->get_redirect_url($course->id, $cmid));
} else {
    $data = [
        'id' => $cm->id,
        'content' => $activityadapter->get_content(),
        'contentformat' => $activityadapter->get_content_format(),
    ];
    $draftitemid = file_get_submitted_draft_itemid($fieldname);
    $data[$fieldname]['format'] = $data['contentformat'];
    $data[$fieldname]['text'] = $activityadapter->prepare_draft_area($draftitemid, $editoroptions);
    $data[$fieldname]['itemid'] = $draftitemid;
    $mform->set_data($data);
}

echo $OUTPUT->header();

ob_start();
$mform->display();
$formhtml = ob_get_clean();

echo $OUTPUT->render_from_template('local_dixeo_editor/mod_content_editor', [
    'formhtml' => $formhtml,
    'cmid' => $cm->id,
    'activityname' => $cm->name,
    'cancelurl' => $activityadapter->get_redirect_url($course->id, $cmid),
]);

$PAGE->requires->js_call_amd('local_dixeo_editor/mod_editor_form', 'init', [$cmid, $subid]);

echo $OUTPUT->footer();
