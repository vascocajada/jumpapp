<?php

namespace App\Service;

use OpenAI;
use App\Exception\CategoryNotFoundException;

class OpenAIService
{
    private $client;

    public function __construct(string $apiKey)
    {
        $this->client = OpenAI::client($apiKey);
    }

    public function categorizeEmail(string $emailBody, array $categoryPairs, array $categoryNames): string
    {
        $prompt = "You are a strict classifier.
            Given the following categories (format: Name: Description):

            " .  implode("\n", $categoryPairs) . "
            
            Classify the following email into **one** of the categories above.

            Email:
            $emailBody
            
            ⚠️ Return only the category name exactly as written in the list above:
            - No description
            - No punctuation
            - No new lines
            - No explanations or additional text
            
            🛑 Output only the exact category name string from the list.";

        $result = $this->client->chat()->create([
            'model' => 'gpt-4o',
            'messages' => [
                ['role' => 'system', 'content' => 'You are a strict classifier.'],
                ['role' => 'user', 'content' => $prompt],
            ],
            'max_tokens' => 10,
            'temperature' => 0,
        ]);

        $raw = trim($result['choices'][0]['message']['content'] ?? $result['choices'][0]['text'] ?? '');

        foreach ($categoryNames as $name) {
            if (strcasecmp(trim($raw, " .\n"), $name) === 0) {
                return $name;
            }
        }

        throw new CategoryNotFoundException('Category not found: ' . $raw);
    }

    public function summarizeEmail(string $emailBody): string
    {
        $prompt = "Summarize this email in 1-2 sentences:\n\n" . $emailBody;
        $result = $this->client->completions()->create([
            'model' => 'gpt-4.1-nano',
            'prompt' => $prompt,
            'max_tokens' => 100,
        ]);
        return trim($result['choices'][0]['text']);
    }
}
