# Nursery SaaS — backend

Multi-nursery (SaaS) daycare platform: child tracking, QR attendance, daily reports, bilingual (ar/en).
Full spec and phase plan: https://claude.ai/artifact/TYC95VPtvL7tXnFAg58PQQ

## Stack
Laravel 12 · PHP 8.3 · PostgreSQL · Redis/Horizon · Filament v5 · Sanctum · spatie/laravel-permission (teams) ·
spatie/laravel-translatable · Pest. Mobile client: Flutter (separate repo).

## Tenancy rules (do not break)
- Single database. Every tenant-owned table has `nursery_id` (indexed, FK to nurseries).
- Every tenant-owned model uses `App\Models\Concerns\BelongsToNursery` (global scope + auto-fill).
- The current nursery lives in `App\Support\CurrentNursery` (scoped singleton). It is set by:
  - `ApplyFilamentTenant` (nursery panel `/app/{slug}`, tenantMiddleware, persistent)
  - `SetNurseryFromHeader` (API, alias `nursery`, header `X-Nursery-Id`)
  - Queued jobs acting for one nursery must call `app(CurrentNursery::class)->set($nursery)` first.
- Setting the nursery also sets spatie's permission team id. Roles are per nursery.
- New Filament resources in the App panel need a matching `HasMany` relation on `Nursery` (Filament tenancy).
- Every new endpoint or resource gets a test in `tests/Feature` proving nursery B can't see nursery A's data.

## Roles (`App\Support\Roles`)
super admin = `users.is_super_admin` (platform panel `/platform`) · nursery_admin · receptionist (web panel) ·
teacher · guardian (mobile app only). Visibility beyond the role (teacher → own classrooms, guardian → own
children) is enforced in Policies.

## Conventions
- User-facing text: `__('app.key')` / `__('api.key')`, keys in both `lang/ar` and `lang/en`.
- Translatable columns are `jsonb` + `HasTranslations`; Filament forms use `name.ar` / `name.en` fields and
  fill them from `getTranslations()`.
- Phones are stored normalized (`+20…`) via the User `phone` mutator / `OtpService::normalizePhone`.
- API: `/api/v1`, Sanctum tokens, JSON resources, `Accept-Language` picks the locale.
- Store times in UTC; display in the nursery timezone.
- Run `./vendor/bin/pest` before finishing any task.

## Status
Phase 1 (foundation + tenancy) done. Next: Phase 2 — classrooms, children, guardians, authorized pickups,
child_medical (encrypted), QR token + printable card.
