<?php

namespace App\Controller;

use App\Entity\Utilisateur;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;

class RegistrationController extends AbstractController
{
    #[Route('/api/auth/register', name: 'app_register', methods: ['POST'])]
    public function register(
        Request $request,
        UserPasswordHasherInterface $passwordHasher,
        EntityManagerInterface $entityManager,
        JWTTokenManagerInterface $jwtManager
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);

        // Basic validation
        if (empty($data['email']) || empty($data['password']) || empty($data['firstName']) || empty($data['lastName']) || empty($data['birthDate'])) {
            return $this->json(['message' => 'Invalid data'], 400);
        }

        $user = new Utilisateur();
        $user->setAdresseMail($data['email']);
        $user->setNom($data['lastName']);
        $user->setPrenom($data['firstName']);
        $user->setDateAnniversaire(new \DateTime($data['birthDate']));
        $user->setNonce(bin2hex(random_bytes(32))); // Add this line

        $hashedPassword = $passwordHasher->hashPassword(
            $user,
            $data['password']
        );
        $user->setPassword($hashedPassword);

        $entityManager->persist($user);
        $entityManager->flush();

        $token = $jwtManager->create($user);

        return $this->json([
            'message' => 'User registered successfully',
            'user' => $user, // Consider creating a User DTO to control exposed data
            'token' => $token,
        ]);
    }
}
