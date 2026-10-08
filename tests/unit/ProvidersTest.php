<?php

namespace Ernestdefoe\Reel\Tests\unit;

use Ernestdefoe\Reel\Provider\Giphy;
use Ernestdefoe\Reel\Provider\Klipy;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;

/**
 * Both providers turn their service's response into the one shape the picker
 * reads, and ask for what the admin chose. No network: Guzzle answers from a
 * queue and records what was asked.
 */
class ProvidersTest extends TestCase
{
    /** @var array<int, array{request: RequestInterface}> */
    private array $sent = [];

    private function client(array $body): Client
    {
        $stack = HandlerStack::create(new MockHandler([new Response(200, [], json_encode($body))]));
        $stack->push(Middleware::history($this->sent));

        return new Client(['handler' => $stack]);
    }

    /** @return array<string, string> */
    private function query(): array
    {
        parse_str($this->sent[0]['request']->getUri()->getQuery(), $query);

        return $query;
    }

    private function gif(string $id, ?array $downsized = ['url' => 'https://g/d.gif', 'width' => '480', 'height' => '270']): array
    {
        return ['id' => $id, 'title' => " $id ", 'images' => array_filter([
            'fixed_width' => ['url' => 'https://g/t.gif', 'webp' => 'https://g/t.webp', 'width' => '200', 'height' => '113'],
            'downsized_medium' => $downsized,
        ])];
    }

    #[Test]
    public function giphy_searches_with_the_rating_and_language_and_maps_each_gif()
    {
        $giphy = new Giphy($this->client([
            'data' => [$this->gif('one'), $this->gif('broken', null)],
            'pagination' => ['offset' => 24, 'count' => 2, 'total_count' => 100],
        ]), 'KEY');

        $result = $giphy->search('cats', 24, 24, 'pg', 'de_DE');

        $this->assertSame('/v1/gifs/search', $this->sent[0]['request']->getUri()->getPath());
        $this->assertSame(['api_key' => 'KEY', 'limit' => '24', 'offset' => '24', 'rating' => 'pg', 'q' => 'cats', 'lang' => 'de'], $this->query());

        $this->assertSame([[
            'id' => 'one', 'title' => 'one', 'thumb' => 'https://g/t.webp', 'thumbWidth' => 200, 'thumbHeight' => 113,
            'url' => 'https://g/d.gif', 'width' => 480, 'height' => 270,
        ]], $result['items'], 'A GIF without a usable full-size image is skipped');
        $this->assertSame(26, $result['next']);
    }

    #[Test]
    public function giphy_shows_trending_for_an_empty_query_and_stops_at_the_last_page()
    {
        $giphy = new Giphy($this->client([
            'data' => [$this->gif('one')],
            'pagination' => ['offset' => 99, 'count' => 1, 'total_count' => 100],
        ]), 'KEY');

        $result = $giphy->search('', 99, 24, 'g', 'en');

        $this->assertSame('/v1/gifs/trending', $this->sent[0]['request']->getUri()->getPath());
        $this->assertArrayNotHasKey('q', $this->query());
        $this->assertNull($result['next']);
    }

    #[Test]
    public function klipy_pages_by_number_maps_the_rating_and_skips_ads()
    {
        $klipy = new Klipy($this->client(['data' => [
            'has_next' => true,
            'data' => [
                ['type' => 'ad', 'slug' => 'ad', 'file' => [
                    'sm' => ['webp' => ['url' => 'https://ad/s.webp']],
                    'md' => ['gif' => ['url' => 'https://ad/m.gif']],
                ]],
                ['slug' => 'happy-cat', 'title' => 'Happy', 'file' => [
                    'sm' => ['webp' => ['url' => 'https://k/s.webp', 'width' => 220, 'height' => 165]],
                    'md' => ['gif' => ['url' => 'https://k/m.gif', 'width' => 498, 'height' => 374]],
                ]],
            ],
        ]]), 'K/EY');

        $result = $klipy->search('cats', 48, 24, 'r', 'fr');

        $this->assertSame('/api/v1/K%2FEY/gifs/search', $this->sent[0]['request']->getUri()->getPath());
        $this->assertSame([
            'page' => '3', 'per_page' => '24', 'content_filter' => 'off', 'format_filter' => 'gif,webp', 'locale' => 'fr', 'q' => 'cats',
        ], $this->query());

        $this->assertSame([[
            'id' => 'happy-cat', 'title' => 'Happy', 'thumb' => 'https://k/s.webp', 'thumbWidth' => 220, 'thumbHeight' => 165,
            'url' => 'https://k/m.gif', 'width' => 498, 'height' => 374,
        ]], $result['items']);
        $this->assertSame(72, $result['next']);
    }

    #[Test]
    public function klipy_has_no_next_page_when_it_says_so()
    {
        $klipy = new Klipy($this->client(['data' => ['has_next' => false, 'data' => []]]), 'KEY');

        $this->assertSame(['items' => [], 'next' => null], $klipy->search('', 0, 24, 'pg-13', 'en'));
        $this->assertSame('low', $this->query()['content_filter']);
    }
}
