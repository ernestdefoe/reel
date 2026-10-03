<?php

namespace Ernestdefoe\Reel\Api;

use Ernestdefoe\Reel\Provider\Provider;
use Ernestdefoe\Reel\Providers;
use Flarum\Http\RequestUtil;
use Flarum\Locale\TranslatorInterface;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Arr;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;

/**
 * GET /api/reel/search?q=&offset=: GIFs from the chosen provider.
 *
 * 🚨 Through the forum, never from the browser. The API key would otherwise
 * sit in every visitor's page source, free for anyone to take and spend. The
 * forum also caches each page of results for ten minutes, so a busy forum
 * searching "lol" all day costs one request, not hundreds, against the
 * provider's free quota.
 */
class SearchController implements RequestHandlerInterface
{
    private const PER_PAGE = 24;

    public function __construct(
        private Providers $providers,
        private Repository $cache,
        private TranslatorInterface $translator,
        private LoggerInterface $log,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $actor = RequestUtil::getActor($request);
        $actor->assertCan('reel.use');

        $provider = $this->providers->make();

        if (! $provider instanceof Provider) {
            return $this->error('not_configured', 503);
        }

        $params = $request->getQueryParams();
        $query = mb_substr(trim((string) Arr::get($params, 'q', '')), 0, 100);
        $offset = max(0, min(500, (int) Arr::get($params, 'offset', 0)));
        $rating = $this->providers->rating();
        $locale = (string) $this->translator->getLocale();

        $key = 'reel.' . sha1(implode('|', [$this->providers->name(), $rating, $locale, mb_strtolower($query), $offset]));

        try {
            $result = $this->cache->remember($key, 600, fn () => $provider->search($query, $offset, self::PER_PAGE, $rating, $locale));
        } catch (\Throwable $e) {
            // The provider's message can contain the request URL, and so the key.
            $this->log->warning('[reel] ' . $provider->credit() . ' search failed: ' . preg_replace('/(api_key=|\/api\/v1\/)[^&\/\s]+/', '$1***', $e->getMessage()));

            return $this->error('unavailable', 502);
        }

        return new JsonResponse($result + ['credit' => $provider->credit()]);
    }

    private function error(string $code, int $status): ResponseInterface
    {
        return new JsonResponse(['errors' => [[
            'status' => (string) $status,
            'code'   => 'reel_' . $code,
            'detail' => $this->translator->trans('ernestdefoe-reel.lib.errors.' . $code),
        ]]], $status);
    }
}
