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

use local_dixeo\service\image\content\editor_image_context;
use local_dixeo\service\image\content\editor_regenerate_service;
use local_dixeo_editor\activity\activity_adapter_factory;

/**
 * Builds {@see editor_image_context} from editor adapters.
 *
 * @package    local_dixeo_editor
 * @copyright  2026 Edunao SAS
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class editor_image_context_factory {
    /**
     * Build an image context for the given course module and editor session.
     *
     * @param int $cmid Course module ID.
     * @param int|null $subid Optional slide/sub-row ID.
     * @param int $sessionid Editor session ID.
     * @return editor_image_context
     */
    public static function from_cmid(int $cmid, ?int $subid, int $sessionid): editor_image_context {
        global $DB;

        $cm = get_coursemodule_from_id('', $cmid, 0, false, MUST_EXIST);
        $context = \context_module::instance($cm->id);
        $adapter = (new activity_adapter_factory($DB))->create($cmid, $subid);

        return editor_regenerate_service::build_image_context(
            $context,
            (int) $cm->course,
            (int) $cm->id,
            $adapter->get_modname(),
            'mod_' . $adapter->get_modname(),
            $adapter->get_file_area_name(),
            $adapter->get_file_item_id(),
            $adapter->get_content_field(),
            $adapter->get_shortcode_entity(),
            $adapter->get_record_id(),
            $sessionid
        );
    }
}
