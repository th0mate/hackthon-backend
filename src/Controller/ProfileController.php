<?php

namespace App\Controller;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\User\UserInterface;

#[Route('/api/user')]
class ProfileController extends AbstractController
{
    #[Route('/change-password', name: 'app_user_change_password', methods: ['POST'])]
    public function changePassword(
        Request $request,
        UserPasswordHasherInterface $passwordHasher,
        EntityManagerInterface $entityManager
    ): JsonResponse {
        /** @var \App\Entity\Utilisateur $user */
        $user = $this->getUser();
        if (!$user instanceof UserInterface) {
            return new JsonResponse(['message' => 'User not authenticated'], JsonResponse::HTTP_UNAUTHORIZED);
        }

        $data = json_decode($request->getContent(), true);
        $currentPassword = $data['currentPassword'] ?? null;
        $newPassword = $data['newPassword'] ?? null;

        if (empty($currentPassword) || empty($newPassword)) {
            return new JsonResponse(['message' => 'Current and new passwords are required'], JsonResponse::HTTP_BAD_REQUEST);
        }

        // Check if the current password is valid
        if (!$passwordHasher->isPasswordValid($user, $currentPassword)) {
            return new JsonResponse(['message' => 'Invalid current password'], JsonResponse::HTTP_BAD_REQUEST);
        }

        // Hash and set the new password
        $hashedPassword = $passwordHasher->hashPassword($user, $newPassword);
        $user->setPassword($hashedPassword);

        $entityManager->flush();

        return new JsonResponse(['message' => 'Password updated successfully']);
    }

    #[Route('', name: 'app_user_delete', methods: ['DELETE'])]
    public function deleteAccount(
        Request $request,
        UserPasswordHasherInterface $passwordHasher,
        EntityManagerInterface $entityManager
    ): JsonResponse {
        /** @var \App\Entity\Utilisateur $user */
        $user = $this->getUser();
        if (!$user instanceof UserInterface) {
            return new JsonResponse(['message' => 'User not authenticated'], JsonResponse::HTTP_UNAUTHORIZED);
        }

        $data = json_decode($request->getContent(), true);
        $password = $data['password'] ?? null;

        if (empty($password)) {
            return new JsonResponse(['message' => 'Password is required for account deletion.'], JsonResponse::HTTP_BAD_REQUEST);
        }

        if (!$passwordHasher->isPasswordValid($user, $password)) {
            return new JsonResponse(['message' => 'Invalid password.'], JsonResponse::HTTP_FORBIDDEN);
        }

        // Assuming you have a soft-delete mechanism (e.g., a 'deletedAt' field)
        // If not, this will be a hard delete.
        $entityManager->remove($user);
        $entityManager->flush();

        // In a real app, you might also want to invalidate all JWT tokens for this user.

        return new JsonResponse(null, JsonResponse::HTTP_NO_CONTENT);
    }
}
