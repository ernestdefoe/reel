<?php

/*
 * Reel — GIF search in the composer.
 */

use Ernestdefoe\Reel\Api\SearchController;
use Ernestdefoe\Reel\Providers;
use Ernestdefoe\Reel\Throttle;
use Flarum\Api\Context;
use Flarum\Api\Resource\ForumResource;
use Flarum\Api\Schema;
use Flarum\Extend;
use Flarum\Extension\ExtensionManager;

return [
    (new Extend\Frontend('forum'))
        ->js(__DIR__.'/js/dist/forum.js')
        ->css(__DIR__.'/less/forum.less'),

    (new Extend\Frontend('admin'))
        ->js(__DIR__.'/js/dist/admin.js'),

    new Extend\Locales(__DIR__.'/locale'),

    (new Extend\Settings())
        ->default('ernestdefoe-reel.provider', 'giphy')
        ->default('ernestdefoe-reel.rating', 'pg-13'),

    (new Extend\ApiResource(ForumResource::class))
        ->fields(fn () => [
            // The GIF button appears only when it will work: a key is set and
            // this member may search.
            Schema\Boolean::make('reelEnabled')
                ->get(fn ($model, Context $context) => resolve(Providers::class)->configured() && $context->getActor()->can('reel.use')),

            /*
             * How a chosen GIF is written into the post. Markdown also covers
             * Scribe, which turns an inserted ![alt](url) into a real image.
             * A forum with neither Markdown nor Scribe gets BBCode, and one
             * with neither of those gets the bare address.
             */
            Schema\Str::make('reelInsertFormat')
                ->get(function () {
                    $ext = resolve(ExtensionManager::class);

                    return match (true) {
                        $ext->isEnabled('flarum-markdown') || $ext->isEnabled('ernestdefoe-scribe') => 'markdown',
                        $ext->isEnabled('flarum-bbcode') => 'bbcode',
                        default => 'url',
                    };
                }),
        ]),

    (new Extend\Routes('api'))
        ->get('/reel/search', 'reel.search', SearchController::class),

    (new Extend\ThrottleApi())
        ->set('reel-search', Throttle::class),
];
