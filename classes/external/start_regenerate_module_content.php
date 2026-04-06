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
 * Start an asynchronous module content regeneration job.
 *
 * @package    local_dixeo_editor
 * @copyright  2026 Edunao SAS
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_dixeo_editor\external;

use context_module;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;
use invalid_parameter_exception;
use local_dixeo\context\context_builder_factory;
use local_dixeo\external\service_factory;
use local_dixeo\service\tiny_autosave_draft_service;

class start_regenerate_module_content extends external_api {
    /**
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'Course module ID'),
            'instructions' => new external_value(PARAM_TEXT, 'AI instructions'),
            'autosave_contextid' => new external_value(
                PARAM_INT,
                'Tiny autosave context id (0 = do not read tiny_autosave)',
                VALUE_DEFAULT,
                0
            ),
            'autosave_pagehash' => new external_value(
                PARAM_RAW,
                'Tiny autosave page hash',
                VALUE_DEFAULT,
                ''
            ),
            'autosave_elementid' => new external_value(
                PARAM_RAW,
                'Tiny autosave target element id',
                VALUE_DEFAULT,
                ''
            ),
        ]);
    }

    /**
     * @param int $cmid
     * @param string $instructions
     * @param int $autosave_contextid
     * @param string $autosave_pagehash
     * @param string $autosave_elementid
     * @return array
     */
    public static function execute(
        int $cmid,
        string $instructions,
        int $autosave_contextid = 0,
        string $autosave_pagehash = '',
        string $autosave_elementid = ''
    ): array {
        global $CFG, $USER;

        $params = self::validate_parameters(self::execute_parameters(), [
            'cmid' => $cmid,
            'instructions' => $instructions,
            'autosave_contextid' => $autosave_contextid,
            'autosave_pagehash' => $autosave_pagehash,
            'autosave_elementid' => $autosave_elementid,
        ]);

        $cm = get_coursemodule_from_id('', $params['cmid'], 0, false, MUST_EXIST);
        $context = context_module::instance($cm->id);
        self::validate_context($context);
        require_capability('moodle/course:manageactivities', $context);

        try {
            require_once($CFG->dirroot . '/local/dixeo/lib.php');

            $draftHtml = self::resolve_autosave_draft_html(
                $cm,
                $context,
                (int) $params['autosave_contextid'],
                (string) $params['autosave_pagehash'],
                (string) $params['autosave_elementid'],
                (int) $USER->id
            );

            $payload = [
                'moduleType' => $cm->modname,
                'instructions' => $params['instructions'],
                'context' => context_builder_factory::buildModuleEditContext($params['cmid'], $draftHtml),
                'courseId' => (string) $cm->course,
            ];

            $namespace = \local_dixeo_get_configured_namespace();
            if (!empty($namespace)) {
                $payload['namespace'] = $namespace;
            }

            $result = service_factory::get_job_service()->submit_job('/v1/modules/edit', $payload);

            return [
                'success' => true,
                'data' => [
                    'jobid' => $result->jobid,
                    'status' => $result->status ?? 'pending',
                    'progress' => $result->progress,
                ],
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'error' => ['message' => $e->getMessage()],
            ];
        }
    }

    /**
     * Resolve draft HTML from tiny_autosave or null for DB-only fallback.
     *
     * @param \stdClass $cm Course module record from get_coursemodule_from_id.
     * @param \context_module $modulecontext Module context.
     * @param int $autosavecontextid 0 = skip.
     * @param string $autosavepagehash
     * @param string $autosaveelementid
     * @param int $userid
     * @return string|null
     */
    private static function resolve_autosave_draft_html(
        \stdClass $cm,
        \context_module $modulecontext,
        int $autosavecontextid,
        string $autosavepagehash,
        string $autosaveelementid,
        int $userid
    ): ?string {
        if ($autosavecontextid <= 0) {
            return null;
        }

        if ((int) $autosavecontextid !== (int) $modulecontext->id) {
            throw new invalid_parameter_exception('autosave_contextid does not match this activity');
        }

        $pagehash = trim($autosavepagehash);
        $elementid = trim($autosaveelementid);
        if ($pagehash === '' || $elementid === '') {
            throw new invalid_parameter_exception(
                'autosave_pagehash and autosave_elementid are required when autosave_contextid is set'
            );
        }

        if (strlen($pagehash) > 64 || !ctype_xdigit($pagehash)) {
            throw new invalid_parameter_exception('Invalid autosave_pagehash');
        }

        if (strlen($elementid) > 255 || !preg_match('/^id_[a-zA-Z0-9_-]+$/', $elementid)) {
            throw new invalid_parameter_exception('Invalid autosave_elementid');
        }

        $service = new tiny_autosave_draft_service();

        return $service->get_draft_text($userid, (int) $modulecontext->id, $pagehash, $elementid);
    }

    /**
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Call success'),
            'data' => new external_single_structure([
                'jobid' => new external_value(PARAM_RAW, 'Job id'),
                'status' => new external_value(PARAM_ALPHA, 'Job status'),
                'progress' => new external_value(PARAM_INT, 'Progress percentage'),
            ], 'Data payload', VALUE_OPTIONAL),
            'error' => new external_single_structure([
                'message' => new external_value(PARAM_RAW, 'Error message'),
            ], 'Error payload', VALUE_OPTIONAL),
        ]);
    }
}
