<?php

namespace App\Controller;

use App\Repository\CarteRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

class MoodController extends AbstractController
{
    #[Route('/api/moods', name: 'app_moods_all', methods: ['GET'])]
    public function getAllMoods(CarteRepository $carteRepository): JsonResponse
    {
        $moods = $carteRepository->findAll();
        return $this->json($moods);
    }

    #[Route('/api/moods/{date}', name: 'app_mood_by_date', methods: ['GET'])]
    public function getMoodByDate(string $date, CarteRepository $carteRepository): JsonResponse
    {
        $mood = $carteRepository->findOneBy(['date' => new \DateTimeImmutable($date)]);

        if (!$mood) {
            return $this->json(null, 404);
        }

        return $this->json($mood);
    }

    #[Route('/api/moods/stats', name: 'app_mood_stats', methods: ['GET'])]
    public function getMoodStats(CarteRepository $carteRepository): JsonResponse
    {
        $moods = $carteRepository->findAll();

        $totalEntries = count($moods);
        $moodDistribution = [];
        foreach ($moods as $mood) {
            $moodDistribution[$mood->getMood()] = ($moodDistribution[$mood->getMood()] ?? 0) + 1;
        }

        $mostFrequentMood = null;
        if (!empty($moodDistribution)) {
            $mostFrequentMood = array_keys($moodDistribution, max($distribution))[0];
        }

        $stats = [
            'totalEntries' => $totalEntries,
            'moodDistribution' => $moodDistribution,
            'mostFrequentMood' => $mostFrequentMood,
            'averageMood' => null,
            'weekTrend' => 'stable',
        ];

        return $this->json($stats);
    }
}
