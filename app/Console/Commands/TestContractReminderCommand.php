<?php

namespace App\Console\Commands;

use App\Models\Kontrak\Contract;
use App\Models\Structure;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class TestContractReminderCommand extends Command
{
    protected $signature = 'contract:test-reminder {--minutes=5 : Minutes until expiry}';

    protected $description = 'Create a sample contract expiring soon and trigger the reminder flow for testing.';

    public function handle(): int
    {
        $minutes = max(1, (int) $this->option('minutes'));

        $end = now()->addMinutes($minutes);
        $partner = 'Test Reminder';

        $employee = Structure::query()
            ->whereNotNull('phone_number')
            ->whereNotNull('roles')
            ->first();

        $pic = $employee?->employe_id ?? 'system';

        $contract = Contract::query()->create([
            'no_contrac' => 'TEST-REMINDER-' . now()->format('YmdHis'),
            'judul' => 'Test Reminder Kontrak ' . $minutes . ' menit',
            'partner' => $partner,
            'start' => now()->toDateString(),
            'end' => $end->toDateString(),
            'pic' => $pic,
            'created_by' => $pic,
        ]);

        $this->info('Created test contract ID: ' . $contract->id);
        $this->info('Expiry: ' . $end->toDateTimeString());

        $this->call('contract:reminder');

        return self::SUCCESS;
    }
}
