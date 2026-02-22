<?php
/**
 * Abstract base class for activity content adapters.
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
 * Template Method pattern: common logic lives here, variations are delegated to abstract methods.
 */
abstract class base_activity_adapter implements activity_adapter_interface {

    /** @var object The DB record for this activity instance. */
    protected object $record;

    /** @var context_module */
    protected context_module $context;

    /** @var moodle_database */
    protected moodle_database $db;

    public function __construct(int $instanceid, context_module $context, moodle_database $db) {
        $this->db = $db;
        $this->context = $context;
        $this->record = $this->db->get_record($this->get_table_name(), ['id' => $instanceid], '*', MUST_EXIST);
    }

    /**
     * Return the module name (e.g., 'page', 'label').
     */
    abstract protected function get_module_name(): string;

    /**
     * Return the DB column name for the content field.
     */
    abstract public function get_content_field(): string;

    /**
     * Return the DB column name for the format field.
     */
    abstract protected function get_format_field(): string;

    /**
     * Return the file area name used for file storage.
     */
    abstract protected function get_file_area(): string;

    /**
     * Return the DB table name for this activity type.
     */
    abstract protected function get_table_name(): string;

    public function get_content(): string {
        $field = $this->get_content_field();
        return (string) $this->record->{$field};
    }

    public function get_content_format(): int {
        $field = $this->get_format_field();
        return (int) $this->record->{$field};
    }

    public function prepare_draft_area(int $draftitemid, array $editoroptions): string {
        $contentfield = $this->get_content_field();

        return file_prepare_draft_area(
            $draftitemid,
            $this->context->id,
            'mod_' . $this->get_module_name(),
            $this->get_file_area(),
            0,
            $editoroptions,
            $this->record->{$contentfield}
        );
    }

    public function save_content(string $content, int $format, int $itemid, array $editoroptions): void {
        $contentfield = $this->get_content_field();
        $formatfield = $this->get_format_field();

        $this->record->{$contentfield} = $content;
        $this->record->{$formatfield} = $format;

        if (!empty($itemid)) {
            $this->record->{$contentfield} = file_save_draft_area_files(
                $itemid,
                $this->context->id,
                'mod_' . $this->get_module_name(),
                $this->get_file_area(),
                0,
                $editoroptions,
                $this->record->{$contentfield}
            );
        }

        $this->db->update_record($this->get_table_name(), $this->record);
    }
}
