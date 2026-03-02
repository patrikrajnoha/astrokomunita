<?php

namespace Tests\Unit;

use Symfony\Component\Yaml\Yaml;
use Tests\TestCase;

class DockerComposeModerationConfigTest extends TestCase
{
    public function test_docker_compose_uses_moderation_service_hostname_for_backend_services(): void
    {
        $composePath = dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'docker-compose.yml';
        $this->assertFileExists($composePath);

        $compose = Yaml::parseFile($composePath);

        $this->assertSame(
            'http://moderation:8090',
            data_get($compose, 'services.backend.environment.MODERATION_BASE_URL')
        );
        $this->assertSame(
            'http://moderation:8090',
            data_get($compose, 'services.queue-worker.environment.MODERATION_BASE_URL')
        );
    }

    public function test_env_example_matches_docker_moderation_hostname(): void
    {
        $envExamplePath = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . '.env.example';
        $this->assertFileExists($envExamplePath);

        $contents = file_get_contents($envExamplePath);
        if ($contents === false) {
            $this->fail('Unable to read backend .env.example');
        }

        $this->assertStringContainsString('MODERATION_BASE_URL=http://moderation:8090', $contents);
        $this->assertStringNotContainsString('MODERATION_BASE_URL=http://127.0.0.1:8090', $contents);
    }
}
