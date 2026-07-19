#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
MOBILE_DIR="$(cd "${SCRIPT_DIR}/.." && pwd)"
PUBSPEC_FILE="${MOBILE_DIR}/pubspec.yaml"

if [[ ! -f "${PUBSPEC_FILE}" ]]; then
  echo "pubspec.yaml not found at: ${PUBSPEC_FILE}" >&2
  exit 1
fi

version_line="$(grep -E '^version:' "${PUBSPEC_FILE}" | head -n 1 || true)"
if [[ -z "${version_line}" ]]; then
  echo "Could not find a version line in pubspec.yaml" >&2
  exit 1
fi

version_value="$(echo "${version_line}" | awk '{print $2}')"
if [[ ! "${version_value}" =~ ^([0-9]+\.[0-9]+\.[0-9]+)\+([0-9]+)$ ]]; then
  echo "Unsupported version format: ${version_value}" >&2
  echo "Expected: x.y.z+buildNumber (example: 1.0.0+12)" >&2
  exit 1
fi

build_name="${BASH_REMATCH[1]}"
current_build_number="${BASH_REMATCH[2]}"
new_build_number=$((current_build_number + 1))

tmp_file="$(mktemp)"
awk -v new_build_number="${new_build_number}" '
  BEGIN { updated = 0 }
  /^version:[[:space:]]*/ && !updated {
    split($2, parts, "+")
    print "version: " parts[1] "+" new_build_number
    updated = 1
    next
  }
  { print }
' "${PUBSPEC_FILE}" > "${tmp_file}"

mv "${tmp_file}" "${PUBSPEC_FILE}"

echo "Updated version: ${build_name}+${new_build_number}"
cd "${MOBILE_DIR}"
flutter build appbundle --release --build-number="${new_build_number}" "$@"
