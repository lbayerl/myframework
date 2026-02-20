# Datenschutzerklärung Kohlkopf

**Stand:** 18. Februar 2026

## 1. Verantwortlicher

[Hier deine Kontaktdaten einfügen]  
E-Mail: [deine@email.de]

## 2. Grundsätze

Kohlkopf ist eine private Anwendung zur Verwaltung von Konzerten und Tickets im Freundeskreis. Wir speichern nur die Daten, die für die Funktion der App notwendig sind, und geben sie nicht an Dritte weiter.

## 3. Verarbeitete Daten

### 3.1 Nutzerkonto (Rechtsgrundlage: Art. 6 Abs. 1 lit. b DSGVO)

Bei der Registrierung speichern wir:

- **E-Mail-Adresse** (für Login und Kommunikation)
- **Anzeigename** (optional, anstelle der E-Mail-Anzeige)
- **Passwort** (verschlüsselt mit bcrypt, nicht lesbar)
- **Verifizierungsstatus** (ob E-Mail bestätigt wurde)

### 3.2 Konzert- und Ticketdaten (Rechtsgrundlage: Art. 6 Abs. 1 lit. b DSGVO)

Für die Kernfunktion der App speichern wir:

- **Konzerte:** Künstler, Ort, Datum, Veranstalterinformationen
- **Tickets:** Zuordnung zu Nutzern (Besitzer, Käufer, Ersteller), Preise, Sitzplätze
- **Zahlungen:** Wer hat wem wann wieviel gezahlt (zur Abwicklung gemeinsamer Ticketkäufe)
- **Teilnahmestatus:** Wer ist an welchem Konzert interessiert

**Wichtig:** Diese Daten werden nur innerhalb deiner privaten Nutzergruppe geteilt und sind für andere nicht sichtbar.

### 3.3 E-Mail-Versand (Rechtsgrundlage: Art. 6 Abs. 1 lit. b DSGVO)

Wir versenden E-Mails für:

- **E-Mail-Verifizierung** bei Registrierung
- **Passwort-Zurücksetzen** auf Anfrage

Die E-Mails werden über einen SMTP-Dienstleister versendet. Temporäre Tokens werden nach Verwendung oder nach 24 Stunden gelöscht.

### 3.4 Push-Benachrichtigungen (Rechtsgrundlage: Art. 6 Abs. 1 lit. a DSGVO)

Wenn du Push-Benachrichtigungen aktivierst, speichern wir:

- **Browser-Endpunkt** (technische Adresse für Benachrichtigungen)
- **Verschlüsselungsschlüssel** (für sichere Übertragung)

Du kannst Push-Benachrichtigungen jederzeit unter `/notifications` deaktivieren.

### 3.5 Technische Daten (Rechtsgrundlage: Art. 6 Abs. 1 lit. f DSGVO)

Für Sicherheit und Fehlerdiagnose speichern wir temporär:

- **Server-Logs:** IP-Adresse, Zugriffszeit, aufgerufene Seiten (automatische Löschung nach 30 Tagen)
- **Session-Daten:** Verschlüsselte Login-Session (gelöscht nach Logout oder 24h Inaktivität)

## 4. Weitergabe von Daten

**Wir geben keine Daten an Dritte weiter.** Die App läuft auf einem privaten Server und nutzt keine externen Analytics-, Tracking- oder Werbedienste.

Ausnahmen:

- **SMTP-Dienstleister** (nur für E-Mail-Versand, siehe 3.3)
- **Gesetzliche Verpflichtung** (z.B. bei richterlichem Beschluss)

## 5. Speicherdauer

- **Nutzerkonto:** Bis zur Löschung durch dich oder Inaktivität >2 Jahre
- **Konzert-/Ticketdaten:** Bis zur manuellen Löschung oder Löschung des Nutzerkontos
- **E-Mail-Tokens:** 24 Stunden oder nach Verwendung
- **Push-Subscriptions:** Bis zur Deaktivierung oder Löschung des Kontos
- **Server-Logs:** 30 Tage

## 6. Deine Rechte (Art. 15-21 DSGVO)

Du hast folgende Rechte:

- **Auskunft** (Art. 15): Welche Daten wir über dich speichern
- **Berichtigung** (Art. 16): Korrektur falscher Daten
- **Löschung** (Art. 17): Löschung deines Kontos und aller Daten
- **Datenübertragbarkeit** (Art. 20): Export deiner Daten in maschinenlesbarem Format
- **Widerspruch** (Art. 21): Widerspruch gegen Datenverarbeitung

Kontaktiere uns unter [deine@email.de] für Anfragen.

## 7. Sicherheit

Deine Daten werden geschützt durch:

- **HTTPS-Verschlüsselung** (TLS) für alle Verbindungen
- **Passwort-Hashing** mit bcrypt (Passwörter sind nicht lesbar)
- **CSRF-Schutz** gegen Angriffe
- **Regelmäßige Updates** der Software

## 8. Cookies und Local Storage

Die App nutzt:

- **Session-Cookie** (technisch notwendig für Login, gelöscht nach Logout)
- **Local Storage** (für Service Worker/PWA-Funktionen, keine personenbezogenen Daten)

Es gibt **keine Tracking-Cookies** oder Drittanbieter-Cookies.

## 9. Änderungen

Diese Datenschutzerklärung kann bei Änderungen der App aktualisiert werden. Der aktuelle Stand ist oben angegeben.

## 10. Kontakt & Beschwerderecht

**Fragen zum Datenschutz:**  
[deine@email.de]

**Beschwerderecht:**  
Du hast das Recht, dich bei einer Datenschutz-Aufsichtsbehörde zu beschweren (z.B. Landesdatenschutzbeauftragter deines Bundeslandes).

---

**Hinweis:** Diese App wird privat betrieben. Da sie nicht kommerziell genutzt wird und nur einem kleinen, geschlossenen Nutzerkreis zur Verfügung steht, gelten ggf. Erleichterungen nach Art. 2 Abs. 2 lit. c DSGVO (Haushaltsausnahme). Trotzdem halten wir uns an die DSGVO-Grundsätze.
