<?php
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

class page_activity_adapter extends base_activity_adapter {

    public static function resolve_record_id(stdClass $cm, ?int $subid): int {
        return (int) $cm->instance;
    }

    protected function get_module_name(): string {
        return 'page';
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
        return 'page';
    }

    public function get_redirect_url(int $courseid, int $cmid): moodle_url {
        return new moodle_url('/mod/page/view.php', ['id' => $cmid]);
    }
}
