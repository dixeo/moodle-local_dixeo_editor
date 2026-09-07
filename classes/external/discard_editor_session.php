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
 * Discard an editor session and its draft images.
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
use local_dixeo_editor\activity\activity_adapter_factory;
use local_dixeo_editor\local\editor_capability;
use local_dixeo_editor\local\editor_session_repository;

/**
 * External API to discard an editor session and its draft images.
 */
class discard_editor_session extends external_api {
    /**
     * Describe parameters for the web service.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'Course module ID'),
            'sessionid' => new external_value(PARAM_INT, 'Editor session ID'),
            'slideid' => new external_value(PARAM_INT, 'Slide row ID', VALUE_DEFAULT, 0),
        ]);
    }

    /**
     * Discard the editor session belonging to the current user.
     *
     * @param int $cmid Course module ID.
     * @param int $sessionid Editor session ID.
     * @param int $slideid Slide row ID (slideshow only, 0 otherwise).
     * @return array
     */
    public static function execute(int $cmid, int $sessionid, int $slideid = 0): array {
        global $DB, $USER;

        $params = self::validate_parameters(self::execute_parameters(), [
            'cmid' => $cmid,
            'sessionid' => $sessionid,
            'slideid' => $slideid,
        ]);

        $cm = get_coursemodule_from_id('', $params['cmid'], 0, false, MUST_EXIST);
        $context = context_module::instance($cm->id);
        self::validate_context($context);
        editor_capability::require_edit_module($context);

        $session = editor_session_repository::get($params['sessionid']);
        if (!$session || (int) $session->userid !== (int) $USER->id) {
            return ['success' => false, 'error' => ['message' => 'Invalid editor session']];
        }

        $adapter = (new activity_adapter_factory($DB))->create($params['cmid'], $params['slideid'] > 0 ? $params['slideid'] : null);
        editor_session_repository::discard($params['sessionid'], $adapter->get_modname(), $context);

        return ['success' => true];
    }

    /**
     * Describe return values for the web service.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Success'),
            'error' => new external_single_structure([
                'message' => new external_value(PARAM_RAW, 'Error message'),
            ], 'Error', VALUE_OPTIONAL),
        ]);
    }
}
