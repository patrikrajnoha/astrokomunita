<?php

namespace Tests\Unit;

use App\Services\Translation\Grammar\GrammarCheckException;
use App\Services\Translation\Grammar\LanguageToolGrammarChecker;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class LanguageToolGrammarCheckerTest extends TestCase
{
    public function test_it_sends_expected_payload_and_applies_replacements(): void
    {
        config()->set('translation.grammar.languagetool.base_url', 'http://lt.test');
        config()->set('translation.grammar.languagetool.check_path', '/v2/check');
        config()->set('translation.grammar.languagetool.internal_token', 'internal-token');
        config()->set('translation.grammar.languagetool.enabled_rules', 'SK_GRAMMAR,SK_PUNCT');
        config()->set('translation.grammar.languagetool.disabled_rules', 'WHITESPACE_RULE');

        Http::fake([
            'http://lt.test/*' => Http::response([
                'matches' => [
                    [
                        'offset' => 5,
                        'length' => 5,
                        'replacements' => [
                            ['value' => 'štvrť'],
                        ],
                    ],
                ],
            ], 200),
        ]);

        $result = app(LanguageToolGrammarChecker::class)->correct('Prvý STVRT Mesiac', 'sk-SK');

        $this->assertSame('Prvý štvrť Mesiac', $result->correctedText);
        $this->assertSame(1, $result->appliedFixes);
        $this->assertSame('languagetool', $result->provider);

        Http::assertSent(function ($request) {
            $data = $request->data();

            return $request->url() === 'http://lt.test/v2/check'
                && $request->method() === 'POST'
                && data_get($request->headers(), 'X-Internal-Token.0') === 'internal-token'
                && data_get($data, 'text') === 'Prvý STVRT Mesiac'
                && data_get($data, 'language') === 'sk-SK'
                && data_get($data, 'enabledRules') === 'SK_GRAMMAR,SK_PUNCT'
                && data_get($data, 'disabledRules') === 'WHITESPACE_RULE';
        });
    }

    public function test_it_throws_domain_exception_on_http_error(): void
    {
        config()->set('translation.grammar.languagetool.base_url', 'http://lt.test');
        config()->set('translation.grammar.languagetool.check_path', '/v2/check');

        Http::fake([
            'http://lt.test/*' => Http::response(['error' => 'down'], 503),
        ]);

        $this->expectException(GrammarCheckException::class);
        $this->expectExceptionMessage('LanguageTool failed with HTTP 503.');

        app(LanguageToolGrammarChecker::class)->correct('Ahoj svet', 'sk-SK');
    }
}

