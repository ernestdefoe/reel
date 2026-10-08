<?php

namespace Ernestdefoe\Reel\Tests\integration\api;

use Flarum\Testing\integration\RetrievesAuthorizedUsers;
use Flarum\Testing\integration\TestCase;
use Flarum\User\User;
use PHPUnit\Framework\Attributes\Test;

/**
 * What the forum payload tells the composer: whether to show the GIF button,
 * and how to write a chosen GIF into the post.
 */
class ForumAttributesTest extends TestCase
{
    use RetrievesAuthorizedUsers;

    protected function setUp(): void
    {
        parent::setUp();

        $this->extension('ernestdefoe-reel');

        $this->prepareDatabase([
            User::class => [$this->normalUser()],
        ]);
    }

    private function forum(?int $actor = null): array
    {
        $response = $this->send($this->request('GET', '/api', $actor ? ['authenticatedAs' => $actor] : []));

        $this->assertSame(200, $response->getStatusCode());

        return json_decode((string) $response->getBody(), true)['data']['attributes'];
    }

    #[Test]
    public function the_gif_button_is_off_until_a_key_is_set()
    {
        $this->assertFalse($this->forum(2)['reelEnabled']);
        $this->assertFalse($this->forum(1)['reelEnabled']);
    }

    #[Test]
    public function with_a_key_it_shows_for_those_allowed_to_search()
    {
        $this->setting('ernestdefoe-reel.giphy_key', 'KEY');

        $this->assertTrue($this->forum(2)['reelEnabled'], 'Members are granted reel.use on install');
        $this->assertTrue($this->forum(1)['reelEnabled']);
        $this->assertFalse($this->forum()['reelEnabled'], 'Guests may not search');
    }

    #[Test]
    public function the_key_is_never_sent_to_the_browser()
    {
        $this->setting('ernestdefoe-reel.giphy_key', 'SECRETKEY');
        $this->setting('ernestdefoe-reel.klipy_key', 'OTHERSECRET');

        $response = (string) $this->send($this->request('GET', '/api', ['authenticatedAs' => 2]))->getBody();

        $this->assertStringNotContainsString('SECRETKEY', $response);
        $this->assertStringNotContainsString('OTHERSECRET', $response);
    }

    #[Test]
    public function without_markdown_or_bbcode_a_gif_is_inserted_as_its_address()
    {
        $this->assertSame('url', $this->forum(2)['reelInsertFormat']);
    }

    #[Test]
    public function with_markdown_a_gif_is_inserted_as_a_markdown_image()
    {
        $this->extension('flarum-markdown', 'flarum-bbcode');

        $this->assertSame('markdown', $this->forum(2)['reelInsertFormat']);
    }

    #[Test]
    public function with_only_bbcode_a_gif_is_inserted_as_an_img_tag()
    {
        $this->extension('flarum-bbcode');

        $this->assertSame('bbcode', $this->forum(2)['reelInsertFormat']);
    }
}
