#!/usr/bin/env bash
# Publishes the bot from a double click on publicar-bot.bat (in the project root): checks that Docker is running,
# runs deploy/deploy.sh and then asks the server which version it runs. Everything shown is also written to
# deploy/ultimo-deploy.log, so a failed run can be read afterwards. Nothing secret is printed: the .env files are
# never shown, and the bot's own logs are not copied.
set -uo pipefail
cd "$(dirname "$0")/.."

{
    echo "== $(date '+%Y-%m-%d %H:%M') =="

    if ! docker info > /dev/null 2>&1; then
        echo "ERRO: o Docker Desktop nao esta aberto. Abra o Docker Desktop, espere ficar verde e de dois cliques de novo em publicar-bot.bat."
        exit 1
    fi

    if ! command -v gcloud > /dev/null 2>&1; then
        echo "ERRO: o gcloud nao foi encontrado. Abra o Git Bash nesta pasta e rode: bash deploy/deploy.sh"
        exit 1
    fi

    echo "== Publicando (uns 10 minutos). Nao feche esta janela. =="
    if ! bash deploy/deploy.sh; then
        echo "ERRO: a publicacao parou antes do fim. Veja as linhas acima."
        exit 1
    fi

    echo "== Conferindo o servidor =="
    sleep 20
    gcloud compute ssh "${VM:-lunch-bot}" --zone "${ZONE:-us-central1-a}" --quiet --command "sudo docker ps --format '{{.Names}}: {{.Status}}' | grep ptbot; sudo docker exec ptbot_app php artisan migrate:status | tail -3"

    echo "== Colocando os audios nas licoes (voce vai receber as mensagens de voz no Telegram) =="
    gcloud compute ssh "${VM:-lunch-bot}" --zone "${ZONE:-us-central1-a}" --quiet --command "sudo docker exec ptbot_app php artisan lessons:import-audio" || echo "AVISO: o envio dos audios falhou; rode de novo para refazer so os que faltam."

    echo "== PRONTO. No Telegram, mande para o bot:  /audio =="
} 2>&1 | tee deploy/ultimo-deploy.log
