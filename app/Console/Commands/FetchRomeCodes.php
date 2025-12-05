<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\FranceTravailClient;
use App\Models\RomeCode;

class FetchRomeCodes extends Command
{
    /**
     * Nom de la commande :
     * php artisan francetravail:fetch-rome-codes
     */
    protected $signature = 'francetravail:fetch-rome-codes';

    /**
     * Description affichée dans "php artisan list"
     */
    protected $description = 'Récupère les codes ROME via France Travail et les enregistre / met à jour dans la table rome_codes';

    protected FranceTravailClient $client;

    public function __construct(FranceTravailClient $client)
    {
        parent::__construct();
        $this->client = $client;
    }

    /**
     * Logique principale de la commande
     */
    public function handle(): int
    {
        $this->info('Récupération des codes ROME depuis France Travail...');

        try {
            $romeItems = $this->client->getRomeCodes();
        } catch (\Throwable $e) {
            $this->error('Erreur lors de l’appel à France Travail : ' . $e->getMessage());
            return self::FAILURE;
        }

        if ($romeItems->isEmpty()) {
            $this->warn('Aucun code ROME reçu depuis l’API.');
            return self::SUCCESS;
        }

        $bar = $this->output->createProgressBar($romeItems->count());
        $bar->start();

        $inserted = 0;
        $updated  = 0;

        foreach ($romeItems as $item) {
            /** @var \App\Models\RomeCode $record */
            $record = RomeCode::updateOrCreate(
                ['code' => $item['code']],
                ['label' => $item['label']]
            );

            if ($record->wasRecentlyCreated) {
                $inserted++;
            } else {
                $updated++;
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        $this->info("Terminé : {$inserted} codes créés, {$updated} mis à jour.");

        return self::SUCCESS;
    }
}
