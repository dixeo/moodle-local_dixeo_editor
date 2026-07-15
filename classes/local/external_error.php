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

/**
 * Sanitized error responses for AJAX externals.
 *
 * @package    local_dixeo_editor
 * @copyright  2026 Edunao SAS
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class external_error {
    /**
     * Build a failure response with a generic client message.
     *
     * Logs the original exception for developers; never exposes raw messages to the client.
     *
     * @param \Throwable $e The caught exception.
     * @return array{success: false, error: array{message: string}}
     */
    public static function response(\Throwable $e): array {
        debugging('local_dixeo_editor external error: ' . $e->getMessage(), DEBUG_DEVELOPER);

        return [
            'success' => false,
            'error' => ['message' => self::generic_message()],
        ];
    }

    /**
     * User-safe error message for external responses.
     *
     * @return string
     */
    public static function generic_message(): string {
        return get_string('error:generic', 'local_dixeo_editor');
    }
}
