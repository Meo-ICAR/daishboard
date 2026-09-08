---
paths:
  - app/Support/CompanyScope.php
---

# Support

## CompanyScope::byOwner is role-aware
byOwner() now matches the DashboardWidget `owned` global scope: no user OR superadmin -> no filter (returns query unchanged); company admin (isAdmin) -> only `(company_id IS NULL OR company_id = user.company_id)`; normal user -> that AND `(user_id IS NULL OR user_id = user.id)`. So an explicit CompanyScope::byOwner() call (DashboardChartsOverview::topLevelMasters, DashboardWidgetsTable, DataAssistant) no longer adds a spurious `user_id = X` / `company_id IS NULL OR company_id IS NULL` for a superadmin. byDatabase() is unchanged (database column, NULL-inclusive).
