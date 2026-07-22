# SimpleVoter – CLAUDE.md

Dieses Dokument beschreibt die Architektur, Konventionen und wichtigsten Entscheidungen des SimpleVoter-Projekts für Claude Code.

---

## Produkt-Übersicht

SimpleVoter ist ein minimales, loginfreies Umfrage-Tool. Jemand erstellt in Sekunden eine Umfrage, teilt einen Link/QR-Code, andere stimmen ab. Zusätzlich können Teilnehmer Fragen/Meinungen über eine Tweet-Wall-Funktion einreichen.

**Live:** https://simplevoter.com

---

## Tech-Stack

| Bereich | Technologie |
|---|---|
| Backend | Laravel 13 |
| Frontend-Bridge | Inertia.js v2 (PHP) + `@inertiajs/vue3` v1.x |
| Datenbank | MySQL (Produktion), SQLite (lokal) |
| Frontend | Vue 3 (Composition API, `<script setup>`) |
| Styling | Tailwind CSS v4 + `@tailwindcss/vite` |
| i18n | vue-i18n v9 (DE/EN/FR/ES/NL) |
| Build | Vite |
| Mail | Laravel Mailable + SMTP (OVH) |
| Dev-Server | `composer dev` (Port 8080) |

**Kritisch: Tailwind v4-Besonderheit**
`resources/css/app.css` wird in `@vite()` in `app.blade.php` **nicht** als separater Eintrag übergeben — Tailwind v4 bundelt CSS automatisch ins JS. Nur `resources/js/app.js` in `@vite()`.

**Kritisch: Inertia-Kompatibilität**
`@inertiajs/vue3` muss v1.x bleiben — v2/v3 ist inkompatibel mit dem Laravel-Package v2.0.24.

---

## Architektur-Prinzip: „Auth ohne Auth"

Kein Login-System, keine `users`-Tabelle. Sicherheit basiert auf Token-Länge/Entropie:

| Token | Länge | Wer bekommt ihn | Zweck |
|---|---|---|---|
| `manage_token` | 64 Zeichen | Nur der Ersteller (LocalStorage) | Voller Zugriff: bearbeiten, Einstellungen, Fragen einsehen |
| `public_token` | 12 Zeichen | Alle via Teilen-Link/QR | Nur Lesen + Abstimmen + Frage einreichen |
| `voter_token` | 32 Zeichen | Client-seitig generiert (LocalStorage) | Eindeutige Identifikation eines Abstimmenden |
| `author_token` | 32 Zeichen | Client-seitig generiert (LocalStorage) | Rate-Limiting beim Fragen einreichen |

Tokens werden im `booted()`-Hook (`static::creating`) automatisch generiert.

---

## Datenmodell

```
polls
  id, public_token(12), manage_token(64)
  question, description
  result_visibility: live | after_vote | hidden_until_closed
  question_name_mode: hidden | optional | required
  questions_enabled: boolean (default: true)
  allows_multiple_choice: boolean
  is_active: boolean
  creator_ip, last_activity_at
  timestamps

poll_options
  id, poll_id, label, sort_order
  timestamps

votes
  id, poll_option_id, voter_token(64)
  UNIQUE(poll_option_id, voter_token)
  timestamps

questions
  id, poll_id, content, author_name, author_token(64)
  timestamps
```

**Wichtige Constraints:**
- `votes`: `UNIQUE(poll_option_id, voter_token)` verhindert Doppel-Votes auf dieselbe Option
- Single-Choice: `VoteController` löscht vorherige Stimme desselben `voter_token` für diese Umfrage vor dem Insert
- `cascade_on_delete` auf allen Foreign Keys → Löschen einer Poll entfernt alles automatisch

---

## Routen-Übersicht

```
GET  /                              → Landing.vue (Poll erstellen)
GET  /datenschutz                   → Legal/Privacy.vue
GET  /impressum                     → Legal/Imprint.vue

POST /polls                         → PollController@store (throttle: 20/min)

# Owner-Bereich (manage_token via Route-Model-Binding)
GET    /p/{manage_token}/edit        → PollManageController@show
GET    /p/{manage_token}/edit/data   → PollManageController@data (axios, JSON)
PATCH  /p/{manage_token}/edit        → PollManageController@update (JSON response)
POST   /p/{manage_token}/edit/reset  → PollManageController@reset (JSON response)
POST   /p/{manage_token}/edit/email  → PollManageController@sendManageLink (throttle: 5/min)
POST   /p/{manage_token}/edit/options
PATCH  /p/{manage_token}/edit/options/{option}
DELETE /p/{manage_token}/edit/options/{option}
POST   /p/{manage_token}/edit/reorder
GET    /p/{manage_token}/edit/questions

# Public-Bereich (public_token via Route-Model-Binding)
GET  /w/{public_token}/             → PublicPollController@show
GET  /w/{public_token}/state        → PublicPollController@state (JSON, Live-Polling)
POST /w/{public_token}/vote         → VoteController@store (throttle: 30/min)
POST /w/{public_token}/questions    → QuestionController@store (throttle: 10/min)
GET  /w/{public_token}/questions    → QuestionController@indexForPublic (JSON)
```

**Wichtig:** Alle axios-aufgerufenen Endpunkte (PATCH, POST) geben **JSON** zurück, kein `redirect()->back()`. Das verursacht sonst Redirect-Loops bei Inertia/axios.

---

## Frontend-Struktur

```
resources/js/
  app.js                          # Inertia-Setup, i18n-Plugin registrieren
  bootstrap.js                    # axios + CSRF
  i18n/
    index.js                      # createI18n, Spracherkennung (navigator.language → localStorage)
    locales/
      de.json, en.json, fr.json, es.json, nl.json
  composables/
    useDeviceToken.js             # voter_token + author_token client-seitig generieren/lesen
  Components/
    Footer.vue                    # Props: powered-by (Boolean) – Public-Seite nutzt powered-by=true
    LanguageSwitcher.vue          # Flaggen-Dropdown, kein nativer <select>
  Pages/
    Landing.vue                   # Zero-Friction-Erstellung
    Poll/
      Manage.vue                  # Owner-Ansicht
      Public.vue                  # Teilnehmer-Ansicht
    Legal/
      Imprint.vue                 # JS-Schutz für E-Mail + Telefon via onMounted
      Privacy.vue                 # JS-Schutz für E-Mail via onMounted
```

---

## Branding & Design-Tokens (Tailwind v4, definiert in `app.css`)

```css
--color-sv-gray:        #9ea7ae  /* Mittelgrau */
--color-sv-gray-light:  #eef0f1  /* Hintergrund/Borders */
--color-sv-dark:        #2b2c30  /* Schwarz/Dunkelgrau (Primärfarbe) */
--color-sv-accent:      #bb3245  /* Rot (Akzent) */
--color-sv-accent-light:#f7e6e8  /* Hellrot (Badge-Hintergrund) */
--color-sv-bg:          #f6f5f4  /* App-Hintergrund */
--color-sv-surface:     #ffffff  /* Karten-Hintergrund */

--font-display: 'Space Grotesk'  /* Headlines, Fragen */
--font-body:    'Inter'          /* Fließtext */
--font-mono:    'IBM Plex Mono'  /* Zahlen (Stimmen-Counts) */
```

---

## i18n-Konventionen

- **5 Sprachen:** DE (vollständig), EN (vollständig), FR/ES/NL (vollständig)
- **Spracherkennung:** `navigator.language` → Fallback `en` → gespeichert in `localStorage('simplevoter_locale')`
- **Keine URL-Präfixe** (`/fr/...`) — Links bleiben sprachunabhängig und teilbar
- **Schlüssel-Gruppen:** `landing`, `manage`, `public`, `footer`, `legal`, `common`
- **Achtung:** `@` in Übersetzungsstrings löst vue-i18n "Linked Message"-Parser aus → E-Mail-Adressen niemals als i18n-Key speichern, stattdessen JS-seitig zusammensetzen
- **Nach jeder Änderung an Locale-Dateien** alle 5 Sprachen auf Key-Konsistenz prüfen:
  ```bash
  python3 -c "
  import json
  def flatten(d, p=''):
      s = set()
      for k,v in d.items():
          f = f'{p}.{k}' if p else k
          s |= flatten(v,f) if isinstance(v,dict) else {f}
      return s
  files = ['de','en','fr','es','nl']
  base = flatten(json.load(open('resources/js/i18n/locales/de.json')))
  for f in files[1:]:
      diff = base ^ flatten(json.load(open(f'resources/js/i18n/locales/{f}.json')))
      if diff: print(f, 'ABWEICHUNG:', diff)
      else: print(f, 'OK')
  "
  ```

---

## Wichtige Implementierungs-Details

### Live-Polling (Manage + Public)
- Manage-Seite pollt `/edit/data` alle 6s via `setInterval`
- Public-Seite pollt `/state` alle 6s via `setInterval`
- **Auf Manage-Seite:** Polling pausiert während Felder fokussiert sind (`isEditingField`), sonst überschreibt der Refresh ungespeicherte Eingaben
- Textarea-Felder (Frage + Beschreibung) wachsen automatisch mit dem Inhalt (`autoGrow`)

### Single-Choice vs. Multiple-Choice
- `allows_multiple_choice = false`: `VoteController` löscht alte Stimme des `voter_token` für diese Poll vor dem Insert ("Auswahl ändern ersetzt vorherige Stimme")
- `allows_multiple_choice = true`: DB-Constraint allein reicht aus
- Frontend rendert Radio-Buttons (Single) oder Checkboxen (Multiple) je nach `state.poll.allows_multiple_choice`

### Fragen/Tweet-Wall
- `questions_enabled` auf der `polls`-Tabelle (Default: `true`) steuert ob das Feature aktiv ist
- Serverseitig abgesichert: `QuestionController@store` gibt 403 zurück wenn deaktiviert
- Badge-Logik client-seitig via `localStorage('poll_{token}_last_seen_question_id')`
- Neu-Indikator: `state.latest_question_id > lastSeenId`

### Poll ohne Abstimmungsoptionen (reine Meinungsumfrage)
- `PollController@store` akzeptiert 0 Optionen (oder ≥ 2, nie genau 1)
- Public-Seite zeigt bei `state.options.length === 0` einen Hinweistext statt Abstimmungsblock

### Anti-Spam
- **Honeypot:** `<input name="website">` unsichtbar auf Landing, Public (Vote + Frage)
- **Rate-Limits:** Poll erstellen 20/min, Vote 30/min, Fragen 10/min, E-Mail 5/min
- **`creator_ip`** beim Poll-Erstellen gespeichert

### 90-Tage-Löschkonzept
- `polls.last_activity_at` wird bei jedem Vote/jeder Frage aktualisiert (`poll->touchActivity()`)
- Täglicher Scheduled Task in `routes/console.php` löscht inaktive Polls
- Cascade-Delete via Foreign Key entfernt `poll_options`, `votes`, `questions` automatisch

### PDF-Export
- `window.print()` mit dedizierten Print-CSS-Regeln
- Nur `#pdf-report`-Block wird beim Drucken angezeigt (alle anderen Elemente `visibility: hidden`)
- `print-color-adjust: exact` erzwingt Hintergrundfarben beim Drucken
- Enthält: Frage, Ergebnisbalken mit Prozenten, Fragen/Tweets, Export-Datum, Stimmenanzahl

### Manage-Token-Sicherheit
- `manage_token` wird im `PublicPollController` **niemals** ausgeliefert — alle Public-Responses bauen die JSON-Shape manuell statt das Model direkt zu serialisieren

### E-Mail (Verwaltungs-Link)
- `ManageLinkMail` (Laravel Mailable, Markdown-Template)
- SMTP via OVH (`smtp.mail.ovh.net:465`)
- `.env`: `MAIL_MAILER`, `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_ENCRYPTION`, `MAIL_FROM_ADDRESS`

---

## Lokale Entwicklung

```bash
# Abhängigkeiten
composer install
npm install

# Umgebung
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
# .env: DB_CONNECTION=sqlite

# Migrationen
php artisan migrate

# Starten (zwei Terminals)
composer dev        # Laravel auf Port 8080
npm run dev         # Vite Dev-Server
```

App läuft unter **http://localhost:8080**

---

## Deployment (Produktion)

```bash
bash deploy_simplevoter.sh
```

Das Skript erkennt Erstinstallation vs. Update automatisch. Wichtige `.env`-Werte für Produktion:

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://simplevoter.com
DB_CONNECTION=mysql
```

Nach Änderungen an Migrationen:
```bash
php artisan migrate --force
```

Nach Änderungen an Routen/Config/Views:
```bash
php artisan config:cache && php artisan route:cache && php artisan view:cache
```

---

## Assets & Bilder

```
public/
  favicon.ico, favicon-16x16.png, favicon-32x32.png
  apple-touch-icon.png
  android-chrome-192x192.png, android-chrome-512x512.png
  site.webmanifest                # theme_color: #2b2c30
  images/
    logo-simplevoter.png          # Für helle Hintergründe (transparent)
    logo-simplevoter-light.png    # Für dunkle Hintergründe (weiß/grau)
    og-image.png                  # 1200×630, Social-Media-Vorschau
```

**Wichtig:** Das Logo (`logo-simplevoter.png`) hat echte Transparenz. Die Checker-Hintergrund-Artefakte aus der Original-PNG-Datei wurden durch Pixel-Analyse entfernt. Falls das Logo ausgetauscht wird, muss die Transparenz neu korrekt eingestellt werden.

---

## Datei-Konventionen

- **Controller:** geben bei axios-Calls immer JSON zurück, nie `redirect()->back()`
- **Vue-Komponenten:** ausschließlich `<script setup>` (Composition API), kein Options API
- **Tailwind:** CSS-Variablen (`var(--color-sv-accent)`) statt hardcodierte Farbwerte, außer im PDF-Report-Block (dort `#bb3245` etc. direkt, weil CSS-Variablen beim Print nicht immer verfügbar)
- **i18n:** alle sichtbaren Texte über `t()`, keine hartcodierten deutschen Strings in Templates
- **Imports in Vue:** `ref`, `computed`, `onMounted` etc. immer explizit importieren (`import { ref } from 'vue'`)
