<?php

use Illuminate\Support\Facades\File;

/**
 * The literal CI rule from Book A Part 1.1: "FAIL the build if any file
 * under Http/Controllers/ or Livewire/ contains: ->save() | ->create( |
 * ->update( | ->delete( | DB::table(". Livewire components and API
 * controllers are thin adapters — every database write goes through an
 * Action (BR-GLOBAL-005).
 */
it('never writes to the database directly from a controller or a Livewire component', function (): void {
    $forbidden = ['->save(', '->create(', '->update(', '->delete(', 'DB::table('];

    // Scoped to Modules/* — the modular sERP build this rule governs.
    // app/Http/Controllers and app/Livewire are pre-existing Laravel
    // starter-kit scaffolding (auth, profile, settings) that predates
    // this architecture and is out of scope for it.
    $directories = collect()
        ->merge(glob(base_path('Modules/*/Http/Controllers'), GLOB_ONLYDIR) ?: [])
        ->merge(glob(base_path('Modules/*/Livewire'), GLOB_ONLYDIR) ?: [])
        ->filter(fn (string $directory): bool => is_dir($directory));

    $violations = [];

    foreach ($directories as $directory) {
        foreach (File::allFiles($directory) as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $contents = $file->getContents();

            foreach ($forbidden as $pattern) {
                if (str_contains($contents, $pattern)) {
                    $violations[] = "{$file->getPathname()} contains forbidden pattern [{$pattern}]";
                }
            }
        }
    }

    expect($violations)->toBe([]);
});
