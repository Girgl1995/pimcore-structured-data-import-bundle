<?php

declare(strict_types=1);

namespace Factotum\StructuredDataImportBundle\EventSubscriber;

use Pimcore\Event\BundleManager\PathsEvent;
use Pimcore\Event\BundleManagerEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class AdminAssetSubscriber implements EventSubscriberInterface
{
    private const JS_PATHS_EVENT = 'onJsPaths';

    /**
     * @return array
     */
    public static function getSubscribedEvents(): array
    {
        return [
            BundleManagerEvents::JS_PATHS => self::JS_PATHS_EVENT,
        ];
    }

    /**
     * @param PathsEvent $event
     * @return void
     */
    public function onJsPaths(PathsEvent $event): void
    {
        $event->addPaths([
            '/bundles/pimcorestructureddataimport/js/configuration/components/mapping/datatarget/structured.js',
        ]);
    }
}
