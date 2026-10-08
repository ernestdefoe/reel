<?php

namespace Ernestdefoe\Reel;

use Flarum\Http\RequestUtil;
use Illuminate\Contracts\Cache\Repository;
use Psr\Http\Message\ServerRequestInterface;

/**
 * At most 30 searches a minute per member: plenty for typing a search, far too
 * few for a script to drain the forum's GIF quota.
 */
class Throttle
{
    public function __construct(private Repository $cache)
    {
    }

    public function __invoke(ServerRequestInterface $request): ?bool
    {
        if ($request->getAttribute('routeName') !== 'reel.search') {
            return null;
        }

        $actor = RequestUtil::getActor($request);
        $key = 'reel.throttle.'.($actor->isGuest() ? 'ip.'.sha1((string) $request->getAttribute('ipAddress')) : $actor->id).'.'.intdiv(time(), 60);

        $this->cache->add($key, 0, 120);
        $count = $this->cache->increment($key);

        return $count > 30 ? true : null;
    }
}
