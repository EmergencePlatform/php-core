pkg_name=php-core
pkg_origin=emergence
pkg_version="0.2.4"
pkg_maintainer="Chris Alfano <chris@jarv.us>"
pkg_license=("MIT")
pkg_build_deps=(
  core/composer
)


do_setup_environment() {
  set_buildtime_env COMPOSER_ALLOW_SUPERUSER "1"
}

do_build() {
  build_line "Copying core (excluding vendor)"
  pushd "${PLAN_CONTEXT}" > /dev/null
  find . \
    -maxdepth 1 -mindepth 1 \
    -not -name 'plan.sh' \
    -not -name 'vendor' \
    -not -name '.git*' \
    -exec cp -r '{}' "${CACHE_PATH}/{}" \;
  popd > /dev/null

  build_line "Running: composer install"
  pushd "${CACHE_PATH}" > /dev/null
  composer install --no-dev  --no-interaction --optimize-autoloader --classmap-authoritative
  popd > /dev/null
}

do_install() {
  cp -r "${CACHE_PATH}"/* "${pkg_prefix}/"
}

do_strip() {
  return 0
}
