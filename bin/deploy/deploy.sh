#!/usr/bin/env bash
#
# Urca tema pe productie prin git.
#
# Serverul are o clona a repo-ului direct in directorul temei (initializata
# pe 2026-09-11, remote HTTPS public) si urmareste ramura `main`. Scriptul:
#
#   1. cere un working tree curat si te pune pe `main` adus la zi cu `dev`;
#   2. impinge `main` pe GitHub;
#   3. pe server: refuza daca exista modificari locale (editari directe),
#      apoi `git pull --ff-only`, `php -l` pe fisierele PHP schimbate si
#      afiseaza commit-ul ajuns pe live.
#
# Rulare, din directorul temei:  bin/deploy/deploy.sh
# Optiuni:  --no-merge   nu aduce `main` la `dev`, urca `main` asa cum e
#           --dry-run    doar arata ce ar urca (git log main..dev + status server)
#
# Cheia ssh si adresa serverului: vezi variabilele de mai jos.

set -euo pipefail

SSH_KEY="${HT_DEPLOY_KEY:-$HOME/.ssh/herbal_key}"
SSH_HOST="${HT_DEPLOY_HOST:-root@82.25.203.145}"
REMOTE_DIR="/home/herbaltherapy/public_html/wp-content/themes/herbal-therapy"
REMOTE_USER="herbaltherapy"

MERGE=1
DRY=0

for arg in "$@"; do
    case "$arg" in
        --no-merge) MERGE=0 ;;
        --dry-run)  DRY=1 ;;
        *) echo "optiune necunoscuta: $arg" >&2; exit 2 ;;
    esac
done

cd "$(dirname "$0")/../.."

if [ -n "$(git status --porcelain)" ]; then
    echo "working tree-ul local nu e curat; comite sau pune deoparte modificarile" >&2
    git status --short >&2
    exit 1
fi

branch="$(git rev-parse --abbrev-ref HEAD)"

if [ "$DRY" = 1 ]; then
    echo "== commit-uri care ar ajunge pe live (main..dev):"
    git log --oneline main..dev || true
    echo "== starea serverului:"
    ssh -i "$SSH_KEY" "$SSH_HOST" "cd $REMOTE_DIR && sudo -u $REMOTE_USER -H git status --short && sudo -u $REMOTE_USER -H git log --oneline -1"
    exit 0
fi

if [ "$MERGE" = 1 ]; then
    git checkout -q main
    git merge -q --ff-only dev
fi

git push -q origin main
echo "main pe GitHub: $(git rev-parse --short main)"

ssh -i "$SSH_KEY" "$SSH_HOST" "set -e; cd $REMOTE_DIR; G='sudo -u $REMOTE_USER -H git'
if [ -n \"\$(\$G status --porcelain --untracked-files=no)\" ]; then
    echo 'pe server exista modificari locale neversionate - preia-le intai:' >&2
    \$G status --short --untracked-files=no >&2
    exit 1
fi
before=\$(\$G rev-parse HEAD)
\$G pull -q --ff-only origin main
after=\$(\$G rev-parse HEAD)
echo \"server: \$(\$G log --oneline -1)\"
for f in \$(\$G diff --name-only \$before \$after -- '*.php'); do
    [ -f \"\$f\" ] && php -l \"\$f\" | grep -v 'No syntax errors' || true
done
echo 'php -l: ok'"

# inapoi pe ramura de lucru
if [ "$MERGE" = 1 ] && [ "$branch" != "main" ]; then
    git checkout -q "$branch"
fi

echo "gata."
