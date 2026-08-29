# Schnittstelle: Versetzungsplan an Moodle

Spezifikation für das Skript, das den Versetzungsplan wöchentlich an `local_berufsbildung` übermittelt.

---

## 1. Dateiformat

CSV, UTF-8, **ohne BOM**. Trennzeichen Semikolon. Zeilenende `\n` oder `\r\n`, beides wird akzeptiert. Erste Zeile ist die Kopfzeile mit den Spaltennamen.

Werte ohne Anführungszeichen, sofern sie kein Semikolon enthalten. Enthält ein Wert eines, wird er in doppelte Anführungszeichen gesetzt.

### Spalten

| Spalte | Pflicht | Format | Beschreibung |
|---|---|---|---|
| `email` | ja | Text | Geschäftsmailadresse der lernenden Person; muss der in Moodle hinterlegten entsprechen |
| `block` | ja | Text | Nummer oder Kürzel des Ausbildungsblocks, exakt wie in der Excel |
| `kw_von` | ja | `JJJJ-Wnn` | Erste Kalenderwoche des Einsatzes, ISO 8601 |
| `kw_bis` | ja | `JJJJ-Wnn` | Letzte Kalenderwoche, einschliesslich |
| `bemerkung` | nein | Text | Freitext, wird unverändert übernommen |

### Beispiel

```csv
email;block;kw_von;kw_bis;bemerkung
anna.muster@firma.ch;4;2027-W15;2027-W26;
anna.muster@firma.ch;7;2027-W27;2027-W33;
anna.muster@firma.ch;BFS;2027-W34;2027-W34;Blockwoche
beat.beispiel@firma.ch;4;2027-W15;2027-W20;
```

**Warum die Mailadresse als Kennung**: Sie wird von Entra ID nach Moodle synchronisiert. Ändert sie sich — etwa bei einer Namensänderung —, zieht Moodle automatisch nach, ohne dass jemand eine Zuordnungstabelle pflegen muss. Das Moodle-Feld `idnumber` scheidet aus, weil es bei uns mit dem Entra-Objektbezeichner belegt ist, der in der Excel nicht vorkommt.

### Alternative: eine Zeile je Woche

Falls es im Skript einfacher ist, jede Woche einzeln auszugeben, statt zusammenhängende Läufe zu erkennen:

```csv
email;block;kw
anna.muster@firma.ch;4;2027-W15
anna.muster@firma.ch;4;2027-W16
anna.muster@firma.ch;4;2027-W17
```

Moodle fasst aufeinanderfolgende Wochen mit demselben Block dann selbst zusammen. Die Kopfzeile entscheidet, welches der beiden Formate vorliegt — `kw` statt `kw_von`/`kw_bis`. Beide Formate in einer Datei zu mischen ist nicht zulässig.

---

## 2. Regeln für den Inhalt

**Vollständigkeit.** Jede Lieferung enthält den **gesamten** aktuellen Plan aller Lernenden, nicht nur Änderungen. Moodle ersetzt den Bestand vollständig. Teillieferungen würden dazu führen, dass in der Excel gelöschte Einsätze in Moodle stehen bleiben.

**Das Skript filtert nicht.** Es liefert alle Lernenden, unabhängig davon, wer im Plugin geführt wird. Welche Personen tatsächlich verarbeitet werden, entscheidet Moodle — siehe Abschnitt 2a. Damit muss das Skript beim Ausweiten des Teilnehmerkreises nicht angefasst werden.

**Kalenderwochen.** ISO 8601, also `2027-W03`, nicht `KW3` oder `3/2027`. Der Jahresbezug muss explizit sein — bei einem Plan über vier Lehrjahre ist er sonst nicht eindeutig. Führende Null bei einstelligen Wochen. Die Woche 53 existiert in manchen Jahren und wird akzeptiert.

**Zeiträume.** `kw_bis` liegt nicht vor `kw_von`. Zeiträume derselben Person dürfen sich nicht überschneiden — überschneidende Einsätze werden abgewiesen und protokolliert, nicht stillschweigend zusammengeführt.

**Lücken sind erlaubt.** Wochen ohne Einsatz werden schlicht nicht geliefert. Kein Platzhalter nötig.

**Nicht betriebliche Zeiten.** Berufsfachschule, überbetriebliche Kurse, Ferien, Militärdienst dürfen mitgeliefert werden — sie machen die Zeitachse vollständig. In Moodle werden die betreffenden Blöcke mit `ist_betrieb = 0` gekennzeichnet und zählen dann nicht zur Kompetenzabdeckung. Werden sie weggelassen, ist das ebenso in Ordnung.

**Unbekannte Blocknummern** brechen die Verarbeitung nicht ab. Moodle legt den Block ohne Kompetenzabdeckung an und meldet ihn im Protokoll — die Zeitachse stimmt dann, nur die Vorbelegung im Bildungsbericht fehlt für diesen Block.

**Unbekannte Mailadressen** brechen die Verarbeitung ebenfalls nicht ab. Die betreffenden Zeilen werden übersprungen und protokolliert. Bei zwanzig Lernenden soll nicht ein Tippfehler den ganzen Lauf verwerfen.

---

## 2a. Wer verarbeitet wird

Moodle verarbeitet eine Zeile nur, wenn die Person **eine aktive Zuordnung zu einer Berufsbildnerin oder einem Berufsbildner** hat. Alle übrigen Zeilen werden übersprungen.

Das ist zugleich der Steuerungsmechanismus für die Einführungsphase: Für einen Pilot mit zwei Lernenden werden zwei Zuordnungen angelegt; die übrigen achtzehn Zeilen der Lieferung laufen ins Leere. Beim Ausweiten kommen Zuordnungen dazu — am Skript ändert sich nichts.

Inhaltlich ist das auch richtig so: Wer keiner Berufsbildnerin und keinem Berufsbildner zugeordnet ist, wird vom Plugin nicht betreut, und dann braucht Moodle seinen Versetzungsplan nicht.

**Übersprungene Zeilen sind keine Fehler.** Sie werden separat gezählt und erscheinen nicht als Warnungen im Protokoll — sonst stünden dort bei zwei Pilot-Lernenden achtzehn Meldungen, und niemand liest es mehr.

---

## 3. Übermittlung

### Endpunkt

```
POST https://<moodle>/webservice/rest/server.php
```

Parameter:

| Parameter | Wert |
|---|---|
| `wstoken` | Token des Dienstkontos |
| `wsfunction` | `local_berufsbildung_import_versetzungsplan` |
| `moodlewsrestformat` | `json` |
| `csvdaten` | Inhalt der CSV-Datei, Base64-kodiert |
| `quelle` | Freitext zur Herkunft, etwa `versetzungsplan-skript` |
| `testlauf` | `1` = nur prüfen und Protokoll zurückgeben, nichts schreiben. `0` = übernehmen |

Base64 statt Rohtext, damit Zeilenumbrüche und Sonderzeichen die Formularkodierung nicht stören.

### Antwort

```json
{
  "status": "ok",
  "unveraendert": false,
  "zeilen_gelesen": 87,
  "personen_verarbeitet": 2,
  "zeilen_ausserhalb_geltungsbereich": 79,
  "einsaetze_erzeugt": 11,
  "protokoll": [
    "Zeile 34: Mailadresse alt.name@firma.ch keinem Moodle-Konto zugeordnet – übersprungen",
    "Zeile 51: Block 'X9' in Moodle unbekannt – ohne Kompetenzabdeckung angelegt"
  ]
}
```

`status` ist `ok`, `mit_warnungen` oder `fehlgeschlagen`. Bei `fehlgeschlagen` wurde nichts geschrieben.

`unveraendert` ist `true`, wenn die Daten dem letzten erfolgreichen Import entsprechen — dann passiert nichts. Das ist bei wöchentlicher Lieferung der Normalfall, weil der Plan sich nur einige Male im Jahr ändert.

### Schutz vor unvollständigen Lieferungen

Enthält eine Lieferung **deutlich weniger verarbeitete Personen** als die vorherige — Standardschwelle 20 Prozent —, wird sie **abgewiesen** und gemeldet, statt verarbeitet.

Gezählt wird der verarbeitete Bestand, nicht die Zeilenzahl der Lieferung. Sonst würde die Schwelle beim Ausweiten des Teilnehmerkreises in die falsche Richtung anschlagen. Das fängt den Fall ab, dass das Skript wegen eines Fehlers eine halbe oder leere Datei erzeugt und Moodle daraufhin sämtliche Einsätze löscht.

Ist der Rückgang beabsichtigt, etwa nach einem Lehrabschluss-Jahrgang, wird der Parameter `rueckgang_bestaetigt=1` mitgeliefert.

---

## 4. Einrichtung in Moodle

1. Webservices aktivieren, Protokoll REST einschalten
2. Dienstkonto anlegen — ein eigener Nutzer, nicht ein persönliches Konto. Bricht sonst beim nächsten Personalwechsel.
3. Rolle mit `local/berufsbildung:importplan` im Systemkontext, dem Dienstkonto zuweisen
4. Externen Dienst anlegen, die Funktion `local_berufsbildung_import_versetzungsplan` hinzufügen, Dienstkonto berechtigen
5. Token erzeugen
6. IP-Einschränkung setzen, falls das Skript von einer festen Adresse läuft

Das Token gehört auf der Skript-Seite in eine Umgebungsvariable oder einen Anmeldeinformationsspeicher, nicht in den Quelltext.

---

## 5. Empfehlungen für das Skript

**Erst mit `testlauf=1`.** Beim Einrichten und nach jeder Änderung an der Excel-Struktur zuerst den Testlauf, Protokoll ansehen, dann übernehmen.

**Fehler müssen laut sein.** Ein nicht erreichbarer Endpunkt, ein abgelaufenes Token oder `status: fehlgeschlagen` gehören in eine Meldung an die zuständige Person. Ein Skript, das monatelang stillschweigend nichts tut, ist schlimmer als eines, das laut scheitert.

**HTTP-Status prüfen, nicht nur den Antworttext.** Moodle antwortet auf Webservice-Fehler mitunter mit Status 200 und einem `exception`-Feld im JSON.

**Zeitpunkt.** Wenn möglich ausserhalb der Arbeitszeit, damit die Excel nicht gerade in Bearbeitung ist. Kritisch ist es nicht — gelesen wird der zuletzt gespeicherte Stand.

---

## 6. Noch zu klären

1. **Mailadresse in der Excel**: Steht sie dort bereits, oder muss das Skript sie aus einer anderen Kennung auflösen?
2. **Blockbezeichnungen**: Liste aller vorkommenden Werte in der Excel — inklusive der nicht betrieblichen wie Schule oder üK. Sie werden in Moodle einmal angelegt und mit Handlungskompetenzen verknüpft.
3. **Umfang**: Enthält der Plan nur laufende Lehrverhältnisse oder auch abgeschlossene? Beeinflusst die Schwelle zur Erkennung unvollständiger Lieferungen.
