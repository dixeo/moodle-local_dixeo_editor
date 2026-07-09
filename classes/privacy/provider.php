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
 * Privacy API: editor sessions, draft files, preferences, and Dixeo API transfers.
 *
 * @package    local_dixeo_editor
 * @copyright  2026 Edunao SAS
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_dixeo_editor\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\transform;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;
use local_dixeo_editor\local\editor_session_repository;

/**
 * Privacy provider for local_dixeo_editor.
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\core_userlist_provider,
    \core_privacy\local\request\plugin\provider,
    \core_privacy\local\request\user_preference_provider {
    /** @var string Editor session table. */
    public const TABLE_SESSION = editor_session_repository::TABLE;

    /** @var string[] Draft file areas owned by editor sessions. */
    public const DRAFT_FILEAREAS = ['draft_page', 'draft_label', 'draft_slideshow'];

    /**
     * Describe metadata stored or transmitted by this plugin.
     *
     * @param collection $collection The privacy metadata collection.
     * @return collection The updated collection.
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_user_preference(
            'local_dixeo_editor_content_panel_state',
            'privacy:metadata:preference:panel_layout'
        );

        $collection->add_database_table(
            self::TABLE_SESSION,
            [
                'cmid' => 'privacy:metadata:session:cmid',
                'slideid' => 'privacy:metadata:session:slideid',
                'userid' => 'privacy:metadata:session:userid',
                'status' => 'privacy:metadata:session:status',
                'timecreated' => 'privacy:metadata:session:timecreated',
                'timemodified' => 'privacy:metadata:session:timemodified',
            ],
            'privacy:metadata:session'
        );

        $collection->add_subsystem_link(
            'core_files',
            [],
            'privacy:metadata:files'
        );

        $collection->add_external_location_link(
            'dixeo_api',
            [
                'instructions' => 'privacy:metadata:instructions',
                'context' => 'privacy:metadata:context',
                'courseId' => 'privacy:metadata:courseid',
                'userId' => 'privacy:metadata:userid',
                'moduleType' => 'privacy:metadata:moduletype',
                'namespace' => 'privacy:metadata:namespace',
            ],
            'privacy:metadata:externalpurpose'
        );

        return $collection;
    }

    /**
     * Export user preferences owned by this plugin.
     *
     * @param int $userid The user whose data is exported.
     * @return void
     */
    public static function export_user_preferences(int $userid): void {
        $layout = get_user_preferences('local_dixeo_editor_content_panel_state', null, $userid);
        if ($layout === null) {
            return;
        }

        writer::export_user_preference(
            'local_dixeo_editor',
            'local_dixeo_editor_content_panel_state',
            (string) $layout,
            get_string('privacy:metadata:preference:panel_layout', 'local_dixeo_editor')
        );
    }

    /**
     * Get module contexts that contain editor sessions for the user.
     *
     * @param int $userid The user ID.
     * @return contextlist
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();

        $sql = "SELECT ctx.id
                  FROM {" . self::TABLE_SESSION . "} s
                  JOIN {course_modules} cm ON cm.id = s.cmid
                  JOIN {context} ctx ON ctx.instanceid = cm.id AND ctx.contextlevel = :contextlevel
                 WHERE s.userid = :userid";

        $contextlist->add_from_sql($sql, [
            'contextlevel' => CONTEXT_MODULE,
            'userid' => $userid,
        ]);

        return $contextlist;
    }

    /**
     * Export session rows and draft files for the user in approved module contexts.
     *
     * @param approved_contextlist $contextlist The approved contexts.
     */
    public static function export_user_data(approved_contextlist $contextlist): void {
        global $DB;

        if (empty($contextlist->count())) {
            return;
        }

        $userid = (int) $contextlist->get_user()->id;

        foreach ($contextlist->get_contexts() as $context) {
            if ($context->contextlevel !== CONTEXT_MODULE) {
                continue;
            }

            $cmid = (int) $context->instanceid;
            $sessions = $DB->get_records(self::TABLE_SESSION, [
                'cmid' => $cmid,
                'userid' => $userid,
            ]);
            if (!$sessions) {
                continue;
            }

            $exported = [];
            foreach ($sessions as $session) {
                $exported[] = (object) [
                    'cmid' => (int) $session->cmid,
                    'slideid' => $session->slideid,
                    'status' => (string) $session->status,
                    'timecreated' => transform::datetime((int) $session->timecreated),
                    'timemodified' => transform::datetime((int) $session->timemodified),
                ];

                foreach (self::DRAFT_FILEAREAS as $filearea) {
                    writer::with_context($context)->export_area_files(
                        [get_string('privacy:path:sessions', 'local_dixeo_editor'), (string) $session->id],
                        'local_dixeo_editor',
                        $filearea,
                        (int) $session->id
                    );
                }
            }

            writer::with_context($context)->export_data(
                [get_string('privacy:path:sessions', 'local_dixeo_editor')],
                (object) ['sessions' => $exported]
            );
        }
    }

    /**
     * Delete all plugin data for a module context.
     *
     * @param \context $context The context.
     */
    public static function delete_data_for_all_users_in_context(\context $context): void {
        global $DB;

        if ($context->contextlevel !== CONTEXT_MODULE) {
            return;
        }

        $cmid = (int) $context->instanceid;
        $sessions = $DB->get_records(self::TABLE_SESSION, ['cmid' => $cmid], '', 'id');
        self::delete_sessions_and_files($context, array_keys($sessions));
        $DB->delete_records(self::TABLE_SESSION, ['cmid' => $cmid]);
    }

    /**
     * Delete the user's sessions and draft files in approved contexts.
     *
     * @param approved_contextlist $contextlist The approved contexts.
     */
    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        global $DB;

        if (empty($contextlist->count())) {
            return;
        }

        $userid = (int) $contextlist->get_user()->id;

        foreach ($contextlist->get_contexts() as $context) {
            if ($context->contextlevel !== CONTEXT_MODULE) {
                continue;
            }

            $cmid = (int) $context->instanceid;
            $sessions = $DB->get_records(self::TABLE_SESSION, [
                'cmid' => $cmid,
                'userid' => $userid,
            ], '', 'id');
            self::delete_sessions_and_files($context, array_keys($sessions));
            $DB->delete_records(self::TABLE_SESSION, [
                'cmid' => $cmid,
                'userid' => $userid,
            ]);
        }
    }

    /**
     * List users with editor sessions in a module context.
     *
     * @param userlist $userlist The userlist for the context.
     */
    public static function get_users_in_context(userlist $userlist): void {
        $context = $userlist->get_context();
        if ($context->contextlevel !== CONTEXT_MODULE) {
            return;
        }

        $userlist->add_from_sql(
            'userid',
            "SELECT s.userid
               FROM {" . self::TABLE_SESSION . "} s
              WHERE s.cmid = :cmid AND s.userid > 0",
            ['cmid' => (int) $context->instanceid]
        );
    }

    /**
     * Delete listed users' sessions in a module context.
     *
     * @param approved_userlist $userlist The approved user list.
     */
    public static function delete_data_for_users(approved_userlist $userlist): void {
        global $DB;

        $context = $userlist->get_context();
        if ($context->contextlevel !== CONTEXT_MODULE) {
            return;
        }

        $userids = $userlist->get_userids();
        if ($userids === []) {
            return;
        }

        $cmid = (int) $context->instanceid;
        [$insql, $params] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED);
        $params['cmid'] = $cmid;

        $sessions = $DB->get_records_select(
            self::TABLE_SESSION,
            "cmid = :cmid AND userid {$insql}",
            $params,
            '',
            'id'
        );
        self::delete_sessions_and_files($context, array_keys($sessions));
        $DB->delete_records_select(
            self::TABLE_SESSION,
            "cmid = :cmid AND userid {$insql}",
            $params
        );
    }

    /**
     * Delete draft files for the given session IDs.
     *
     * @param \context $context Module context.
     * @param int[] $sessionids Session IDs.
     */
    private static function delete_sessions_and_files(\context $context, array $sessionids): void {
        $fs = get_file_storage();
        foreach ($sessionids as $sessionid) {
            foreach (self::DRAFT_FILEAREAS as $filearea) {
                $fs->delete_area_files($context->id, 'local_dixeo_editor', $filearea, (int) $sessionid);
            }
        }
    }
}
