#!/usr/bin/env bash
# Installs Demo City Council on LocalGov Drupal from scratch.
#
# Usage (DDEV):     ddev setup-site
# Usage (other):    DRUSH=vendor/bin/drush DB_URL=sqlite://sites/default/files/.ht.sqlite bash scripts/setup-site.sh
set -euo pipefail

DRUSH="${DRUSH:-drush}"
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

DB_ARGS=()
if [ -n "${DB_URL:-}" ]; then
  DB_ARGS=(--db-url="$DB_URL")
fi

echo "Installing LocalGov Drupal..."
$DRUSH site:install localgov "${DB_ARGS[@]}" \
  --site-name="Demo City Council" \
  --account-name=admin --account-pass=admin -y

echo "Enabling LocalGov features and custom modules..."
$DRUSH pm:install -y \
  localgov_guides localgov_publications localgov_step_by_step \
  localgov_alert_banner localgov_search localgov_search_db inline_form_errors \
  accessible_formats bin_day_finder

echo "Enabling the Demo City Council theme..."
$DRUSH theme:install -y demo_city
$DRUSH config:set -y system.theme default demo_city

# Content goes last, after the theme, because it updates theme blocks.
echo "Creating demonstration content..."
$DRUSH pm:install -y demo_city_content

$DRUSH search-api:index -q || true
$DRUSH cache:rebuild
echo "Done. Log in with admin / admin, or run: $DRUSH user:login"
