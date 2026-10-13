#!/usr/bin/env bash
# Builds the production image on this machine, sends it to the server and restarts the bot there.
# Run it from Git Bash, from the project root:   bash deploy/deploy.sh
#
# The server needs ~/portuguesebot/.env once (see "Running on the server" in README.md); this script
# only ever replaces the image and docker-compose.yml, never the .env or the database.
# The upload is about 230 MB and takes around ten minutes on a slow connection.
set -euo pipefail

VM="${VM:-lunch-bot}"
ZONE="${ZONE:-us-central1-a}"
IMAGE="portuguesebot-app:prod"
ARCHIVE="deploy/image.tar.gz"

cd "$(dirname "$0")/.."
trap 'rm -f "$ARCHIVE"' EXIT

# One-line remote commands only: a multi-line --command does not survive PuTTY's plink on Windows.
remote() { gcloud compute ssh "$VM" --zone "$ZONE" --quiet --command "$1"; }

echo "==> Checking the server"
remote "mkdir -p ~/portuguesebot && (test -f ~/portuguesebot/.env || (echo 'Missing ~/portuguesebot/.env on the server' >&2; false))"

echo "==> Building $IMAGE"
docker build -f docker/Dockerfile.prod -t "$IMAGE" .

echo "==> Saving the image"
docker save "$IMAGE" | gzip > "$ARCHIVE"

echo "==> Uploading $(du -h "$ARCHIVE" | cut -f1) to $VM"
# Remote paths are relative to the home folder: on Windows gcloud uses PuTTY's pscp, which does not expand "~/".
gcloud compute scp --zone "$ZONE" --quiet "$ARCHIVE" "$VM:portuguesebot/image.tar.gz"
gcloud compute scp --zone "$ZONE" --quiet docker-compose.prod.yml "$VM:portuguesebot/docker-compose.yml"
gcloud compute scp --zone "$ZONE" --quiet deploy/backup.sh "$VM:portuguesebot/backup.sh"

echo "==> Loading the image and restarting the bot"
remote "cd ~/portuguesebot && chmod +x backup.sh && gunzip -c image.tar.gz | sudo docker load && rm image.tar.gz && sudo docker compose up -d && sudo docker image prune -f > /dev/null && sudo docker compose ps"

echo "==> Done. Logs:  gcloud compute ssh $VM --zone $ZONE --command 'sudo docker logs --tail 30 ptbot_app'"
