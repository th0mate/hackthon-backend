<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Carte;
use App\Repository\CarteRepository;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\AsDecorator;

#[AsDecorator('api_platform.doctrine.orm.state.persist_processor')]
class CarteStateProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly ProcessorInterface $persistProcessor,
        private readonly Security $security,
        private readonly CarteRepository $carteRepository
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = [])
    {
        if ($data instanceof Carte) {
            $user = $this->security->getUser();
            $data->setUtilisateur($user);

            if ($operation->getName() === 'create_mood') {
                $now = new \DateTimeImmutable();
                $data->setBeginAt($now);
                $data->setEndAt($now->setTime(23, 59, 59));

                $latestMood = $this->carteRepository->findOneBy([
                    'utilisateur' => $user,
                    'date' => $data->getDate()
                ], ['beginAt' => 'DESC']);

                if ($latestMood) {
                    $latestMood->setEndAt($now->modify('-1 minute'));
                }
            }
        }

        return $this->persistProcessor->process($data, $operation, $uriVariables, $context);
    }
}
