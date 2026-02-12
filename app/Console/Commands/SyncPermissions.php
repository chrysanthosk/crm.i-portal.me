<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use App\Models\Permission;

class SyncPermissions extends Command
{
    protected $signature = 'permissions:sync {--prune : Delete permissions not found in sync map}';
    protected $description = 'Sync permissions table from route name prefixes';

    public function handle(): int
    {
        $map = config('permission_map', []);

        if (empty($map)) {
            $this->error('config/permission_map.php is empty.');
            return self::FAILURE;
        }

        $routeNames = collect(Route::getRoutes())
            ->map(fn($r) => $r->getName())
            ->filter()
            ->unique()
            ->values();

        $foundKeys = [];
        $created = 0;
        $updated = 0;

        foreach ($map as $m) {
            $prefix = $m['prefix'] ?? '';
            $key    = $m['key'] ?? null;

            if (!$prefix || !$key) {
                continue;
            }

            $hasAnyRoute = $routeNames->first(fn($n) => Str::startsWith($n, $prefix)) !== null;
            if (!$hasAnyRoute) {
                continue;
            }

            $permName  = $m['name']  ?? $key;
            $permGroup = $m['group'] ?? 'General';

            $permission = Permission::query()->where('permission_key', $key)->first();

            if (!$permission) {
                Permission::create([
                    'permission_key'   => $key,
                    'permission_name'  => $permName,
                    'permission_group' => $permGroup,
                ]);
                $created++;
            } else {
                $permission->update([
                    'permission_name'  => $permName,
                    'permission_group' => $permGroup,
                ]);
                $updated++;
            }

            $foundKeys[] = $key;
        }

        if ($this->option('prune')) {
            Permission::query()
                ->whereNotIn('permission_key', array_unique($foundKeys))
                ->delete();
            $this->info('Pruned permissions not found in sync map.');
        }

        $this->info("Permissions synced. Created: {$created}, Updated: {$updated}");
        return self::SUCCESS;
    }
}
