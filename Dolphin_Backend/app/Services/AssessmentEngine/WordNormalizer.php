<?php

namespace App\Services\AssessmentEngine;

/**
 * WordNormalizer
 *
 * Normalizes human-entered words to keys used by weight dictionaries by:
 * - trimming and lowercasing
 * - removing spaces, hyphens, punctuation
 * - mapping a few known legacy aliases/misspellings to the canonical key
 */
class WordNormalizer
{
    /**
     * Normalize a selected word into a key matching our weight dictionaries.
     * - lowercase
     * - remove spaces and hyphens
     * - trim punctuation
     * - fix a few known aliases/misspellings from legacy data
     */
    /**
     * Normalize a selected word into a dictionary key.
     */
    public static function normalize(string $word): string
    {
        $w = trim($word);
        $w = mb_strtolower($w);
        // replace common punctuation and spaces
        $w = str_replace(["\u{2011}", "\u{2013}", "\u{2014}", "-", " "], '', $w); // hyphen-like and spaces
        $w = preg_replace('/[^a-z]/u', '', $w) ?? $w; // keep only letters

        // legacy aliases
        $aliases = [
            'selfstarter'    => 'selfstarter',
            'selfstarterr'   => 'selfstarter',
            'nonconforming'  => 'nonconfirming', // file uses 'nonconfirming'
            'worrying'       => 'worrier',
            'highstrung'     => 'highstrung', // already normalized
            'fastpaced'      => 'fastpaced',   // already normalized
            'deeplyreserved' => 'deeplyreserved',
            'deeplyreserverd'=> 'deeplyreserved',
            'confirming'     => 'confirming', // present in weights
            'conforming'     => 'confirming', // selected data often says 'Conforming'
        ];

        if (isset($aliases[$w])) {
            return $aliases[$w];
        }
        return $w;
    }
}
