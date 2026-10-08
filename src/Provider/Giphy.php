<?php

namespace Ernestdefoe\Reel\Provider;

use GuzzleHttp\Client;

/** GIPHY: https://developers.giphy.com/docs/api/endpoint */
class Giphy implements Provider
{
    public function __construct(private Client $http, private string $key)
    {
    }

    public function credit(): string
    {
        return 'GIPHY';
    }

    public function search(string $query, int $offset, int $limit, string $rating, string $locale): array
    {
        $params = ['api_key' => $this->key, 'limit' => $limit, 'offset' => $offset, 'rating' => $rating];
        $path = 'trending';

        if ($query !== '') {
            $path = 'search';
            $params['q'] = $query;
            $params['lang'] = substr($locale, 0, 2);
        }

        $body = json_decode((string) $this->http->get('https://api.giphy.com/v1/gifs/'.$path, ['query' => $params])->getBody(), true);
        $items = [];

        foreach ($body['data'] ?? [] as $gif) {
            $thumb = $gif['images']['fixed_width'] ?? null;
            // downsized_medium stays under 5 MB; the original can run far larger.
            $full = $gif['images']['downsized_medium'] ?? $gif['images']['original'] ?? null;

            if (! $thumb || ! $full || empty($full['url'])) {
                continue;
            }

            $items[] = [
                'id' => (string) $gif['id'],
                'title' => trim((string) ($gif['title'] ?? '')),
                'thumb' => $thumb['webp'] ?? $thumb['url'],
                'thumbWidth' => (int) ($thumb['width'] ?? 200),
                'thumbHeight' => (int) ($thumb['height'] ?? 200),
                'url' => $full['url'],
                'width' => (int) ($full['width'] ?? 0),
                'height' => (int) ($full['height'] ?? 0),
            ];
        }

        $p = $body['pagination'] ?? [];
        $nextOffset = (int) ($p['offset'] ?? $offset) + (int) ($p['count'] ?? count($items));
        $more = $items && $nextOffset < (int) ($p['total_count'] ?? 0);

        return ['items' => $items, 'next' => $more ? $nextOffset : null];
    }
}
