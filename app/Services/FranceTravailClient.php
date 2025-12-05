<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Arr;
use Carbon\Carbon;

class FranceTravailClient
{
    protected string $clientId;
    protected string $clientSecret;
    protected string $scope;
    protected string $tokenUrl;
    protected string $apiBaseUrl;
    protected string $romeMetiersEndpoint;
    protected string $offersSearchEndpoint;

    protected ?string $accessToken = null;
    protected ?Carbon $tokenExpiresAt = null;

    // Pour le garde-fou 5 secondes entre appels API
    protected ?float $lastCallTimestamp = null;
    protected float $minIntervalSeconds = 0.5; // garde-fou global

    public function __construct()
    {
        $config = config('services.france_travail');

        $this->clientId             = $config['client_id'];
        $this->clientSecret         = $config['client_secret'];
        $this->scope                = $config['scope'];
        $this->tokenUrl             = $config['token_url'];
        $this->apiBaseUrl           = rtrim($config['api_base_url'], '/');
        $this->romeMetiersEndpoint  = $config['rome_metiers_endpoint'];
        $this->offersSearchEndpoint = $config['api_base_url'] . '/offres/search';

        if (!$this->clientId || !$this->clientSecret || !$this->tokenUrl) {
            throw new \RuntimeException('Configuration France Travail incomplète.');
        }
    }

    /**
     * Garde-fou entre les appels : attend pour garantir un intervalle minimum.
     */
    protected function throttleIfNeeded(): void
    {
        if ($this->lastCallTimestamp === null) {
            $this->lastCallTimestamp = microtime(true);
            return;
        }

        $now = microtime(true);
        $elapsed = $now - $this->lastCallTimestamp;

        if ($elapsed < $this->minIntervalSeconds) {
            $sleepSeconds = $this->minIntervalSeconds - $elapsed;
            usleep((int) ($sleepSeconds * 1_000_000)); // microsecondes
        }

        $this->lastCallTimestamp = microtime(true);
    }

    /**
     * Récupère (ou renouvelle) le token OAuth2 France Travail.
     */
    public function getAccessToken(): string
    {
        // Token encore valide ?
        if ($this->accessToken && $this->tokenExpiresAt && $this->tokenExpiresAt->isFuture()) {
            return $this->accessToken;
        }

        // On applique aussi le garde-fou ici (appel HTTP)
        $this->throttleIfNeeded();

        $response = Http::asForm()->post($this->tokenUrl, [
            'grant_type'    => 'client_credentials',
            'client_id'     => $this->clientId,
            'client_secret' => $this->clientSecret,
            'scope'         => $this->scope,
        ]);

        if (!$response->successful()) {
            Log::error('FranceTravail OAuth error', [
                'status' => $response->status(),
                'body'   => $response->body(),
            ]);

            throw new \RuntimeException('Impossible de récupérer le token France Travail');
        }

        $data = $response->json();

        $this->accessToken = $data['access_token'] ?? null;

        if (!$this->accessToken) {
            throw new \RuntimeException('Token France Travail absent dans la réponse');
        }

        // expires_in (en secondes, souvent 3600) → on enlève 60s de marge
        $expiresIn = (int) ($data['expires_in'] ?? 1800);
        $this->tokenExpiresAt = now()->addSeconds($expiresIn - 60);

        return $this->accessToken;
    }

    /**
     * Appel générique d'une API France Travail avec bearer + throttle.
     */
    protected function get(string $endpoint, array $query = [])
    {
        // 1) S'assurer qu'on a un token à jour
        $token = $this->getAccessToken();

        // 2) Garde-fou 5s avant l'appel
        $this->throttleIfNeeded();

        $url = $this->apiBaseUrl . '/' . ltrim($endpoint, '/');

        $response = Http::withToken($token)
            ->acceptJson()
            ->get($url, $query);

        return $response;
    }

    /**
     * Récupère les métiers / codes ROME.
     */
    public function getRomeCodes()
    {
        // 1) Récupération du token (renouvelle si expiré)
        $token = $this->getAccessToken();

        // 2) Appliquer le garde-fou 5 secondes avant un appel API
        $this->throttleIfNeeded();

        // 3) Construire l’URL complète
        $url = $this->apiBaseUrl . '/partenaire/offresdemploi/v2/referentiel/domaines';

        // 4) Appeler l’API France Travail
        $response = Http::withHeaders([
            'Authorization' => "Bearer {$token}",
            'Accept'        => 'application/json'
        ])->get($url);

        // 5) Vérifier la réponse
        if (!$response->successful()) {
            Log::error('Erreur récupération des métiers ROME', [
                'status' => $response->status(),
                'body'   => $response->body(),
            ]);

            throw new \RuntimeException('Impossible de récupérer les codes ROME.');
        }

        $data = $response->json();

        /**
         * Structure attendue :
         * [
         *   { "code": "A1101", "libelle": "Boulangerie - viennoiserie" },
         *   { ... }
         * ]
         *
         * Donc on mappe directement code + libelle.
         */
        return collect($data)->map(function ($item) {
            return [
                'code'  => $item['code']   ?? null,
                'label' => $item['libelle'] ?? null,
            ];
        })->filter(fn ($x) => !empty($x['code']));
    }


    /**
     * Récupère les offres pour un code ROME donné.
     */
    public function getOffersByRomeCode(string $romeCode, int $limit = 150, string $region = '28')
    {
        // Nombre max d'offres à demander (150 max)
        $requested = min($limit, 150);

        // 1) Récupérer un token valide (renouvelle si besoin)
        $token = $this->getAccessToken();

        // 2) Garde-fou entre les appels
        $this->throttleIfNeeded();

        // 3) URL de recherche d'offres
        $url = $this->apiBaseUrl . '/partenaire/offresdemploi/v2/offres/search';

        // 4) Paramètres de requête
        //    → région Normandie = 28
        //    → domaine = code en BDD
        //    → range = 0-(limit-1) pour récupérer jusqu'à 150 offres
        $query = [
            'region'   => $region,
            'domaine' => $romeCode,                  // ex: D11
            'range'    => '0-' . ($requested - 1),    // à adapter si jamais France Travail impose Range en header
        ];

        $response = Http::withHeaders([
            'Authorization' => "Bearer {$token}",
            'Accept'        => 'application/json',
        ])->get($url, $query);

        if (!$response->successful()) {
            Log::error('Erreur récupération offres France Travail', [
                'status'   => $response->status(),
                'body'     => $response->body(),
                'romeCode' => $romeCode,
                'region'   => $region,
            ]);

            throw new \RuntimeException("Impossible de récupérer les offres pour le code ROME {$romeCode} en région {$region}");
        }

        $data = $response->json();
        $offers = collect(data_get($data, 'resultats', $data));


        // Généralement les offres sont dans "resultats"
        $contentRange = $response->header('Content-Range'); // ex: "offres 0-149/987"
        $total = null;

        if ($contentRange && preg_match('/offres\s+\d+-\d+\/(\d+)/', $contentRange, $matches)) {
            $total = (int) $matches[1];  // t = total d'offres
        } else {
            // fallback : si pas de header, on met au moins la taille de la page
            $total = $offers->count();
        }

        return [
            'offers' => $offers,
            'total'  => $total,
        ];

    }
}
