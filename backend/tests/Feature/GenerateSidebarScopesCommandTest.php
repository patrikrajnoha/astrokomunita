<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class GenerateSidebarScopesCommandTest extends TestCase
{
    public function test_sidebar_scope_generator_writes_expected_typescript_file(): void
    {
        $outputPath = storage_path('framework/testing/sidebarScopes.ts');
        File::delete($outputPath);
        File::ensureDirectoryExists(dirname($outputPath));

        $exitCode = Artisan::call('sidebar:generate-scopes', [
            '--path' => $outputPath,
        ]);

        $this->assertSame(0, $exitCode);
        $this->assertFileExists($outputPath);

        $contents = File::get($outputPath);

        $this->assertStringContainsString("export const SIDEBAR_SCOPES = [", $contents);
        $this->assertStringContainsString("'home'", $contents);
        $this->assertStringContainsString("'search'", $contents);
        $this->assertStringContainsString("export const DEFAULT_SIDEBAR_SCOPE: SidebarScope = 'home'", $contents);
        $this->assertStringContainsString('export function normalizeSidebarScope(v: unknown): SidebarScope {', $contents);

        File::delete($outputPath);
    }
}
