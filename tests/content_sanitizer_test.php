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
 * Tests for HTML purification (MF-SEC-005).
 *
 * @package    local_dixeo_editor
 * @category   test
 * @copyright  2026 Edunao SAS (contact@edunao.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_dixeo_editor;

use local_dixeo\dto\job_status;
use local_dixeo\external\service_factory;
use local_dixeo\repository\job_repository;
use local_dixeo\service\job_service;
use local_dixeo_editor\activity\activity_adapter_factory;
use local_dixeo_editor\external\get_regenerate_module_content_status;
use local_dixeo_editor\local\content_sanitizer;

/**
 * XSS payloads must not survive sanitize / status / save paths.
 *
 * @covers \local_dixeo_editor\local\content_sanitizer
 * @covers \local_dixeo_editor\activity\base_activity_adapter::save_content
 * @covers \local_dixeo_editor\external\get_regenerate_module_content_status
 */
final class content_sanitizer_test extends \advanced_testcase {
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    protected function tearDown(): void {
        if (class_exists(\local_dixeo\external\service_factory::class)) {
            service_factory::reset();
        }
        parent::tearDown();
    }

    /**
     * XSS-ish HTML samples for purification checks.
     *
     * @return array<string, array{0: string, 1: string}>
     */
    public static function xss_payload_provider(): array {
        return [
            'script tag' => ['<p>Hello</p><script>alert(1)</script>', 'script'],
            'inline onclick' => ['<p onclick="alert(1)">Click</p>', 'onclick'],
            'javascript uri' => ['<a href="javascript:alert(1)">x</a>', 'javascript:'],
            'onerror img' => ['<img src=x onerror="alert(1)">', 'onerror'],
        ];
    }

    /**
     * Forbidden XSS markers must not remain after sanitize.
     *
     * @dataProvider xss_payload_provider
     * @param string $payload Raw HTML.
     * @param string $forbidden Substring that must not remain (case-insensitive).
     */
    public function test_sanitize_strips_xss_payloads(string $payload, string $forbidden): void {
        $cleaned = content_sanitizer::sanitize($payload);
        $this->assertStringNotContainsStringIgnoringCase($forbidden, $cleaned);
        $this->assertStringNotContainsString('<script', strtolower($cleaned));
    }

    public function test_sanitize_keeps_safe_markup(): void {
        $html = '<p><strong>Title</strong> and <em>emphasis</em></p>';
        $cleaned = content_sanitizer::sanitize($html);
        $this->assertStringContainsString('<p>', $cleaned);
        $this->assertStringContainsString('<strong>Title</strong>', $cleaned);
        $this->assertStringContainsString('<em>emphasis</em>', $cleaned);
    }

    public function test_save_content_purifies_before_persist(): void {
        global $DB;

        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $page = $generator->create_module('page', [
            'course' => $course->id,
            'content' => '<p>Original</p>',
            'contentformat' => FORMAT_HTML,
        ]);
        $cm = get_coursemodule_from_instance('page', $page->id, $course->id, false, MUST_EXIST);

        $this->setAdminUser();
        $adapter = (new activity_adapter_factory($DB))->create((int) $cm->id, null);
        $adapter->save_content(
            '<p>Safe</p><script>alert("xss")</script><img src=x onerror=alert(1)>',
            FORMAT_HTML,
            0,
            []
        );

        $stored = $DB->get_field('page', 'content', ['id' => $page->id], MUST_EXIST);
        $this->assertStringContainsString('Safe', $stored);
        $this->assertStringNotContainsStringIgnoringCase('script', $stored);
        $this->assertStringNotContainsStringIgnoringCase('onerror', $stored);
    }

    public function test_status_external_returns_purified_content(): void {
        if (!class_exists(\local_dixeo\external\service_factory::class)) {
            $this->markTestSkipped('local_dixeo hub is required');
        }

        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $user = $generator->create_and_enrol($course, 'editingteacher');
        $page = $generator->create_module('page', [
            'course' => $course->id,
            'content' => '<p>Original</p>',
        ]);
        $cm = get_coursemodule_from_instance('page', $page->id, $course->id, false, MUST_EXIST);

        $repo = new job_repository();
        $repo->register('job-xss', (int) $course->id, (int) $user->id, 'default', 'module_edit');

        $poller = $this->getMockBuilder(\local_dixeo\api\job_poller::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['get_job_status'])
            ->getMock();
        $poller->expects($this->once())
            ->method('get_job_status')
            ->with('job-xss')
            ->willReturn(new job_status(
                jobid: 'job-xss',
                type: 'module',
                status: 'completed',
                progress: 100,
                createdat: time(),
                result: [
                    'data' => [
                        'content' => '<p>OK</p><script>document.cookie</script>',
                    ],
                ]
            ));
        service_factory::set_test_job_service(new job_service(null, $poller, $repo));

        $this->setUser($user);
        $result = get_regenerate_module_content_status::execute((int) $cm->id, 'job-xss');

        $this->assertTrue($result['success']);
        $this->assertSame('completed', $result['data']['status']);
        $this->assertStringContainsString('OK', $result['data']['content']);
        $this->assertStringNotContainsStringIgnoringCase('script', $result['data']['content']);
        $this->assertStringNotContainsString('document.cookie', $result['data']['content']);
    }
}
