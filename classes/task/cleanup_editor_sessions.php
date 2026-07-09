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

namespace local_dixeo_editor\task;

use local_dixeo\repository\image\job_repository;
use local_dixeo_editor\local\editor_session_repository;

/**
 * Discards stale editor sessions (abandoned tabs) and purges old session rows.
 *
 * @package    local_dixeo_editor
 * @copyright  2026 Edunao SAS
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class cleanup_editor_sessions extends \core\task\scheduled_task {
    /** @var int Active sessions untouched for this long are considered abandoned (24h). */
    public const STALE_ACTIVE_SECONDS = DAYSECS;

    /** @var int Terminal (saved/discarded) rows are deleted after this long (30 days). */
    public const PURGE_TERMINAL_SECONDS = 30 * DAYSECS;

    /**
     * Get a descriptive name for this task.
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('taskcleanupeditorsessions', 'local_dixeo_editor');
    }

    /**
     * Discard stale active sessions and purge old terminal rows.
     */
    public function execute(): void {
        global $DB;

        $this->discard_stale_active_sessions();

        $DB->delete_records_select(
            editor_session_repository::TABLE,
            'status <> :active AND timemodified < :cutoff',
            [
                'active' => editor_session_repository::STATUS_ACTIVE,
                'cutoff' => time() - self::PURGE_TERMINAL_SECONDS,
            ]
        );
    }

    /**
     * Mark abandoned active sessions as discarded and cancel their jobs.
     */
    private function discard_stale_active_sessions(): void {
        global $DB;

        $stale = $DB->get_records_select(
            editor_session_repository::TABLE,
            'status = :active AND timemodified < :cutoff',
            [
                'active' => editor_session_repository::STATUS_ACTIVE,
                'cutoff' => time() - self::STALE_ACTIVE_SECONDS,
            ]
        );

        foreach ($stale as $session) {
            try {
                $cm = get_coursemodule_from_id('', (int) $session->cmid, 0, false, IGNORE_MISSING);
                if ($cm) {
                    $context = \context_module::instance($cm->id);
                    editor_session_repository::discard((int) $session->id, (string) $cm->modname, $context);
                    continue;
                }
            } catch (\Throwable $e) {
                debugging('Editor session cleanup failed for session ' . $session->id . ': ' .
                    $e->getMessage(), DEBUG_DEVELOPER);
            }

            // Module gone (or discard failed): still stop the jobs and close the
            // session. Draft files are removed with the module context when the
            // module is deleted.
            $failed = job_repository::fail_session_jobs(
                (int) $session->id,
                get_string('editorsessionexpired', 'local_dixeo_editor')
            );
            foreach ($failed as $job) {
                \local_dixeo\service\image\job_orchestrator::cancel_remote((string) ($job->jobid ?? ''));
            }
            editor_session_repository::set_status((int) $session->id, editor_session_repository::STATUS_DISCARDED);
        }
    }
}
