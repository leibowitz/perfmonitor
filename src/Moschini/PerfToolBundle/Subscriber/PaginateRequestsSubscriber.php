<?php

namespace Moschini\PerfToolBundle\Subscriber;

use Symfony\Component\Finder\Finder;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Knp\Component\Pager\Event\ItemsEvent;

class PaginateRequestsSubscriber implements EventSubscriberInterface
{
    public function items(ItemsEvent $event)
    {
        $cursor = $event->target;
        $event->items = $cursor->skip($event->getOffset())->limit($event->getLimit());
        $event->count = $cursor->count();
        $event->stopPropagation();
    }

    public static function getSubscribedEvents()
    {
        return array(
            'knp_pager.items' => array('items', 1/*increased priority to override any internal*/)
        );
    }
}
