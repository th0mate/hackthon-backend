<?php

namespace App\Controller;

use App\Entity\Utilisateur;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\User\UserInterface;

#[Route('/api')]
class EmailChangeController extends AbstractController
{
    #[Route('/user/request-email-change', name: 'app_request_email_change', methods: ['POST'])]
    public function requestEmailChange(Request $request, MailerInterface $mailer, EntityManagerInterface $entityManager, \App\Repository\UtilisateurRepository $utilisateurRepository): JsonResponse
    {
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

        $existingUser = $utilisateurRepository->findOneBy(['adresseMail' => $newEmail]);
        if ($existingUser) {
            return new JsonResponse(['message' => 'This email address is already in use.'], JsonResponse::HTTP_CONFLICT);
        }

        // Generate a unique token
        $token = bin2hex(random_bytes(32));

        $user->setPendingEmail($newEmail);
        $user->setNonce($token);

        $entityManager->persist($user);
        $entityManager->flush();


        $confirmationLink = $this->generateUrl('app_confirm_email_change', ['token' => $token], \Symfony\Component\Routing\Generator\UrlGeneratorInterface::ABSOLUTE_URL);

        $emailBody = '<body style="font-family: Arial, sans-serif; margin: 0; padding: 40px; background-color: #f4f4f4;">\n'
        . '    <div style="max-width: 600px; margin: 0 auto; background-color: #ffffff; padding: 30px; border-radius: 10px; box-shadow: 0 4px 10px rgba(0,0,0,0.1);">
'
        . '        <h1 style="color: #333; text-align: center; font-size: 24px;">Confirmez votre nouvelle adresse e-mail</h1>
'
        . '        <p style="color: #555; font-size: 16px; line-height: 1.6;">Bonjour,</p>
'
        . '        <p style="color: #555; font-size: 16px; line-height: 1.6;">Veuillez cliquer sur le bouton ci-dessous pour confirmer le changement de votre adresse e-mail pour votre compte MoodFlow+.</p>
'
        . '        <div style="text-align: center; margin: 30px 0;">
'
        . '            <a href="' . $confirmationLink . '" style="background-color: #8a2be2; color: #ffffff; padding: 15px 25px; text-decoration: none; border-radius: 5px; font-size: 16px; font-weight: bold;">Confirmer mon e-mail</a>
'
        . '        </div>
'
        . '        <p style="color: #555; font-size: 16px; line-height: 1.6;">Si vous n\'avez pas demandé ce changement, vous pouvez ignorer cet e-mail en toute sécurité.</p>
'
        . '        <hr style="border: none; border-top: 1px solid #eeeeee; margin: 20px 0;">
'
        . '        <p style="color: #888; font-size: 12px; text-align: center;">L\'équipe MoodFlow+</p>
'
        . '    </div>
'
        . '</body>';

        $email = (new Email())
            ->from('no-reply@moodflow.com')
            ->to($newEmail)
            ->subject('Confirmez votre nouvelle adresse e-mail')
            ->html($emailBody);

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
    public function confirmEmailChange(string $token, EntityManagerInterface $entityManager): JsonResponse|RedirectResponse
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

        return new RedirectResponse('http://localhost:5173/dashboard?email_changed=true');
    }
}
