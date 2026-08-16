<?php

/*
 * This file is part of fof/filter.
 *
 * Copyright (c) FriendsOfFlarum.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace FoF\Filter\Tests\integration;

use FoF\Filter\Listener\CheckPost;
use PHPUnit\Framework\Attributes\Test;

class CheckPostContentTest extends FilterTestCase
{
    protected function setUp(): void
    {
        $this->extension('flarum-flags', 'flarum-approval', 'fof-filter');

        $this->addWordsToFilter("badword\nc++");

        parent::setUp();
    }

    protected function checker(): CheckPost
    {
        return $this->app()->getContainer()->make(CheckPost::class);
    }

    #[Test]
    public function it_detects_a_filtered_word()
    {
        $this->assertTrue($this->checker()->checkContent('this contains badword here'));
    }

    #[Test]
    public function it_leaves_clean_content_alone()
    {
        $this->assertFalse($this->checker()->checkContent('a perfectly ordinary post'));
    }

    #[Test]
    public function a_word_containing_metacharacters_matches_literally()
    {
        $checker = $this->checker();

        $this->assertTrue($checker->checkContent('I write c++ every day'));
        $this->assertFalse($checker->checkContent('I write rust every day'));
    }

    #[Test]
    public function malformed_utf8_does_not_smuggle_a_filtered_word_through()
    {
        // Unicode-aware patterns refuse to run against invalid UTF-8, which
        // would otherwise let a post opt out of filtering entirely.
        $this->assertTrue(
            $this->checker()->checkContent("stray bytes \xFF\xFE and badword"),
            'A filtered word slipped through alongside malformed UTF-8.'
        );
    }

    #[Test]
    public function malformed_utf8_alone_is_not_flagged()
    {
        $this->assertFalse($this->checker()->checkContent("just \xFF\xFE stray bytes"));
    }
}
