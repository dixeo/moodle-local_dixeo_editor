<?php
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

class slideshow_slide_activity_adapter extends base_activity_adapter {

    public static function resolve_record_id(stdClass $cm, ?int $subid): int {
        if ($subid === null || $subid <= 0) {
            throw new \coding_exception('slideid is required for slideshow adapter');
        }
        return $subid;
    }

    protected function get_module_name(): string {
        return 'slideshow';
    }

    public function get_content_field(): string {
        return 'content';
    }

    protected function get_format_field(): string {
        return 'contentformat';
    }

    protected function get_file_area(): string {
        return 'content';
    }

    protected function get_table_name(): string {
        return 'slideshow_slide';
    }

    protected function get_file_itemid(): int {
        return (int) $this->record->id;
    }

    protected function after_save(): void {
        // Bump parent slideshow.revision for cache busting.
        $this->db->execute(
            'UPDATE {slideshow} SET revision = revision + 1, timemodified = ? WHERE id = ?',
            [time(), (int) $this->record->slideshow]
        );
    }

    public function get_redirect_url(int $courseid, int $cmid): moodle_url {
        return new moodle_url('/mod/slideshow/slides.php', ['id' => $cmid]);
    }
}
