#!/usr/bin/env bash
# Checks a release zip of local_classprogress or mod_classprogress (REQ-REL-002): every entry lies under the one root
# folder classprogress/, the files a release needs are there, and none of the development material is.
#
# Usage: .github/scripts/check-release-zip.sh <file.zip>
# Prints every problem found and exits 1, or prints OK and exits 0. The release workflow runs it on the zip it built;
# the same file is kept in both repositories.

set -euo pipefail

if [ $# -ne 1 ] || [ ! -f "$1" ]; then
    echo "Usage: $0 <file.zip>" >&2
    exit 2
fi

root=classprogress
# Development material, relative to the plugin folder; a trailing slash means a folder.
forbidden=(PLAN.md docs/ prototype/ test-data/ tools/ .github/ .gitattributes .gitignore .moodle-plugin-ci.yml)
# What a release holds: these files, and at least one file in each of these folders.
required=(README.md CHANGES.md version.php lang/en/ lang/ca/ lang/es/)

if ! listing=$(unzip -Z1 "$1"); then
    echo "FAIL: $1 cannot be read as a zip, or holds nothing"
    exit 1
fi
mapfile -t entries <<< "$listing"

problems=0
problem() {
    echo "FAIL: $*"
    problems=$((problems + 1))
}

for entry in "${entries[@]}"; do
    case "$entry" in
        "$root"/*) ;;
        *) problem "outside the root folder $root/: $entry" ;;
    esac
done

for path in "${forbidden[@]}"; do
    for entry in "${entries[@]}"; do
        relative=${entry#"$root"/}
        if [ "$relative" = "$path" ] || { [ "${path%/}" != "$path" ] && [[ "$relative" == "$path"* ]]; }; then
            problem "development material in the zip: $root/$path"
            break
        fi
    done
done

for path in "${required[@]}"; do
    found=0
    for entry in "${entries[@]}"; do
        if [ "${path%/}" = "$path" ]; then
            [ "$entry" = "$root/$path" ] && found=1
        else
            [[ "$entry" == "$root/$path"* && "$entry" != */ ]] && found=1
        fi
        if [ $found -eq 1 ]; then
            break
        fi
    done
    if [ $found -eq 0 ]; then
        problem "missing from the zip: $root/$path"
    fi
done

if [ $problems -gt 0 ]; then
    echo "$1: $problems problem(s)"
    exit 1
fi
echo "OK: $1 (${#entries[@]} entries under $root/)"
