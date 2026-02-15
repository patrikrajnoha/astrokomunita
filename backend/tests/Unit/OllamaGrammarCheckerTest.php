<?php

namespace Tests\Unit;

use App\Services\Translation\Grammar\OllamaGrammarChecker;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OllamaGrammarCheckerTest extends TestCase
{
    public function test_it_corrects_text_using_ollama(): void
    {
        config()->set('ai.ollama.base_url', 'http://ollama.test');
        config()->set('ai.ollama.generate_path', '/api/generate');
        config()->set('translation.grammar.ollama.model', 'mistral');
        config()->set('translation.grammar.ollama.temperature', 0.0);

        Http::fake([
            'http://ollama.test/*' => Http::response([
                'model' => 'mistral',
                'response' => 'prvá štvrť Mesiaca',
                'done' => true,
            ], 200),
        ]);

        $result = app(OllamaGrammarChecker::class)->correct('prva tlac mesiaca', 'sk');

        $this->assertSame('prvá štvrť Mesiaca', $result->correctedText);
        $this->assertSame('ollama', $result->provider);
        $this->assertSame(1, $result->appliedFixes);
    }
}
