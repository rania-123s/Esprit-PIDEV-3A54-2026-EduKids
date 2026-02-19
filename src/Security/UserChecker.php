<?php

namespace App\Security;

use App\Entity\User;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;

class UserChecker implements UserCheckerInterface
{
    public function checkPreAuth(UserInterface $user): void
    {
        if (!$user instanceof User) {
            return;
        }

        // Check if user is banned (is_active = false)
        if (!$user->isActive()) {
            throw new CustomUserMessageAuthenticationException('Your account has been banned. Please contact administrator for support.');
        }
    }

    public function checkPostAuth(UserInterface $user): void
    {
        // No additional checks after authentication
    }
}
