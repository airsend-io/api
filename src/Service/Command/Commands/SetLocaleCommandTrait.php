<?php declare(strict_types=1);
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/
namespace CodeLathe\Service\Command\Commands;

trait SetLocaleCommandTrait
{
    protected function sanitizeLocale(string $locale): string {

        $locale = trim($locale);
        $locale = preg_replace_callback('/^[a-zA-Z]{2}/', function ($matches) {
            return strtolower($matches[0]);
        }, $locale);
        $locale = preg_replace_callback('/[a-zA-Z]{2}$/', function ($matches) {
            return strtoupper($matches[0]);
        }, $locale);
        return str_replace('_', '-', $locale);
    }
}