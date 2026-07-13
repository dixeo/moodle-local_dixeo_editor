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
 * Factory for creating activity-specific content adapters.
 *
 * @package    local_dixeo_editor
 * @copyright  2025 Edunao SAS (contact@edunao.com)
 * @author     Pierre FACQ <pierre.facq@edunao.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_dixeo_editor\activity;

use context_module;
use moodle_database;

/**
 * Factory for creating activity-specific content adapters.
 */
class activity_adapter_factory {
    /** @var array<string, class-string<base_activity_adapter>> Registered adapter classes by module name. */
    private static array $adapters = [
        'page' => page_activity_adapter::class,
        'label' => label_activity_adapter::class,
        'slideshow' => slideshow_slide_activity_adapter::class,
    ];

    /** @var moodle_database Database handle. */
    private moodle_database $db;

    /**
     * Constructor.
     *
     * @param moodle_database $db Database handle.
     */
    public function __construct(moodle_database $db) {
        $this->db = $db;
    }

    /**
     * Register an adapter class for a module type.
     *
     * @param string $modname The module name.
     * @param string $classname Adapter class name (must extend base_activity_adapter).
     */
    public static function register_adapter(string $modname, string $classname): void {
        self::$adapters[$modname] = $classname;
    }

    /**
     * Return list of supported module names.
     *
     * @return array<int, string>
     */
    public static function get_supported_types(): array {
        return array_keys(self::$adapters);
    }

    /**
     * Create the appropriate adapter based on cmid.
     *
     * For composite modules that target a sub-record (slideshow slide, future
     * quiz question, etc.), pass $subid to identify the child row. Each
     * adapter class decides how to resolve its record id from (cm, subid)
     * via its static resolve_record_id() method — so this factory stays
     * generic (no modname branching).
     *
     * @param int $cmid Course module ID.
     * @param int|null $subid Optional child record ID for composite modules.
     * @return activity_adapter_interface
     *
     * @throws \coding_exception if the module type is unsupported or the
     *         adapter rejects the given subid.
     */
    public function create(int $cmid, ?int $subid = null): activity_adapter_interface {
        $cm = get_coursemodule_from_id('', $cmid, 0, false, MUST_EXIST);
        $context = context_module::instance($cm->id);

        $classname = self::$adapters[$cm->modname] ?? null;
        if ($classname === null) {
            throw new \coding_exception("Unsupported module type: {$cm->modname}");
        }

        $recordid = $classname::resolve_record_id($cm, $subid);

        return new $classname($recordid, $context, $this->db);
    }
}
