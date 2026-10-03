<?php

namespace Ernestdefoe\Reel\Provider;

/**
 * A GIF service. Both return the same shape, so the picker never knows which
 * one it is talking to.
 *
 * An item: id, title, thumb (small animated preview), thumbWidth/thumbHeight,
 * url (the GIF that goes in the post), width/height.
 *
 * @phpstan-type Item array{id:string,title:string,thumb:string,thumbWidth:int,thumbHeight:int,url:string,width:int,height:int}
 */
interface Provider
{
    /**
     * Trending when $query is empty.
     *
     * @return array{items: array<int, array<string, mixed>>, next: ?int}
     */
    public function search(string $query, int $offset, int $limit, string $rating, string $locale): array;

    /** The name shown in the panel's "Powered by" credit. */
    public function credit(): string;
}
