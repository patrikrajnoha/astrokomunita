## Fresh clone / CI parity

Run the same steps locally that CI runs on GitHub Actions.

### 1) Backend (Laravel, SQLite only)

```bash
git clone <repo-url>
cd astrokomunita/backend
composer install --prefer-dist --no-progress --no-interaction
cp .env.example .env
mkdir -p database
touch database/database.sqlite
APP_ENV=testing \
DB_CONNECTION=sqlite \
DB_DATABASE=database/database.sqlite \
CACHE_DRIVER=array \
QUEUE_CONNECTION=sync \
MAIL_MAILER=array \
SESSION_DRIVER=array \
BROADCAST_DRIVER=log \
FILES_DISK=public \
php artisan key:generate --force
APP_ENV=testing \
DB_CONNECTION=sqlite \
DB_DATABASE=database/database.sqlite \
CACHE_DRIVER=array \
QUEUE_CONNECTION=sync \
MAIL_MAILER=array \
SESSION_DRIVER=array \
BROADCAST_DRIVER=log \
FILES_DISK=public \
php artisan migrate --force
APP_ENV=testing \
DB_CONNECTION=sqlite \
DB_DATABASE=database/database.sqlite \
CACHE_DRIVER=array \
QUEUE_CONNECTION=sync \
MAIL_MAILER=array \
SESSION_DRIVER=array \
BROADCAST_DRIVER=log \
FILES_DISK=public \
php artisan test
```

### 2) Frontend (Vue)

```bash
cd ../frontend
npm ci
npm run lint
npm run test
npm run build
```

### 3) Moderation service (FastAPI, lightweight checks)

```bash
cd ../moderation-service
python -m pip install --upgrade pip
pip install -r requirements.txt
python -m py_compile $(git ls-files '*.py')
TRANSFORMERS_OFFLINE=1 HF_HUB_OFFLINE=1 python -c "import app.main"
if command -v pytest >/dev/null 2>&1 && [ -d tests ]; then pytest -q; else echo "Skipping pytest"; fi
```
