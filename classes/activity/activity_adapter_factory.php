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

use moodle_database;
use context_module;

class activity_adapter_factory {
    /** @var array<string, class-string> Registered adapter classes by module name. */
    private static array $adapters = [
        'page' => page_activity_adapter::class,
        'label' => label_activity_adapter::class,
        'slideshow' => slideshow_slide_activity_adapter::class,
    ];

    private moodle_database $db;

    /**
     * Constructor requires a DB instance (injected).
     */
    public function __construct(moodle_database $db) {
        $this->db = $db;
    }

    /**
     * Register an adapter class for a module type.
     *
     * Allows external plugins to extend the factory with new adapters.
     *
     * @param string $modname The module name.
     * @param string $classname The fully qualified adapter class name.
     */
    public static function register_adapter(string $modname, string $classname): void {
        self::$adapters[$modname] = $classname;
    }

    /**
     * Get supported module types.
     *
     * @return array List of supported module names.
     */
    public static function get_supported_types(): array {
        return array_keys(self::$adapters);
    }

    /**
     * Create the appropriate adapter object based on cmid.
     *
     * For composite modules that target a sub-record (e.g. slideshow → one
     * slide row), pass $slideid to identify the specific child being edited.
     *
     * @param int $cmid Course module ID.
     * @param int|null $slideid Optional child record ID (required for slideshow).
     * @return activity_adapter_interface
     *
     * @throws \coding_exception if module type is unsupported or slideid is missing when required.
     */
    public function create(int $cmid, ?int $slideid = null): activity_adapter_interface {
        $cm = get_coursemodule_from_id('', $cmid, 0, false, MUST_EXIST);
        $modname = $cm->modname;
        $context = context_module::instance($cm->id);

        if (!isset(self::$adapters[$modname])) {
            throw new \coding_exception("Unsupported module type: {$modname}");
        }

        if ($modname === 'slideshow') {
            if ($slideid === null) {
                throw new \coding_exception('slideid is required for slideshow adapter');
            }
            $instanceid = $slideid;
        } else {
            $instanceid = (int) $cm->instance;
        }

        $classname = self::$adapters[$modname];
        return new $classname($instanceid, $context, $this->db);
    }
}

