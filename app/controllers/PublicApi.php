<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');
require_once APP_DIR . 'controllers/ApiController.php';

class PublicApi extends ApiController
{
    public function home()
    {
        if ($this->method_not_allowed('GET, OPTIONS')) {
            return;
        }
        $featured = $this->ResortService->featured();

        $this->respond([
            'hero' => [
                'title' => 'Welcome to ESPED Resort',
                'subtitle' => 'Classic comfort in a peaceful destination.',
            ],
            'about_preview' => '',
            'featured_services' => $featured,
        ]);
    }

    public function resort_info()
    {
        if ($this->method_not_allowed('GET, OPTIONS')) {
            return;
        }
        $this->respond(['resort_info' => null]);
    }

    public function services()
    {
        if ($this->method_not_allowed('GET, OPTIONS')) {
            return;
        }
        $search = trim((string) ($this->request->get('search') ?? ''));
        $serviceType = trim((string) ($this->request->get('service_type') ?? ''));
        $sort = strtolower(trim((string) ($this->request->get('sort') ?? 'asc')));
        if (!in_array($sort, ['asc', 'desc'], true)) {
            $sort = 'asc';
        }

        $services = $this->ResortService->search_active($search, $serviceType, $sort);
        $this->respond(['services' => $services]);
    }

    public function service_availability($serviceId)
    {
        if ($this->method_not_allowed('GET, OPTIONS')) {
            return;
        }

        if (!$this->ResortService->find_active((int) $serviceId)) {
            $this->error('Service not found.', 404);
            return;
        }

        $today = date('Y-m-d');
        $availableDates = [];
        foreach ($this->ServiceAvailability->list_for_service((int) $serviceId) as $row) {
            if (
                $row['available_date'] >= $today
                && (int) $row['booked_quantity'] < (int) $row['total_quantity']
            ) {
                $availableDates[] = $row['available_date'];
            }
        }

        $this->respond(['available_dates' => $availableDates]);
    }

    public function recommendation()
    {
        if ($this->method_not_allowed('POST, OPTIONS')) {
            return;
        }
        $data = $this->get_request_data();

        $maximumBudget = (float) ($data['maximum_budget'] ?? 0);
        $guests = (int) ($data['number_of_guests'] ?? 0);
        $preferences = trim((string) ($data['preferences'] ?? ''));

        if ($maximumBudget <= 0 || $guests <= 0) {
            $this->error('Maximum budget and number of guests are required.', 422);
            return;
        }

        $services = $this->ResortService->recommend_within_budget_and_capacity($maximumBudget, $guests);

        $source = 'fallback';
        $ranked = [];
        $aiApiKey = getenv('AI_API_KEY') ?: '';
        $aiApiUrl = getenv('AI_API_URL') ?: '';
        if ($aiApiKey !== '' && $aiApiUrl !== '' && $services !== []) {
            $payload = [
                'budget' => $maximumBudget,
                'guests' => $guests,
                'preferences' => $preferences,
                'services' => $services,
            ];
            $ch = curl_init($aiApiUrl);
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/json',
                    'Authorization: Bearer ' . $aiApiKey,
                ],
                CURLOPT_POSTFIELDS => json_encode($payload),
                CURLOPT_TIMEOUT => 15,
            ]);
            $response = curl_exec($ch);
            $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($response !== false && $httpCode >= 200 && $httpCode < 300) {
                $decoded = json_decode($response, true);
                if (is_array($decoded) && isset($decoded['recommendations']) && is_array($decoded['recommendations'])) {
                    $allowedById = [];
                    foreach ($services as $service) {
                        $allowedById[(int) $service['id']] = $service;
                    }
                    $rank = 1;
                    foreach ($decoded['recommendations'] as $item) {
                        $serviceId = (int) ($item['service_id'] ?? 0);
                        if (!isset($allowedById[$serviceId])) {
                            continue;
                        }
                        $service = $allowedById[$serviceId];
                        if ((float) $service['price'] > $maximumBudget || (int) $service['capacity'] < $guests) {
                            continue;
                        }
                        $ranked[] = [
                            'service' => $service,
                            'rank_order' => $rank,
                            'match_reason' => (string) ($item['match_reason'] ?? 'Matches your budget and guest count.'),
                        ];
                        $rank++;
                        if ($rank > 3) {
                            break;
                        }
                    }
                    if ($ranked !== []) {
                        $source = 'ai';
                    }
                }
            }
        }

        if ($ranked === []) {
            $preferenceTokens = preg_split('/\s+/', strtolower($preferences));
            $scored = [];
            foreach ($services as $service) {
                $text = strtolower($service['name'] . ' ' . $service['description'] . ' ' . $service['service_type']);
                $score = 0;
                foreach ($preferenceTokens as $token) {
                    if ($token !== '' && str_contains($text, $token)) {
                        $score++;
                    }
                }
                $scored[] = [
                    'service' => $service,
                    'score' => $score,
                ];
            }
            usort($scored, function ($a, $b) {
                if ($a['score'] === $b['score']) {
                    return (float) $a['service']['price'] <=> (float) $b['service']['price'];
                }
                return $b['score'] <=> $a['score'];
            });

            $rank = 1;
            foreach ($scored as $entry) {
                if ($rank > 3) {
                    break;
                }
                $ranked[] = [
                    'service' => $entry['service'],
                    'rank_order' => $rank,
                    'match_reason' => $entry['score'] > 0
                        ? 'Matched your preferences and budget.'
                        : 'Fits your budget and guest count.',
                ];
                $rank++;
            }
        }

        $user = $this->current_user();
        $this->db->table('recommendation_requests')->insert([
            'user_id' => $user ? (int) $user['id'] : null,
            'budget_min' => 0,
            'budget_max' => $maximumBudget,
            'guest_count' => $guests,
            'preferences' => $preferences,
        ]);
        $requestId = (int) $this->db->last_id();

        foreach ($ranked as $item) {
            $this->db->table('recommendation_items')->insert([
                'recommendation_id' => $requestId,
                'service_id' => (int) $item['service']['id'],
                'rank_order' => (int) $item['rank_order'],
                'match_reason' => $item['match_reason'],
            ]);
        }

        $this->respond([
            'success' => true,
            'request_id' => $requestId,
            'source' => $source,
            'items' => $ranked,
        ]);
    }
}
