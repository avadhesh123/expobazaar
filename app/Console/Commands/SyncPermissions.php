<?php

// app/Console/Commands/SyncPermissions.php

namespace App\Console\Commands;

use App\Models\Permission;
use Illuminate\Console\Command;

class SyncPermissions extends Command
{
    protected $signature = 'permissions:sync {--dry-run}';
    protected $description = 'Sync permissions from config/modules.php to database';

    public function handle(): int
    {
        $modules = config('modules');
        $dryRun = $this->option('dry-run');
        $created = 0;
        $existing = 0;
        $allPermNames = [];

        foreach ($modules as $module => $config) {
            foreach ($config['entities'] as $entity => $entityConfig) {
                foreach ($entityConfig['actions'] as $action) {
                    $name = "{$module}.{$entity}.{$action}";
                    $displayName = ucfirst($action) . ' ' . $entityConfig['label'];
                    $allPermNames[] = $name;

                    $exists = Permission::where('name', $name)->first();
                    if (!$exists) {
                        if (!$dryRun) {
                            Permission::create([
                                'name'         => $name,
                                'module'       => $module,
                                'entity'       => $entity,
                                'action'       => $action,
                                'display_name' => $displayName,
                                'guard_name'   => 'web',
                                'is_system'    => true,
                            ]);
                        }
                        $created++;
                        $this->line("  <info>+</info> {$name} ({$displayName})");
                    } else {
                        $existing++;
                    }
                }
            }
        }

        // Flag orphaned permissions (in DB but not in config)
        $orphaned = Permission::whereNotIn('name', $allPermNames)
            ->where('is_system', true)
            ->pluck('name');

        if ($orphaned->isNotEmpty()) {
            $this->warn("Orphaned permissions (in DB but not in config):");
            $orphaned->each(fn ($p) => $this->line("  <comment>?</comment> {$p}"));
        }

        $prefix = $dryRun ? '[DRY RUN] ' : '';
        $this->info("{$prefix}Created: {$created}, Existing: {$existing}, Orphaned: {$orphaned->count()}");

        return 0;
    }
}
