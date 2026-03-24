# o aplikácii

astrokomunita je webová aplikácia pre fanúšikov astronómie. používatelia v nej sledujú astronomické udalosti, čítajú a pridávajú príspevky, ukladajú pozorovania a dostávajú upozornenia na zaujímavé eventy.

## ako funguje

frontend zobrazuje feed, eventy, profil a ďalšie časti aplikácie. backend poskytuje api, autentifikáciu a business logiku. dáta sa ukladajú do mysql. realtime aktualizácie bežia cez reverb. doplnkové python služby riešia astro výpočty (sky service) a moderáciu obsahu, preklady zabezpečuje libretranslate a ai generovanie textov beží cez ollama.

## inštalácia

1. skontroluj, že máš pripravené `backend/.env` a `frontend/.env` (ak chýbajú, vytvor ich z `.env.example`).
2. v koreni projektu spusti `docker compose up -d --build`.
3. volitelne: ak chces pouzivat ai funkcie cez ollama, po prvom starte stiahni model prikazom `docker compose exec ollama ollama pull mistral:latest`.
4. otvor aplikáciu na `http://127.0.0.1:5174` a api na `http://127.0.0.1:8001`.
5. testovacie emaily nájdeš v mailpit ui na `http://127.0.0.1:8025` (smtp `mailpit:1025` v docker sieti).
6. databázu si môžeš pozrieť cez adminer na `http://127.0.0.1:8086` (server `your_db_host`, user `your_db_user`, heslo `your_db_password`, databáza `your_db_name`).
7. zastavenie prostredia: `docker compose down`.

poznámka k bezpečnosti:

- compose porty sú viazané len na `127.0.0.1`, aby sa dev stack omylom nevystavil do siete.
- interné služby `sky` a `moderation` nie sú mapované na host port; backend ich volá iba cez docker network.
- ollama je pre vacsinu aplikacie volitelny; bez neho zostane funkcny bezny beh, ale ai funkcie napojene na ollama budu fallbackovat alebo hlasit chybu.
- ak chceš stack spúšťať mimo lokálneho developmentu, zmeň všetky predvolené heslá a tokeny (`astro`, `root`, `change-me`) ešte pred prvým štartom.

## lokálne emaily (mailpit)

- docker service `mailpit` zachytí všetky emaily z backendu a neposiela ich do reálneho sveta.
- backend kontajnery majú natvrdo nastavené `MAIL_MAILER=smtp`, `MAIL_HOST=mailpit`, `MAIL_PORT=1025`.
- keď chceš overiť odosielanie, spusti akciu v appke (registrácia, reset hesla...) a skontroluj inbox v `http://127.0.0.1:8025`.

## technológie

- frontend: vue 3, vite, pinia, vue router, tailwind css
- backend: php 8.2, laravel 12, sanctum, reverb
- databáza a infra: mysql 8, docker compose, queue worker, scheduler, mailpit
- mikroservisy: python (sky service, moderation service), libretranslate, ollama
