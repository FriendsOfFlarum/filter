<?php

/*
 * This file is part of fof/filter.
 *
 * Copyright (c) FriendsOfFlarum.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace FoF\Filter;

class CensorGenerator
{
    private const LEET_REPLACE = [
        'a' => '(a|a\.|a\-|4|@|Á|á|À|Â|à|Â|â|Ä|ä|Ã|ã|Å|å|α|Δ|Λ|λ)',
        'b' => '(b|b\.|b\-|8|\|3|ß|Β|β)',
        'c' => '(c|c\.|c\-|Ç|ç|¢|€|<|\(|{|©)',
        'd' => '(d|d\.|d\-|&part;|\|\)|Þ|þ|Ð|ð)',
        'e' => '(e|e\.|e\-|3|€|È|è|É|é|Ê|ê|∑)',
        'f' => '(f|f\.|f\-|ƒ)',
        'g' => '(g|g\.|g\-|6|9)',
        'h' => '(h|h\.|h\-|Η)',
        'i' => '(i|i\.|i\-|!|\||\]\[|]|1|∫|Ì|Í|Î|Ï|ì|í|î|ï)',
        'j' => '(j|j\.|j\-)',
        'k' => '(k|k\.|k\-|Κ|κ)',
        'l' => '(l|1\.|l\-|!|\||\]\[|]|£|∫|Ì|Í|Î|Ï)',
        'm' => '(m|m\.|m\-)',
        'n' => '(n|n\.|n\-|η|Ν|Π)',
        'o' => '(o|o\.|o\-|0|Ο|ο|Φ|¤|°|ø)',
        'p' => '(p|p\.|p\-|ρ|Ρ|¶|þ)',
        'q' => '(q|q\.|q\-)',
        'r' => '(r|r\.|r\-|®)',
        's' => '(s|s\.|s\-|5|\$|§)',
        't' => '(t|t\.|t\-|Τ|τ|7)',
        'u' => '(u|u\.|u\-|υ|µ)',
        'v' => '(v|v\.|v\-|υ|ν)',
        'w' => '(w|w\.|w\-|ω|ψ|Ψ)',
        'x' => '(x|x\.|x\-|Χ|χ)',
        'y' => '(y|y\.|y\-|¥|γ|ÿ|ý|Ÿ|Ý)',
        'z' => '(z|z\.|z\-|Ζ)',
    ];

    public static function generateCensors(string $wordsList): array
    {
        $badwords = explode("\n", trim($wordsList));

        // If badwords is empty or contains empty strings, it will generate a regex that matches everything.
        // We need to skip those.
        $filteredBadwords = array_filter(array_map('trim', $badwords));

        return array_map(static function ($word) {
            return '/'.str_ireplace(array_keys(self::LEET_REPLACE), array_values(self::LEET_REPLACE), $word).'/i';
        }, $filteredBadwords);
    }
}
