<?php

namespace App\Service;

use OpenAI;
use App\Exception\CategoryNotFoundException;
use Psr\Log\LoggerInterface;

class OpenAIService
{
    private $client;
    private $contentPreprocessor;
    private LoggerInterface $logger;

    public function __construct(string $apiKey, AIContentPreprocessor $contentPreprocessor, LoggerInterface $logger)
    {
        $this->client = OpenAI::client($apiKey);
        $this->contentPreprocessor = $contentPreprocessor;
        $this->logger = $logger;
    }

    public function categorizeEmail(string $emailBody, array $categoryPairs, array $categoryNames): string
    {
        // Extract only relevant text for categorization to reduce token usage
        $cleanText = $this->contentPreprocessor->extractTextForAI($emailBody);
        
        $categoriesText = implode("\n", $categoryPairs);
        $prompt = <<<PROMPT
You are a strict classifier.
Given the following categories (format: Name: Description):

$categoriesText

Classify the following email into **one** of the categories above.

Email:
$cleanText

⚠️ Return only the category name exactly as written in the list above:
- No description
- No punctuation
- No new lines
- No explanations or additional text

🛑 Output only the exact category name string from the list.
PROMPT;
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
        // Extract only relevant text for summarization to reduce token usage
        $cleanText = $this->contentPreprocessor->extractTextForAI($emailBody);
        
        $prompt = <<<PROMPT
You are an expert at summarizing emails. Your job is to write a crisp, clear summary of the main content of the email, in 1-2 sentences.

- Ignore links, headers, footers, and boilerplate.
- Do NOT include URLs, unsubscribe info, or legal disclaimers.
- If the email has little information, a very short summary or just a few words is enough.
- Use only the information provided in the email.
- Do not make up information.
- Output only human readable text.
- If you cannot find any information in the email, return 'No information found'.
- Focus only on the main message or intent.

Email:
$cleanText

Summary (1-2 sentences, no links, no boilerplate):
PROMPT;
        $result = $this->client->completions()->create([
            'model' => 'gpt-4.1-nano',
            'prompt' => $prompt,
            'max_tokens' => 100,
        ]);
        return trim($result['choices'][0]['text']);
    }

    public function findUnsubscribeLink(string $emailBody): ?string
    {
        // Extract only unsubscribe-related content to reduce token usage
        $relevantContent = $this->contentPreprocessor->extractRelevantHtmlForAI($emailBody);

        $prompt = <<<PROMPT
You are an expert at finding unsubscribe links in emails. Given the following email content, extract the most likely unsubscribe link (URL) that a user should visit to unsubscribe.

If the unsubscribe link is very long, output the entire URL, even if it is hundreds of characters. Do not truncate, break, or shorten the URL. Output only the full, complete URL, with no spaces, line breaks, or extra characters. If there is no unsubscribe link, return 'NONE'.

Relevant Body Sections:
$relevantContent
PROMPT;
        $result = $this->client->chat()->create([
            'model' => 'gpt-4o',
            'messages' => [
                ['role' => 'system', 'content' => 'You are an expert at finding unsubscribe links in emails.'],
                ['role' => 'user', 'content' => $prompt],
            ],
            'max_tokens' => 500, // Increased for safety
            'temperature' => 0,
        ]);
        $raw = trim($result['choices'][0]['message']['content'] ?? $result['choices'][0]['text'] ?? '');

        $this->logger->debug('Raw OpenAI unsubscribe output', ['raw' => $raw, 'length' => strlen($raw)]);

        if (strtolower($raw) === 'none') {
            return null;
        }
        // Robust URL extraction
        if (preg_match('/https?:\/\/[^
\s\'"<>]+/', $raw, $matches)) {
            return $matches[0];
        }
        return null;
    }

    public function analyzeUnsubscribeSuccess(string $html, string $context): array
    {
        // Extract content specifically for success/failure analysis
        $relevantContent = $this->contentPreprocessor->extractVisibleTextFromHtml($html);
        
        $prompt = <<<PROMPT
Analyze this content from an unsubscribe page response. Context: {$context}

Content Sections:
{$relevantContent}

Determine if the unsubscribe was successful, failed, or inconclusive. Look for:
1. Success indicators (unsubscribed, confirmed, removed, success, done, etc.)
2. Failure indicators (error, failed, not found, invalid, already, etc.)
3. Status messages and notifications
4. Page headings and titles that indicate result

Respond with JSON: {"status": "success"|"failure"|"inconclusive", "reason": "explanation"}
Make the reason as short as possible.
PROMPT;
        
        $result = $this->client->chat()->create([
            'model' => 'gpt-4o',
            'messages' => [
                ['role' => 'system', 'content' => 'You are an expert at analyzing unsubscribe page responses.'],
                ['role' => 'user', 'content' => $prompt],
            ],
            'max_tokens' => 200,
            'temperature' => 0,
        ]);
        
        $raw = trim($result['choices'][0]['message']['content'] ?? $result['choices'][0]['text'] ?? '');
        
        $this->logger->info('Raw OpenAI success analysis output', ['raw' => $raw]);

        // Try to parse JSON response
        $jsonStart = strpos($raw, '{');
        $jsonEnd = strrpos($raw, '}');
        
        if ($jsonStart !== false && $jsonEnd !== false) {
            $jsonStr = substr($raw, $jsonStart, $jsonEnd - $jsonStart + 1);
            $result = json_decode($jsonStr, true);
            
            if ($result && isset($result['status'])) {
                $status = strtolower($result['status']);
                if (in_array($status, ['success', 'failure', 'inconclusive'])) {
                    return [
                        'status' => $status,
                        'reason' => $result['reason'] ?? 'No reason provided'
                    ];
                }
            }
        }
        
        // Fallback: simple keyword analysis
        $successKeywords = ['unsubscribed', 'success', 'confirmed', 'removed', 'cancelled', 'done'];
        $failureKeywords = ['error', 'failed', 'not found', 'invalid', 'expired'];
        
        $htmlLower = strtolower($html);
        $hasSuccess = false;
        $hasFailure = false;
        
        foreach ($successKeywords as $keyword) {
            if (strpos($htmlLower, $keyword) !== false) {
                $hasSuccess = true;
                break;
            }
        }
        
        foreach ($failureKeywords as $keyword) {
            if (strpos($htmlLower, $keyword) !== false) {
                $hasFailure = true;
                break;
            }
        }
        
        if ($hasSuccess && !$hasFailure) {
            return [
                'status' => 'success',
                'reason' => 'Success keywords found'
            ];
        } elseif ($hasFailure) {
            return [
                'status' => 'failure',
                'reason' => 'Failure keywords found'
            ];
        } else {
            return [
                'status' => 'inconclusive',
                'reason' => 'No clear success or failure indicators found'
            ];
        }
    }

    public function analyzeUnsubscribeActions(string $html, string $context): array
    {
        // Extract only relevant HTML for action analysis
        $this->logger->info('Analyzing unsubscribe actions', ['html' => $html]);
        $relevantHtml = $this->contentPreprocessor->extractRelevantHtmlForUnsubscribe($html);
        $this->logger->info('Relevant HTML', ['relevantHtml' => $relevantHtml]);

        if (empty($relevantHtml)) {
            return [
                'canUsePanther' => false,
                'actions' => []
            ];
        }
        
        $prompt = <<<PROMPT
Analyze this relevant HTML response from an unsubscribe page. Context: {$context}

Relevant HTML Sections:
{$relevantHtml}

Determine if Panther automation can help complete the unsubscribe process. Look for:
1. Forms that need to be filled out
2. Buttons that need to be clicked
3. Links that need to be followed
4. Checkboxes that need to be checked

When specifying selectors, use only valid CSS selectors (e.g., button, button#id, button.class, button[name='foo']). Do NOT use [text='...'] or :contains(...). If you want to select a button by its text, use a third part in the action (e.g., click:button:Proceed) where the third part is the visible text content to match. If no text match is needed, omit the third part.

If actions are needed, provide them in this format:
click:button
click:button#id
click:button:Proceed
fill:input[name='email']:user@example.com
submit:form#unsubscribe

Respond with JSON: {"canUsePanther": true/false, "actions": ["action1", "action2"]}
If you cannot find any relevant actions, return {"canUsePanther": false, "actions": []}
PROMPT;
        
        $result = $this->client->chat()->create([
            'model' => 'gpt-4o',
            'messages' => [
                ['role' => 'system', 'content' => 'You are an expert at determining web automation actions.'],
                ['role' => 'user', 'content' => $prompt],
            ],
            'max_tokens' => 300,
            'temperature' => 0,
        ]);
        
        $raw = trim($result['choices'][0]['message']['content'] ?? $result['choices'][0]['text'] ?? '');
        
        // Try to parse JSON response
        $jsonStart = strpos($raw, '{');
        $jsonEnd = strrpos($raw, '}');
        
        if ($jsonStart !== false && $jsonEnd !== false) {
            $jsonStr = substr($raw, $jsonStart, $jsonEnd - $jsonStart + 1);
            $result = json_decode($jsonStr, true);
            
            if ($result && isset($result['canUsePanther'])) {
                return [
                    'canUsePanther' => $result['canUsePanther'],
                    'actions' => $result['actions'] ?? []
                ];
            }
        }
        
        // Fallback: simple form detection
        $hasForms = strpos($html, '<form') !== false;
        $hasButtons = strpos($html, '<button') !== false || strpos($html, 'type="submit"') !== false;
        
        return [
            'canUsePanther' => $hasForms || $hasButtons,
            'actions' => []
        ];
    }
}
