---
paths:
  - 'app/Models/**'
  - app/Models/DashboardWidget.php
  - app/Models/User.php
---

# Models

## StampsOwnership: owner columns on create
DashboardWidget, Dashboard and Project use App\Models\Concerns\StampsOwnership. On `creating` it stamps by the auth user's role: superadmin -> neither company_id nor user_id; company admin (isAdmin) -> company_id only; normal user -> company_id + user_id. Explicit non-null values passed to create() are preserved (null is treated as "unset"). Requires nullable `company_id` and `user_id` columns (projects got both via 2026_09_08 migrations; dashboards.user_id was already nullable). The trait's `bootStampsOwnership` runs alongside each model's own `booted()` (which still holds the `owned` global scope).

## DashboardWidget owned global scope
The `owned` global scope filters reads by the auth user's role: no user -> `0 = 1` (nothing); superadmin (isSuperAdmin) -> no filter; company admin (isAdmin) -> `(company_id IS NULL OR company_id = user.company_id)`; normal user -> that AND `(user_id IS NULL OR user_id = user.id)`. NULL company_id/user_id rows (superadmin-created, see StampsOwnership) stay visible to everyone. Creation-side stamping lives in the StampsOwnership trait; this scope is the read side.

## User remembers last project & dashboard
users.project_id and users.dashboard_id (both nullable FK, nullOnDelete) store the user's last selection, re-proposed at login. User::rememberSelection(int|false|null $projectId, int|false|null $dashboardId) writes them via saveQuietly (null = leave, false = clear). DashboardChartsOverview and DashboardTablesOverview default their #[Url] $dashboardId from auth()->user()->dashboard_id on a clean visit and call rememberSelection on explicit dashboard changes. Project::storeCurrentFilters() keeps users.project_id pointed at the current study (User::withoutGlobalScopes()->update). DashboardChartsOverview::mount() prefers auth()->user()->project over Project::currentFor(). DashboardWidgetsTable's dashboard_id filter defaults to the remembered dashboard.
