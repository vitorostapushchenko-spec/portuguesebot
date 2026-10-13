#!/usr/bin/env bash
# Read-only check: shows how lessons 9, 22, 29, 30, 40 and 46 are stored in the server's database.
gcloud compute ssh "${VM:-lunch-bot}" --zone "${ZONE:-us-central1-a}" --quiet --command \
  "sudo docker exec ptbot_postgres sh -c 'psql -U \$POSTGRES_USER -d \$POSTGRES_DB -c \"select \\\"order\\\", phrase_pt, has_variant, audio_file_id is not null as tem_audio from lessons where \\\"order\\\" in (9,22,29,30,40,46) order by 1;\"'"
