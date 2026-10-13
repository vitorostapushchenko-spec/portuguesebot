#!/usr/bin/env bash
# Sends the project to a GitHub repository. Usage:  bash deploy/subir-github.sh https://github.com/SEU-USUARIO/portuguesebot.git
# (create the empty repository on github.com first, without README). .env stays out (see .gitignore).
set -euo pipefail
cd "$(dirname "$0")/.."
URL="${1:?Passe a URL do repositorio. Ex: bash deploy/subir-github.sh https://github.com/SEU-USUARIO/portuguesebot.git}"
[ -d .git ] || git init -b main
git add -A
git status --short | grep -E '(^|/)\.env$' && { echo "ERRO: .env entrou no commit, abortei."; exit 1; } || true
git commit -m "portuguesebot: bot, licoes e audios das frases" || echo "(nada novo para commitar)"
git remote remove origin 2>/dev/null || true
git remote add origin "$URL"
git push -u origin main
echo "Pronto: enviado para $URL"
