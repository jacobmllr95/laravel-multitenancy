<?php

namespace Spatie\Multitenancy\Commands\Concerns;

use Illuminate\Support\Arr;
use Spatie\Multitenancy\Concerns\UsesMultitenancyConfig;
use Spatie\Multitenancy\Models\Concerns\UsesTenantModel;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

trait TenantAware
{
    use UsesMultitenancyConfig;
    use UsesTenantModel;

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $tenants = Arr::wrap($this->option('tenant'));

        $tenantQuery = $this->getTenantModel()::query()
            ->when(! blank($tenants), function ($query) use ($tenants) {
                collect($this->getTenantArtisanSearchFields())
                    ->each(fn ($field) => $query->orWhereIn($field, $tenants));
            });

        if ($tenantQuery->count() === 0) {
            $this->error('No tenant(s) found.');

            return -1;
        }

        $result = 0;

        foreach ($tenantQuery->cursor() as $index => $tenant) {
            if ($index > 0) {
                $this->line('');
            }

            $this->info("Running command for tenant `{$tenant->name}` (ID: {$tenant->getKey()})...");
            $this->line("---------------------------------------------------------");

            $result += $tenant->execute(fn () => (int) $this->laravel->call([$this, 'handle']));
        }

        return $result;
    }
}
