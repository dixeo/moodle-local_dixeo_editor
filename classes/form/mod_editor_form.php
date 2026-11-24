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

        if (!empty($this->_customdata['editorsessionid'])) {
            $mform->addElement('hidden', 'editorsessionid', (int) $this->_customdata['editorsessionid']);
            $mform->setType('editorsessionid', PARAM_INT);
        }

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

    /**
     * Load data, converting the content to HTML because the Dixeo editor only runs in TinyMCE.
     *
     * @param \stdClass|array $data Form data.
     */
    public function set_data($data) {
        $fieldname = $this->_customdata['fieldname'];
        $data = (array) $data;
        if (isset($data[$fieldname]['text'], $data[$fieldname]['format'])) {
            $data[$fieldname]['text'] = \local_dixeo_editor\local\tiny_editor::to_html(
                (string) $data[$fieldname]['text'],
                $data[$fieldname]['format']
            );
            $data[$fieldname]['format'] = FORMAT_HTML;
        }
        parent::set_data($data);
    }

    /**
     * Display the form with TinyMCE, whatever editor the user prefers (the AI tools need it).
     */
    public function display() {
        \local_dixeo_editor\local\tiny_editor::run(function () {
            parent::display();
        });
    }
}
