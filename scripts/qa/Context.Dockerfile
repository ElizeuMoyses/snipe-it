FROM busybox:1.37
COPY . /context
RUN test -f /context/composer.lock \
 && test -f /context/docker/docker.env \
 && test -d /context/storage/private_uploads \
 && test -d /context/storage/app/backups \
 && test ! -e /context/.env \
 && test ! -e /context/.env.testing \
 && test ! -e /context/.local-production \
 && test ! -e /context/.local-validation \
 && test -z "$(find /context -name '*security-canary*' -print)"
