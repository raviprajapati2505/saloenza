<?php

namespace App\Console\Commands;

use App\Models\Saloon;
use App\Services\Customer\OccasionWishService;
use Illuminate\Console\Command;

class SendCustomerOccasionWishes extends Command
{
    protected $signature = 'customers:send-occasion-wishes
                            {--dry-run : List matching customers without sending}';

    protected $description = 'Send birthday and anniversary wish emails with each salon’s standard offer';

    public function __construct(
        private readonly OccasionWishService $wishes,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $sent = 0;
        $skipped = 0;

        $salons = Saloon::query()
            ->where('is_active', true)
            ->orderBy('id')
            ->get();

        foreach ($salons as $salon) {
            $result = $this->wishes->sendForSalon($salon, $dryRun);
            $sent += $result['sent'];
            $skipped += $result['skipped'];

            if ($result['sent'] === 0 && $result['skipped'] === 0) {
                continue;
            }

            $prefix = $dryRun ? '[dry-run] ' : '';
            $this->info(sprintf(
                '%s%s — sent %d, skipped %d',
                $prefix,
                $salon->name,
                $result['sent'],
                $result['skipped'],
            ));
        }

        $this->info(sprintf('Occasion wishes complete. Sent %d, skipped %d.', $sent, $skipped));

        return self::SUCCESS;
    }
}
