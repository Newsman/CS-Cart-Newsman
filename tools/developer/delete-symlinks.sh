#!/bin/bash
#
# Deletes symlinks in a CS-Cart installation that point to the
# Newsman addon's src/ directory. Only removes symlinks whose target
# starts with the Newsman src/ path.
#
# Usage:
#   ./delete-symlinks.sh <newsman_dir> <cscart_dir>
#
# Example:
#   ./delete-symlinks.sh /path/to/newsman /path/to/cscart

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

# Recursively walk cscart_dir. For each entry:
#   - if it is a symlink pointing into NEWSMAN_SRC, remove it
#   - if it is a real directory, recurse into it
process_dir() {
    local cscart_dir="$1"

    for entry in "$cscart_dir"/*; do
        [ -e "$entry" ] || [ -L "$entry" ] || continue

        if [ -L "$entry" ]; then
            local target
            target="$(readlink -f "$entry" 2>/dev/null || readlink "$entry")"
            if [[ "$target" == "$NEWSMAN_SRC"* ]]; then
                rm "$entry"
                echo "Deleted: $entry"
                link_count=$((link_count + 1))
            fi
        elif [ -d "$entry" ]; then
            process_dir "$entry"
        fi
    done
}

echo "Newsman src : $NEWSMAN_SRC"
echo "CS-Cart dir : $CSCART_DIR"
echo ""

process_dir "$CSCART_DIR"

echo ""
echo "Done. $link_count symlink(s) deleted."
