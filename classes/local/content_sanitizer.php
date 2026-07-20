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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Sanitize untrusted HTML before return or persistence.
 *
 * @package    local_dixeo_editor
 * @copyright  2026 Edunao SAS (contact@edunao.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_dixeo_editor\local;

/**
 * Moodle HTML purification for untrusted module content (e.g. AI output).
 */
final class content_sanitizer {
    /**
     * Purify HTML so script/event payloads cannot be returned or saved.
     *
     * @param string $html Raw HTML from the Dixeo API or editor form.
     * @param int $format Moodle content format (defaults to FORMAT_HTML).
     * @return string Sanitized HTML safe for FORMAT_HTML storage/display.
     */
    public static function sanitize(string $html, int $format = FORMAT_HTML): string {
        if ($html === '') {
            return '';
        }

        return clean_text($html, $format);
    }
}
