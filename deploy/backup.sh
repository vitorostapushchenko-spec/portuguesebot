#!/bin/sh
# Nightly dump of the bot's database on the server, kept for 14 days in ~/portuguesebot/backups.
# Run by cron (see "Running on the server" in README.md). Restore with:
#   sudo docker exec -i ptbot_postgres pg_restore -U postgres -d pt_bot --clean --if-exists --no-owner < FILE
set -eu

dir="$HOME/portuguesebot/backups"
mkdir -p "$dir"
file="$dir/pt_bot-$(date +%F).dump"

sudo docker exec ptbot_postgres pg_dump -U postgres -d pt_bot -Fc > "$file.part" || {
    rm -f "$file.part"
    echo "backup failed" >&2
    exit 1
}
mv "$file.part" "$file"

find "$dir" -name 'pt_bot-*.dump' -mtime +14 -delete
echo "ok $file $(du -h "$file" | cut -f1)"
