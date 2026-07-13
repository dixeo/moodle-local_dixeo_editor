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
 * Activity adapter for Page modules.
 *
 * @package    local_dixeo_editor
 * @copyright  2025 Edunao SAS (contact@edunao.com)
 * @author     Pierre FACQ <pierre.facq@edunao.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_dixeo_editor\activity;

use moodle_url;
use stdClass;

/**
 * Activity adapter for Page modules.
 */
class page_activity_adapter extends base_activity_adapter {
    /**
     * Resolve the page instance id from the course module.
     *
     * @param stdClass $cm Course module record.
     * @param int|null $subid Unused for pages.
     * @return int
     */
    public static function resolve_record_id(stdClass $cm, ?int $subid): int {
        return (int) $cm->instance;
    }

    /**
     * Return the module name.
     *
     * @return string
     */
    protected function get_module_name(): string {
        return 'page';
    }

    /**
     * Return the DB column name for the content field.
     *
     * @return string
     */
    public function get_content_field(): string {
        return 'content';
    }

    /**
     * Return the DB column name for the format field.
     *
     * @return string
     */
    protected function get_format_field(): string {
        return 'contentformat';
    }

    /**
     * Return the file area name used for file storage.
     *
     * @return string
     */
    protected function get_file_area(): string {
        return 'content';
    }

    /**
     * Return the DB table name for this activity type.
     *
     * @return string
     */
    protected function get_table_name(): string {
        return 'page';
    }

    /**
     * Return the redirect URL after save or cancel.
     *
     * @param int $courseid Course id.
     * @param int $cmid Course module id.
     * @return moodle_url
     */
    public function get_redirect_url(int $courseid, int $cmid): moodle_url {
        return new moodle_url('/mod/page/view.php', ['id' => $cmid]);
    }
}
