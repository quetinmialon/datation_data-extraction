# France Travail Job Market Monitor (Laravel)

This project is a Laravel-based CLI tool that connects to the **France Travail** API to:

- Fetch **domains / ROME-like codes** (referential) from France Travail  
- Fetch **job offers** for each domain in a given region (e.g. Normandy = region `28`)  
- Compute and store **weekly indicators** per domain:

  - Average monthly salary (normalized from monthly / yearly / hourly values)  
  - Percentage of offers marked as “urgent” in title or description  
  - Average number of days since the offers were created  
  - Total number of offers available for that domain (using `Content-Range` header)  

All indicators are stored in a SQL database so you can track **market tension over time**.

---

## 1. Requirements

- PHP >= 8.1  
- Composer  
- SQLite (default), MySQL or PostgreSQL also supported  
- A valid **France Travail “Offres d’emploi” API** client:
  - `client_id`
  - `client_secret`
  - Proper scopes (e.g. `api_offresdemploiv2 o2dsoffre`)

---

## 2. Installation

Clone the project and install dependencies:

```bash
git clone https://github.com/quetinmialon/datation_data-extraction.git
cd datation_data-extraction

composer install
```

Copy the environment file: 

```bash
cp .env.example .env
```

Generate the Laravel application key

```bash
php artisan key:generate
```

## 3. Environment configuration (.env)

You start from the provided .env.example.
After copying it to .env, you should adjust the following sections.

### 3.1 Application section

```env
APP_NAME=Datathon_extraction
APP_ENV=local
APP_KEY= # filled automatically by php artisan key:generate
APP_DEBUG=true
APP_URL=http://localhost
```

You can rename APP_NAME for something that suits your needs

```env 
APP_NAME=FranceTravailMonitor
```

### 3.2 Database

By default, the example uses sqlite as database

```env
DB_CONNECTION=sqlite
# DB_HOST=127.0.0.1
# DB_PORT=3306
# DB_DATABASE=laravel
# DB_USERNAME=root
# DB_PASSWORD=
```

- Option A – Use SQLite (easiest for local dev)
1. Create a SQLite file (if not existing already):
```bash 
touch database/database.sqlite
```
2. Ensure the .env contains:
```env
DB_CONNECTION=sqlite
```
3. Comment out or leave unused the host/port/user/password lines.


- Option B - Use MySQL (or MariaDB)
Uncomment and set:
```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=france_travail
DB_USERNAME=your_user
DB_PASSWORD=your_password
```
Make sure the DB exists:


```bash
mysql -u your_user -p -e "CREATE DATABASE france_travail;"
```

### 3.3 France travail API configuration 

This is the core part of the project.
From your .env.example:

```env
FRANCE_TRAVAIL_CLIENT_ID =
FRANCE_TRAVAIL_CLIENT_SECRET =
FRANCE_TRAVAIL_SCOPE='api_offresdemploi/v2 o2dsoffre'
FRANCE_TRAVAIL_TOKEN_URL = "https://entreprise.francetravail.fr/connexion/oauth2/access_token?realm=/partenaire"
FRANCE_TRAVAIL_BASE_API = "https://api.francetravail.io/partenaire/offresdemploi"
FRANCE_TRAVAIL_API_BASE_URL="https://api.francetravail.io"
FRANCE_TRAVAIL_ROME_METIERS_ENDPOINT="/partenaire/offresdemploi/v2/referentiel/domaines"
```
you should adjust your actual .env file as:
```env
FRANCE_TRAVAIL_CLIENT_ID=your_client_id_here
FRANCE_TRAVAIL_CLIENT_SECRET=your_client_secret_here

# Scope required for Offres d'emploi API (check your France Travail contract/docs)
FRANCE_TRAVAIL_SCOPE=api_offresdemploi/v2 o2dsoffre

# OAuth2 token endpoint
FRANCE_TRAVAIL_TOKEN_URL=https://entreprise.francetravail.fr/connexion/oauth2/access_token?realm=/partenaire

# Base API URL (used by the service)
FRANCE_TRAVAIL_API_BASE_URL=https://api.francetravail.io

# (Optional/legacy) base path for offres d'emploi if needed by your code
FRANCE_TRAVAIL_BASE_API=https://api.francetravail.io/partenaire/offresdemploi

# Endpoint used to fetch domains (referential)
FRANCE_TRAVAIL_ROME_METIERS_ENDPOINT=/partenaire/offresdemploi/v2/referentiel/domaines

```
Notes : 
FRANCE_TRAVAIL_CLIENT_ID and FRANCE_TRAVAIL_CLIENT_SECRET are provided by France Travail when your application is registered.
FRANCE_TRAVAIL_SCOPE might vary depending on your contract; keep it consistent with the documentation.
FRANCE_TRAVAIL_ROME_METIERS_ENDPOINT is the endpoint used by the app to import the activity domains referential, not strictly ROME codes anymore.

## 4 Database Migration

Once the .env configuration is correct (especially DB and APP_KEY), run:

```bash
php artisan migrate
```
This will create tables such as:
- rome_codes (or “domains” codes, depending on how you named it)
- rome_stats_runs
- rome_stats
- Laravel system tables (migrations, jobs, sessions, etc.)

## 5 Artisan Commands

### 5.1. Import domains / codes from France Travail
This command fetches the referential of domains / ROME-like activity codes and stores them in rome_codes table:
```bash
php artisan francetravail:fetch-rome-codes
```
What it does:
- Calls the France Travail referential endpoint (FRANCE_TRAVAIL_ROME_METIERS_ENDPOINT)
- Parses the JSON and upserts each code + label into the database
You should run this at least once before running the statistics command.


### 5.2. Fetch offers and compute statistics
This command:
- Runs a new analytics “run” (rome_stats_runs)
- For each domain/code stored in rome_codes:
    - Calls France Travail /offres/search endpoint (region can be fixed, e.g. region=28 for Normandy)
    - Fetches up to 150 offers (sample)
    - Uses Content-Range header to get the total number of offers
    - Computes:
        - Average monthly salary (converting from monthly / yearly / hourly using 150h/month)
        - “Urgent” rate (title and description)
        - Average age of offers in days
        - Total number of offers for the domain
    - Stores everything in rome_stats
Example:

```bash
php artisan francetravail:fetch-stats --limit=150
```
YOu can schedule this command as you want to track evolution over time


