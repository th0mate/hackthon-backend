<?php

namespace App\Controller;

use App\Entity\Utilisateur;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Environment;

class ResetPasswordController extends AbstractController
{
    #[Route('/api/forgot-password', name: 'app_forgot_password', methods: ['POST'])]
    public function forgotPassword(
        Request $request,
        EntityManagerInterface $entityManager,
        MailerInterface $mailer,
        UrlGeneratorInterface $urlGenerator,
        Environment $twig
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);
        $email = $data['email'] ?? null;

        if (!$email) {
            return $this->json(['message' => 'Email is required.'], 400);
        }

        $user = $entityManager->getRepository(Utilisateur::class)->findOneBy(['adresseMail' => $email]);

        if ($user) {
            // Generate a unique token and save it in the 'nonce' field
            $token = bin2hex(random_bytes(32));
            $user->setNonce($token);
            $entityManager->flush();

            // Send password reset email
            $resetLink = 'http://localhost:5173/reset-password/' . $token;

            $email = (new Email())
                ->from('no-reply@moodflow.com')
                ->to($user->getAdresseMail())
                ->subject('Réinitialisation de votre mot de passe')
                ->html($twig->render('emails/reset_password.html.twig', ['resetLink' => $resetLink]));

            $mailer->send($email);
        }

        // Always return a success message to prevent user enumeration
        return $this->json([
            'message' => 'Si un compte correspondant à cet e-mail existe, un lien de réinitialisation de mot de passe a été envoyé.'
        ]);
    }

    #[Route('/api/reset-password', name: 'app_reset_password', methods: ['POST'])]
    public function resetPassword(
        Request $request,
        EntityManagerInterface $entityManager,
        UserPasswordHasherInterface $passwordHasher
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);
        $token = $data['token'] ?? null;
        $newPassword = $data['password'] ?? null;
        $confirmPassword = $data['confirmPassword'] ?? null;

        if (!$token || !$newPassword || !$confirmPassword) {
            return $this->json(['message' => 'Token and new password are required.'], 400);
        }

        if ($newPassword !== $confirmPassword) {
            return $this->json(['message' => 'Passwords do not match.'], 400);
        }

        $user = $entityManager->getRepository(Utilisateur::class)->findOneBy(['nonce' => $token]);

        if (!$user) {
            return $this->json(['message' => 'Invalid or expired token.'], 400);
        }

        // Password strength validation
        if (
            !preg_match('/[A-Z]/', $newPassword) ||
            !preg_match('/[a-z]/', $newPassword) ||
            !preg_match('/[0-9]/', $newPassword) ||
            !preg_match('/[^a-zA-Z0-9]/', $newPassword) ||
            strlen($newPassword) < 8
        ) {
            return $this->json(['message' => 'Le mot de passe doit comporter au moins 8 caractères et contenir au moins une lettre majuscule, une lettre minuscule, un chiffre et un caractère spécial.'], 400);
        }

        $hashedPassword = $passwordHasher->hashPassword($user, $newPassword);
        $user->setPassword($hashedPassword);
        $user->setNonce(null); // Invalidate the token

        $entityManager->flush();

        return $this->json(['message' => 'Le mot de passe a été correctement modifié.']);
    }
}
