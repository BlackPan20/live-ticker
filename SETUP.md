# Live Ticker - Setup mit Netlify + Firebase

## Einleitung

Dieses Projekt verwendet jetzt:
- **Netlify Functions** als Backend
- **Firebase Realtime Database** für Datenspeicher
- **Automatische Synchronisation** zwischen allen Geräten (Echtzeit)

## Voraussetzungen

1. Netlify Account (kostenlos unter netlify.com)
2. Firebase Project (kostenlos unter firebase.google.com)

## Setup-Anweisungen

### 1. Firebase Project erstellen

1. Gehe zu [firebase.google.com](https://firebase.google.com)
2. Klicke auf "Konsole" oder "Get started"
3. Erstelle ein neues Projekt (z.B. "live-ticker")
4. Wähle "Realtime Database" und erstelle eine neue Datenbank
5. Starte im **Test Mode** (für Entwicklung)
6. Wechsele zu den Project Settings (Zahnrad-Icon oben links)
7. Gehe zum Tab "Servicekonten"
8. Klicke "Node.js" und dann "Neuen privaten Schlüssel generieren"
9. Speichere die JSON-Datei

### 2. Firebase Credentials auf Netlify konfigurieren

1. Öffne die JSON-Datei und kopiere folgende Werte:
   - `type`
   - `project_id`
   - `private_key_id`
   - `private_key` (mit `\n` Escape-Sequenzen)
   - `client_email`
   - `client_id`
   - `auth_uri`
   - `token_uri`
   - `auth_provider_x509_cert_url`
   - `client_x509_cert_url`

2. Gehe zu deinem Netlify Project → Site Settings → Environment
3. Füge folgende Umgebungsvariablen hinzu:
   - `FIREBASE_TYPE`: `service_account`
   - `FIREBASE_PROJECT_ID`: (aus JSON)
   - `FIREBASE_PRIVATE_KEY_ID`: (aus JSON)
   - `FIREBASE_PRIVATE_KEY`: (aus JSON - Wichtig: `\n` muss erhalten bleiben!)
   - `FIREBASE_CLIENT_EMAIL`: (aus JSON)
   - `FIREBASE_CLIENT_ID`: (aus JSON)
   - `FIREBASE_AUTH_URI`: `https://accounts.google.com/o/oauth2/auth`
   - `FIREBASE_TOKEN_URI`: `https://oauth2.googleapis.com/token`
   - `FIREBASE_AUTH_PROVIDER_CERT_URL`: `https://www.googleapis.com/oauth2/v1/certs`
   - `FIREBASE_CLIENT_CERT_URL`: (aus JSON)
   - `FIREBASE_DATABASE_URL`: (aus Firebase Console: https://YOURPROJECT.firebaseio.com)

### 3. Deploy

1. Pushe deine Änderungen zu GitHub
2. Netlify wird automatisch neu deployed
3. Teste die App auf mehreren Geräten

## Wie es funktioniert

- **Admin**: Meldet sich an und kann Daten bearbeiten
- **Zuschauer**: Können auf jedem Gerät die Live-Daten sehen (automatisches Refresh)
- **Offline**: Die App funktioniert auch offline mit localStorage, synchronisiert aber bei Verbindung

## Passwort ändern

Das Admin-Passwort ist derzeit: **OLE1234!?** (oder wie du es eingestellt hast)

Um es zu ändern:
1. Generiere einen neuen SHA-256 Hash: https://www.online-toolz.com/tools/hash-generator
2. Ersetze den Wert von `passwordHash` in `admin.js`

## Sicherheit

Für Production:
- Firebase Security Rules aktivieren (nicht im Test Mode)
- HTTPS verwenden (Netlify macht das automatisch)
- Passwort regelmäßig ändern
- Private Key sicher speichern
