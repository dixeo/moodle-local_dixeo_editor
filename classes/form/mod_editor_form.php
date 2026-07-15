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
 * AI-powered content editor form for Page activities.
 *
 * @package    local_dixeo_editor
 * @copyright  2025 Edunao SAS (contact@edunao.com)
 * @author     Pierre FACQ <pierre.facq@edunao.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_dixeo_editor\form;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/lib/formslib.php');

/**
 * Moodle form for the Dixeo AI content editor.
 */
class mod_editor_form extends \moodleform {
    /**
     * Define form fields.
     */
    protected function definition() {
        $mform = $this->_form;
        $editoroptions = $this->_customdata['editoroptions'];
        // The field name (either 'page' for pages or 'intro' for labels) is provided.
        $fieldname = $this->_customdata['fieldname'];

        $mform
            ->setAttributes([
                'id' => 'mod_editor_form',
                'class' => 'd-flex flex-column flex-grow-1',
            ] + $mform->getAttributes());

        $mform->addElement('hidden', 'cmid', $this->_customdata['cmid']);
        $mform->setType('cmid', PARAM_INT);

        $slideid = optional_param('slideid', 0, PARAM_INT);
        if ($slideid) {
            $mform->addElement('hidden', 'slideid', $slideid);
            $mform->setType('slideid', PARAM_INT);
        }

        // Configure editor attributes.
        $editorattributes = [
            'name' => $fieldname,
            'class' => 'd-flex flex-column',
            'style' => 'min-width: 100%',
        ];
        $mform->addElement('editor', $fieldname, '', null, $editoroptions);
        $mform->setType($fieldname, PARAM_RAW);
        $mform->getElement($fieldname)->setAttributes($editorattributes);
    }
}
