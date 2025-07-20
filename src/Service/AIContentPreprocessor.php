<?php

namespace App\Service;

class AIContentPreprocessor
{
    /**
     * Extracts relevant HTML for the unsubscribe flow, focusing on forms, buttons, and interactive elements.
     */
    public function extractRelevantHtmlForUnsubscribe(string $html): string
    {
        if (empty($html)) {
            return '';
        }
        $dom = new \DOMDocument();
        @$dom->loadHTML($html);
        $xpath = new \DOMXPath($dom);

        $sections = [];

        // 1. Extract all forms
        foreach ($xpath->query('//form') as $form) {
            $formHtml = $dom->saveHTML($form);
            $sections[] = "--- FORM ---\n" . strip_tags($formHtml);
        }

        // 2. Extract all buttons
        foreach ($xpath->query('//button') as $button) {
            $buttonText = trim($button->textContent);
            if ($buttonText) {
                $sections[] = "--- BUTTON ---\n" . $buttonText;
            }
        }

        // 3. Extract all input elements (any type)
        foreach ($xpath->query('//input') as $input) {
            if ($input instanceof \DOMElement) {
                $type = $input->getAttribute('type');
                $name = $input->getAttribute('name');
                $value = $input->getAttribute('value');
                $sections[] = "--- INPUT ---\n"
                    . "type: " . ($type ?: 'text') . "\n"
                    . "name: " . $name . "\n"
                    . "value: " . $value;
            }
        }

        // 4. Extract all textarea elements
        foreach ($xpath->query('//textarea') as $textarea) {
            if ($textarea instanceof \DOMElement) {
                $name = $textarea->getAttribute('name');
                $text = trim($textarea->textContent);
                $sections[] = "--- TEXTAREA ---\n"
                    . "name: " . $name . "\n"
                    . "value: " . $text;
            }
        }

        // 5. Extract all select elements and their options
        foreach ($xpath->query('//select') as $select) {
            if ($select instanceof \DOMElement) {
                $name = $select->getAttribute('name');
                $options = [];
                foreach ($xpath->query('.//option', $select) as $option) {
                    $options[] = trim($option->textContent);
                }
                $sections[] = "--- SELECT ---\n"
                    . "name: " . $name . "\n"
                    . "options: " . implode(', ', $options);
            }
        }

        // 6. Extract all links
        foreach ($xpath->query('//a') as $a) {
            if ($a instanceof \DOMElement) {
                $href = $a->getAttribute('href');
                $text = trim($a->textContent);
                if ($href || $text) {
                    $sections[] = "--- LINK ---\n" . ($text ? $text . "\n" : '') . $href;
                }
            }
        }

        // 7. Extract visible instructions: paragraphs, headings, spans, divs
        foreach ($xpath->query('//p | //span | //div | //h1 | //h2 | //h3 | //h4 | //h5 | //h6') as $node) {
            $text = trim($node->textContent);
            if ($text) {
                $sections[] = "--- INSTRUCTION ---\n" . $text;
            }
        }

        // Remove duplicates and combine
        $sections = array_unique($sections);
        $combinedContent = implode("\n\n", $sections);

        // Return the combined content, minimized
        return $this->minimizeText($combinedContent);
    }

    /**
     * Extracts all visible text from the HTML for AI analysis.
     * This is the main method to use for OpenAI input.
     */
    public function extractVisibleTextFromHtml(string $html): string
    {
        if (empty($html)) {
            return '';
        }
        $dom = new \DOMDocument();
        @$dom->loadHTML($html);
        $xpath = new \DOMXPath($dom);
        $body = $xpath->query('//body')->item(0);
        $text = $body ? $this->getVisibleText($body) : '';
        return $this->minimizeText($text);
    }

    /**
     * Recursively gets visible text from a DOM node.
     * Used internally for visible text extraction.
     */
    private function getVisibleText(\DOMNode $node): string
    {
        if ($node->nodeType === XML_TEXT_NODE) {
            return trim($node->nodeValue);
        }
        $text = '';
        foreach ($node->childNodes as $child) {
            $text .= ' ' . $this->getVisibleText($child);
        }
        return trim($text);
    }

    /**
     * Extracts only the text for AI categorization/summarization, preserving hierarchy (titles, subtitles, etc.).
     * Removes all styling, images, icons, svg, and tags that do not contain text.
     * Returns a structured plain text (e.g., with markdown-like markers for headings).
     */
    public function extractTextForAI(string $html): string
    {
        if (empty($html)) {
            return '';
        }
        $dom = new \DOMDocument();
        @$dom->loadHTML($html);
        $this->removeScriptsAndStyles($dom);
        $this->removeMetaImagesIconsLinks($dom);
        if (!$dom->documentElement) {
            return '';
        }
        $text = $this->extractStructuredText($dom->documentElement);
        return $this->minimizeText($text);
    }

    private function removeScriptsAndStyles(\DOMNode $node): void
    {
        if ($node instanceof \DOMDocument) {
            $node = $node->documentElement;
        }
        if (!$node) return;
        $remove = [];
        foreach ($node->childNodes as $child) {
            if ($child->nodeType === XML_COMMENT_NODE ||
                ($child->nodeType === XML_ELEMENT_NODE && in_array(strtolower($child->nodeName), ['script', 'style']))) {
                $remove[] = $child;
            } else {
                $this->removeScriptsAndStyles($child);
            }
        }
        foreach ($remove as $child) {
            $node->removeChild($child);
        }
    }

    private function removeMetaImagesIconsLinks(\DOMNode $node): void
    {
        if ($node instanceof \DOMDocument) {
            $node = $node->documentElement;
        }
        if (!$node) return;
        $remove = [];
        $tagsToRemove = [
            'meta', 'img', 'svg', 'icon', 'link', 'code', 'pre', 'iframe', 'object', 'embed', 'canvas', 'map', 'area',
            'base', 'source', 'track', 'param', 'picture', 'audio', 'video', 'noscript'
        ];
        foreach ($node->childNodes as $child) {
            if ($child->nodeType === XML_ELEMENT_NODE && in_array(strtolower($child->nodeName), $tagsToRemove)) {
                $remove[] = $child;
                continue;
            }
            $this->removeMetaImagesIconsLinks($child);
        }
        foreach ($remove as $child) {
            $node->removeChild($child);
        }
    }

    /**
     * Recursively extracts structured text, preserving headings and important tags.
     * Uses markdown-like markers for headings and bold/italic.
     */
    private function extractStructuredText(\DOMNode $node): string
    {
        if ($node->nodeType === XML_TEXT_NODE) {
            return $node->nodeValue;
        }
        if ($node->nodeType !== XML_ELEMENT_NODE) {
            return '';
        }
        $tag = strtolower($node->nodeName);
        $text = '';
        foreach ($node->childNodes as $child) {
            $childText = $this->extractStructuredText($child);
            if ($childText) {
                if (in_array($tag, ['h1'])) {
                    $text .= "\n# $childText\n";
                } elseif (in_array($tag, ['h2'])) {
                    $text .= "\n## $childText\n";
                } elseif (in_array($tag, ['h3'])) {
                    $text .= "\n### $childText\n";
                } elseif (in_array($tag, ['b', 'strong'])) {
                    $text .= "**$childText**";
                } elseif (in_array($tag, ['i', 'em'])) {
                    $text .= "*$childText*";
                } elseif (in_array($tag, ['li'])) {
                    $text .= "- $childText\n";
                } elseif (in_array($tag, ['p', 'div', 'section', 'article'])) {
                    $text .= "$childText\n";
                } else {
                    $text .= $childText;
                }
            }
        }
        return $text;
    }

    /**
     * Minimizes text by removing line breaks and extra spaces.
     */
    private function minimizeText(string $text): string
    {
        $text = preg_replace('/[ \t\x0B\f\r]+/', ' ', $text); // collapse whitespace
        $text = preg_replace('/\n{2,}/', "\n", $text); // collapse multiple newlines
        $text = preg_replace('/\s+/', ' ', $text); // collapse all whitespace to single space
        return trim($text);
    }

    /**
     * Extracts the HTML body with only whitelisted tags for AI link extraction and context.
     * Keeps all text and hierarchy, and all elements that may contain URLs.
     * Excludes noisy tags like svg, img, script, style, etc.
     */
    public function extractRelevantHtmlForAI(string $html): string
    {
        if (empty($html)) {
            return '';
        }

        $allowedTags = [
            'a', 'button', 'form', 'input', 'label', 'div', 'span', 'p', 'ul', 'ol', 'li',
            'table', 'tr', 'td', 'th', 'tbody', 'thead', 'tfoot',
            'section', 'article', 'header', 'footer',
            'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'b', 'strong', 'i', 'em'
        ];

        $dom = new \DOMDocument();
        @$dom->loadHTML($html);

        // Find the <body> if it exists, otherwise use the whole document
        $body = $dom->getElementsByTagName('body')->item(0);
        $root = $body ?: $dom->documentElement;

        // Improved recursive filter: always process children if root is not allowed
        $filter = function ($node) use (&$filter, $allowedTags, $dom) {
            if ($node->nodeType === XML_ELEMENT_NODE) {
                $tag = strtolower($node->nodeName);
                if (!in_array($tag, $allowedTags)) {
                    // Instead of skipping, process children
                    $fragment = $dom->createDocumentFragment();
                    foreach ($node->childNodes as $child) {
                        $filteredChild = $filter($child);
                        if ($filteredChild) {
                            $fragment->appendChild($filteredChild);
                        }
                    }
                    // Only return the fragment if it has children
                    return $fragment->hasChildNodes() ? $fragment : null;
                }
                $newNode = $dom->createElement($node->nodeName);
                foreach ($node->attributes ?? [] as $attr) {
                    $newNode->setAttribute($attr->nodeName, $attr->nodeValue);
                }
                foreach ($node->childNodes as $child) {
                    $filteredChild = $filter($child);
                    if ($filteredChild) {
                        $newNode->appendChild($filteredChild);
                    }
                }
                return $newNode;
            } elseif ($node->nodeType === XML_TEXT_NODE) {
                return $dom->createTextNode($node->nodeValue);
            }
            return null;
        };

        // Instead of filtering just the root, filter all children of root
        $cleanDom = new \DOMDocument();
        $container = $cleanDom->createElement('div');
        foreach (iterator_to_array($root->childNodes) as $child) {
            $filtered = $filter($child);
            if ($filtered) {
                $container->appendChild($cleanDom->importNode($filtered, true));
            }
        }
        $cleanDom->appendChild($container);

        return $cleanDom->saveHTML($container);
    }
} 