<?php

namespace App\Controller;

use App\Entity\Utilisateur;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\User\UserInterface;

#[Route('/api')]
class EmailChangeController extends AbstractController
{
    #[Route('/user/request-email-change', name: 'app_request_email_change', methods: ['POST'])]
    public function requestEmailChange(Request $request, MailerInterface $mailer, EntityManagerInterface $entityManager): JsonResponse
    {
        echo ("affichage de qql chpse");
        /** @var Utilisateur $securityUser */
        $securityUser = $this->getUser();
        if (!$securityUser instanceof UserInterface) {
            return new JsonResponse(['message' => 'User not authenticated'], JsonResponse::HTTP_UNAUTHORIZED);
        }

        // Fetch a managed instance of the user from the database to ensure changes are persisted.
        $user = $entityManager->getRepository(Utilisateur::class)->find($securityUser->getId());
        if (!$user) {
            return new JsonResponse(['message' => 'User not found in database'], JsonResponse::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true);
        $newEmail = $data['email'] ?? null;

        if (empty($newEmail) || !filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
            return new JsonResponse(['message' => 'Invalid email provided'], JsonResponse::HTTP_BAD_REQUEST);
        }

        // Generate a unique token
        $token = bin2hex(random_bytes(32));

        $user->setPendingEmail($newEmail);
        $user->setNonce($token);

        $entityManager->persist($user);
        $entityManager->flush();

        // --- Mailer part to be uncommented once symfony/mailer is installed ---
        
        $confirmationLink = $this->generateUrl('app_confirm_email_change', ['token' => $token], \Symfony\Component\Routing\Generator\UrlGeneratorInterface::ABSOLUTE_URL);

        $email = (new Email())
            ->from('no-reply@yourdomain.com')
            ->to($newEmail)
            ->subject('Confirm your new email address')
            ->html('<p>Please click the following link to confirm your new email address:</p><p><a href="' . $confirmationLink . '">Confirm Email</a></p>');

        try {
            $mailer->send($email);
        } catch (\Symfony\Component\Mailer\Exception\TransportExceptionInterface $e) {
            // Log the error and return a specific message
            return new JsonResponse([
                'message' => 'Could not send the confirmation email. Please check your mailer configuration in .env',
                'error' => $e->getMessage()
            ], JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
        }
        

        return new JsonResponse(['message' => 'A confirmation email has been sent to your new email address. Please check your inbox.']);
    }

    #[Route('/user/confirm-email-change/{token}', name: 'app_confirm_email_change', methods: ['GET'])]
    public function confirmEmailChange(string $token, EntityManagerInterface $entityManager): JsonResponse
    {
        $user = $entityManager->getRepository(Utilisateur::class)->findOneBy(['nonce' => $token]);

        if (!$user) {
            return new JsonResponse(['message' => 'Invalid or expired token'], JsonResponse::HTTP_BAD_REQUEST);
        }

        $newEmail = $user->getPendingEmail();
        if (empty($newEmail)) {
            return new JsonResponse(['message' => 'No pending email change found'], JsonResponse::HTTP_BAD_REQUEST);
        }

        $user->setAdresseMail($newEmail);
        $user->setPendingEmail(null);
        $user->setNonce(null);

        $entityManager->persist($user);
        $entityManager->flush();

        return new JsonResponse(['message' => 'Your email address has been successfully updated.']);
    }
}
