<?php

namespace App\Console\Commands;

use App\Models\Kontrak\Contract;
use App\Models\Kontrak\ContractHistory;
use App\Models\Structure;
use App\Services\WhatsAppService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ContractReminderCommand extends Command
{
    protected $signature = 'contract:reminder';

    protected $description = 'Send WhatsApp reminders for contracts expiring in 1 to 15 days once per day at 09:00.';

    public function handle(): int
    {
        $now = Carbon::now();

        if ((int) $now->format('H') !== 9) {
            return self::SUCCESS;
        }

        $start = now()->startOfDay();
        $end = now()->addDays(15)->endOfDay();

        $contracts = Contract::query()
            ->whereNull('deleted_at')
            ->whereNotNull('end')
            ->where('end', '>=', $start)
            ->where('end', '<=', $end)
            ->orderBy('end', 'asc')
            ->get();

        foreach ($contracts as $contract) {
            if ($this->alreadySentToday($contract->id)) {
                continue;
            }

            $numbers = $this->getRecipientNumbers($contract);

            if (empty($numbers)) {
                continue;
            }

            $message = $this->buildMessage($contract);

            foreach ($numbers as $number) {
                try {
                    app(WhatsAppService::class)->sendMessage((string) $number, $message);
                } catch (\Throwable $e) {
                    Log::warning('Failed to send contract expiring WA reminder', [
                        'contract_id' => $contract->id,
                        'number' => $number,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            ContractHistory::create([
                'contract_id' => $contract->id,
                'action' => 'reminder',
                'action_by' => 'system',
                'action_time' => now(),
            ]);
        }

        return self::SUCCESS;
    }

    protected function alreadySentToday(int $contractId): bool
    {
        return ContractHistory::where('contract_id', $contractId)
            ->where('action', 'reminder')
            ->whereDate('action_time', today())
            ->exists();
    }

    protected function getRecipientNumbers(Contract $contract): array
    {
        $numbers = [];

        $roleRecipients = Structure::query()
            ->select('phone_number', 'roles')
            ->whereNotNull('phone_number')
            ->get();

        foreach ($roleRecipients as $row) {
            $roles = $this->parseRolesValue($row->roles);

            if (in_array('PICKontrak', $roles, true)) {
                foreach ($this->extractPhoneNumbers((string) $row->phone_number) as $phone) {
                    $numbers[] = $phone;
                }
            }
        }

        if (!empty($contract->pic)) {
            $picStructure = Structure::query()
                ->select('phone_number')
                ->where('employe_id', $contract->pic)
                ->first();

            if ($picStructure && !empty($picStructure->phone_number)) {
                foreach ($this->extractPhoneNumbers((string) $picStructure->phone_number) as $phone) {
                    $numbers[] = $phone;
                }
            }
        }

        return array_values(array_unique(array_filter($numbers, fn ($number) => !empty($number))));
    }

    protected function buildMessage(Contract $contract): string
    {
        $daysLeft = (int) now()->startOfDay()->diffInDays(Carbon::parse($contract->end)->startOfDay(), false);

        return "Peringatan kontrak akan berakhir\n"
            . "Nomor Kontrak: {$contract->no_contrac}\n"
            . "Judul: {$contract->judul}\n"
            . "PIC: {$contract->pic}\n"
            . "Tanggal Berakhir: " . ($contract->end ? Carbon::parse($contract->end)->format('d-m-Y') : '-') . "\n"
            . "Sisa waktu: {$daysLeft} hari\n"
            . "Harap segera dilakukan pengecekan atau perpanjangan kontrak.";
    }

    protected function parseRolesValue($roles): array
    {
        if (is_array($roles)) {
            return $roles;
        }

        if (is_string($roles) && $roles !== '') {
            $decoded = json_decode($roles, true);

            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return $decoded;
            }

            return array_values(array_filter(array_map('trim', explode(',', $roles))));
        }

        return [];
    }

    protected function extractPhoneNumbers(string $rawPhone): array
    {
        if ($rawPhone === '') {
            return [];
        }

        $parts = preg_split('/[;,|\s]+/', $rawPhone);

        return array_values(array_filter(array_map('trim', $parts), fn ($value) => $value !== ''));
    }
}
