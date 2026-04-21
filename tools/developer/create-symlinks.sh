#!/bin/bash
#
# Creates symlinks in a CS-Cart installation for the Newsman addon.
# For every file/dir in newsman/src/, if the corresponding path in the
# CS-Cart dir is a real directory (shared with CS-Cart core), it recurses
# into it. Otherwise it creates an absolute symlink.
#
# Usage:
#   ./create-symlinks.sh <newsman_dir> <cscart_dir>
#
# Example:
#   ./create-symlinks.sh /path/to/newsman /path/to/cscart

set -e

if [ $# -ne 2 ]; then
    echo "Usage: $0 <newsman_dir> <cscart_dir>"
    echo "  newsman_dir — path to the Newsman plugin root (contains src/)"
    echo "  cscart_dir  — path to the CS-Cart installation directory"
    exit 1
fi

NEWSMAN_SRC="$(realpath "$1")/src"
CSCART_DIR="$(realpath "$2")"

if [ ! -d "$NEWSMAN_SRC" ]; then
    echo "Error: $NEWSMAN_SRC does not exist."
    exit 1
fi

if [ ! -d "$CSCART_DIR" ]; then
    echo "Error: $CSCART_DIR does not exist."
    exit 1
fi

link_count=0

# Directories that should be symlinked as a whole instead of recursed into.
# Paths are relative to the CS-Cart dir.
SYMLINK_WHOLE_DIRS="app/addons/newsman"

# Recursively walk src_dir. For each entry:
#   - if the relative path is in SYMLINK_WHOLE_DIRS, symlink the whole dir
#   - if it maps to a real directory in cscart_dir, recurse
#   - otherwise remove any existing symlink and create a new one
process_dir() {
    local src_dir="$1"
    local cscart_target="$2"

    for src_entry in "$src_dir"/*; do
        [ -e "$src_entry" ] || continue

        local name
        name="$(basename "$src_entry")"
        local cscart_entry="$cscart_target/$name"

        # Compute relative path from CSCART_DIR for whole-dir check.
        local rel_path="${cscart_entry#"$CSCART_DIR"/}"

        if [ "$rel_path" = "$SYMLINK_WHOLE_DIRS" ]; then
            # This directory should be symlinked as a whole.
            if [ -d "$cscart_entry" ] && [ ! -L "$cscart_entry" ]; then
                rm -rf "$cscart_entry"
                echo "Removed real dir: $cscart_entry"
            elif [ -L "$cscart_entry" ]; then
                rm "$cscart_entry"
            fi
            ln -s "$src_entry" "$cscart_entry"
            echo "Created:  $cscart_entry -> $src_entry"
            link_count=$((link_count + 1))
        elif [ -d "$cscart_entry" ] && [ ! -L "$cscart_entry" ]; then
            # Real directory exists in CS-Cart — shared with core, recurse into it
            process_dir "$src_entry" "$cscart_entry"
        else
            # Not a real directory in CS-Cart — create symlink
            if [ -L "$cscart_entry" ]; then
                rm "$cscart_entry"
                echo "Replaced: $cscart_entry"
            else
                echo "Created:  $cscart_entry"
            fi
            ln -s "$src_entry" "$cscart_entry"
            link_count=$((link_count + 1))
        fi
    done
}

echo "Newsman src : $NEWSMAN_SRC"
echo "CS-Cart dir : $CSCART_DIR"
echo ""

process_dir "$NEWSMAN_SRC" "$CSCART_DIR"

echo ""
echo "Done. $link_count symlink(s) created/replaced."
