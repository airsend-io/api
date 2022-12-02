<?php

declare(strict_types=1);
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/

namespace CodeLathe\Core\Indexers\FileParsers;

use PhpOffice\PhpWord\IOFactory;

trait PhpWordParserTrait
{

    protected abstract function reader(): string;

    protected function recursiveExtractText($element, string &$output = ''): void
    {

        if (method_exists($element, 'getElements')) {
            foreach ($element->getElements() as $innerElement) {
                $this->recursiveExtractText($innerElement, $output);
            }
        }

        if (method_exists($element, 'getText')) {
            $text = $element->getText();
            if (is_string($text)) {
                $output .= " $text";
            }
            if (method_exists($text, 'getElements')) {
                foreach ($text->getElements() as $innerElement) {
                    $this->recursiveExtractText($innerElement, $output);
                }
            }
        }
    }

    protected function extractText(string $filePath): string
    {
        $text = '';
        $phpWord = @IOFactory::load($filePath, $this->reader());
        foreach ($phpWord->getSections() as $section) {
            foreach ($section->getElements() as $element) {
                $this->recursiveExtractText($element, $text);
            }
        }
        return $text;

    }

    protected function sanitizeContent(string $text): string
    {
        $text = preg_replace('/\s+([.,;:]+)/', '$1', $text);
        return preg_replace('/\s{2,}/', ' ', $text);
    }
}