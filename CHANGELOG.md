# Changelog

## [0.5.2] — 2026-09-22

### Hinzugefügt

- **`api::get_aktive_lernende()`**. Es gab bisher keinen Weg, alle aktiven Lernenden aufzuzählen, ohne die Zuordnungstabelle direkt zu lesen — `get_lernende_for()` braucht eine `berufsbildnerid` als Eingabe. Gebraucht von `local_lerndokumentation`, dessen Erinnerungs-Task über alle aktiven Lernenden iterieren muss, auch über solche ohne eigene Periode. Gleiche Stichtagsbedingung wie `is_zustaendig()`. Optionaler Parameter `$ohneuekextern` schliesst üK-externe Personen direkt aus, für aufrufende Plugins, die sie gar nicht erst sehen sollen.

## [0.5.1] — 2026-09-21

### Hinzugefügt

- **Die Suche in der Kompetenzauswahl filtert beim Tippen** (`js/kompetenz_suche.js`). Ergänzung, keine Voraussetzung: ohne JavaScript bleibt das Formular der GET-Filter aus 0.5.0, den `kompetenz_auswahl::filtere()` auswertet — dieselben Regeln, dasselbe Ergebnis. Mit Skript entfällt der Seitenaufbau, und **angekreuzte Kompetenzen bleiben über mehrere Suchen hinweg stehen**; beim Serverfilter gehen sie mit jedem Neuladen verloren. Die Schaltfläche „Suchen" blendet sich aus, sobald das Skript läuft.

  Gesucht wird in `data-suchtext`, nicht im sichtbaren Text: dort ist die Beschreibung auf 120 Zeichen gekürzt. Das Attribut trägt denselben Text, den auch der Serverfilter durchsucht — beide können damit nicht auseinanderlaufen.

  Bewusst eine einfache Datei über `$PAGE->requires->js()` statt eines AMD-Moduls: das Plugin hat keine Build-Kette, ein AMD-Modul bräuchte neben `amd/src` auch ein `amd/build/*.min.js`, und zwei Kopien hält hier nichts synchron. Die Plugin-Version hängt als Parameter an der URL, weil `requires->js()` anders als AMD von sich aus kein Cache-Busting mitbringt.

### Geändert

- `blocklk:suche_keine_treffer` nennt den Suchbegriff nicht mehr. Die Live-Suche blendet dieselbe Meldung ohne Neuladen ein und würde sonst den Begriff des letzten Seitenaufbaus nennen.

## [0.5.0] — 2026-09-21

### Hinzugefügt

- **Suche in der Kompetenzauswahl** (`kompetenz_auswahl::filtere()`). Ein Rahmen hat über 250 Leistungskriterien; wer den Code eines bestimmten kennt, sollte ihn nicht über vier Bereiche und zwei Dutzend Handlungskompetenzen zusammensuchen müssen. Gesucht wird in Bezeichnung, ID-Nummer und Beschreibung, unabhängig von Gross-/Kleinschreibung.

  Der Filter gibt die Gliederung nicht auf: Bereich und Handlungskompetenz bleiben stehen, damit ein Treffer weiterhin seinen Platz im Bildungsplan zeigt. Trifft ein Bereich oder eine Handlungskompetenz selbst, gehört alles darunter dazu — wer „Instandhalten" sucht, meint den ganzen Bereich. Trifft nur ein Leistungskriterium, bleibt von seiner Handlungskompetenz genau dieses übrig, und die Aufklapper öffnen sich, weil ein Treffer hinter einer zugeklappten Zeile das Gegenteil dessen wäre, wofür man sucht.

  Serverseitig als GET-Filter, ohne JavaScript — dasselbe Muster wie die Suche in „Meine Lernenden". Der Filter überlebt das Speichern, damit man nach dem Zuordnen dort weiterarbeitet, wo man war.

## [0.4.16] — 2026-09-20

- **Externe üK-Teilnehmende.** Die Teilnahmeart gehört pro Person zum
  Berufsbildungsplugin, nicht zur gemeinsamen Kohorte. Externe Personen
  erscheinen weiterhin bei ihren Berufsbildner/innen und mit ihren
  üK-Nachweisen, führen aber keine Lerndokumentation und sehen keinen
  Einstieg „Meine Lehre“.
- Externe üK-Teilnehmende stehen im Roster nach allen regulären Lernenden.
- CI: Neue Klassen und API-Methoden erfüllen die Moodle-Code-Checker-Regeln.
- Der Umschalter der Teilnahmeart ist ein kompakter Button ohne zusätzlichen
  Rahmen; er bleibt eine sesskey-geschützte POST-Aktion.
- **Steuerung auf „Meine Lernenden“ gegliedert:** Blockverwaltung, dann
  Filter und die Zusammenfassung direkt darunter. Der Seitenkopf zeigt
  keinen Avatar der betreuenden Person; die Avatare der Lernenden bleiben
  Teil ihrer jeweiligen Zeile.

- **Fälligkeiten sind in den Arbeitskontext integriert.** Sie stehen nicht
  mehr als dritter Einstieg in der primären Navigation: Lernende sehen ihre
  eigenen Fristen in „Meine Lehre“, Berufsbildner/innen im Detail der
  jeweiligen Person. Überfällige Aufgaben sind bereits im geschlossenen
  Header sichtbar. Die bisherige, losgelöste Seite wurde entfernt.

Alle nennenswerten Änderungen an diesem Plugin werden hier festgehalten. Format angelehnt an [Keep a Changelog](https://keepachangelog.com/de/1.0.0/).

## [0.4.7] — 2026-09-17

### Hinzugefügt

- **Der laufende Ausbildungsblock steht in der Roster-Zeile.** Wo jemand gerade ist, war bisher nur nach dem Aufklappen und Scrollen zu sehen — beim Blick auf den Roster ist es die erste Frage. Der Block steht als eigene Spalte fester Breite, damit er über alle Zeilen hinweg bündig ist und sich vergleichen lässt; lange Bezeichnungen werden abgeschnitten und stehen vollständig im Titel. Läuft zum Stichtag kein Einsatz, bleibt die Spalte leer, behält aber ihre Breite. Blocknamen werden je Block einmal geladen, nicht je Person — im Roster stehen typischerweise mehrere Lernende im selben üK.
- **Unter der Erfassen-Schaltfläche in „Meine Lehre" steht ein Hinweis der jeweiligen Quelle.** Die Schaltfläche sagte, dass sich etwas erfassen lässt, aber nicht, bis wann — diese Angabe stand nur auf der Seite des liefernden Plugins, die eine lernende Person nicht zwingend aufruft. Eine Quelle kann dafür jetzt zusätzlich `nachweis\quelle_mit_hinweis` implementieren und einen kurzen Satz beisteuern (z. B. „Nächster Eintrag fällig bis 1. Oktober 2026"), der unter ihrer eigenen Schaltfläche steht — bei mehreren Quellen wäre sonst nicht erkennbar, welche gemeint ist. Eigene Schnittstelle statt einer weiteren Methode in `erfassbare_quelle`: eine Quelle kann eine Erfassung anbieten, ohne dazu eine Frist zu kennen, und eine Quelle, die nur die ältere Fassung dieses Plugins kennt, bleibt lauffähig. Formuliert wird der Hinweis von der Quelle; dieses Plugin gibt ihn unverändert aus, hebt ihn nicht hervor und deutet ihn nicht — eine überschrittene Frist muss die Quelle deshalb ausschreiben, statt sich auf eine Farbe zu verlassen.
- **Profilbilder in „Meine Lernenden".** Bisher stand dort ausschliesslich ein selbst gezeichneter Initialen-Kreis; `user_picture` kam in der ganzen Codebasis nicht vor. Wer ein Bild hinterlegt hat, erscheint jetzt damit. Wer keines hat, behält die Initialen: Moodles Ersatzbild ist für alle dieselbe graue Silhouette und unterscheidet damit niemanden. Das Bild kostet keine zusätzliche Abfrage — der Nutzerdatensatz wird für den Namen ohnehin vollständig geladen. Ohne Verlinkung, weil ein Link im `<summary>` bei jedem Klick zugleich auf- und zuklappen würde; der Weg ins Profil steht im Detailbereich. Für Screenreader ausgeblendet, weil der Name unmittelbar daneben steht.

## [0.4.6] — 2026-09-17

### Geändert

- **„Meine Lernenden" zeigte jede Person in einer 300-px-Kachelspalte.** Darin ist kein Kompetenzraster darstellbar: die Bereichsnamen brachen zeichenweise um („Instandhalt / en von / automatisie / rten Anlagen"), die Kürzel liefen ohne Abstand ineinander (`a.01a.02a.03`), und die aufgeklappte Kachel wurde über tausend Pixel hoch, während daneben die halbe Seite leer blieb. Jede Person ist jetzt eine Zeile über die volle Breite. Ausgeklappt steht damit dasselbe Kompetenzraster zur Verfügung wie auf „Meine Lehre" — mit Bezeichnungen statt nur Kürzeln.
- Die Lückenliste bleibt hier neben dem Raster, anders als auf „Meine Lehre". Die Fragen sind verschieden: eine berechtigte Person plant („was muss ich noch einplanen" — dafür ist die kurze Aufzählung richtig), die lernende Person schaut nach, wo sie steht. Die Bereiche der Liste stehen bei voller Breite nebeneinander statt in einer Spalte untereinander.
- **Die Filterleiste brach um.** Ein `.form-control` ist in Bootstrap 100 % breit, deshalb drängte das Suchfeld Beruf-Auswahl und Schaltfläche in eine zweite Zeile, obwohl daneben Platz war. Such- und Auswahlfeld haben jetzt eine begrenzte Breite.
- **Die Kennzahlen neben dem Namen sind beschriftet.** „14" neben einer Glühbirne sagt nicht, wovon 14 — es steht jetzt „14 noch offen" und „0 Tätigkeiten". Die Semesterleiste ist aus dem Namensblock in die Zeile gerückt, wo sie der Fortschrittsbalken der Person ist. Die Tätigkeitenliste im Detail hat eine Überschrift.

## [0.4.5] — 2026-09-17

### Geändert

- **Der Versetzungsplan war eine Liste aus zwanzig gleich aussehenden Zeilen.** Er ist jetzt nach Semestern gegliedert — dieselben Bezeichnungen wie in der Tätigkeitenliste, damit beide gleich gelesen werden. Die Kalenderwoche steht als eigene linke Spalte, an der das Auge die Liste hinunterlaufen kann, statt jede Zeile von vorn zu lesen; sie ist der bessere Anker als das Datum, weil der Plan selbst in Wochen geführt wird und sie immer gleich breit ist. Einsätze über die Semestergrenze bleiben im Semester ihres Beginns, Einsätze ausserhalb der Lehrzeit fallen nicht aus dem Plan.
- **Jedes Semester ist ein eigener Aufklapper, offen ist das laufende.** Über die ganze Lehrzeit hat ein Versetzungsplan rund sechzig Einsätze — vollständig ausgeklappt ein Referenzdokument, kein Überblick, während die Fragen an die Liste („wo bin ich", „was kommt als Nächstes") immer nur ein Semester betreffen. Zugeklappt heisst nicht versteckt: der Plan bleibt mit einem Klick je Semester vollständig erreichbar (Architekturregel 5). Fällt „jetzt" in keinen Einsatz, öffnet das Semester mit dem nächsten kommenden; liegt der ganze Plan in der Vergangenheit, das letzte. Natives `<details>` wie in den Roster-Kacheln, kein JavaScript.
- Vergangene Einsätze treten weniger stark zurück (Deckkraft 0.85 statt 0.7). Die Historie ist der halbe Zweck der Liste; zurückgenommen wird sie schon durch den Punkt in der Sekundärfarbe.
- **Die Flächen im Kompetenzraster sind kräftiger** (Deckung 0.55 / 0.30 / 0.10 statt 0.38 / 0.16 / 0.05). Bei den alten Werten lagen alle drei Stände so nah beieinander, dass das Raster einheitlich cremefarben wirkte und die Fläche ihren Zweck — den Ausbildungsstand zu tragen — nicht erfüllte. Die Legendenfelder haben dieselbe Deckung wie eine abgedeckte Zelle, damit die Legende aussieht wie das, was sie erklärt.
- Die Erklärzeile „Gegliedert wie im Bildungsplan. Gezeigt wird, was im Versetzungsplan vorkommt — kein Beurteilungsergebnis." entfällt; `raster:beschreibung` ist damit weg. Der Planungshorizont heisst jetzt „Vorliegender Versetzungsplan reicht bis …" statt „Vorliegender Plan reicht bis …".

### Entfernt

- **Die Teilangabe an mehrfach vorkommenden Ausbildungsblöcken** („Teil 1 von 2") aus 0.4.4. Sie sollte den wiederholten üK erklären, hängte sich aber an jeden wiederkehrenden Block — an drei Betriebsferien und zwei Verdathungsprojekten stand plötzlich eine Nummerierung, die nichts nummeriert: das sind keine Teile einer Sache, sondern getrennte Ereignisse. Auseinanderhalten lassen sich die beiden Fälle aus den Plandaten nicht. Die Gliederung nach Semestern und die Wochenspalte lösen das ursprüngliche Problem besser — zwei üK-Einträge Monate auseinander lesen sich in einer strukturierten Liste als das, was sie sind.

## [0.4.4] — 2026-09-17

### Geändert

- **„Meine Lehre" zeigte dieselbe Information zweimal.** Die Lückenliste zählte alle noch offenen Handlungskompetenzen namentlich auf, das Kompetenzraster direkt darunter zeigte dieselben noch einmal — nur mit den Kürzeln (`a.01`…`d.07`), die in der Liste fehlten, sodass sich die beiden nicht einmal aufeinander beziehen liessen. Auf „Meine Lehre" steht jetzt nur noch das Raster; die Bezugsgrösse („x von y Pflicht-Handlungskompetenzen sind im Plan abgedeckt") bringt es selbst mit. In „Meine Lernenden" bleibt die Liste: dort ist das Raster kompakt und zeigt nur Kürzel.
- **Der häufigste Zustand war der lauteste.** In 24 von 27 Rasterzellen standen ein Minuszeichen und der Text „nicht im Plan" — die Angabe mit dem geringsten Aussagewert, in fast jeder Zelle wiederholt. Sichtbares Symbol und Klartext tragen jetzt nur noch die beiden Stände, die etwas aussagen; „nicht im Plan" bleibt im Dokument (Screenreader, Titel beim Darüberfahren) und tritt visuell zurück. Die Legende erklärt nur noch, was im gezeigten Raster auch vorkommt, und steht über der Tabelle statt darunter.
- **Die Seite war eine Textwand.** Einordnung, Kompetenzen, Versetzungsplan und Tätigkeiten lagen ohne Abstand in einer einzigen Karte. Jedes Thema hat jetzt seine eigene Karte mit eigener Überschrift. Der Erfassen-Button ist die einzige Handlung der Seite und steht als Primärschaltfläche oben rechts statt als graue Schaltfläche im Lesefluss. Die Tätigkeitenliste bekommt eine Überschrift, damit „Keine Tätigkeiten vorhanden." nicht mehr kontextlos am Seitenende hängt.
- **Ein Ausbildungsblock, der mehrfach im Plan steht, weist seine Folge aus** („Teil 1 von 2"). Ein üK über zwei getrennte Wochen erschien bisher zweimal mit identischem Titel und las sich wie ein doppelt importierter Eintrag.
- **Die Semesterleiste war unbeschriftet.** Sechs Punkte mit Trennstrichen liessen offen, ob sie Semester oder Lehrjahre zählen. Ausserhalb der Roster-Kacheln steht jetzt „Lehrjahr" davor und die Nummer unter jeder Gruppe.
- Datumsangaben folgen nur noch zwei Formaten statt drei: ausgeschrieben im Fliesstext (ohne Wochentag — bei „Plan reicht bis …" ist das Datum die Auskunft), kompakt in den Listen.
- Leere Rasterfelder am Ende kürzerer Zeilen tragen keinen Rahmen mehr; mit Rahmen lasen sie sich wie fehlende Daten statt wie das Ende der Zeile.

## [0.4.3] — 2026-09-17

### Geändert

- **Leistungskriterien zeigen ihre Beschreibung.** Der Kurzname ist im Rahmen nur ein Code (`MEM 02 02`); was gemeint ist, steht im `description`-Feld und war bisher nur als Titel beim Darüberfahren erreichbar. Die Beschreibung steht jetzt gekürzt neben dem Code (120 Zeichen, an der Wortgrenze abgeschnitten), der volle Text bleibt im Titel.

## [0.4.2] — 2026-09-17

### Geändert

- **Die Kompetenzauswahl war unübersichtlich.** Jede Handlungskompetenz war aufgeklappt, und unter jeder hängen rund zehn Leistungskriterien — über alle Bereiche mehrere hundert Zeilen, in denen die HK-Namen untergingen. Die Handlungskompetenz ist jetzt selbst die Zeile mit der Checkbox; die Leistungskriterien liegen in einem eigenen, zugeklappten Aufklapper darunter („10 Leistungskriterien einzeln wählen"). Ein Bereich zeigt damit das, was der Bildungsplan auf einer Seite zeigt: seine Handlungskompetenzen mit Namen. Die Checkbox steht bewusst ausserhalb des `<details>` — in einem `<summary>` würde jeder Klick darauf zugleich auf- und zuklappen, und das liesse sich nur mit JavaScript trennen.
- Leistungskriterien stehen natürlich sortiert statt in Rahmenreihenfolge. Deren `sortorder` folgt der Reihenfolge beim Rahmenimport und warf „MEM 02 04" vor „AU a1 01".
- Kompetenzen tragen ihre Beschreibung aus dem Rahmen als Titel. Bei einem Leistungskriterium ist der Kurzname nur ein Code; der erklärende Text steht im `description`-Feld und war bisher nirgends erreichbar.

## [0.4.1] — 2026-09-17

### Behoben

- **Die Wahlpflicht-Kennzeichnung griff nie.** Der Abgleich zwischen der Einstellung `beruf_wahlpflicht_hk` und dem Kompetenzrahmen war ein exakter Stringvergleich über die volle ID-Nummer. Die Voreinstellung nannte `7777 a.04`, die ID-Nummern im Rahmen lauten aber `7777BE a.04` — damit galt jede Handlungskompetenz als Pflicht, im Kompetenzraster war alles gelb und in der Lückenanalyse zählten Wahlpflicht-HK fälschlich in die Bezugsgrösse. Verglichen wird jetzt über das Kürzel ohne Rahmen-Präfix und ohne Rücksicht auf Gross-/Kleinschreibung: `a.04`, `7777 a.04` und `7777BE a.04` treffen dieselbe Handlungskompetenz. Die Voreinstellung nennt nur noch die Codes des Bildungsplans.

## [0.4.0] — 2026-09-17

### Behoben

- **Der Import löschte die Einsatzhistorie früherer Lehrjahre.** `import_service` ersetzte je gelieferter Person deren gesamten Einsatzbestand. Solange der Versetzungsplan die volle Lehrzeit umfasste, war das richtig; deckt eine Lieferung nur ein Planungsjahr ab, nahm sie die Vorjahre mit. Die Lückenanalyse rechnet ab Unix-Epoche und meldete dadurch für alle ab dem zweiten Lehrjahr systematisch zu viele Lücken — sichtbar war das kaum, weil die Liste nur Namen ohne Historie zeigt. Ersetzt wird jetzt nur der Zeitraum, den die gelieferten Zeilen der jeweiligen Person aufspannen; Einsätze ausserhalb bleiben stehen und ergänzen sich über aufeinanderfolgende Lieferungen zur vollen Lehrzeit. Innerhalb des Zeitraums bleibt die Lieferung unverändert massgebend, auch für Einsätze, die nur teilweise hineinragen. `docs/schnittstelle_versetzungsplan.md` beschreibt die Regel für das liefernde Skript.

### Hinzugefügt

- **Kompetenzraster** (`classes/output/kompetenzraster.php`): die Handlungskompetenzen in derselben Anordnung wie im offiziellen Bildungsplan — Handlungskompetenzbereiche als Zeilen, Handlungskompetenzen als Spalten. Auf „Meine Lehre" vollständig, in den Roster-Karten von „Meine Lernenden" kompakt (Kürzel und Symbol, Bezeichnung im Titel). Die Farbe bedeutet dasselbe wie im gedruckten Plan, nämlich Pflicht oder Wahlpflicht; der Ausbildungsstand hängt an Symbol, Klartext und Flächendeckung, damit dieselbe Farbe hier nicht etwas anderes heisst als im Dokument daneben — und damit Farbe nie alleiniger Träger einer Information ist. Kein Beurteilungsstatus: gezeigt wird ausschliesslich, was im Versetzungsplan vorkommt.
- **`api::get_kompetenzraster()`** mit den Wertobjekten `raster_bereich` und `raster_kompetenz`: alle Handlungskompetenzen des Rahmens mit einem von drei Ständen — bis zum Stichtag abgedeckt, im vorliegenden Plan später eingeplant, oder gar nicht im Plan. Die bisherige Lückenliste kannte nur „bis zum Stichtag nicht vorgekommen" und konnte „noch nicht" nicht von „gar nicht" unterscheiden. Anders als `get_luecken_nach_bereich()` zeigt das Raster auch die Wahlpflicht-HK und die Bereiche, die ausschliesslich aus solchen bestehen — im Bildungsplan stehen sie ja ebenfalls.
- **`api::get_planungshorizont()`**: bis wohin der vorliegende Plan reicht. Ohne diese Angabe ist „nicht im Plan" nicht einzuordnen, weil eine Lieferung nur ihren eigenen Planungszeitraum abdeckt.
- **`api::abdeckung_aus_raster()`**: leitet die Lückensicht ohne erneuten Datenbankzugriff aus einem bereits berechneten Raster ab, für Seiten, die beide Darstellungen zeigen.

### Geändert

- **Kompetenzzuordnung folgt dem Bildungsplan statt einer flachen Liste** (`block_kompetenzen.php`, neu `classes/output/kompetenz_auswahl.php`). Bisher ein Suchfeld über alle Leistungskriterien des Rahmens — dieselbe LK-Bezeichnung („AU b1 01 1-2") hängt aber unter einem Dutzend Handlungskompetenzen und war nur am vorangestellten HK-Satz zu unterscheiden; sortiert war alphabetisch nach diesem Satz, nicht in Rahmenreihenfolge. Jetzt aufklappbar nach Handlungskompetenzbereich und Handlungskompetenz, in der Reihenfolge des Rahmens (`sortorder`), mit dem Kürzel aus der ID-Nummer als Anker zum gedruckten Plan (`7777BE b.07` → `b.07`). Ohne JavaScript über `<details>`. Die generierte LK-ID-Nummer entfällt in der Anzeige — sie sagt nichts, was nicht schon im Kurznamen steht.
- **Ein Block lässt sich direkt einer Handlungskompetenz zuordnen**, nicht nur einzelnen Leistungskriterien darunter. Für Lückenanalyse und Raster ist das gleichwertig, weil LK ohnehin auf ihre HK hochgerechnet werden; wer „dieser Block deckt b7 ab" meint, muss dafür nicht mehr vier Leistungskriterien einzeln suchen.
- `kompetenz_baum::baum()` ersetzt `blatt_beschriftungen()`, das ausschliesslich die flache Auswahlliste bediente. Neu ausserdem `kompetenz_baum::kuerzel()`. Die Formularklasse `block_lk_form` entfällt ersatzlos — die Auswahl ist jetzt ein eigenes Template.
- `luecken_analyse` rechnet nicht mehr selbst, sondern reduziert das Ergebnis von `raster_analyse` auf die Planungssicht „was fehlt noch". Damit können Liste und Raster nicht auseinanderlaufen. Das Verhalten von `get_luecken()` und `get_luecken_nach_bereich()` bleibt unverändert — eine später eingeplante Handlungskompetenz zählt dort weiterhin als Lücke, weil sie zum Stichtag genauso wenig ausgebildet ist wie eine offene.

## [0.3.0] — 2026-09-09

### Hinzugefügt

- **Zugang zur Blockverwaltung für Berufsbildner/innen**: neue systemweite Rolle `berufsbildung_planung` („Ausbildungsplanung"), die `local/berufsbildung:manageblocks` trägt. `role_sync_service` weist sie jeder Person mit mindestens einer **laufenden** Zuordnung zu und entzieht sie, sobald die letzte davon beendet, gelöscht oder über die Aufbewahrungsfrist bereinigt ist. Bewusst getrennt von der personenbezogenen Rolle `berufsbildner`: die hängt am Nutzerkontext einer einzelnen lernenden Person und würde global zugewiesen die Stichtagsprüfung in `api::is_zustaendig()` unterlaufen. Details in der README, Abschnitt „Rollen und Zugang".
- **Zugang zur Blockverwaltung über „Meine Lernenden"**: der Button *Ausbildungsblöcke verwalten* erscheint dort für alle mit `manageblocks` — die Capability allein machte die Seite nicht auffindbar. Bewusst kein eigener Eintrag in der primären Navigationsleiste: die trägt nur die beiden täglichen Einstiege *Meine Lehre* und *Meine Lernenden*, die Blockpflege ist Stammdatenarbeit. Ohne eigene Lernende bleibt der Weg über den Admin-Baum.

### Behoben

- **Die eigenen Capabilities des Plugins waren wirkungslos.** `settings.php` registrierte alle `admin_externalpage`-Einträge innerhalb von `if ($hassiteconfig)`. Für Nutzer ohne `moodle/site:config` existierten die Seiten im Admin-Baum damit gar nicht, und `admin_externalpage_setup()` brach mit „accessdenied" ab, obwohl die Capability vorlag. Nur die Systemeinstellungen bleiben jetzt `$hassiteconfig`; über den Zugriff auf die Verwaltungsseiten entscheidet `check_access()` je Seite.
- **Kompetenzen liessen sich einem Ausbildungsblock praktisch nicht zuordnen.** Der einzige Zugang von der Blockliste zur Kompetenzseite war ein Link auf die *Anzahl* der Leistungskriterien — bei einem neuen Block also die Ziffer „0". Die Blockliste hat jetzt eine Aktionsspalte mit beschrifteten Buttons.
- **`zuordnung_service::beenden()` glich die Rollen nicht ab.** Anlegen und Löschen taten es, Beenden nicht — der Zugang wäre bis zum nächsten Task-Lauf bestehen geblieben.
- **Die Rolle *Ausbildungsplanung* liess sich praktisch nicht von Hand vergeben.** `create_role()` trägt in `role_allow_assign` und `role_allow_view` keine Zeile ein, und genau daran filtert `get_assignable_roles()` für alle, die nicht Administrator/in sind. Eine Ausbildungsleitung mit Manager-Rolle bekam die Rolle also nie zur Auswahl, obwohl die README das so beschrieb. Der neue `role_matrix_service` trägt bei Installation und Upgrade nach, dass *Manager* die Rolle *Ausbildungsplanung* vergeben und beide Rollennamen sehen darf; er ist idempotent, weil die beiden Tabellen keinen Unique-Index haben. `berufsbildner` bleibt bewusst nur sichtbar und nicht zuweisbar — die Rolle allein öffnet nichts, weil die aufsetzenden Plugins zusätzlich `api::is_zustaendig()` prüfen.

### Geändert

- **Kompetenzzuordnung als Mehrfachauswahl mit Suchfeld** (`block_kompetenzen.php`): vorher ein Leistungskriterium pro Seitenreload, aus einem flachen Dropdown von bis zu 1000 Einträgen, die nur ihren `shortname` zeigten. Die Einträge lesen sich jetzt als „Handlungskompetenz: Leistungskriterium (Nummer)" (`kompetenz_baum::blatt_beschriftungen()`), bereits zugeordnete werden nicht mehr angeboten, und die drei Sackgassen (kein Beruf, kein Rahmen, Kompetenzverwaltung aus) verlinken die Seite, auf der das Fehlende nachgetragen wird. Ein Leistungskriterium in mehreren Blöcken war und bleibt möglich — der Unique-Index gilt je Block.
- Die primären Navigationseinträge stehen **vor** der Website-Administration (`add_node()` mit `$beforekey` statt `add()`); fehlt der Admin-Knoten, hängen sie wie bisher hinten an.
- „Meine Ausbildung" heisst wieder **„Meine Lehre"** — Rücknahme der Umbenennung aus 0.1.0.

## [0.2.0] — 2026-09-05

### Hinzugefügt

- **„Meine Ausbildung" zeigt den Versetzungsplan**: der laufende Einsatz als Kopfzeile („wo bin ich gerade") und der vollständige Zeitstrahl aller Einsätze mit vergangen/aktuell/kommend (`classes/output/einsatz_karte.php`, `classes/output/einsatz_timeline.php`). Die dafür nötigen API-Methoden gab es bereits, sie hatten bisher nur keinen Anzeige-Ort.
- **Ausbildungsphase** (`api::get_ausbildungsphase()` mit den Konstanten `PHASE_UNBEKANNT`, `PHASE_VOR_BEGINN`, `PHASE_LAUFEND`, `PHASE_BEENDET`) und **`api::get_ausbildungsbeginn()`** als Gegenstück zu `get_ausbildungsende()`. `get_ausbildungsstand()` liefert in drei fachlich verschiedenen Fällen `null`; aufsetzende Plugins mussten bisher raten, welcher davon vorlag.
- **`api::get_semester_grenzen()`**: Grenzen aller Semester einer Ausbildung, damit fremde Datensätze (etwa Nachweise) nach Semester einsortiert werden können, ohne je Datum das Profil neu aufzulösen.
- **`api::get_luecken_nach_bereich()`** und das Wertobjekt `bereich_abdeckung`: dieselbe Lückenanalyse wie `get_luecken()`, aber nach Handlungskompetenzbereich gruppiert und mit Bezugsgrösse („vier von sechs abgedeckt"). Bereiche, deren HK ausschliesslich Wahlpflicht sind, entfallen, statt als „0 von 0" zu erscheinen.
- **Nachweise nach Semester gruppiert** auf „Meine Ausbildung" (`nachweis_liste::render()` nimmt optional Semestergrenzen entgegen) — eine lernende Person denkt ihre Ausbildung in Semestern, nicht in liefernden Plugins. Ohne Semestergrenzen bleibt es bei der Gruppierung nach Quelle, wie sie „Meine Lernenden" weiterhin nutzt.
- `kw_converter::zu_wochennummer()` für die Anzeige einer Kalenderwoche, die bei unbekanntem Format bewusst nicht wirft.

### Behoben

- **„Meine Ausbildung" wurde am Tag des Lehrabschlusses schlagartig leer.** Da `get_ausbildungsstand()` nach Ausbildungsende `null` liefert, fiel die gesamte Seite auf die Meldung „Für Ihr Profil ist kein Beruf oder Jahrgang hinterlegt" zurück — inhaltlich falsch (der Beruf *ist* hinterlegt), und alle Nachweise verschwanden aus der Ansicht, obwohl sie während der Aufbewahrungsfrist weiterhin existieren. Die Seite unterscheidet die Fälle jetzt: fehlendes Profil, Lehre beginnt erst (mit Startdatum und, falls vorhanden, bereits dem Versetzungsplan), laufende Lehre und abgeschlossene Lehre (Rückblick mit Abschlussdatum, Zeitstrahl und Nachweisen). Der Stand des letzten Semesters wird dafür über den Stichtag-Parameter aufgelöst, wie ihn Architekturregel 3 vorsieht.

### Geändert

- `luecken_liste::render()` nimmt `bereich_abdeckung[]` statt `int[]` entgegen und kennt eine kompakte Variante für die Roster-Karten in „Meine Lernenden". Die Lückenanalyse wird auf „Meine Ausbildung" nur noch während der laufenden Lehre gezeigt: nach dem Abschluss wäre sie keine Planung mehr, sondern ein Urteil.

## [0.1.0] — 2026-08-29

Erste funktionale Version. Nicht produktiv freigegeben — siehe README.md, Abschnitt "Was noch fehlt".

### Hinzugefügt

- **Nutzerführung**: Die bisher doppeldeutige Seite „Meine Lehre“ heisst neu „Meine Ausbildung“; Lernendenkarten verweisen deutlicher auf Profil und Lerndokumentation.
- **Ausbildungsstand**: Beruf/Jahrgang aus Profilfeldern, Semester-/Lehrjahrberechnung mit konfigurierbarer Lehrdauer je Beruf (`api::get_ausbildungsstand()`). Das Jahrgang-Profilfeld darf auch ein kombiniertes Feld sein, das zusätzlich den Beruf enthält (z. B. "AU 2026", wie es manche Schulen für die automatische Kursgruppierung pflegen) — die Jahreszahl wird daraus extrahiert, der Beruf kommt weiterhin aus dem separat konfigurierten Profilfeld.
- **Zuordnung**: CRUD-Verwaltung, Rollenmodell (mehrere gleichzeitige Rollen je Person möglich, Auswahl im Formular als Dropdown mit den aktuell genutzten Werten "hauptverantwortlich"/"stellvertretung" — das Feld selbst bleibt bewusst Freitext ohne Schema-Einschränkung, siehe CLAUDE.md Architekturregel 4), Bulk- und Kohorten-Zuordnung, laufende Kohorten-Synchronisation (stündlicher Task), CSV-Import für den Jahreswechsel, automatischer Rollen-Sync in Moodles Rollensystem.
- **Report-Builder-Übersicht** der Zuordnungen mit Filtern und Export.
- **Nachweis-Provider-Mechanismus**: aufsetzende Plugins registrieren sich, "Meine Lernenden"/"Meine Lehre" sammeln Nachweise über Plugin-Grenzen hinweg ein, mit vorheriger Zuständigkeitsprüfung.
- **Versetzungsplan**: CSV-Parser (Block- und Wochenformat), Import-Pipeline mit Idempotenz, Vollständigkeitsschutz und Testlauf-Modus, Ausbildungsblöcke mit Kompetenzabdeckung, Webservice-Funktion `local_berufsbildung_import_versetzungsplan`, manueller Upload als Rückfallweg, Alterungshinweis.
- **Öffentliche API** für Einsätze, ausgebildete Kompetenzen und aktuellen Einsatz (`get_einsaetze()`, `get_ausgebildete_kompetenzen()`, `get_aktueller_einsatz()`).
- **Lücken-Analyse** (`api::get_luecken()`): Handlungskompetenzen ohne Abdeckung, basierend auf einer zentral gepflegten Beruf→Kompetenzrahmen-Zuordnung. Jetzt sichtbar auf "Meine Lehre" und "Meine Lernenden" (`classes/output/luecken_liste.php`) — vorher nur über die API abrufbar, ohne Anzeige-Ort.
- **Datenschutz-Provider** (`classes/privacy/provider.php`): vollständiger Export für Zuordnung, Kohorten-Link, Einsatz, Versetzungsplan-Import und neu Aufbewahrungspflichten; Löschanfragen anonymisieren den ausführenden Account eines Imports und lassen Einsatz als Ausbildungsnachweis bestehen (Architekturregel 5). Zuordnung folgt seit dem Retention-Feature einer feineren Regel, siehe unten.
- **Aufbewahrungsfrist nach Lehrabschluss** (`api::get_ausbildungsende()`, `api::ist_ausbildung_beendet()`, `api::hat_aufbewahrungspflicht()`, `api::darf_personendaten_geloescht_werden()`, `api::aufbewahrungsfrist_abgelaufen()`, neue Einstellung `retention_monate`, Standard 12): Zuordnungen werden automatisch endgültig gelöscht (`zuordnung_retention_service`, Task `task\zuordnung_retention`), sobald die Frist seit dem berechneten Lehrabschluss verstrichen ist — ausser eine dokumentierte Aufbewahrungspflicht liegt vor (neue Tabelle `local_berufsbildung_aufbewahrung`, Verwaltungsseite `aufbewahrung.php`, Capability `local/berufsbildung:manageaufbewahrung`). Eine Löschanfrage nach Lehrabschluss wird aus demselben Grund vorzeitig honoriert; eine Account-Löschung löscht immer sofort und unbedingt, auch während laufender Ausbildung. `kohorten_sync_service` erzeugt für eine Person mit abgeschlossener Ausbildung bewusst keine neue Zuordnung mehr, sonst würde eine bereits gelöschte Zuordnung beim nächsten Sync-Lauf wieder auferstehen.
- `api::get_block_name()` — Bezeichnung eines Ausbildungsblocks für aufsetzende Plugins, u.a. Grundlage der automatischen Abteilungs-Vorbelegung in `local_lerndokumentation`.
- Erste Behat-Testabdeckung (`tests/behat/`) und eine `.github/workflows/ci.yml` (moodle-plugin-ci, Moodle 4.5–5.2 × PHP 8.1–8.4).
- 110 automatisierte Tests (PHPUnit), Datenbankschema-Check grün.

### Behoben

- **`beruf`-Feld auf Zuordnung und Kohorten-Verknüpfung akzeptiert jetzt Freitext** (`PARAM_TEXT`, Spaltenbreite auf 255 Zeichen erweitert) statt streng `PARAM_ALPHANUMEXT`: Schulen, die im Beruf-Profilfeld die ausgeschriebene Bezeichnung statt eines Kurzcodes pflegen (z. B. "Automatiker/in EFZ" statt "AU_EFZ"), bekamen beim automatischen Übernehmen aus dem Profil (Kohorten-Sync, manuelles Zuordnungsformular, CSV-Import mit leerer Beruf-Spalte) eine `invalid_persistent_exception` ("Übermittelte Daten sind ungültig") wegen des enthaltenen Schrägstrichs/Leerzeichens. Der CSV-Import hätte den Wert zuvor sogar stillschweigend auf Buchstaben/Zahlen zusammengestutzt, statt abzubrechen.

### Bekannte Einschränkungen

- Behat deckt bislang nur den zentralen Zuordnungs-Workflow ab, nicht jede Seite. Die CI-Pipeline ist noch nie gegen einen echten Git-Remote gelaufen.
- Lehrverlängerung/Wiederholung und Stellvertretung sind dokumentierte, bewusst zurückgestellte offene Fragen (`docs/plan.md` §13).
- Lehrabschluss beendet eine einzelne laufende Zuordnung nicht automatisch (`gueltig_bis` bleibt `NULL`) — bewusste Entscheidung, siehe `docs/plan.md` §13.4. Sie wird trotzdem automatisch gelöscht, sobald die Aufbewahrungsfrist danach verstrichen ist.
