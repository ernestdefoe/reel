<?php

namespace Ernestdefoe\Reel\Provider;

use GuzzleHttp\Client;

/**
 * KLIPY: https://docs.klipy.com/gifs-api. The service X, Discord and others
 * moved to when Google shut Tenor's API on 30 June 2026.
 */
class Klipy implements Provider
{
    /** GIPHY's ratings mapped onto KLIPY's content filter. */
    private const FILTER = ['g' => 'high', 'pg' => 'medium', 'pg-13' => 'low', 'r' => 'off'];

    public function __construct(private Client $http, private string $key)
    {
    }

    public function credit(): string
    {
        return 'KLIPY';
    }

    public function search(string $query, int $offset, int $limit, string $rating, string $locale): array
    {
        // KLIPY pages by number; Reel speaks offsets so both providers look alike.
        $page = intdiv($offset, $limit) + 1;
        $params = [
            'page' => $page,
            'per_page' => $limit,
            'content_filter' => self::FILTER[$rating] ?? 'medium',
            'format_filter' => 'gif,webp',
            'locale' => substr($locale, 0, 2),
        ];
        $path = 'trending';

        if ($query !== '') {
            $path = 'search';
            $params['q'] = $query;
        }

        $url = 'https://api.klipy.com/api/v1/'.rawurlencode($this->key).'/gifs/'.$path;
        $body = json_decode((string) $this->http->get($url, ['query' => $params])->getBody(), true);
        $data = $body['data'] ?? [];
        $items = [];

        foreach ($data['data'] ?? [] as $gif) {
            // Ad rows only appear when ad parameters are sent, and Reel sends none.
            if (($gif['type'] ?? '') === 'ad' || empty($gif['file'])) {
                continue;
            }

            $file = $gif['file'];
            $thumb = $file['sm']['webp'] ?? $file['sm']['gif'] ?? $file['xs']['gif'] ?? null;
            // md, not hd: hd regularly runs past 10 MB, far too heavy for a post.
            $full = $file['md']['gif'] ?? $file['hd']['gif'] ?? null;

            if (! $thumb || ! $full || empty($full['url'])) {
                continue;
            }

            $items[] = [
                'id' => (string) ($gif['slug'] ?? $gif['id'] ?? ''),
                'title' => trim((string) ($gif['title'] ?? '')),
                'thumb' => $thumb['url'],
                'thumbWidth' => (int) ($thumb['width'] ?? 200),
                'thumbHeight' => (int) ($thumb['height'] ?? 200),
                'url' => $full['url'],
                'width' => (int) ($full['width'] ?? 0),
                'height' => (int) ($full['height'] ?? 0),
            ];
        }

        return ['items' => $items, 'next' => ! empty($data['has_next']) ? $offset + $limit : null];
    }
}
