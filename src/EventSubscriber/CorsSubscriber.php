<?php

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gère les en-têtes CORS pour permettre les appels depuis Vercel vers InfinityFree.
 */
class CorsSubscriber implements EventSubscriberInterface
{
    private const ALLOWED_ORIGINS = [
        'https://archon-crm-frontend.vercel.app',
        'http://localhost:3000',
        'http://127.0.0.1:3000',
    ];

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST  => ['onRequest', 9999],
            KernelEvents::RESPONSE => ['onResponse', 9999],
        ];
    }

    public function onRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $origin  = $request->headers->get('Origin', '');

        // Répondre immédiatement aux preflight OPTIONS
        if ($request->getMethod() === 'OPTIONS') {
            $response = new Response('', 204);
            $this->addCorsHeaders($response, $origin);
            $event->setResponse($response);
        }
    }

    public function onResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $origin = $event->getRequest()->headers->get('Origin', '');
        $this->addCorsHeaders($event->getResponse(), $origin);
    }

    private function addCorsHeaders(Response $response, string $origin): void
    {
        // Autoriser l'origine si elle est dans la liste, ou toute origine vercel.app
        if (
            in_array($origin, self::ALLOWED_ORIGINS, true)
            || preg_match('#^https://[a-z0-9-]+\.vercel\.app$#i', $origin)
            || preg_match('#^https?://localhost(:\d+)?$#i', $origin)
        ) {
            $response->headers->set('Access-Control-Allow-Origin', $origin);
        } else {
            // En fallback, autoriser toutes les origines (à restreindre en prod si besoin)
            $response->headers->set('Access-Control-Allow-Origin', '*');
        }

        $response->headers->set('Access-Control-Allow-Methods', 'GET, POST, PUT, PATCH, DELETE, OPTIONS');
        $response->headers->set('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With');
        $response->headers->set('Access-Control-Allow-Credentials', 'true');
        $response->headers->set('Access-Control-Max-Age', '3600');
    }
}
