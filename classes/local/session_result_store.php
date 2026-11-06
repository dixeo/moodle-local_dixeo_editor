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

use local_dixeo\service\image\content\decode_result;
use local_dixeo\service\image\content\editor_result_store;
use local_dixeo\util\input_limits;

/**
 * Applied edit results stored on the editor session row.
 *
 * One result is kept per session: the job id, the decoded HTML and the JSON list of the new
 * image placeholder ids. Saving or discarding the session clears it.
 *
 * @package    local_dixeo_editor
 * @copyright  2026 Edunao SAS
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class session_result_store implements editor_result_store {
    /** @var int Maximum bytes of the stored placeholder id list that are decoded. */
    private const MAX_PLACEHOLDER_JSON_BYTES = 16384;

    /** @var int Nesting depth of the stored placeholder id list. */
    private const PLACEHOLDER_JSON_DEPTH = 2;

    /**
     * Result stored for $jobid on the session, or null when none is stored for that job.
     *
     * @param int $sessionid Editor session id.
     * @param string $jobid Remote edit job id.
     * @return decode_result|null
     */
    public function get_applied_result(int $sessionid, string $jobid): ?decode_result {
        global $DB;

        $record = $DB->get_record(
            editor_session_repository::TABLE,
            ['id' => $sessionid],
            'id, resultjobid, resulthtml, resultplaceholderids',
            IGNORE_MISSING
        );
        if (!$record || $record->resultjobid === null || (string) $record->resultjobid !== $jobid) {
            return null;
        }

        $ids = input_limits::decode_json_array(
            (string) ($record->resultplaceholderids ?? ''),
            self::MAX_PLACEHOLDER_JSON_BYTES,
            self::PLACEHOLDER_JSON_DEPTH
        ) ?? [];
        $ids = array_values(array_filter($ids, 'is_string'));

        return new decode_result((string) ($record->resulthtml ?? ''), $ids, []);
    }

    /**
     * Store the result of $jobid on the session, replacing any earlier job's result.
     *
     * @param int $sessionid Editor session id.
     * @param string $jobid Remote edit job id.
     * @param decode_result $result Decoded content and new placeholder ids.
     * @return void
     */
    public function store_applied_result(int $sessionid, string $jobid, decode_result $result): void {
        global $DB;

        $DB->update_record(editor_session_repository::TABLE, (object) [
            'id' => $sessionid,
            'resultjobid' => $jobid,
            'resulthtml' => $result->html,
            'resultplaceholderids' => json_encode(array_values($result->newplaceholderids)),
            'timemodified' => time(),
        ]);
    }
}
