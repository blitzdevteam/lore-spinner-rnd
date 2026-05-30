#!/bin/bash

PATCH_DIR="patches/fcc27778f930e793c7f20e19d47c6a5757650830"
START=${1:-0001}

resolve_conflicts() {
    python3 - "$@" <<'PYEOF'
import re, sys

def resolve(filepath):
    with open(filepath, 'r') as f:
        content = f.read()
    
    pattern = re.compile(r'<<<<<<< ours\n(.*?)\n=======\n(.*?)\n>>>>>>> theirs\n', re.DOTALL)
    resolved = pattern.sub(lambda m: m.group(2) + '\n', content)
    
    # Handle case where ours is empty (no trailing newline after =======)
    resolved = re.sub(r'<<<<<<< ours\n=======\n', '', resolved)
    resolved = re.sub(r'\n>>>>>>> theirs\n', '\n', resolved)
    
    with open(filepath, 'w') as f:
        f.write(resolved)
    
    remaining = re.findall(r'<<<<<<< ours|>>>>>>> theirs', resolved)
    if remaining:
        print(f"WARNING: {filepath} still has {len(remaining)} conflict markers!")
        return False
    return True

all_ok = True
for filepath in sys.argv[1:]:
    if not resolve(filepath):
        all_ok = False

sys.exit(0 if all_ok else 1)
PYEOF
}

for patch_file in "$PATCH_DIR"/[0-9][0-9][0-9][0-9]-*.patch; do
    num=$(basename "$patch_file" | cut -c1-4)
    if [[ "$num" < "$START" ]]; then
        continue
    fi

    echo "=== Patch $num: $(basename $patch_file) ==="

    git apply --3way "$patch_file" 2>&1 || true

    conflicts=$(git status --short 2>/dev/null | grep "^UU" | awk '{print $2}')
    if [ -n "$conflicts" ]; then
        echo "Auto-resolving conflicts (accepting theirs): $conflicts"
        resolve_conflicts $conflicts
        if [ $? -ne 0 ]; then
            echo "COULD NOT AUTO-RESOLVE — manual fix needed"
            exit 2
        fi
        git add $conflicts 2>/dev/null || true
    fi

    git add -A -- ':!.DS_Store' ':!patches/' ':!apply_patches.sh' 2>/dev/null || true

    if git diff --cached --quiet 2>/dev/null; then
        echo "(no changes, skipping commit)"
    else
        subject=$(grep "^Subject:" "$patch_file" | head -1 | sed 's/^Subject: \[PATCH[^]]*\] //')
        git commit -m "${subject:-patch-$num}" 2>&1
        echo "Committed $num"
    fi
done

echo ""
echo "All patches applied successfully!"
