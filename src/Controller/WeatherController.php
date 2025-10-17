<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\HttpClient\HttpClientInterface;

use Symfony\Component\HttpFoundation\Request;

class WeatherController extends AbstractController
{
    private $httpClient;

    public function __construct(HttpClientInterface $httpClient)
    {
        $this->httpClient = $httpClient;
    }

    #[Route('/api/weather', name: 'app_weather', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function getWeather(Request $request): JsonResponse
    {
        $latitude = $request->query->get('latitude');
        $longitude = $request->query->get('longitude');

        if (!$latitude || !$longitude) {
            return $this->json(['error' => 'Latitude and longitude are required.'], 400);
        }

        // Reverse geocoding to get city name
        $city = 'Unknown';
        try {
            $reverseGeocodingResponse = $this->httpClient->request('GET', "https://nominatim.openstreetmap.org/reverse?format=json&lat={$latitude}&lon={$longitude}");
            if ($reverseGeocodingResponse->getStatusCode() === 200) {
                $locationData = $reverseGeocodingResponse->toArray();
                if (isset($locationData['address']['city'])) {
                    $city = $locationData['address']['city'];
                } elseif (isset($locationData['address']['town'])) {
                    $city = $locationData['address']['town'];
                } elseif (isset($locationData['address']['village'])) {
                    $city = $locationData['address']['village'];
                }
            }
        } catch (\Exception $e) {
            // Log the error but continue, as city name is not critical
        }

        $response = $this->httpClient->request('GET', "https://api.open-meteo.com/v1/forecast?latitude={$latitude}&longitude={$longitude}&current_weather=true");

        if ($response->getStatusCode() !== 200) {
            return $this->json(['error' => 'Could not retrieve weather data.'], 500);
        }

        $weatherData = $response->toArray();
        $weatherCode = $weatherData['current_weather']['weathercode'];

        [$mood, $sentence] = $this->getMoodFromWeatherCode($weatherCode);

        return $this->json([
            'city' => $city,
            'weather' => [
                'temperature' => $weatherData['current_weather']['temperature'],
                'weathercode' => $weatherCode,
            ],
            'suggestion' => [
                'mood' => $mood,
                'sentence' => $sentence,
            ],
        ]);
    }

    private function getMoodFromWeatherCode(int $weatherCode): array
    {
        switch ($weatherCode) {
            case 0:
                return ["Ensoleillé", "C'est une belle journée pour être heureux !"];
            case 1:
            case 2:
            case 3:
                return ["Nuageux", "Même avec quelques nuages, il y a de la place pour un sourire."];
            case 45:
            case 48:
                return ["Brumeux", "Un temps mystérieux, parfait pour l'introspection."];
            case 51:
            case 53:
            case 55:
            case 61:
            case 63:
            case 65:
            case 80:
            case 81:
            case 82:
                return ["Pluvieux", "La pluie nettoie l'air. Pourquoi ne pas en profiter pour se détendre ?"];
            case 71:
            case 73:
            case 75:
            case 77:
            case 85:
            case 86:
                return ["Neigeux", "La neige apporte le calme. Une bonne journée pour se sentir apaisé."];
            case 95:
            case 96:
            case 99:
                return ["Orageux", "L'énergie de l'orage est puissante. Canalisez-la pour une journée productive."];
            default:
                return ["Indéterminé", "La météo est incertaine, mais votre humeur vous appartient."];
        }
    }
}
