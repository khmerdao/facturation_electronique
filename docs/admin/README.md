# Section ADMIN SAAS — Super-administration

Voir la documentation complète dans `docs/notifications/README.md` (Partie 2).

Ce fichier est un alias — la documentation Admin est colocalisée avec
les Notifications dans le même commit pour des raisons de lisibilité.

Les pages documentées :
- `/admin/tenants` — Liste & supervision des tenants
- `/admin/tenants/{id}` — Détail tenant (utilisation, santé technique, actions)
- `/admin/logs` — Logs audit global, super-admin, PDP, e-reporting, workers

Entités spécifiques admin :
- `SuperAdminLog` — actions cross-tenant (isolation du AuditLog tenant)
- Firewall séparé `admin` dans security.yaml
- Impersonation avec `ROLE_PREVIOUS_ADMIN` + logging is_impersonated
