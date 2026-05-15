<?php
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

class label_activity_adapter extends base_activity_adapter {

    protected function get_module_name(): string {
        return 'label';
    }

    public function get_content_field(): string {
        return 'intro';
    }

    protected function get_format_field(): string {
        return 'introformat';
    }

    protected function get_file_area(): string {
        return 'intro';
    }

    protected function get_table_name(): string {
        return 'label';
    }

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
