<?php

namespace Drupal\example_chat\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class RocketChatEventSubscriber implements EventSubscriberInterface {
  public function ModifyXFrameOptions(ResponseEvent $event) {
    $response = $event->getResponse();

    if ($event->getRequest()->getPathInfo() == '/chat-iframe-login') {
      $response->headers->remove('X-Frame-Options');
    }
  }

  public static function getSubscribedEvents() {
    $events[KernelEvents::RESPONSE][] = array('ModifyXFrameOptions', -10);
    return $events;
  }
}
