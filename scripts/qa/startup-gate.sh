#!/bin/sh
set -eu
repo=$(pwd)
scratch=$(mktemp -d)
trap 'rm -rf "$scratch"' EXIT
mkdir "$scratch/bin"
cat > "$scratch/bin/php" <<'EOF'
#!/bin/sh
printf '%s\n' "$*" >> "$GATE_TRACE"
if [ "$2" = "$GATE_FAIL" ]; then exit 42; fi
EOF
chmod +x "$scratch/bin/php"
export PATH="$scratch/bin:$PATH"
export GATE_TRACE="$scratch/trace"
for GATE_FAIL in config:clear migrate config:cache none; do
    export GATE_FAIL
    : > "$GATE_TRACE"
    result=0
    sh "$repo/docker/initialize-app.sh" || result=$?
    case "$GATE_FAIL" in
        config:clear) expected=1 ;;
        migrate) expected=2 ;;
        config:cache|none) expected=3 ;;
    esac
    test "$(wc -l < "$GATE_TRACE" | tr -d ' ')" = "$expected"
    if [ "$GATE_FAIL" = none ]; then test "$result" = 0; else test "$result" = 42; fi
done
printf 'Startup gate: success and all three failure boundaries passed.\n'
