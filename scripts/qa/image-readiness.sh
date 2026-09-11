#!/bin/bash
set -euo pipefail
# Only committed source and synthetic data enter these builds.
source_root=$(pwd)
revision=$(git rev-parse HEAD)
scratch_base=$(cd "${TMPDIR:-/tmp}" && pwd -P)
scratch=$(mktemp -d "$scratch_base/snipeit-image.XXXXXXXX")
cleanup_context() {
    case "$scratch" in
        "$scratch_base"/snipeit-image.*) rm -rf -- "$scratch" ;;
        *) echo 'Refusing cleanup outside the image-test temporary directory.' >&2 ;;
    esac
}
trap cleanup_context EXIT
git archive HEAD | tar -x -C "$scratch"
mkdir -p "$scratch"/{.local-production,.local-validation,storage/private_uploads,public/uploads,storage/app/backups,storage/logs,bootstrap/cache}
for path in .env .env.testing .local-production/security-canary.sql .local-validation/security-canary.json storage/private_uploads/security-canary.txt public/uploads/security-canary.txt storage/app/backups/security-canary.zip storage/logs/security-canary.log storage/security-canary.key bootstrap/cache/security-canary.php security-canary.sql.gz; do
    printf 'SYNTHETIC BUILD EXCLUSION CANARY\n' > "$scratch/$path"
done
build_context="$scratch"
context_recipe="$source_root/scripts/qa/Context.Dockerfile"
if command -v cygpath >/dev/null 2>&1; then
    export MSYS_NO_PATHCONV=1
    build_context=$(cygpath -m "$scratch")
    context_recipe=$(cygpath -m "$context_recipe")
fi
docker build --progress=plain -f "$context_recipe" "$build_context"
docker build --progress=plain --label "org.opencontainers.image.revision=$revision" -t "snipeit-readiness:$revision" -f "$build_context/Dockerfile" "$build_context"
docker image inspect "snipeit-readiness:$revision" --format '{{.Id}} {{index .Config.Labels "org.opencontainers.image.revision"}}'
bash scripts/qa/image-smoke.sh "snipeit-readiness:$revision"
