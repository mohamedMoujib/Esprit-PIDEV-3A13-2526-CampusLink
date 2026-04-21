<?php

namespace App\EventListener;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Bundle\SecurityBundle\Security;

#[AsEventListener(event: KernelEvents::REQUEST, priority: 7)]
class TwoFAEnforcerListener
{
    private const PUBLIC_ROUTES = [
        'app_login', 'app_logout', 'app_register',
        'app_2fa_check', 'app_2fa_setup',
        'app_forgot_password', 'app_verify_code', 'app_reset_password',
        'app_activate_account', 'app_google_connect', 'app_google_callback',
        'app_home', '_wdt', '_profiler', '_error',
    ];

    public function __construct(
        private Security $security,
        private UrlGeneratorInterface $urlGenerator,
    ) {}

    public function __invoke(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) return;

        $request = $event->getRequest();
        $path    = $request->getPathInfo();
        $route   = $request->attributes->get('_route');

        // Skip assets / profiler
        if (str_starts_with($path, '/assets')
         || str_starts_with($path, '/build')
         || str_starts_with($path, '/_')) {
            return;
        }

        // Skip public routes
        if (in_array($route, self::PUBLIC_ROUTES, true)) return;

        // Skip unauthenticated users
        $user = $this->security->getUser();
        if (!$user) return;

        if ($user->getUserType() !== 'ADMIN') return;

        $session = $request->getSession();

        // No secret yet this session → setup
        if (!$session->get('2fa_secret')) {
            $event->setResponse(new RedirectResponse(
                $this->urlGenerator->generate('app_2fa_setup')
            ));
            return;
        }

        // Has secret but not verified → check
        if (!$session->get('2fa_verified')) {
            $event->setResponse(new RedirectResponse(
                $this->urlGenerator->generate('app_2fa_check')
            ));
        }
    }
}