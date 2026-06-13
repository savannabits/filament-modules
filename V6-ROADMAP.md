# Filament Modules v6 Roadmap

This document defines the architecture and release plan for **coolsam/modules v6**.

## Branch strategy

| Branch | Purpose |
|--------|---------|
| `main` | Default branch for v6 — **protected**, merge via pull request only |
| `5.x` | Maintenance line for v5 — **protected**, merge via pull request only |
| `feature/*` | All development work (v6 and v5 fixes) |

**No direct pushes** to `main` or `5.x`. Every change goes through a feature branch and a pull request:

| Target | Use for | Example branch |
|--------|---------|----------------|
| `main` | v6 features and breaking changes | `feature/v6-module-runtime` |
| `5.x` | v5 bug fixes and compatibility patches | `fix/nwidart-facade-alias` |

CI runs on pushes to `feature/**` and on pull requests into `main` or `5.x`.

v5 continues to wrap [nwidart/laravel-modules](https://github.com/nWidart/laravel-modules) with disk-based enable/disable. v6 introduces a driver-agnostic module runtime with **tenant-scoped activation** and **dependency enforcement**, while keeping nwidart as the default discovery driver.

---

## Requirements (v6)

| Dependency | Version |
|------------|---------|
| PHP | 8.3+ |
| Laravel | 12.x and 13.x |
| Filament | 4.x or 5.x |
| nwidart/laravel-modules | 12.x or 13.x |
| orchestra/testbench (dev) | ^10 (L12) / ^11 (L13) |

v5 remains on Laravel 11+ and Filament 4.x on the `5.x` branch. v6 drops Laravel 11 support.

---

## Goals

1. **Organize Filament code into modules** — resources, pages, widgets, clusters, and optional sub-panels per module.
2. **Organize modules in Filament panels** — single admin panel with plugins/clusters by default; sub-panels when isolation is required.
3. **Per-tenant activation/deactivation** — enable or disable modules at runtime per tenant (SaaS feature flags).
4. **Dependency constraints** — cannot enable a module without its dependencies active; cannot disable or uninstall while other active modules depend on it.
5. **Driver-agnostic design** — nwidart is the first driver; a future modular system can replace it without rewriting the Filament layer.

---

## Core insight: discovery vs activation

nwidart conflates two responsibilities:

| Responsibility | Meaning | Owner in v6 |
|----------------|---------|-------------|
| **Discovery** | Module exists on disk, has paths, manifest, autoload | **Driver** (`NwidartDriver`) |
| **Activation** | Module is enabled for *this request / this tenant* | **Package** (`ModuleActivator`) |

Filament registration, route bootstrapping, and `canAccess()` checks use **activation**, not nwidart's `modules_statuses.json`. Module PHP classes remain autoloaded via Composer; activation gates what registers in the panel and what runs for the current tenant.

---

## Architecture

```
┌─────────────────────────────────────────────────────────────┐
│  Contracts (stable public API)                              │
│  ModuleDefinition · ModuleRegistry · ModuleActivator        │
│  TenantContext · DependencyResolver                         │
└──────────────────────────┬──────────────────────────────────┘
                           │
         ┌─────────────────┴─────────────────┐
         ▼                                   ▼
┌─────────────────┐               ┌─────────────────────┐
│  Drivers        │               │  Activation stores  │
│  NwidartDriver  │               │  Database (tenant)  │
│  (future…)      │               │  File (fallback)    │
└─────────────────┘               └─────────────────────┘
                           │
                           ▼
┌─────────────────────────────────────────────────────────────┐
│  Filament bridge                                            │
│  ModulesPlugin · ModuleFilamentPlugin · CanAccessTrait      │
└─────────────────────────────────────────────────────────────┘
```

### Contracts

```php
interface ModuleDefinition
{
    public function name(): string;
    public function alias(): string;
    public function path(): string;
    /** @return list<string> */
    public function dependencies(): array;
    /** @return array<string, mixed> */
    public function manifest(): array;
}

interface ModuleRegistry
{
    public function all(): Collection;
    public function find(string $name): ?ModuleDefinition;
    public function exists(string $name): bool;
}

interface TenantContext
{
    public function resolve(): string|int|null; // null = single-tenant / global
}

interface ModuleActivator
{
    public function isActive(ModuleDefinition|string $module, string|int|null $tenantId = null): bool;
    public function active(string|int|null $tenantId = null): Collection;
    public function activate(ModuleDefinition|string $module, string|int|null $tenantId = null): void;
    public function deactivate(ModuleDefinition|string $module, string|int|null $tenantId = null): void;
}

interface DependencyResolver
{
    public function assertCanActivate(ModuleDefinition|string $module, string|int|null $tenantId = null): void;
    public function assertCanDeactivate(ModuleDefinition|string $module, string|int|null $tenantId = null): void;
    /** @return list<string> */
    public function activationOrder(ModuleDefinition|string $module): array;
    /** @return list<string> */
    public function dependents(ModuleDefinition|string $module, string|int|null $tenantId = null): array;
}
```

**Rule:** no code in this package calls `\Module::isEnabled()` or `\Module::allEnabled()` directly. Use `ModuleActivator` and `ModuleRegistry`.

---

## Per-tenant activation

### Data model

```sql
-- tenant_module_activations
tenant_id      uuid/bigint  not null
module         varchar      not null   -- studly name, e.g. Blog
activated_at   timestamp    not null
activated_by   nullable
version        nullable     -- manifest version at activation time
primary key (tenant_id, module)
```

Optional: `tenant_module_activation_logs` for audit history.

### Activation flow

**Activate**

1. Resolve module via `ModuleRegistry`.
2. `DependencyResolver::assertCanActivate()` — all `requires` modules must exist and be active for the tenant.
3. Run module lifecycle hook (`onActivate($tenant)`).
4. Persist row in `tenant_module_activations`.
5. Clear tenant module cache.

**Deactivate**

1. `DependencyResolver::assertCanDeactivate()` — block if any **active** module depends on this one.
2. Run module lifecycle hook (`onDeactivate($tenant)`).
3. Delete activation row.
4. Clear cache.

### Tenant context

Stay package-agnostic — do not hard-code Stancl Tenancy or Spatie Multitenancy.

```php
// config/filament-modules.php
'tenancy' => [
    'enabled' => false,
    'context' => null, // e.g. App\Support\FilamentTenantContext::class
],
```

When `tenancy.enabled` is `false`, the activator uses global mode (`FileModuleActivator` wrapping `modules_statuses.json`).

### Runtime behavior

For a tenant where module `Blog` is inactive:

- `ModuleFilamentPlugin::register()` returns early.
- `ModulesPlugin` loads only tenant-active plugins.
- `CanAccessTrait` denies access.
- Module service providers may remain registered; gate Filament, routes, and permissions via the activator (lazy provider registration is a future optimization).

---

## Dependency enforcement

### Manifest extension

Extend `module.json` with optional keys (nwidart ignores unknown keys):

```json
{
  "name": "Shop",
  "alias": "shop",
  "requires": ["Core", "Blog"],
  "filament": {
    "plugin": "ShopPlugin",
    "panels": ["admin"],
    "cluster": "Shop"
  }
}
```

### Rules

| Action | Rule |
|--------|------|
| **Enable** | All `requires` modules exist in the registry and are active for the tenant |
| **Disable** | No other **active** module lists this one in `requires` |
| **Uninstall** | Same as disable, plus dry-run report for migrations and data |

### New Artisan commands

```bash
php artisan module:activate Shop --tenant=acme
php artisan module:deactivate Shop --tenant=acme
php artisan module:activation-status --tenant=acme
php artisan module:filament:uninstall Shop --dry-run
```

nwidart's `module:enable` / `module:disable` become deprecated aliases delegating to the activator in global mode.

---

## Filament panel organization

| Use case | Recommended mode |
|----------|------------------|
| SaaS admin, tenant feature flags | `plugins` + clusters (default) |
| Separate mini-apps per domain | `panels` or `both` |
| Simple single-tenant app | `plugins`, global file activator |

### Default: one panel + plugin per module + optional cluster

```
/admin
├── Core (cluster)
├── Blog (cluster)     ← hidden when inactive for tenant
└── Shop (cluster)
```

### Optional: module sub-panels

Use when a module needs different middleware, branding, or auth. Navigation links in the main panel appear only when the module is active for the tenant.

Tenant activation applies to both plugins and panel navigation links in `ModulesPlugin`.

---

## Driver strategy

### v6.0 — Nwidart driver (default)

`NwidartModuleRegistry` implements `ModuleRegistry`:

- Discovers modules via nwidart.
- Reads `module.json` for `requires` and `filament` config.
- Does not treat `modules_statuses.json` as source of truth when the database activator is enabled.

### Future driver

A replacement modular system only implements `ModuleRegistry` (and optionally path helpers). Filament commands, stubs, tenant activation, and dependency resolution remain unchanged.

---

## Release milestones

### Milestone 1 — Internal refactor (no user-visible breakage)

- [x] Introduce contracts and `NwidartModuleRegistry`.
- [x] Replace direct `\Module::` / `isEnabled()` usage in this package.
- [x] Ship `FileModuleActivator` for backward-compatible global mode.
- [x] Bind contracts in `ModulesServiceProvider`.

### Milestone 2 — Tenant activation

- [ ] Migration and `DatabaseModuleActivator`.
- [ ] `TenantContext` contract and config.
- [ ] Cache layer: `tenant:{id}:active_modules`.
- [ ] Update `ModuleFilamentPlugin`, `ModulesPlugin`, `CanAccessTrait`, `ModulesServiceProvider`.

### Milestone 3 — Dependencies

- [ ] `ModuleDependencyResolver` with graph cache.
- [ ] Manifest validation on activate.
- [ ] New Artisan commands and typed exceptions.

### Milestone 4 — Lifecycle and uninstall safety

- [ ] Optional `ModuleLifecycle` contract on module service providers.
- [ ] `module:filament:uninstall --dry-run`.
- [ ] Filament admin page for per-tenant module management (may ship as v6.1).

### Milestone 5 — Documentation and migration

- [ ] v5 → v6 upgrade guide.
- [ ] Single-tenant: file activator, no DB required.
- [ ] Multi-tenant: publish migration, bind `TenantContext`.
- [ ] Update README and CHANGELOG.

---

## Public API (target)

```php
use Coolsam\Modules\Facades\ModuleActivator;
use Coolsam\Modules\Facades\ModuleRegistry;

// Single-tenant
ModuleActivator::activate('Blog');
ModuleActivator::isActive('Blog');

// Multi-tenant
ModuleActivator::activate('Blog', tenantId: $tenant->id);
ModuleActivator::isActive('Blog', tenantId: $tenant->id);

ModuleRegistry::find('Blog')?->dependencies();
```

---

## Out of scope for v6

- Replacing nwidart in the same release as tenant activation.
- Tenant-scoped Composer autoloading.
- Coupling to a specific tenancy package.
- Silent dependency resolution (always show activation order in CLI/UI).

---

## v6 release tagline

> **Filament Modules v6 — modular Filament for Laravel 12 and 13 with tenant-aware activation and safe dependency boundaries, driver-agnostic by design.**
