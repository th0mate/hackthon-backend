<?php

namespace App\Controller;

use App\Entity\Utilisateur;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Environment;

class RegistrationController extends AbstractController
{
    #[Route('/api/auth/register', name: 'app_register', methods: ['POST'])]
    public function register(
        Request $request,
        UserPasswordHasherInterface $passwordHasher,
        EntityManagerInterface $entityManager,
        MailerInterface $mailer,
        UrlGeneratorInterface $urlGenerator,
        Environment $twig
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);

        // Basic validation
        if (empty($data['email']) || empty($data['password']) || empty($data['firstName']) || empty($data['lastName']) || empty($data['birthDate'])) {
            return $this->json(['message' => 'Invalid data'], 400);
        }

        // Password strength validation
        $password = $data['password'];
        if (
            !preg_match('/[A-Z]/', $password) ||       // At least one uppercase letter
            !preg_match('/[a-z]/', $password) ||       // At least one lowercase letter
            !preg_match('/[0-9]/', $password) ||       // At least one digit
            !preg_match('/[^a-zA-Z0-9]/', $password) || // At least one special character
            strlen($password) < 8                     // Minimum length of 8 characters
        ) {
            return $this->json(['message' => 'Le mot de passe doit comporter au moins 8 caractères et contenir au moins une lettre majuscule, une lettre minuscule, un chiffre et un caractère spécial.'], 400);
        }

        // Age validation (must be at least 12 years old)
        $birthDate = new \DateTime($data['birthDate']);
        $now = new \DateTime();
        $age = $now->diff($birthDate)->y;
        if ($age < 12) {
            return $this->json(['message' => 'You must be at least 12 years old to register.'], 400);
        }

        // Check if a user with this email already exists
        $existingUser = $entityManager->getRepository(Utilisateur::class)->findOneBy(['adresseMail' => $data['email']]);
        if ($existingUser) {
            return $this->json(['message' => 'Cet adresse email est déjà utilisée.'], 409); // 409 Conflict
        }

        $user = new Utilisateur();
        $user->setAdresseMail($data['email']);
        $user->setNom($data['lastName']);
        $user->setPrenom($data['firstName']);
        $user->setDateAnniversaire(new \DateTime($data['birthDate']));
        $user->setVerified(false);
        $user->setVerificationToken(bin2hex(random_bytes(32)));

        $hashedPassword = $passwordHasher->hashPassword(
            $user,
            $data['password']
        );
        $user->setPassword($hashedPassword);

        $entityManager->persist($user);
        $entityManager->flush();

        // Send verification email
        $verificationLink = $this->generateUrl('app_verify_email', ['token' => $user->getVerificationToken()],
            UrlGeneratorInterface::ABSOLUTE_URL
        );

        $email = (new Email())
            ->from('no-reply@moodflow.com')
            ->to($user->getAdresseMail())
            ->subject('Confirmation de la création de compte.')
            ->html($twig->render('emails/registration_confirmation.html.twig', ['verificationLink' => $verificationLink]));

        $mailer->send($email);

        return $this->json([
            'message' => 'Un email a été envoyé à votre adresse email afin de finaliser la création de votre compte.',
        ]);
    }

    #[Route('/api/auth/verify-email', name: 'app_verify_email', methods: ['GET'])]
    public function verifyEmail(Request $request, EntityManagerInterface $entityManager): JsonResponse|RedirectResponse
    {
        $token = $request->query->get('token');

        if (!$token) {
            return $this->json(['message' => 'Verification token missing.'], 400);
        }

        $user = $entityManager->getRepository(Utilisateur::class)->findOneBy(['verificationToken' => $token]);

        if (!$user) {
            return $this->json(['message' => 'Invalid verification token.'], 400);
        }

        // Mark user as verified and clear verification token
        $user->setVerified(true);
        $user->setVerificationToken(null);

        $entityManager->persist($user);
        $entityManager->flush();

        // Redirect to a frontend page (e.g., login page with a success message)
        return new RedirectResponse('http://localhost:5173/login');

    }
}
