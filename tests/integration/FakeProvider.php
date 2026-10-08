<?php

namespace Ernestdefoe\Reel\Tests\integration;

use Ernestdefoe\Reel\Provider\Provider;
use RuntimeException;

/** Stands in for GIPHY/KLIPY: records each search and answers from memory. */
class FakeProvider implements Provider
{
    /** @var array<int, array{query: string, offset: int, limit: int, rating: string, locale: string}> */
    public array $calls = [];

    public bool $fail = false;

    public function search(string $query, int $offset, int $limit, string $rating, string $locale): array
    {
        $this->calls[] = compact('query', 'offset', 'limit', 'rating', 'locale');

        if ($this->fail) {
            throw new RuntimeException('cURL error 28 for https://api.giphy.com/v1/gifs/search?api_key=SECRETKEY&q=x');
        }

        return [
            'items' => [[
                'id' => 'abc', 'title' => 'A '.$query, 'thumb' => 'https://media.example/t.webp', 'thumbWidth' => 200,
                'thumbHeight' => 150, 'url' => 'https://media.example/f.gif', 'width' => 480, 'height' => 360,
            ]],
            'next' => $offset + $limit,
        ];
    }

    public function credit(): string
    {
        return 'FAKE';
    }
}
