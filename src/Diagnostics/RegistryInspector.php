<?php

declare(strict_types=1);

namespace Gcego\Registry\Diagnostics;

use Gcego\Registry\Exceptions\RegistryException;
use Gcego\Registry\Kinds\DefinitionKinds;
use Gcego\Registry\RegistryConfig;
use Gcego\Registry\RegistryManager;
use Throwable;

/**
 * Explains the registry: roots, discovered entrypoints, loader failures,
 * duplicates, unsupported kinds and cache state.
 *
 * It performs a full, non-strict build without writing the cache, so it is
 * meant for tooling (gcego/cli, health pages), not for ordinary requests.
 * It never throws.
 */
final readonly class RegistryInspector
{
    public function __construct(
        private RegistryManager $manager,
        private RegistryConfig $config,
        private DefinitionKinds $kinds,
    ) {}

    public function inspect(): RegistryReport
    {
        $diagnostics = [];
        $roots = [];

        foreach ($this->config->roots as $root) {
            $exists = is_dir($root->path);
            $registered = $this->kinds->has($root->kind);
            $roots[] = [...$root->toArray(), 'exists' => $exists, 'kindRegistered' => $registered];

            if (! $exists) {
                $diagnostics[] = new RegistryDiagnostic('discovery.root', DiagnosticLevel::Ok, sprintf('Optional root %s does not exist.', $root->path), ['path' => $root->path]);
            } elseif (! $registered) {
                $diagnostics[] = new RegistryDiagnostic('discovery.kind', DiagnosticLevel::Warning, sprintf(
                    'Root %s holds "%s" definitions, but no such kind is registered; its files are ignored.',
                    $root->path,
                    $root->kind,
                ), ['path' => $root->path, 'kind' => $root->kind]);
            }
        }

        if (! $this->config->discoveryEnabled) {
            $diagnostics[] = new RegistryDiagnostic('discovery.enabled', DiagnosticLevel::Warning, 'Discovery is disabled; only configured providers and runtime registrations are used.');
        }

        $countsByKind = [];

        try {
            $discovery = $this->manager->discovery()->discover();
            $diagnostics[] = new RegistryDiagnostic('discovery.files', DiagnosticLevel::Ok, sprintf('%d entrypoint(s) discovered, %d path(s) skipped.', count($discovery->files), count($discovery->skipped)), [
                'entrypoints' => count($discovery->files),
                'skipped' => count($discovery->skipped),
            ]);

            foreach ($discovery->skipped as $skipped) {
                if (str_starts_with($skipped['reason'], 'symbolic') || str_contains($skipped['reason'], 'outside') || str_contains($skipped['reason'], 'not readable')) {
                    $diagnostics[] = new RegistryDiagnostic('discovery.skipped', DiagnosticLevel::Warning, sprintf('Skipped %s: %s.', $skipped['path'], $skipped['reason']), $skipped);
                }
            }

            $result = $this->manager->build($discovery, strict: false);

            foreach ($result->failures as $failure) {
                $diagnostics[] = new RegistryDiagnostic('registration.failure', DiagnosticLevel::Error, $failure->exception->getMessage(), [
                    'source' => $failure->source,
                    'exception' => $failure->exception::class,
                ]);
            }

            foreach ($result->unsupported as $unsupported) {
                $diagnostics[] = new RegistryDiagnostic('registration.unsupported', DiagnosticLevel::Warning, sprintf('%s was not registered: kind "%s" is not registered.', $unsupported['path'], $unsupported['kind']), $unsupported);
            }

            foreach ($result->index->kinds() as $kind) {
                $countsByKind[$kind] = count($result->index->ofKind($kind));
            }

            $diagnostics[] = new RegistryDiagnostic('registration.count', DiagnosticLevel::Ok, sprintf('%d definition(s) registered.', $result->index->count()), $countsByKind);
        } catch (RegistryException $exception) {
            $diagnostics[] = new RegistryDiagnostic('registration.build', DiagnosticLevel::Error, $exception->getMessage(), ['exception' => $exception::class]);
        } catch (Throwable $exception) {
            $diagnostics[] = new RegistryDiagnostic('registration.build', DiagnosticLevel::Error, sprintf('Building the registry failed (%s).', $exception::class), ['exception' => $exception::class]);
        }

        $cache = $this->manager->status();
        $diagnostics[] = match (true) {
            ! $cache->enabled => new RegistryDiagnostic('cache', DiagnosticLevel::Ok, 'The registry cache is disabled; definitions are discovered on first use in every process.'),
            ! $cache->exists => new RegistryDiagnostic('cache', DiagnosticLevel::Warning, sprintf('The registry cache %s has not been built.', $cache->path)),
            ! $cache->valid => new RegistryDiagnostic('cache', DiagnosticLevel::Error, (string) $cache->problem),
            $cache->fresh === false => new RegistryDiagnostic('cache', DiagnosticLevel::Warning, sprintf('The registry cache %s is stale; rebuild it.', $cache->path)),
            default => new RegistryDiagnostic('cache', DiagnosticLevel::Ok, sprintf('The registry cache is valid (%d definitions, generated %s).', $cache->definitions ?? 0, $cache->generatedAt ?? 'unknown')),
        };

        return new RegistryReport($diagnostics, $countsByKind, $roots, $cache->toArray());
    }
}
