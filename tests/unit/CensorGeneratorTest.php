<?php

/*
 * This file is part of fof/filter.
 *
 * Copyright (c) FriendsOfFlarum.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace FoF\Filter\Tests\unit;

use FoF\Filter\CensorGenerator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class CensorGeneratorTest extends TestCase
{
    /**
     * Mirrors how CheckPost::checkContent applies the generated patterns, so
     * these assertions describe real filtering behaviour rather than just the
     * shape of the regex.
     */
    protected function isFiltered(array $censors, string $text): bool
    {
        $isExplicit = false;

        preg_replace_callback(
            $censors,
            static function ($matches) use (&$isExplicit) {
                if ($matches) {
                    $isExplicit = true;
                }

                return $matches[0];
            },
            str_replace(' ', '', $text)
        );

        return $isExplicit;
    }

    #[Test]
    public function it_still_matches_plain_words()
    {
        $censors = CensorGenerator::generateCensors('badword');

        $this->assertTrue($this->isFiltered($censors, 'this has badword in it'));
        $this->assertFalse($this->isFiltered($censors, 'this is perfectly fine'));
    }

    #[Test]
    public function it_still_matches_leet_speak_substitutions()
    {
        $censors = CensorGenerator::generateCensors('badword');

        // The whole point of the LEET_REPLACE table: b4dw0rd must still match.
        $this->assertTrue($this->isFiltered($censors, 'this has b4dw0rd in it'));
    }

    #[Test]
    public function it_matches_multibyte_words()
    {
        $censors = CensorGenerator::generateCensors('敏感词');

        $this->assertTrue($this->isFiltered($censors, '这是敏感词内容'));
        $this->assertFalse($this->isFiltered($censors, '完全正常的句子'));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function regexMetacharacters(): array
    {
        return [
            'dot'              => ['.'],
            'plus'             => ['c++'],
            'star'             => ['a*'],
            'open group'       => ['('],
            'close group'      => [')'],
            'character class'  => ['[test]'],
            'anchor'           => ['^foo'],
            'alternation'      => ['a|b'],
            'quantifier range' => ['a{2,3}'],
            'backslash'        => ['back\\slash'],
        ];
    }

    #[Test]
    #[DataProvider('regexMetacharacters')]
    public function metacharacters_do_not_produce_broken_patterns(string $word)
    {
        $censors = CensorGenerator::generateCensors($word);

        foreach ($censors as $pattern) {
            $this->assertNotFalse(
                @preg_match($pattern, 'probe'),
                "Pattern for '$word' failed to compile: $pattern"
            );
        }
    }

    #[Test]
    #[DataProvider('regexMetacharacters')]
    public function metacharacters_do_not_match_unrelated_text(string $word)
    {
        $censors = CensorGenerator::generateCensors($word);

        $this->assertFalse(
            $this->isFiltered($censors, 'a completely unrelated sentence'),
            "Filtering on '$word' incorrectly flagged clean text."
        );
    }

    #[Test]
    public function words_containing_metacharacters_still_match_themselves()
    {
        $censors = CensorGenerator::generateCensors('c++');

        $this->assertTrue($this->isFiltered($censors, 'I write c++ code'));
        $this->assertFalse($this->isFiltered($censors, 'I write rust code'));
    }

    #[Test]
    public function an_invalid_word_does_not_suppress_later_words()
    {
        // preg_replace_callback aborts on the first invalid pattern, so an
        // unescaped metacharacter used to silently disable every word listed
        // after it.
        $censors = CensorGenerator::generateCensors("(\nbadword");

        $this->assertTrue(
            $this->isFiltered($censors, 'this has badword in it'),
            'A word listed after a metacharacter was silently ignored.'
        );
    }

    #[Test]
    public function empty_and_whitespace_only_lines_are_skipped()
    {
        $censors = CensorGenerator::generateCensors("badword\n\n   \n");

        $this->assertCount(1, $censors);
        $this->assertFalse($this->isFiltered($censors, 'nothing to see here'));
    }

    #[Test]
    public function an_empty_list_produces_no_patterns()
    {
        $this->assertSame([], CensorGenerator::generateCensors(''));
        $this->assertSame([], CensorGenerator::generateCensors("\n  \n"));
    }
}
