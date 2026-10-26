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
 * Dixeo editor markup for embedding in another activity's modal.
 *
 * @package    local_dixeo_editor
 * @copyright  2026 Edunao SAS
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_dixeo_editor\output;

/**
 * Editor form and AI prompt bar without the full-page title, OK, and Cancel.
 */
class embedded_editor {
    /**
     * Render the editor for one slideshow slide and open an editor session.
     *
     * @param \stdClass $cm Course module record.
     * @param int $slideid Slideshow slide id.
     * @param \context_module $context Module context.
     * @return array{html: string, sessionid: int} Editor markup and session id.
     */
    public static function render(\stdClass $cm, int $slideid, \context_module $context): array {
        global $CFG, $DB, $OUTPUT, $USER;

        require_once($CFG->dirroot . '/mod/page/locallib.php');

        \local_dixeo_editor\local\editor_capability::require_edit_module($context);

        $factory = new \local_dixeo_editor\activity\activity_adapter_factory($DB);
        $activityadapter = $factory->create((int) $cm->id, $slideid);
        $editorsession = \local_dixeo_editor\local\editor_session_repository::get_or_create_active(
            (int) $cm->id,
            $slideid,
            (int) $USER->id
        );

        $editoroptions = page_get_editor_options($context);
        $fieldname = 'modulecontent';
        $mform = new \local_dixeo_editor\form\mod_editor_form(null, [
            'cmid' => $cm->id,
            'editoroptions' => $editoroptions,
            'fieldname' => $fieldname,
            'editorsessionid' => (int) $editorsession->id,
        ]);

        $draftitemid = 0;
        $mform->set_data([
            $fieldname => [
                'format' => $activityadapter->get_content_format(),
                'text' => $activityadapter->prepare_draft_area($draftitemid, $editoroptions),
                'itemid' => $draftitemid,
            ],
        ]);

        $html = $OUTPUT->render_from_template('local_dixeo_editor/mod_content_editor', [
            'formhtml' => $mform->render(),
            'activityname' => $cm->name,
            'cancelurl' => '',
            'showheader' => false,
        ]);

        return [
            'html' => $html,
            'sessionid' => (int) $editorsession->id,
        ];
    }
}
