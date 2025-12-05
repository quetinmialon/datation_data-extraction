<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\FranceTravailClient;
use App\Models\RomeCode;
use App\Models\RomeStatsRun;
use App\Models\RomeStat;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Carbon\Carbon;

class FetchRomeStats extends Command
{
    protected $signature = 'francetravail:fetch-stats
                            {--limit=150 : Nombre max d\'offres par code ROME}';

    protected $description = 'Récupère les offres par code ROME, calcule les indicateurs et les enregistre dans rome_stats';

    protected FranceTravailClient $client;

    public function __construct(FranceTravailClient $client)
    {
        parent::__construct();
        $this->client = $client;
    }

    public function handle(): int
    {
        $limit = (int) $this->option('limit');

        $this->info('Démarrage d\'un run d\'analyse des offres par code ROME');

        $run = RomeStatsRun::create([
            'started_at' => now(),
            'comment'    => 'Run automatique ' . now()->toDateTimeString(),
        ]);

        $romes = RomeCode::all();

        if ($romes->isEmpty()) {
            $this->error('Aucun code ROME en base. Remplis d\'abord la table rome_codes.');
            return self::FAILURE;
        }

        $bar = $this->output->createProgressBar($romes->count());
        $bar->start();

        foreach ($romes as $rome) {
            try {
                $result = $this->client->getOffersByRomeCode($rome->code, $limit);
            } catch (\Throwable $e) {
                $this->error("\nErreur pour le code {$rome->code} : " . $e->getMessage());
                $bar->advance();
                continue;
            }

            $offers      = $result['offers'];  // Collection (échantillon, max 150)
            $totalOffers = $result['total'];   // Nombre total d'offres (depuis Content-Range)

            $stats = $this->computeStatsFromOffers($offers, $totalOffers);

            RomeStat::create([
                'rome_code_id'       => $rome->id,
                'run_id'             => $run->id,
                'execution_datetime' => now(),

                'avg_salary'         => $stats['avg_salary'],
                'urgent_rate'        => $stats['urgent_rate'],
                'avg_days_open'      => $stats['avg_days_open'],
                'offer_count'        => $stats['offer_count'], // <-- ici on met le TOTAL, pas juste 150
            ]);

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        $run->update([
            'finished_at' => now(),
        ]);

        $this->info('Run terminé !');

        return self::SUCCESS;
    }

protected function computeStatsFromOffers(Collection $offers, ?int $totalOffers = null): array
{
    $sampleCount = $offers->count();
    $offerCount  = $totalOffers ?? $sampleCount;

    if ($sampleCount === 0) {
        return [
            'avg_salary'    => null,
            'urgent_rate'   => 0.0,
            'avg_days_open' => null,
            'offer_count'   => $offerCount,
        ];
    }

    // 1) Salaire moyen mensuel
    $salaries = $offers->map(function ($offer) {
        return $this->extractMonthlySalaryFromOffer($offer);
    })->filter();

    $avgSalary = $salaries->isNotEmpty()
        ? round($salaries->avg(), 2)
        : null;

    // 2) Taux de "urgent"
    $urgentCount = $offers->filter(function ($offer) {
        $title = Str::lower((string) data_get($offer, 'intitule', ''));
        $desc  = Str::lower((string) data_get($offer, 'description', data_get($offer, 'descriptionOffre', '')));

        return Str::contains($title . ' ' . $desc, 'urgent');
    })->count();

    $urgentRate = round(($urgentCount / $sampleCount) * 100, 2);

    // 3) Durée moyenne (jours)
    $daysOpen = $offers->map(function ($offer) {
        $dateString = data_get($offer, 'dateCreation', data_get($offer, 'dateActualisation'));

        if (!$dateString) {
            return null;
        }

        try {
            $createdAt = Carbon::parse($dateString);
            return now()->diffInDays($createdAt);
        } catch (\Throwable $e) {
            return null;
        }
    })->filter();

    $avgDaysOpen = $daysOpen->isNotEmpty()
        ? round($daysOpen->avg(), 2)
        : null;

    return [
        'avg_salary'    => $avgSalary,     // mensuel
        'urgent_rate'   => $urgentRate,
        'avg_days_open' => $avgDaysOpen,
        'offer_count'   => $offerCount,    // total global (Content-Range)
    ];
}


    protected function extractMonthlySalaryFromOffer($offer): ?float
    {
        $libelle = data_get($offer, 'salaire.libelle');

        if (!$libelle || !is_string($libelle)) {
            return null;
        }

        $libelleLower = Str::lower($libelle);

        $isMensuel = Str::contains($libelleLower, 'mensuel');
        $isAnnuel  = Str::contains($libelleLower, 'annuel');
        $isHoraire = Str::contains($libelleLower, 'horaire');

        // On récupère le premier nombre dans la chaîne
        // ex: "Mensuel de 1923.00 Euros sur 12 mois"
        // ex: "Annuel de 35000 Euros"
        // ex: "Horaire de 12,50 Euros"
        if (!preg_match('/([\d]+[.,]\d+|[\d]+)/', $libelle, $matches)) {
            return null;
        }

        $raw = str_replace(',', '.', $matches[1]);
        $amount = (float) $raw;

        if ($amount <= 0) {
            return null;
        }

        // Conversion en mensuel
        if ($isAnnuel) {
            // Annuel -> mensuel
            $monthly = $amount / 12.0;
        } elseif ($isHoraire) {
            // Horaire -> mensuel (150h/mois)
            $monthly = $amount * 150.0;
        } else {
            // Par défaut, on considère que c'est mensuel
            // (Mensuel explicite ou libellé sans mot-clé clair)
            $monthly = $amount;
        }

        return round($monthly, 2);
    }

}
