#!/bin/sh

set -u

ROOT=$(CDPATH= cd -- "$(dirname -- "$0")/.." && pwd)
MAX_ITERATIONS=${MAX_ITERATIONS:-3}
TARGET_REF=${TARGET_REF:-WORKTREE}
STATE_FILE=${STATE_FILE:-"$ROOT/.lab-loop-state.env"}
RUN_CODEX=${RUN_CODEX:-0}

iteration=0
status=running

if [ -f "$STATE_FILE" ]; then
    . "$STATE_FILE"
fi

write_state() {
    {
        echo "iteration=$iteration"
        echo "status=$status"
        echo "target_ref=$TARGET_REF"
    } > "$STATE_FILE"
}

while [ "$iteration" -lt "$MAX_ITERATIONS" ]; do
    iteration=$((iteration + 1))
    status=checking
    write_state
    echo "ITERATION=$iteration TARGET=$TARGET_REF"

    if [ "$TARGET_REF" = "WORKTREE" ]; then
        (cd "$ROOT" && composer verify)
        verify_exit=$?
    else
        php "$ROOT/tools/verify-task-sorting.php" "$TARGET_REF"
        verify_exit=$?
    fi

    if [ "$verify_exit" -eq 0 ]; then
        status=done
        write_state
        echo "STOP=checks_passed ITERATION=$iteration"
        exit 0
    fi

    if [ "$RUN_CODEX" = "1" ]; then
        codex exec --ephemeral --ignore-user-config \
            --sandbox workspace-write \
            -C "$ROOT" \
            "$(cat "$ROOT/PROMPT.md")"
    else
        echo "WORKER=skipped RUN_CODEX=0"
    fi
done

status=escalated
write_state
echo "STOP=max_iterations ITERATION=$iteration"
exit 1
