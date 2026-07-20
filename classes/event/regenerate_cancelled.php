<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Event when an async module content regeneration job is cancelled.
 *
 * @package    local_dixeo_editor
 * @copyright  2026 Edunao SAS (contact@edunao.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_dixeo_editor\event;

/**
 * Fired after a regenerate job is successfully cancelled from the editor.
 *
 * Includes job id and course module only — no prompts or content.
 */
class regenerate_cancelled extends \core\event\base {
    /**
     * Init method.
     */
    protected function init(): void {
        $this->data['crud'] = 'u';
        $this->data['edulevel'] = self::LEVEL_TEACHING;
        $this->data['objecttable'] = 'course_modules';
    }

    /**
     * Localised event name.
     *
     * @return string
     */
    public static function get_name(): string {
        return get_string('eventregeneratecancelled', 'local_dixeo_editor');
    }

    /**
     * Non-localised description for logs.
     *
     * @return string
     */
    public function get_description(): string {
        $jobid = clean_param((string) ($this->other['jobid'] ?? ''), PARAM_TEXT);
        return get_string('eventregeneratecancelleddesc', 'local_dixeo_editor', (object) [
            'userid' => $this->userid,
            'cmid' => $this->objectid,
            'courseid' => $this->courseid,
            'jobid' => $jobid,
        ]);
    }

    /**
     * Relevant URL.
     *
     * @return \moodle_url
     */
    public function get_url() {
        return new \moodle_url('/local/dixeo_editor/content_edition.php', ['cmid' => $this->objectid]);
    }

    /**
     * Create an event for a cancelled regenerate job.
     *
     * @param \stdClass $cm Course module from get_coursemodule_from_id.
     * @param int $userid User who cancelled regeneration.
     * @param string $jobid Remote job UUID.
     * @return self
     */
    public static function create_for_cm(\stdClass $cm, int $userid, string $jobid): self {
        return self::create([
            'context' => \context_module::instance((int) $cm->id),
            'objectid' => (int) $cm->id,
            'userid' => $userid,
            'courseid' => (int) $cm->course,
            'other' => [
                'jobid' => $jobid,
            ],
        ]);
    }

    /**
     * Custom validation.
     */
    protected function validate_data(): void {
        parent::validate_data();
        if (empty($this->other['jobid'])) {
            throw new \coding_exception('The \'jobid\' value must be set in other.');
        }
    }

    /**
     * Object id mapping for backup/restore.
     *
     * @return array
     */
    public static function get_objectid_mapping(): array {
        return ['db' => 'course_modules', 'restore' => 'course_module'];
    }

    /**
     * Other mapping for backup/restore.
     *
     * @return false
     */
    public static function get_other_mapping() {
        return false;
    }
}
