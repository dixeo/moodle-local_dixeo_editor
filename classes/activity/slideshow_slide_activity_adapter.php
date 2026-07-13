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
 * Activity adapter for a single slide of a Slideshow module.
 *
 * Targets a row in slideshow_slide identified by a sub-id (slide id) rather
 * than the module instance. Relies on base_activity_adapter's Template Method
 * hooks: per-slide file itemid via get_file_itemid(), and slideshow.revision
 * bump via after_save() for cache busting.
 *
 * @package    local_dixeo_editor
 * @copyright  2026 Edunao SAS (contact@edunao.com)
 * @author     Pierre FACQ <pierre.facq@edunao.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_dixeo_editor\activity;

use moodle_url;
use stdClass;

/**
 * Activity adapter for a single slideshow slide.
 */
class slideshow_slide_activity_adapter extends base_activity_adapter {
    /**
     * Resolve the slide row id from the course module and sub-id.
     *
     * @param stdClass $cm Course module record.
     * @param int|null $subid Slide row id.
     * @return int
     */
    public static function resolve_record_id(stdClass $cm, ?int $subid): int {
        if ($subid === null || $subid <= 0) {
            throw new \coding_exception('slideid is required for slideshow adapter');
        }
        return $subid;
    }

    /**
     * Ensure the slide row belongs to the authorized slideshow module instance.
     *
     * @param stdClass $cm Course module record.
     * @param int $slideid Slideshow slide row id.
     * @return void
     * @throws \moodle_exception When the slide is not part of this module.
     */
    public static function assert_belongs_to_cm(stdClass $cm, int $slideid): void {
        global $DB;

        $slide = $DB->get_record('slideshow_slide', ['id' => $slideid], 'slideshow', MUST_EXIST);
        if ((int) $slide->slideshow !== (int) $cm->instance) {
            throw new \moodle_exception('error:slidenotinslideshow', 'local_dixeo');
        }
    }

    /**
     * Return the module name.
     *
     * @return string
     */
    protected function get_module_name(): string {
        return 'slideshow';
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
        return 'slideshow_slide';
    }

    /**
     * Return the itemid used when reading/writing files for this slide.
     *
     * @return int
     */
    protected function get_file_itemid(): int {
        return (int) $this->record->id;
    }

    /**
     * Bump parent slideshow revision after save for cache busting.
     */
    protected function after_save(): void {
        $this->db->execute(
            'UPDATE {slideshow} SET revision = revision + 1, timemodified = ? WHERE id = ?',
            [time(), (int) $this->record->slideshow]
        );
    }

    /**
     * Return the redirect URL after save or cancel.
     *
     * @param int $courseid Course id.
     * @param int $cmid Course module id.
     * @return moodle_url
     */
    public function get_redirect_url(int $courseid, int $cmid): moodle_url {
        return new moodle_url('/mod/slideshow/slides.php', ['id' => $cmid]);
    }
}
