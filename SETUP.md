# Live Ticker - Firebase Setup Anleitung

## Überblick

Die App speichert alle Daten auf **Firebase Realtime Database**, das bedeutet:
- Alle Geräte sehen die gleichen Daten in Echtzeit
- Admin kann Daten bearbeiten
- Zuschauer sehen Live-Updates alle 2,5 Sekunden
- Funktioniert auch offline (Daten werden dann später synchronisiert)

---

## 🚀 Schritt-für-Schritt Setup

### Schritt 1: Firebase Project erstellen

1. Gehe zu https://firebase.google.com
2. Klicke **"Go to console"** (oben rechts)
3. Klicke **"Add project"**
   - Name: `live-ticker` (oder beliebig)
   - Akzeptiere die Bedingungen
   - Klicke **Create project**
4. Warte bis das Projekt erstellt ist

### Schritt 2: Realtime Database einrichten

1. Im Firebase-Dashboard: Klick auf **"Realtime Database"** (links in der Navigation)
2. Klicke **"Create Database"**
   - Region: `europe-west1` (Europäisch/schneller) oder `us-central1`
   - **Wichtig:** Wähle **"Start in test mode"** (für Entwicklung)
   - Klicke **Enable**
3. Notiere die **Database URL** oben in der Ansicht (sieht so aus):
   ```
   https://YOUR-PROJECT-NAME.firebaseio.com
   ```

### Schritt 3: Service Account Keys generieren

1. Im Firebase-Dashboard oben links: Klicke auf das **Zahnrad-Icon** → **Project Settings**
2. Gehe zum Tab **"Service Accounts"**
3. Klicke **"Node.js"** (links)
4. Klicke **"Generate new private key"**
5. Eine JSON-Datei wird heruntergeladen: speichere sie sicher (z.B. `firebase-key.json`)

### Schritt 4: Netlify Environment-Variablen konfigurieren

Öffne die heruntergeladene `firebase-key.json` Datei und kopiere diese Werte:

**A) Gehe zu Netlify Dashboard**
- https://app.netlify.com
- Wähle dein Projekt (`live-ticker`)
- Klicke **Site settings** → **Environment** (links)
- Klicke **Add a variable**

**B) Füge diese Variablen ein:**

| Variable Name | Wert aus `firebase-key.json` |
|---|---|
| `FIREBASE_TYPE` | `service_account` |
| `FIREBASE_PROJECT_ID` | `project_id` |
| `FIREBASE_PRIVATE_KEY_ID` | `private_key_id` |
| `FIREBASE_PRIVATE_KEY` | `private_key` (⚠️ WICHTIG: siehe unten!) |
| `FIREBASE_CLIENT_EMAIL` | `client_email` |
| `FIREBASE_CLIENT_ID` | `client_id` |
| `FIREBASE_AUTH_URI` | `https://accounts.google.com/o/oauth2/auth` |
| `FIREBASE_TOKEN_URI` | `https://oauth2.googleapis.com/token` |
| `FIREBASE_AUTH_PROVIDER_CERT_URL` | `https://www.googleapis.com/oauth2/v1/certs` |
| `FIREBASE_CLIENT_CERT_URL` | `client_x509_cert_url` |
| `FIREBASE_DATABASE_URL` | Beispiel: `https://live-ticker-abc123.firebaseio.com` |

#### ⚠️ WICHTIG: Private Key korrekt einfügen

Die `private_key` in der JSON-Datei sieht so aus:
```json
"private_key": "-----BEGIN PRIVATE KEY-----\nMIIEvQIBADANBgkqhkiG9w0BAQEFAASCBKcwggSjAgEAAoIBAQC...\n-----END PRIVATE KEY-----\n"
```

Kopiere den **kompletten String** mit den `\n`:
```
-----BEGIN PRIVATE KEY-----\nMIIEvQIBADANBgkqhkiG9w0BAQE...\n-----END PRIVATE KEY-----\n
```

Netlify ersetzt die `\n` automatisch, das ist richtig!

### Schritt 5: Redeploy auf Netlify

Nach dem Konfigurieren der Umgebungsvariablen:

1. Im Netlify Dashboard: **Deploys**
2. Klicke **"Trigger deploy"** → **"Deploy site"**
3. Warte bis Status **"Published"** ist

---

## ✅ Test durchführen

1. Öffne die App im Browser: https://dein-projekt.netlify.app/neu
2. Öffne die **Browser-Konsole** (F12 → Console)
3. Tippe folgendes ein:
   ```javascript
   fetch('/.netlify/functions/data').then(r => r.json()).then(d => console.log('✓ Firebase connected!', d))
   ```
4. Wenn du die Daten siehst → **Firebase funktioniert! 🎉**

---

## 🔄 Mehrere Geräte testen

1. Öffne die App auf **Gerät A** (Computer)
2. Öffne die App auf **Gerät B** (Handy)
3. Bearbeite Daten auf Gerät A
4. Auf Gerät B sollten die Änderungen nach max. 2,5 Sekunden erscheinen

---

## 🔐 Sicherheit

**Für Production (nicht für Demo!):**

1. Firebase Security Rules aktivieren:
   ```json
   {
     "rules": {
       "tournament": {
         ".read": true,
         ".write": "auth.uid !== null"
       }
     }
   }
   ```
2. Private Key NICHT im Code speichern (nur Umgebungsvariablen!)
3. HTTPS verwenden (Netlify macht das automatisch)
4. Admin-Passwort regelmäßig ändern

---

## ❌ Häufige Fehler

| Fehler | Lösung |
|---|---|
| `403 Forbidden` | Firebase Security Rules prüfen (derzeit Test Mode - OK) |
| `Cannot find module 'firebase-admin'` | npm install firebase-admin in `netlify/functions/` |
| `Connection refused` | Umgebungsvariablen nicht gespeichert / redeploy nötig |
| Daten nur lokal | API funktioniert nicht → Console prüfen (F12) |

---

## 📞 Support

Wenn etwas nicht funktioniert:

1. Öffne Browser-Konsole (F12)
2. Führe aus: `fetch('/.netlify/functions/data').then(r => console.log(r.status, r.statusText))`
3. Schau in Netlify: **Functions** → Logs
