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
 * Activity adapter for Label modules.
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
 * Activity adapter for Label modules.
 */
class label_activity_adapter extends base_activity_adapter {
    /**
     * Resolve the label instance id from the course module.
     *
     * @param stdClass $cm Course module record.
     * @param int|null $subid Unused for labels.
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
        return 'label';
    }

    /**
     * Return the DB column name for the content field.
     *
     * @return string
     */
    public function get_content_field(): string {
        return 'intro';
    }

    /**
     * Return the DB column name for the format field.
     *
     * @return string
     */
    protected function get_format_field(): string {
        return 'introformat';
    }

    /**
     * Return the file area name used for file storage.
     *
     * @return string
     */
    protected function get_file_area(): string {
        return 'intro';
    }

    /**
     * Return the DB table name for this activity type.
     *
     * @return string
     */
    protected function get_table_name(): string {
        return 'label';
    }

    /**
     * Return the redirect URL after save or cancel.
     *
     * @param int $courseid Course id.
     * @param int $cmid Course module id.
     * @return moodle_url
     */
    public function get_redirect_url(int $courseid, int $cmid): moodle_url {
        // Labels have no dedicated view page; return to the course section that contains this label.
        try {
            $modinfo = get_fast_modinfo($courseid);
            $cm = $modinfo->get_cm($cmid);
            $sectionid = (int) $cm->sectionid;
            if ($sectionid <= 0) {
                return new moodle_url('/course/view.php', ['id' => $courseid]);
            }
            $url = new moodle_url('/course/section.php', ['id' => $sectionid]);
            $url->set_anchor('module-' . $cmid);
            return $url;
        } catch (\Throwable $e) {
            return new moodle_url('/course/view.php', ['id' => $courseid]);
        }
    }
}
