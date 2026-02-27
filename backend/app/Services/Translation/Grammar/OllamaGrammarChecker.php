<?php

namespace App\Services\Translation\Grammar;

use App\Services\AI\OllamaClient;
use App\Services\AI\OllamaClientException;
use App\Services\Translation\Grammar\Contracts\GrammarCheckerInterface;
use Throwable;

class OllamaGrammarChecker implements GrammarCheckerInterface
{
    public function __construct(
        private readonly OllamaClient $ollamaClient,
    ) {
    }

    public function correct(string $text, string $language): GrammarCheckResult
    {
        $value = trim($text);
        if ($value === '') {
            return new GrammarCheckResult(
                correctedText: $text,
                provider: 'none',
                appliedFixes: 0,
                durationMs: 0
            );
        }

        $languageCode = trim($language) !== '' ? $language : 'sk';
        $system = 'Si editor slovenskeho jazyka. Oprav pravopis a gramatiku bez zmeny vyznamu.';
        $prompt = "Jazyk: {$languageCode}\n\nText:\n{$value}\n\nVrat iba opravenu verziu textu bez komentarov.";

        try {
            $result = $this->ollamaClient->generate(
                prompt: $prompt,
                system: $system,
                options: [
                    'model' => (string) config('translation.grammar.ollama.model', config('ai.ollama.model', 'mistral')),
                    'temperature' => (float) config('translation.grammar.ollama.temperature', 0.0),
                    'num_predict' => (int) config('translation.grammar.ollama.num_predict', 256),
                    'timeout' => (int) config('translation.grammar.ollama.timeout', 20),
                ]
            );
        } catch (OllamaClientException $exception) {
            throw new GrammarCheckException(
                $exception->getMessage(),
                $exception->errorCode(),
                $exception->statusCode()
            );
        } catch (Throwable) {
            throw new GrammarCheckException('Ollama grammar check failed.', 'ollama_grammar_error');
        }

        $corrected = trim((string) ($result['text'] ?? ''));
        if ($corrected === '') {
            return new GrammarCheckResult(
                correctedText: $text,
                provider: 'ollama',
                appliedFixes: 0,
                durationMs: (int) ($result['duration_ms'] ?? 0)
            );
        }

        return new GrammarCheckResult(
            correctedText: $corrected,
            provider: 'ollama',
            appliedFixes: $corrected === $value ? 0 : 1,
            durationMs: (int) ($result['duration_ms'] ?? 0),
            meta: [
                'model' => (string) ($result['model'] ?? ''),
                'language' => $languageCode,
            ]
        );
    }
}
