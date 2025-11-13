<?php

namespace App\Services\AssessmentEngine;

class WordNormalizer
{
    /**
     * Normalize a selected word into a dictionary key.
     * - trims and lowercases
     * - removes non-letter characters (keeps unicode letters)
     */
    public static function normalize(string $word): string
    {
        //Remove whitespace from the beginning and end of the word.
        $w = trim($word);
        //Convert to lowercase
        $w = mb_strtolower($w);

        // Keep only unicode letters (removes spaces, punctuation, digits, symbols)
        $w = preg_replace('/[^\p{L}]+/u', '', $w);

        return $w ?? '';
    }
}
