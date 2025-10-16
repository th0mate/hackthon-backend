<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use App\Repository\CarteRepository;
use Doctrine\DBAL\Types\Types;
use App\State\CarteStateProcessor;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Context;
use Symfony\Component\Serializer\Annotation\Groups;


#[ORM\Entity(repositoryClass: CarteRepository::class)]
#[ApiResource(
    shortName: 'Mood',
    operations: [
        new \ApiPlatform\Metadata\GetCollection(),
        new \ApiPlatform\Metadata\Post(name: 'create_mood', uriTemplate: '/moods', processor: App\State\CarteProcessor::class),
        new \ApiPlatform\Metadata\Get(security: "object.getUtilisateur() == user"),
        new \ApiPlatform\Metadata\Put(security: "object.getUtilisateur() == user"),
        new \ApiPlatform\Metadata\Delete(security: "object.getUtilisateur() == user"),
    ],
    normalizationContext: ['groups' => ['mood:read']],
    denormalizationContext: ['groups' => ['mood:write']],
)]
class Carte
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['mood:read'])]
    private ?int $id = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    #[Groups(['mood:read', 'mood:write'])]
    #[Context([
        'datetime_format' => 'Y-m-d',
    ])]
    private ?\DateTimeImmutable $date = null;

    #[ORM\Column(length: 255)]
    #[Groups(['mood:read', 'mood:write'])]
    private ?string $mood = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['mood:read', 'mood:write'])]
    private ?string $note = null;

    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    #[Groups(['mood:read', 'mood:write'])]
    private ?int $intensity = null;

    #[ORM\ManyToOne(inversedBy: 'cartes')]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['mood:read'])]
    private ?Utilisateur $utilisateur = null;

    #[ORM\Column]
    #[Groups(['mood:read', 'mood:write'])]
    private ?\DateTimeImmutable $beginAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    #[Groups(['mood:read', 'mood:write'])]
    private ?\DateTimeImmutable $endAt = null;

    #[ORM\Column]
    #[Groups(['mood:read'])]
    private ?\DateTimeImmutable $updatedAt = null;

    public function __construct()
    {
        $this->beginAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getDate(): ?\DateTimeImmutable
    {
        return $this->date;
    }

    public function setDate(\DateTimeImmutable $date): static
    {
        $this->date = $date;

        return $this;
    }

    public function getMood(): ?string
    {
        return $this->mood;
    }

    public function setMood(string $mood): static
    {
        $this->mood = $mood;

        return $this;
    }

    public function getNote(): ?string
    {
        return $this->note;
    }

    public function setNote(?string $note): static
    {
        $this->note = $note;

        return $this;
    }

    public function getIntensity(): ?int
    {
        return $this->intensity;
    }

    public function setIntensity(?int $intensity): static
    {
        $this->intensity = $intensity;

        return $this;
    }

    public function getUtilisateur(): ?Utilisateur
    {
        return $this->utilisateur;
    }

    public function setUtilisateur(?Utilisateur $utilisateur): static
    {
        $this->utilisateur = $utilisateur;

        return $this;
    }

    public function getBeginAt(): ?\DateTimeImmutable
    {
        return $this->beginAt;
    }

    public function setBeginAt(\DateTimeImmutable $beginAt): static
    {
        $this->beginAt = $beginAt;

        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTimeImmutable $updatedAt): static
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }

    public function getEndAt(): ?\DateTimeImmutable
    {
        return $this->endAt;
    }

    public function setEndAt(?\DateTimeImmutable $endAt): static
    {
        $this->endAt = $endAt;

        return $this;
    }
}

