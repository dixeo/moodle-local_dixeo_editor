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
 * Privacy API: user preference metadata and external Dixeo API transfer declarations.
 *
 * @package    local_dixeo_editor
 * @copyright  2026 Edunao SAS
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_dixeo_editor\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\writer;

/**
 * Privacy provider for local_dixeo_editor.
 *
 * Declares the content-editor panel preference and ephemeral payloads sent to the Dixeo API.
 * The plugin does not persist AI requests or responses in its own database tables.
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\user_preference_provider {

    /**
     * Describe metadata stored or transmitted by this plugin.
     *
     * @param collection $collection The privacy metadata collection.
     * @return collection The updated collection.
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_user_preference(
            'local_dixeo_editor_content_panel_state',
            'privacy:metadata:preference:panel_layout'
        );

        $collection->add_external_location_link(
            'dixeo_api',
            [
                'instructions' => 'privacy:metadata:instructions',
                'context' => 'privacy:metadata:context',
                'courseId' => 'privacy:metadata:courseid',
                'moduleType' => 'privacy:metadata:moduletype',
                'namespace' => 'privacy:metadata:namespace',
            ],
            'privacy:metadata:externalpurpose'
        );

        return $collection;
    }

    /**
     * Export user preferences owned by this plugin.
     *
     * @param int $userid The user whose data is exported.
     * @return void
     */
    public static function export_user_preferences(int $userid): void {
        $layout = get_user_preferences('local_dixeo_editor_content_panel_state', null, $userid);
        if ($layout === null) {
            return;
        }

        writer::export_user_preference(
            'local_dixeo_editor',
            'local_dixeo_editor_content_panel_state',
            (string) $layout,
            get_string('privacy:metadata:preference:panel_layout', 'local_dixeo_editor')
        );
    }
}
