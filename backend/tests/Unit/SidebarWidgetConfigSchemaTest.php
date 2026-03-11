<?php

namespace Tests\Unit;

use App\Support\SidebarWidgetConfigSchema;
use PHPUnit\Framework\TestCase;

class SidebarWidgetConfigSchemaTest extends TestCase
{
    public function test_cta_href_is_rejected_when_not_absolute_path_or_http_url(): void
    {
        $config = SidebarWidgetConfigSchema::normalizeConfig(SidebarWidgetConfigSchema::TYPE_CTA, [
            'headline' => 'Headline',
            'body' => 'Body',
            'buttonText' => 'Open',
            'buttonHref' => 'javascript:alert(1)',
        ]);

        $this->assertNull($config['buttonHref']);
    }

    public function test_html_widget_sanitization_removes_unsafe_tags_and_attributes(): void
    {
        $config = SidebarWidgetConfigSchema::normalizeConfig(SidebarWidgetConfigSchema::TYPE_HTML, [
            'html' => '<p onclick="alert(1)">Safe <a href="/events" onclick="x()">read</a></p><iframe src="https://example.com"></iframe>',
        ]);

        $html = (string) ($config['html'] ?? '');

        $this->assertStringNotContainsString('onclick=', strtolower($html));
        $this->assertStringNotContainsString('<iframe', strtolower($html));
        $this->assertStringContainsString('href="/events"', $html);
    }

    public function test_html_widget_sanitization_keeps_only_safe_anchor_targets(): void
    {
        $config = SidebarWidgetConfigSchema::normalizeConfig(SidebarWidgetConfigSchema::TYPE_HTML, [
            'html' => '<a href="//evil.example.com">bad</a><a href="https://example.com" target="_blank">good</a>',
        ]);

        $html = (string) ($config['html'] ?? '');

        $this->assertStringNotContainsString('href="//evil.example.com"', $html);
        $this->assertStringContainsString('href="https://example.com"', $html);
        $this->assertStringContainsString('rel="noopener noreferrer"', strtolower($html));
    }
}
