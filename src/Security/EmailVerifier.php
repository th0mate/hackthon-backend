<?php

namespace App\Security;

use App\Entity\Utilisateur;
use Symfony\Component\Security\Core\Exception\AccountExpiredException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;

class EmailVerifier implements UserCheckerInterface
{
    public function checkPreAuth(UserInterface $user): void
    {
        if (!$user instanceof Utilisateur) {
            return;
        }

        if (!$user->isVerified()) {
            throw new CustomUserMessageAuthenticationException(
                "Votre adresse email n'est pas valide, veuillez valider le mail qui vous a été envoyé."
            );
        }
    }

    public function checkPostAuth(UserInterface $user): void
    {
        if (!$user instanceof Utilisateur) {
            return;
        }

        // We can add additional checks here if needed after authentication
    }
}
