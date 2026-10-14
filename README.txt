SmartDoc Laravel scaffold - overlay onto the root of a fresh Laravel project.

1. DELETE the default database/migrations/0001_01_01_000000_create_users_table.php
   (database/migrations/2026_10_14_000002_create_users_table.php replaces it).
2. Add the 'legacy' connection from config-snippet.php to config/database.php.
3. Add DB_LEGACY_DATABASE=smartdoc_legacy to .env.
4. Overwrite routes/web.php when prompted (it contains only login/logout/dashboard placeholder).
