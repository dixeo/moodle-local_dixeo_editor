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
 * The Dixeo editor only works with TinyMCE: this forces it whatever the user's preferred editor is.
 *
 * Core picks the editor from the user's "htmleditor" preference, with no per-form override, so the
 * preference is swapped in memory while the form renders and restored afterwards. Nothing is saved.
 *
 * @package    local_dixeo_editor
 * @copyright  2026 Edunao SAS (contact@edunao.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class tiny_editor {
    /** @var string Plugin name of the TinyMCE editor (editor_tiny). */
    public const NAME = 'tiny';

    /**
     * Whether TinyMCE is enabled on the site, so it can be forced.
     *
     * @return bool
     */
    public static function is_available(): bool {
        global $CFG;
        require_once($CFG->libdir . '/editorlib.php');

        return array_key_exists(self::NAME, editors_get_enabled());
    }

    /**
     * Throw when TinyMCE cannot be used, so callers stop before rendering a broken editor.
     *
     * @throws \moodle_exception error:tinyunavailable when TinyMCE is disabled on the site.
     */
    public static function require_available(): void {
        if (!self::is_available()) {
            throw new \moodle_exception('error:tinyunavailable', 'local_dixeo_editor');
        }
    }

    /**
     * Run a callback (typically a form render) with TinyMCE as the current user's preferred editor.
     *
     * @param callable $callback Code that renders the editor element.
     * @return mixed The callback's return value.
     */
    public static function run(callable $callback) {
        global $USER;

        // Load the preferences first: core reloads them at most once per request, which would drop the override.
        check_user_preferences_loaded($USER);
        $hadpreference = array_key_exists('htmleditor', $USER->preference);
        $previous = $hadpreference ? $USER->preference['htmleditor'] : null;
        $USER->preference['htmleditor'] = self::NAME;

        try {
            return $callback();
        } finally {
            // The user object lives in the session: restore it so the user's choice still applies everywhere else.
            if ($hadpreference) {
                $USER->preference['htmleditor'] = $previous;
            } else {
                unset($USER->preference['htmleditor']);
            }
        }
    }

    /**
     * Convert editor content to HTML, the only format TinyMCE accepts (core falls back to a textarea otherwise).
     *
     * @param string $text Content in its stored format.
     * @param int|string $format FORMAT_* constant of the stored content.
     * @return string HTML for the editor.
     */
    public static function to_html(string $text, $format): string {
        if ((string) $format === FORMAT_HTML) {
            return $text;
        }

        // Filters stay off: they would bake rendered output (glossary links, media players) into the saved content.
        return format_text($text, $format, ['filter' => false, 'para' => false]);
    }
}
