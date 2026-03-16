## o aplikacii

astrokomunita je webova aplikacia pre fanusikov astronomie. pouzivatelia v nej sleduju astronomicke udalosti, citaju a pridavaju prispevky, ukladaju pozorovania a dostavaju upozornenia na zaujimave eventy.

## ako funguje

frontend zobrazuje feed, eventy, profil a dalsie casti aplikacie. backend poskytuje api, autentifikaciu a business logiku. data sa ukladaju do mysql. realtime aktualizacie bezia cez reverb. doplnkove python sluzby riesia astro vypocty (sky service) a moderaciu obsahu, preklady zabezpecuje libretranslate a ai generovanie textov bezi cez ollama.

## instalacia

1. skontroluj, ze mas pripravene `backend/.env` a `frontend/.env` (ak chybaju, vytvor ich z `.env.example`).
2. v koreni projektu spusti `docker compose up -d --build`.
3. po prvom starte stiahni ai model prikazom `docker compose exec ollama ollama pull mistral:latest`.
4. otvor aplikaciu na `http://127.0.0.1` a api na `http://127.0.0.1:8001`.
5. testovacie emaily najdes v mailpit ui na `http://127.0.0.1:8025` (smtp `mailpit:1025` v docker sieti).
6. databazu si mozes pozriet cez adminer na `http://127.0.0.1:8086` (server `mysql`, user `astro`, heslo `astro`, databaza `astrokomunita`).
7. zastavenie prostredia: `docker compose down`.

poznamka k bezpecnosti:
- compose porty su viazane len na `127.0.0.1`, aby sa dev stack omylom nevystavil do siete.
- interne sluzby `sky` a `moderation` nie su mapovane na host port; backend ich vola iba cez docker network.
- ak chces stack spustat mimo lokalneho developmentu, zmen vsetky defaultne hesla a tokeny (`astro`, `root`, `change-me`) este pred prvym startom.

## lokalne emaily (mailpit)

- docker service `mailpit` zachyti vsetky emaily z backendu a neposiela ich do realneho sveta
- backend kontajnery maju natvrdo nastavene `MAIL_MAILER=smtp`, `MAIL_HOST=mailpit`, `MAIL_PORT=1025`
- ked chces overit odosielanie, spusti akciu v appke (registracia, reset hesla...) a skontroluj inbox v `http://127.0.0.1:8025`

## technologie

- frontend: vue 3, vite, pinia, vue router, tailwind css
- backend: php 8.2, laravel 12, sanctum, reverb
- databaza a infra: mysql 8, docker compose, queue worker, scheduler, mailpit
- mikroservisy: python (sky service, moderation service), libretranslate, ollama
