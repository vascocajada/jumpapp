<?php

namespace App\Service;

use Symfony\Component\Panther\Client as PantherClient;
use Psr\Log\LoggerInterface;

class PantherAutomationService
{
    private string $chromedriverPath;
    private LoggerInterface $logger;

    public function __construct(string $chromedriverPath, LoggerInterface $logger)
    {
        $this->chromedriverPath = $chromedriverPath;
        $this->logger = $logger;
    }

    /**
     * Visit a URL with Panther and return the page source, with retry logic.
     *
     * @param string $url
     * @param int $maxRetries
     * @return array [success, html, message]
     */
    public function visitUrl(string $url, int $maxRetries = 5): array
    {
        $this->logger->info('visitUrl called', ['url' => $url, 'maxRetries' => $maxRetries]);
        $attempt = 0;
        $chromeOptions = [
            '--user-agent=Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36'
        ];
        while ($attempt < $maxRetries) {
            $client = null;
            try {
                $this->logger->info('Panther visiting URL (attempt ' . ($attempt + 1) . ')', ['url' => $url]);
                $client = PantherClient::createChromeClient($this->chromedriverPath, null, $chromeOptions);
                $crawler = $client->request('GET', $url);
                if ($crawler === null) {
                    $client->quit();
                    return [
                        'success' => false,
                        'html' => '',
                        'message' => 'Panther request returned null crawler'
                    ];
                }
                $client->wait(15); // Further increased wait time for page load
                $html = $client->getPageSource();
                $client->quit();
                $this->logger->info('Panther page loaded successfully', ['url' => $url]);
                return [
                    'success' => true,
                    'html' => $html,
                    'message' => 'Panther page loaded successfully'
                ];
            } catch (\Facebook\WebDriver\Exception\NoSuchWindowException $e) {
                $this->logger->warning('Panther NoSuchWindowException, retrying', [
                    'url' => $url,
                    'error' => $e->getMessage(),
                    'attempt' => $attempt + 1
                ]);
            } catch (\Exception $e) {
                $this->logger->error('Panther visitUrl failed', [
                    'url' => $url,
                    'error' => $e->getMessage(),
                    'attempt' => $attempt + 1
                ]);
                if ($attempt + 1 >= $maxRetries) {
                    if ($client) {
                        try {
                            $client->quit();
                        } catch (\Exception $ignored) {}
                    }
                    $this->logger->info('visitUrl failed after retries', ['url' => $url]);
                    return [
                        'success' => false,
                        'html' => '',
                        'message' => 'Panther visitUrl failed: ' . $e->getMessage()
                    ];
                }
            } finally {
                if ($client) {
                    try {
                        $client->quit();
                    } catch (\Exception $ignored) {}
                }
            }
            $attempt++;
            sleep(3); // Increased wait between retries
        }
        return [
            'success' => false,
            'html' => '',
            'message' => 'Panther visitUrl failed after retries'
        ];
    }

    /**
     * Perform a sequence of actions on a page using Panther, with retry logic.
     *
     * @param string $url
     * @param array $actions
     * @param int $maxRetries
     * @return array [success, html, message]
     */
    public function performActions(string $url, array $actions, int $maxRetries = 5): array
    {
        $this->logger->info('performActions called', ['url' => $url, 'actions' => $actions, 'maxRetries' => $maxRetries]);
        $attempt = 0;
        $chromeOptions = [
            '--user-agent=Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36'
        ];
        while ($attempt < $maxRetries) {
            $client = null;
            try {
                $this->logger->info('Panther performing actions (attempt ' . ($attempt + 1) . ')', ['url' => $url, 'actions' => $actions]);
                $client = PantherClient::createChromeClient($this->chromedriverPath, null, $chromeOptions);
                $crawler = $client->request('GET', $url);
                if ($crawler === null) {
                    $client->quit();
                    return [
                        'success' => false,
                        'html' => '',
                        'message' => 'Panther request returned null crawler for actions'
                    ];
                }
                $client->wait(15); // Further increased wait time for page load
                foreach ($actions as $action) {
                    $this->logger->info('Performing action', ['action' => $action]);
                    $result = $this->executeAction($client, $crawler, $action);
                    if (!$result['success']) {
                        $this->logger->warning('Action failed', [
                            'action' => $action,
                            'error' => $result['message']
                        ]);
                    } else {
                        $this->logger->info('Action completed successfully', ['action' => $action]);
                    }
                    $client->wait(2);
                    // Optionally, increase wait between actions if needed
                }
                $html = $client->getPageSource();
                $client->quit();
                $this->logger->info('Panther actions completed', ['url' => $url, 'actions' => $actions]);
                return [
                    'success' => true,
                    'html' => $html,
                    'message' => 'Panther actions completed'
                ];
            } catch (\Facebook\WebDriver\Exception\NoSuchWindowException $e) {
                $this->logger->warning('Panther NoSuchWindowException in actions, retrying', [
                    'url' => $url,
                    'error' => $e->getMessage(),
                    'attempt' => $attempt + 1
                ]);
            } catch (\Exception $e) {
                $this->logger->error('Panther performActions failed', [
                    'url' => $url,
                    'error' => $e->getMessage(),
                    'attempt' => $attempt + 1
                ]);
                if ($attempt + 1 >= $maxRetries) {
                    if ($client) {
                        try {
                            $client->quit();
                        } catch (\Exception $ignored) {}
                    }
                    $this->logger->info('performActions failed after retries', ['url' => $url, 'actions' => $actions]);
                    return [
                        'success' => false,
                        'html' => '',
                        'message' => 'Panther performActions failed: ' . $e->getMessage()
                    ];
                }
            } finally {
                if ($client) {
                    try {
                        $client->quit();
                    } catch (\Exception $ignored) {}
                }
            }
            $attempt++;
            sleep(3); // Increased wait between retries
        }
        return [
            'success' => false,
            'html' => '',
            'message' => 'Panther performActions failed after retries'
        ];
    }

    /**
     * Execute a single Panther action (click, fill, submit).
     *
     * @param $client
     * @param $crawler
     * @param string $action
     * @return array [success, message]
     */
    private function executeAction($client, $crawler, string $action): array
    {
        $this->logger->info('executeAction called', ['action' => $action]);
        try {
            $parts = explode(':', $action, 3);
            $actionType = $parts[0] ?? '';
            $selector = $parts[1] ?? '';
            $value = $parts[2] ?? '';
            $this->logger->info('Parsed action', ['actionType' => $actionType, 'selector' => $selector, 'value' => $value]);
            switch ($actionType) {
                case 'click':
                    $element = $crawler->filter($selector);
                    $this->logger->info('Click action: elements found', ['count' => $element->count(), 'selector' => $selector]);
                    if ($element->count() > 0) {
                        if ($value) {
                            foreach ($element as $el) {
                                $elText = trim($el->getText());
                                $this->logger->info('Checking element text', ['elementText' => $elText, 'targetText' => $value]);
                                if ($elText === $value) {
                                    $el->click();
                                    $this->logger->info('Clicked element with matching text', ['selector' => $selector, 'text' => $value]);
                                    // Simple wait after click
                                    sleep(5);
                                    // Take screenshot after waiting
                                    try {
                                        $screenshotPath = sys_get_temp_dir() . '/panther_unsubscribe_' . uniqid() . '.png';
                                        $client->takeScreenshot($screenshotPath);
                                        $this->logger->info('Screenshot taken after click', ['path' => $screenshotPath]);
                                    } catch (\Exception $e) {
                                        $this->logger->warning('Failed to take screenshot after click', ['error' => $e->getMessage()]);
                                    }
                                    return ['success' => true, 'message' => "Clicked element with text: {$value}"];
                                }
                            }
                            $this->logger->info('No element with matching text found', ['selector' => $selector, 'text' => $value]);
                            return ['success' => false, 'message' => "No element with text '{$value}' found for selector: {$selector}"];
                        } else {
                            $element->first()->click();
                            $this->logger->info('Clicked first element', ['selector' => $selector]);
                            // Simple wait after click
                            sleep(5);
                            // Take screenshot after waiting
                            try {
                                $screenshotPath = sys_get_temp_dir() . '/panther_unsubscribe_' . uniqid() . '.png';
                                $client->takeScreenshot($screenshotPath);
                                $this->logger->info('Screenshot taken after click', ['path' => $screenshotPath]);
                            } catch (\Exception $e) {
                                $this->logger->warning('Failed to take screenshot after click', ['error' => $e->getMessage()]);
                            }
                            return ['success' => true, 'message' => "Clicked element: {$selector}"];
                        }
                    }
                    $this->logger->info('No elements found for click action', ['selector' => $selector]);
                    break;
                case 'fill':
                    $element = $crawler->filter($selector);
                    $this->logger->info('Fill action: elements found', ['count' => $element->count(), 'selector' => $selector, 'value' => $value]);
                    if ($element->count() > 0) {
                        $element->first()->sendKeys($value);
                        $this->logger->info('Filled element', ['selector' => $selector, 'value' => $value]);
                        return ['success' => true, 'message' => "Filled element: {$selector} with {$value}"];
                    }
                    $this->logger->info('No elements found for fill action', ['selector' => $selector]);
                    break;
                case 'submit':
                    $form = $crawler->filter($selector);
                    $this->logger->info('Submit action: forms found', ['count' => $form->count(), 'selector' => $selector]);
                    if ($form->count() > 0) {
                        $form->first()->submit();
                        $this->logger->info('Submitted form', ['selector' => $selector]);
                        return ['success' => true, 'message' => "Submitted form: {$selector}"];
                    }
                    $this->logger->info('No forms found for submit action', ['selector' => $selector]);
                    break;
                default:
                    $this->logger->info('Unknown action type', ['actionType' => $actionType]);
                    return ['success' => false, 'message' => "Unknown action type: {$actionType}"];
            }
            $this->logger->info('Element not found for action', ['action' => $action]);
            return ['success' => false, 'message' => "Element not found for action: {$action}"];
        } catch (\Exception $e) {
            $this->logger->info('Exception in executeAction', ['action' => $action, 'error' => $e->getMessage()]);
            return ['success' => false, 'message' => "Action execution failed: " . $e->getMessage()];
        }
    }
} 