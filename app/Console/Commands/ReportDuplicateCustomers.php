<?php

namespace App\Console\Commands;

use App\Models\Customer;
use Illuminate\Console\Command;

class ReportDuplicateCustomers extends Command
{
    protected $signature = 'customers:duplicates';

    protected $description = 'List customers that share the same phone number and name (nothing is changed or merged)';

    public function handle(): int
    {
        $groups = Customer::query()->withCount('transactions')->whereNotNull('phone_key')->get()
            ->groupBy(fn (Customer $c) => $c->phone_key.'|'.mb_strtolower(trim($c->name)))
            ->filter(fn ($group) => $group->count() > 1);

        if ($groups->isEmpty()) {
            $this->info('No duplicate customers found.');

            return self::SUCCESS;
        }

        foreach ($groups as $group) {
            $this->line("{$group->first()->name} · {$group->first()->contact_number}");
            $this->table(['ID', 'Phone', 'Orders', 'Created'], $group->map(fn ($c) => [$c->id, $c->contact_number, $c->transactions_count, $c->created_at?->toDateString()])->all());
        }
        $this->warn($groups->count().' duplicate group(s). Review them and merge by hand; nothing was changed.');

        return self::SUCCESS;
    }
}
