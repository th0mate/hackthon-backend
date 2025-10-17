<?php

namespace App\Controller;

use ApiPlatform\Metadata\ApiResource;
use App\Entity\Carte;
use App\Repository\CarteRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[AsController]
class CreateCarteAction extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly CarteRepository $carteRepository,
        private readonly SerializerInterface $serializer,
        private readonly ValidatorInterface $validator
    ) {
    }

    public function __invoke(Request $request): JsonResponse
    {
        $user = $this->getUser();
        if (!$user) {
            throw $this->createAccessDeniedException('User not authenticated.');
        }

        /** @var Carte $carte */
        $carte = $this->serializer->deserialize($request->getContent(), Carte::class, 'json', [
            'groups' => ['mood:write'],
            'api_allow_update' => true,
        ]);

        $carte->setUtilisateur($user);

        $this->handleMoodTimestamps($carte);

        $errors = $this->validator->validate($carte);
        if (count($errors) > 0) {
            throw new \RuntimeException((string) $errors);
        }

        $this->entityManager->persist($carte);
        $this->entityManager->flush();

        return $this->json($carte, 201, [], ['groups' => ['mood:read']]);
    }

    private function handleMoodTimestamps(Carte $newCarte): void
    {
        if (!$newCarte->getBeginAt()) {
            $newCarte->setBeginAt(new \DateTimeImmutable());
        }

        $beginAt = $newCarte->getBeginAt();

        $newCarte->setEndAt($beginAt->setTime(23, 59, 59));

        $previousCarte = $this->carteRepository->findPreviousMoodOnSameDay($newCarte->getUtilisateur(), $newCarte->getDate());

        if ($previousCarte && $previousCarte->getId() !== $newCarte->getId()) {
            $previousCarte->setEndAt($beginAt->modify('-1 minute'));
            $this->entityManager->persist($previousCarte);
        }
    }
}
