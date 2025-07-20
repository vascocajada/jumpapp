<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;
use Psr\Log\LoggerInterface;
use App\Service\OpenAIService;
use App\Service\PantherAutomationService;

class UnsubscribeAutomationService
{
    private LoggerInterface $logger;
    private HttpClientInterface $httpClient;
    private OpenAIService $openAIService;
    private PantherAutomationService $pantherAutomationService;

    public function __construct(
        LoggerInterface $logger,
        HttpClientInterface $httpClient,
        OpenAIService $openAIService,
        PantherAutomationService $pantherAutomationService
    ) {
        $this->logger = $logger;
        $this->httpClient = $httpClient;
        $this->openAIService = $openAIService;
        $this->pantherAutomationService = $pantherAutomationService;
    }

    /**
     * Intelligent unsubscribe automation with AI analysis
     */
    public function automateUnsubscribe(string $url): array
    {
        $this->logger->info('Starting intelligent unsubscribe automation', ['url' => $url]);

        // Step 1: Try simple HTTP request first
        $this->logger->info('Step 1: Attempting simple HTTP request');
        $httpResult = $this->attemptHttpUnsubscribe($url);

        if ($httpResult['success']) {
            $this->logger->info('HTTP unsubscribe successful');
            return [
                'status' => 'success',
                'method' => 'http',
                'message' => $httpResult['message']
            ];
        }

        // Step 2: Analyze HTTP response with AI to determine if Panther actions are needed
        $this->logger->info('Step 2: Analyzing HTTP response with AI');
        if (!empty($httpResult['html'])) {
            $aiAnalysis = $this->openAIService->analyzeUnsubscribeActions($httpResult['html'], 'http_response');
            $this->logger->info('AI analysis', ['aiAnalysis' => $aiAnalysis]);

            if ($aiAnalysis['canUsePanther'] && $aiAnalysis['actions']) {
                $this->logger->info('AI suggests Panther actions for HTTP response', [
                    'actions' => $aiAnalysis['actions']
                ]);

                $pantherResult = $this->performPantherActions($url, $aiAnalysis['actions']);

                if ($pantherResult['status'] === 'success') {
                    $this->logger->info('Panther actions on HTTP response successful');
                    return [
                        'status' => 'success',
                        'method' => 'panther_http_actions',
                        'message' => $pantherResult['message']
                    ];
                }
            } else {
                $this->logger->info('AI does not suggest Panther actions');
            }
        } else {
            $this->logger->info('No HTML content to analyze');
        }

        // Step 3: Try Panther request to the original URL
        $this->logger->info('Step 3: Attempting Panther request to original URL');
        $pantherResult = $this->attemptPantherUnsubscribe($url);

        if ($pantherResult['success']) {
            $this->logger->info('Panther unsubscribe successful');
            return [
                'status' => 'success',
                'method' => 'panther_direct',
                'message' => $pantherResult['message']
            ];
        }

        // Step 4: Analyze Panther response with AI to determine additional actions
        $this->logger->info('Step 4: Analyzing Panther response with AI');
        if (!empty($pantherResult['html'])) {
            $aiAnalysis = $this->openAIService->analyzeUnsubscribeActions($pantherResult['html'], 'panther_response');
            $this->logger->info('AI analysis', ['aiAnalysis' => $aiAnalysis]);

            if ($aiAnalysis['canUsePanther'] && $aiAnalysis['actions']) {
                $this->logger->info('AI suggests additional Panther actions', [
                    'actions' => $aiAnalysis['actions']
                ]);

                $finalResult = $this->performPantherActions($url, $aiAnalysis['actions']);

                if ($finalResult['status'] === 'success' || $finalResult['status'] === 'inconclusive') {
                    $this->logger->info('Final Panther actions successful');
                    return [
                        'status' => $finalResult['status'],
                        'method' => 'panther_ai_actions',
                        'message' => $finalResult['message']
                    ];
                }
            } else {
                $this->logger->info('AI does not suggest additional Panther actions');
            }
        } else {
            $this->logger->info('No HTML content to analyze');
        }

        // All methods failed
        $this->logger->error('All unsubscribe methods failed');
        return [
            'status' => 'error',
            'method' => 'all_failed',
            'message' => 'All unsubscribe automation methods failed. Manual intervention required.'
        ];
    }

    /**
     * Attempt simple HTTP unsubscribe request
     */
    private function attemptHttpUnsubscribe(string $url): array
    {
        try {
            $this->logger->info('Making HTTP request to unsubscribe URL', ['url' => $url]);

            $response = $this->httpClient->request('GET', $url, [
                'timeout' => 30,
                'headers' => [
                    'User-Agent' => 'Mozilla/5.0 (compatible; EmailSorter/1.0)',
                    'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                ]
            ]);

            $statusCode = $response->getStatusCode();
            $html = $response->getContent();

            $this->logger->info('HTTP response received', [
                'statusCode' => $statusCode,
                'htmlLength' => strlen($html)
            ]);

            // Always analyze the response content with AI to determine success
            $successAnalysis = $this->openAIService->analyzeUnsubscribeSuccess($html, 'http_response');
            if (!isset($successAnalysis['status'])) {
                $this->logger->error('OpenAIService did not return a status', ['result' => $successAnalysis]);
                return [
                    'success' => false,
                    'html' => $html,
                    'message' => 'No status returned from OpenAIService'
                ];
            }
            if ($successAnalysis['status'] === 'success') {
                return [
                    'success' => true,
                    'html' => $html,
                    'message' => "HTTP unsubscribe successful: " . $successAnalysis['reason']
                ];
            } elseif ($successAnalysis['status'] === 'failure') {
                return [
                    'success' => false,
                    'html' => $html,
                    'message' => "HTTP request completed but unsubscribe failed: " . $successAnalysis['reason']
                ];
            } else { // inconclusive
                return [
                    'success' => false,
                    'html' => $html,
                    'message' => "HTTP request completed but unsubscribe status is inconclusive: " . $successAnalysis['reason']
                ];
            }
        } catch (\Exception $e) {
            $this->logger->error('HTTP unsubscribe request failed', [
                'url' => $url,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'html' => '',
                'message' => "HTTP request failed: " . $e->getMessage()
            ];
        }
    }

    /**
     * Attempt Panther-based unsubscribe using PantherAutomationService
     */
    private function attemptPantherUnsubscribe(string $url): array
    {
        $result = $this->pantherAutomationService->visitUrl($url);
        if ($result['success']) {
            $successAnalysis = $this->openAIService->analyzeUnsubscribeSuccess($result['html'], 'panther_initial');
            if (!isset($successAnalysis['status'])) {
                $this->logger->error('OpenAIService did not return a status', ['result' => $successAnalysis]);
                return [
                    'success' => false,
                    'html' => $result['html'],
                    'message' => 'No status returned from OpenAIService'
                ];
            }
            if ($successAnalysis['status'] === 'success') {
                return [
                    'success' => true,
                    'html' => $result['html'],
                    'message' => 'Panther unsubscribe successful on initial request'
                ];
            } elseif ($successAnalysis['status'] === 'failure') {
                return [
                    'success' => false,
                    'html' => $result['html'],
                    'message' => 'Panther request completed but unsubscribe failed: ' . $successAnalysis['reason']
                ];
            } else { // inconclusive
                return [
                    'success' => false,
                    'html' => $result['html'],
                    'message' => 'Panther request completed but unsubscribe status is inconclusive: ' . $successAnalysis['reason']
                ];
            }
        }
        return [
            'success' => false,
            'html' => $result['html'],
            'message' => $result['message']
        ];
    }

    /**
     * Perform specific Panther actions based on AI analysis using PantherAutomationService
     */
    private function performPantherActions(string $url, array $actions): array
    {
        $result = $this->pantherAutomationService->performActions($url, $actions);
        if ($result['success']) {
            $this->logger->info('Panther actions completed', ['url' => $url, 'actions' => $actions]);
            $successAnalysis = $this->openAIService->analyzeUnsubscribeSuccess($result['html'], 'panther_ai_actions');
            if (!isset($successAnalysis['status'])) {
                $this->logger->error('OpenAIService did not return a status', ['result' => $successAnalysis]);
                return [
                    'status' => 'error',
                    'message' => 'No status returned from OpenAIService'
                ];
            }
            if ($successAnalysis['status'] === 'success') {
                return [
                    'status' => 'success',
                    'message' => 'AI-guided Panther actions successful'
                ];
            } elseif ($successAnalysis['status'] === 'failure') {
                return [
                    'status' => 'failure',
                    'message' => 'AI-guided Panther actions completed but unsubscribe failed: ' . $successAnalysis['reason']
                ];
            } else { // inconclusive
                return [
                    'status' => 'inconclusive',
                    'message' => 'AI-guided Panther actions completed but unsubscribe status is inconclusive: ' . $successAnalysis['reason']
                ];
            }
        }
        return [
            'status' => 'error',
            'message' => $result['message']
        ];
    }
}
