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

use local_dixeo\repository\image\job_repository;
use local_dixeo\service\image\content\editor_draft_fileareas;
use local_dixeo\service\image\job_orchestrator;

/**
 * CRUD for editor sessions and draft fileareas.
 *
 * @package    local_dixeo_editor
 * @copyright  2026 Edunao SAS
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class editor_session_repository {
    /** @var string Database table for editor sessions. */
    public const TABLE = 'local_dixeo_editor_session';

    /** @var string Session is open and may receive draft images. */
    public const STATUS_ACTIVE = 'active';

    /** @var string Session was finalized on successful save. */
    public const STATUS_SAVED = 'saved';

    /** @var string Session was discarded without saving. */
    public const STATUS_DISCARDED = 'discarded';

    /**
     * Return the active session for the user, creating one if needed.
     *
     * @param int $cmid Course module ID.
     * @param int|null $slideid Optional slide row ID.
     * @param int $userid User ID.
     * @return \stdClass
     */
    public static function get_or_create_active(int $cmid, ?int $slideid, int $userid): \stdClass {
        global $DB;

        $sql = 'SELECT * FROM {' . self::TABLE . '}
                 WHERE cmid = :cmid AND userid = :userid AND status = :status';
        $params = [
            'cmid' => $cmid,
            'userid' => $userid,
            'status' => self::STATUS_ACTIVE,
        ];

        if ($slideid !== null && $slideid > 0) {
            $sql .= ' AND slideid = :slideid';
            $params['slideid'] = $slideid;
        } else {
            $sql .= ' AND slideid IS NULL';
        }

        $sql .= ' ORDER BY id DESC';

        $records = $DB->get_records_sql($sql, $params, 0, 1);
        if ($records) {
            return reset($records);
        }

        $now = time();
        $record = (object) [
            'cmid' => $cmid,
            'slideid' => ($slideid !== null && $slideid > 0) ? $slideid : null,
            'userid' => $userid,
            'status' => self::STATUS_ACTIVE,
            'timecreated' => $now,
            'timemodified' => $now,
        ];
        $record->id = $DB->insert_record(self::TABLE, $record);
        return $record;
    }

    /**
     * Fetch a session by ID.
     *
     * @param int $sessionid Editor session ID.
     * @return \stdClass|null
     */
    public static function get(int $sessionid): ?\stdClass {
        global $DB;
        $record = $DB->get_record(self::TABLE, ['id' => $sessionid], '*', IGNORE_MISSING);
        return $record ?: null;
    }

    /**
     * Require that the session exists, belongs to the user, and matches the course module.
     *
     * @param int $sessionid Editor session ID.
     * @param int $userid Expected owner.
     * @param int $cmid Expected course module ID.
     * @return \stdClass
     * @throws \moodle_exception
     */
    public static function require_owned(int $sessionid, int $userid, int $cmid): \stdClass {
        $session = self::get($sessionid);
        if (
            !$session
            || (int) $session->userid !== $userid
            || (int) $session->cmid !== $cmid
        ) {
            throw new \moodle_exception('nopermissions', 'error');
        }
        return $session;
    }

    /**
     * Update the status of a session.
     *
     * @param int $sessionid Editor session ID.
     * @param string $status New status value.
     */
    public static function set_status(int $sessionid, string $status): void {
        global $DB;
        $DB->update_record(self::TABLE, (object) [
            'id' => $sessionid,
            'status' => $status,
            'timemodified' => time(),
        ]);
    }

    /**
     * Delete draft files, fail jobs, and mark the session discarded.
     *
     * @param int $sessionid Editor session ID.
     * @param string $modname Activity module name.
     * @param \context_module $context Module context.
     */
    public static function discard(int $sessionid, string $modname, \context_module $context): void {
        self::discard_files_only($sessionid, $modname, $context);

        $failed = job_repository::fail_session_jobs(
            $sessionid,
            get_string('editorsessiondiscarded', 'local_dixeo_editor')
        );
        foreach ($failed as $job) {
            job_orchestrator::cancel_remote((string) ($job->jobid ?? ''));
        }

        self::set_status($sessionid, self::STATUS_DISCARDED);
    }

    /**
     * Remove draft files and mark the session as saved.
     *
     * @param int $sessionid Editor session ID.
     * @param string $modname Activity module name.
     * @param \context_module $context Module context.
     */
    public static function finalize_saved(int $sessionid, string $modname, \context_module $context): void {
        self::discard_files_only($sessionid, $modname, $context);
        self::set_status($sessionid, self::STATUS_SAVED);
    }

    /**
     * Delete session draft files without changing the session status.
     *
     * @param int $sessionid Editor session ID.
     * @param string $modname Activity module name.
     * @param \context_module $context Module context.
     */
    public static function discard_files_only(int $sessionid, string $modname, \context_module $context): void {
        $filearea = editor_draft_fileareas::for_modname($modname);
        $fs = get_file_storage();
        $fs->delete_area_files($context->id, editor_draft_fileareas::COMPONENT, $filearea, $sessionid);
    }
}
