# mailForwarder

Önálló Laravel 13 + React alkalmazás: bejövő leveleket ellenőriz, és a szabályoknak megfelelőket a saját postafiók nevében továbbítja. A Gmail-fiókok száma nincs a kódba írva.

## Stack

Laravel 13, PHP 8.4, React, Vite, Tailwind CSS, MySQL 8, Redis, Nginx, Mailpit. A postafiók-kapcsolat IMAP/SMTP, cserélhető felület (`MailboxClient`) mögött van. Gmail API nincs implementálva.

Fejlesztésben minden kimenő levél a Mailpitre megy. Éles SMTP csak `FORWARD_LIVE_SMTP=true` mellett indul.

## Indítás

A host portok a többi helyi projekttől külön vannak (web `28090`, Mailpit felület `28029`, SMTP `21031`, MySQL `23307`). A Redis portja nincs kinyitva a host felé. HeidiSQL: `127.0.0.1:23307`, adatbázis/felhasználó `mailforwarder`.

```bash
cp .env.example .env
cp backend/.env.example backend/.env
docker compose up -d --build
docker compose exec app php artisan migrate
docker compose exec app php artisan admin:create admin@example.com
```

- Alkalmazás: http://localhost:28090
- API: http://localhost:28090/api
- Mailpit: http://localhost:28029

Az admin parancs, ha nem adsz meg `--password` értéket, egyszer kiír egy jelszót. Ezt nem naplózza.

## Ellenőrzés

```bash
docker compose ps
docker compose exec app php artisan schedule:list
docker compose exec app php artisan test
docker compose logs queue scheduler
```

A `schedule:list` a `mail:process-accounts` parancsot tízpercenként, `Europe/Budapest` időzónával mutatja. Üres időablak mellett ez a teljes nap. A kezdő és záró idő az admin felületen vagy a `MAIL_CHECK_START` / `MAIL_CHECK_END` környezeti változóban adható meg.

Leállítás volume törlés nélkül: `docker compose down`.

## Régi fiókok importja

A `DatabaseSeeder` nem indítja az importot. Külön, migráció után:

```bash
docker compose exec app php artisan db:seed --class=GmailAccountsSeeder
docker compose exec app php artisan db:seed --class=UsersSeeder
```

Előbb a `backend/.env`-ben:

- `LEGACY_DB_HOST`, `LEGACY_DB_PORT`, `LEGACY_DB_DATABASE`, `LEGACY_DB_USERNAME`, `LEGACY_DB_PASSWORD` — a `gmail_eval` adatbázis **csak olvasásra** jogosult felhasználója
- `LEGACY_APP_KEY` — a gmail-evaluator `APP_KEY` értéke

A seeder a régi kulccsal fejti vissza a postafiók jelszavát, és az új alkalmazás kulcsával titkosítja újra. A két kulcs nem cserélhető fel. A `users` tábla jelszava bcrypt hash, ezt a `UsersSeeder` másolja; `LEGACY_APP_KEY` ahhoz nem kell. Az importált felhasználók adminok. A forrásadatbázist nem módosítja (`SET SESSION TRANSACTION READ ONLY`). Hiányzó kapcsolatnál érthető hibát ad, titok nélkül.

Importszabály: a régi `status` kapcsolati állapot marad, nem jelent kikapcsolást. Új fiók csak sikeres jelszó-visszafejtés után engedélyezett. Ha a kulcs hiányzik vagy hibás, a nyilvános mezők bekerülnek, a fiók `credentials_required` állapotú és tiltott, automatikus IMAP nincs. Ismételt futás e-mail alapján idempotens, és a meglévő működő jelszót nem írja felül üres vagy hibás forrással.

Sikeres import után a `LEGACY_DB_*` és `LEGACY_APP_KEY` értékeket el lehet távolítani.

Ha a régi adatbázis külön Compose projektben fut, a hostját a mailforwarder hálózatról elérhető címmel add meg. A régi volume-ot nem csatoljuk, és a gmail-evaluator Compose fájlját nem módosítjuk.

**A postafiókimport ebben a környezetben lefutott (4 fiók).** A `users` import a `UsersSeeder` külön futtatása.

## Levelek tárolása

A levéltörzs és a méretkorlát alatti melléklet az alkalmazás privát tárolójába kerül (`storage/app/private`), nem nyilvános URL-re. Alapértelmezett megőrzés 90 nap (`MESSAGE_RETENTION_DAYS`): a `mail:prune-messages` parancs kiüríti a törzset és törli a mellékletfájlt, a kézbesítési napló megmarad. A felületen a HTML csak szűrt formában jelenik meg. A számlalinket a program nem nyitja meg.

Mellékletméret: `FORWARD_MAX_ATTACHMENT_BYTES` (alapból 10 MB). A nagyobb melléklet nem csatolódik, az oka a naplóban látszik.

## Kézbesítés

A feladó a hitelesített saját postafiók, az eredeti feladó nem kerül a `From` mezőbe. A tárgy `Fwd: …`. SMTP időtúllépés után az állapot `delivery_unknown`, automatikus újraküldés nincs; az admin a naplóból indíthat tudatos újraküldést. Más hiba a queue retry szabályát követi (3 próba, növekvő várakozás). A `retry_after` (120 s) hosszabb a worker timeoutjánál (90 s).

## Tesztek

```bash
docker compose exec app php artisan test
```

A fejlesztői gépen, PHP nélkül, a tesztkép futtatta: 23 teszt, mind sikeres. Lefedi a feladó és Számlázz.hu-link szabályokat, az idegen domaint, az üres és inaktív elemeket, a több szabály / egy címzett esetet, az 50-nél több levelet és a kimaradt futást, az egyedi UID-t és a zárolást, a queue újrapróbálást, a bizonytalan kézbesítést, a multipart levelet, a küldés nélküli szabálytesztet, valamint a seeder újrafuttatását és a hibás kulcsot.

## Éles kép

```bash
docker compose -f compose.prod.yaml up -d --build
```

Nincs Vite dev szerver, nincs bind mount a teljes forrásra, és nincs Mailpit. Éles küldéshez a `FORWARD_LIVE_SMTP=true` mellett a fiókok saját SMTP beállítása kell. Amíg ez hamis, a capture DSN akkor is a Mailpit hostot keresné, ezért éles küldés előtt a capture helyett a fiók SMTP-je és a kapcsoló együtt állítandó.

## Adaptált részek a gmail-evaluatorból

- `GmailAccount` IMAP/SMTP mezői, titkosított jelszó, `authUsername`, `imapSelectCommand`, `smtpDsn`
- IMAP kapcsolódás és kapcsolatteszt mintája, UID lapozással a `SEARCH ALL` + utolsó 50 helyett
- Symfony Mailer SMTP küldés, HTML törzzsel, melléklettel és bizonytalan kézbesítéssel kiegészítve

Nem került át: levélértékelés, összefoglalás, kategorizálás, automatikus válasz.

## Felület

A stack szerint külön React frontend készült, Tailwindben. A színek és a betű a menteshetes / gmail-evaluator arculatból jönnek: krém háttér `#fdfaf3`, papír kártya `#f8f1e4`, zöld `#4a7c3f` és `#2d4a2b`, Figtree betű. Felhasznált skillek: `menteshetes-devops`, `menteshetes-project-structure`, `menteshetes-design`, `gmail-evaluator-api-structure`, `gmail-evaluator-design`.

A React kliens a postafiók jelszavát és a régi titkosítási kulcsot nem kapja meg. A belépéshez Sanctum token kell, ez csak az admin munkamenethez tartozik.
