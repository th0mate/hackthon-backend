<?php

namespace App\EventListener;

use App\Entity\Utilisateur;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Http\Event\InteractiveLoginEvent;
use DateTime;

class LoginListener
{
    private $em;

    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;
    }

    public function onSecurityInteractiveLogin(InteractiveLoginEvent $event)
    {
        $user = $event->getAuthenticationToken()->getUser();

        if ($user instanceof Utilisateur) {
            $today = new DateTime('today');
            $lastLogin = $user->getDerniereConnexion();

            if ($lastLogin === null) {
                $user->setJoursConsecutifs(1);
            } else {
                $diff = $today->diff($lastLogin)->days;
                if ($diff === 1) {
                    $user->setJoursConsecutifs($user->getJoursConsecutifs() + 1);
                } elseif ($diff > 1) {
                    $user->setJoursConsecutifs(1);
                }
                // If $diff is 0, do nothing.
            }

            $user->setDerniereConnexion($today);
            $this->em->persist($user);
            $this->em->flush();
        }
    }
}
