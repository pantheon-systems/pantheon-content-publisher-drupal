#!/bin/bash
#
# @file Remote Test Orchestrator for Pantheon
# Executes Unit and Kernel tests on a Multidev environment.
# Uses sentinel strings (SENTINEL_SUCCESS/SENTINEL_ERROR) to detect pass/fail
# since exit codes are unreliable through the terminus remote execution chain.
#
# Requires: TERMINUS_SITE, MULTIDEV_ENV environment variables.

set -eo pipefail

# ── Preflight ────────────────────────────────────────────────────────
if [[ -z "$TERMINUS_SITE" || -z "$MULTIDEV_ENV" ]]; then
  echo "::error::TERMINUS_SITE and MULTIDEV_ENV must be set."
  exit 1
fi

SITE_ENV="${TERMINUS_SITE}.${MULTIDEV_ENV}"
BASE_URL="https://${MULTIDEV_ENV}-${TERMINUS_SITE}.pantheonsite.io"

# ── Ensure writable filesystem ───────────────────────────────────────
# SFTP mode needed for PHPUnit to write temp files during test execution.
terminus connection:set "$SITE_ENV" sftp -y 2>/dev/null || true

# ── PHP payload template ─────────────────────────────────────────────
# Single-quoted heredoc prevents bash from expanding PHP $ variables.
# Placeholders __TEST_DIR__ and __BASE_URL__ are substituted per suite.
read -r -d '' PHP_TEMPLATE << 'PHPEOF' || true
$conn = \Drupal::database()->getConnectionOptions();
$db_url = sprintf('mysql://%s:%s@%s:%s/%s',
  $conn['username'], $conn['password'],
  $conn['host'], $conn['port'] ?? 3306, $conn['database']
);

$drupal_root = is_dir('/code/web/core') ? '/code/web' : '/code';
$module_path = \Drupal::service('extension.list.module')
  ->getPath('pantheon_content_publisher');

if (!$module_path) {
  echo 'SENTINEL_ERROR: Module not found';
  return;
}

$phpunit    = dirname($drupal_root) . '/vendor/bin/phpunit';
$config     = $drupal_root . '/core/phpunit.xml.dist';
$test_path  = $drupal_root . '/' . $module_path . '/__TEST_DIR__';
$base_url   = '__BASE_URL__';

$cmd = sprintf(
  'SIMPLETEST_DB=%s SIMPLETEST_BASE_URL=%s BROWSERTEST_OUTPUT_DIRECTORY=/tmp SYMFONY_DEPRECATIONS_HELPER=weak %s -c %s %s --testdox --no-coverage',
  escapeshellarg($db_url),
  escapeshellarg($base_url),
  escapeshellarg($phpunit),
  escapeshellarg($config),
  escapeshellarg($test_path)
);

passthru($cmd, $exit_status);
if ($exit_status === 0) echo 'SENTINEL_SUCCESS';
PHPEOF

# ── Test runner ──────────────────────────────────────────────────────
run_tests() {
  local suite="$1"
  local test_dir="$2"

  # Substitute placeholders into the PHP template.
  local php_code="${PHP_TEMPLATE//__TEST_DIR__/$test_dir}"
  php_code="${php_code//__BASE_URL__/$BASE_URL}"

  echo "::group::${suite} tests"

  set +e
  # 2>/dev/null suppresses the Terminus "[notice] Command:" line that
  # otherwise dumps the full PHP payload (including DB credentials)
  # into CI logs. Test output (stdout from passthru) is preserved.
  # sed redacts any DB connection strings that leak through.
  local output
  output=$(terminus remote:drush "$SITE_ENV" -- ev "$php_code" 2>/dev/null \
    | sed -E 's|mysql://[^@]*@|mysql://REDACTED@|g')
  set -e

  echo "$output"
  echo "::endgroup::"

  if echo "$output" | grep -q "SENTINEL_SUCCESS"; then
    echo "::notice::${suite} tests passed"
    echo "- **${suite}**: passed" >> "${GITHUB_STEP_SUMMARY:-/dev/null}"
    return 0
  else
    echo "::error::${suite} tests failed"
    echo "- **${suite}**: failed" >> "${GITHUB_STEP_SUMMARY:-/dev/null}"
    return 1
  fi
}

# ── Execute suites (fail-fast: Unit → Kernel) ────────────────────────
echo "## PHPUnit Test Results" >> "${GITHUB_STEP_SUMMARY:-/dev/null}"
run_tests "Unit"   "tests/src/Unit"   || exit 1
run_tests "Kernel" "tests/src/Kernel" || exit 1
