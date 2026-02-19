<?php

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class LocaleSubscriber implements EventSubscriberInterface
{
    private const SESSION_LOCALE_KEY = '_locale';
    private const SUPPORTED_LOCALES = ['en', 'fr', 'ar'];

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 20],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $session = $request->hasSession() ? $request->getSession() : null;

        $queryLocale = $request->query->get('_locale');
        if (is_string($queryLocale) && in_array($queryLocale, self::SUPPORTED_LOCALES, true)) {
            $request->setLocale($queryLocale);

            if ($session !== null) {
                $session->set(self::SESSION_LOCALE_KEY, $queryLocale);
            }

            return;
        }

        if ($session !== null) {
            $sessionLocale = $session->get(self::SESSION_LOCALE_KEY);
            if (is_string($sessionLocale) && in_array($sessionLocale, self::SUPPORTED_LOCALES, true)) {
                $request->setLocale($sessionLocale);
            }
        }
    }
}
