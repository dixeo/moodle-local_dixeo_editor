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

namespace local_dixeo_editor\output;

use core\output\html_writer;
use moodle_url;

/**
 * Notice shown instead of the editor when TinyMCE is disabled on the site, with the actions left to the user.
 *
 * @package    local_dixeo_editor
 * @copyright  2026 Edunao SAS (contact@edunao.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tiny_unavailable {
    /**
     * Render the notice and its actions.
     *
     * @param moodle_url $backurl Where the user came from (activity, section or slide list).
     * @return string HTML.
     */
    public static function render(moodle_url $backurl): string {
        global $OUTPUT;

        $isadmin = has_capability('moodle/site:config', \context_system::instance());
        $action = get_string($isadmin ? 'tinyunavailable_admin' : 'tinyunavailable_user', 'local_dixeo_editor');
        $message = html_writer::tag('p', get_string('tinyunavailable', 'local_dixeo_editor'), ['class' => 'mb-1'])
            . html_writer::tag('p', $action, ['class' => 'mb-0']);

        $buttons = [];
        if ($isadmin) {
            $buttons[] = $OUTPUT->single_button(
                new moodle_url('/admin/settings.php', ['section' => 'manageeditors']),
                get_string('editorsettings', 'editor'),
                'get',
                ['type' => \single_button::BUTTON_PRIMARY]
            );
        }
        $buttons[] = $OUTPUT->single_button($backurl, get_string('back'), 'get');

        return $OUTPUT->notification($message, \core\output\notification::NOTIFY_WARNING, false)
            . html_writer::div(implode('', $buttons), 'd-flex flex-wrap gap-2');
    }
}
