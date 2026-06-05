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

namespace local_dixeo_editor\local;

defined('MOODLE_INTERNAL') || die();

/**
 * Module-context capability checks for Dixeo editor operations.
 *
 * @package    local_dixeo_editor
 * @copyright  2026 Edunao SAS
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class editor_capability {

    /**
     * Require manageactivities and local/dixeo:edit in the module context.
     *
     * @param \context_module $context
     * @return void
     */
    public static function require_edit_module(\context_module $context): void {
        require_capability('moodle/course:manageactivities', $context);
        require_capability('local/dixeo:edit', $context);
    }

    /**
     * Whether the user can use Dixeo editor features on the module.
     *
     * @param \context $context Module or compatible context.
     * @return bool
     */
    public static function can_edit_module(\context $context): bool {
        return has_capability('moodle/course:manageactivities', $context)
            && has_capability('local/dixeo:edit', $context);
    }
}
