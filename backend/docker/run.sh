#!/bin/bash
set -e

mkdir -p database storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs storage/app/knowledge_documents storage/app/private/knowledge_documents
rm -f bootstrap/cache/*.php

touch database/database.sqlite

php artisan package:discover --ansi
php artisan migrate --force

USER_COUNT=$(php -r 'try { $p = new PDO("sqlite:database/database.sqlite"); echo (int) $p->query("SELECT COUNT(*) FROM users")->fetchColumn(); } catch (Throwable $e) { echo 0; }')
if [ "$USER_COUNT" = "0" ]; then
    php artisan db:seed --force
fi

exec php artisan serve --host=0.0.0.0 --port=8000
