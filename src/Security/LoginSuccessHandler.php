<?php

namespace App\Security;

use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationSuccessHandlerInterface;

class LoginSuccessHandler implements AuthenticationSuccessHandlerInterface
{
    public function __construct(private RouterInterface $router)
    {
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token): Response
    {
        $user = $token->getUser();
        $roles = $user->getRoles();

        // Redirect based on user roles
        if (in_array('ROLE_ADMIN', $roles)) {
            return new RedirectResponse($this->router->generate('app_admin_page'));
        }

        // For ROLE_ELEVE and ROLE_PARENT, redirect to home
        return new RedirectResponse($this->router->generate('app_home_page'));
    }
}
