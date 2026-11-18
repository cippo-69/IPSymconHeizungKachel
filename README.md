# Heizung Kachel

Die Bibliothek enthält zwei Module: **Heizung Kachel** (Raum) und **Heizungsanlage Kachel** (Wärmeerzeuger, siehe unten).

Kachel für die Symcon-Kachel-Visualisierung (HTML-SDK): ein Raum mit Ist- und Sollwert, Sollwertverstellung,
Wochenprogramm und einem Schieberegler je Betriebsart (z. B. Heizbetrieb und Absenkbetrieb).

## Inhalt der Kachel

- **Sollwert** links, **Ist-Temperatur** rechts; optional relative Luftfeuchte (auf die Ist-Temperatur umgerechnet) und Ventilposition
- **Sollwert** mit − / +; die Verstellung gilt bis zum nächsten Schaltpunkt des Wochenplans
- **Betriebsart** jetzt, mit nächstem Schaltpunkt
- **Sollwerte** je Aktion des Wochenplans als Schieberegler. Ist die geänderte Betriebsart gerade aktiv, wird der
  Sollwert sofort nachgeführt (abschaltbar).
- **Wochenprogramm** als Tagesbalken je Gruppe; Antippen öffnet die Schaltpunkte (Zeit, Betriebsart, löschen,
  hinzufügen). Wochenplan ein/aus.

Kleine Kachel: Sollwert, Ist und Betriebsart. Ist eine Einstellungsseite (Eigenschaft „Einstellungsseite“, eine Kategorie) gesetzt, öffnet das Regler-Symbol per `openObject` diese Seite; dort liegt eine zweite Instanz mit „immer zeigen“, die Wochenprogramm und Sollwerte vollständig darstellt; ihr aktives (rotes) Regler-Symbol schließt die Seite und springt zurück auf die übergeordnete Kategorie (die Kategorie darf versteckt sein; die Kachelgröße dort wird in der Visu je Gerät eingestellt). Ohne Einstellungsseite öffnet das Symbol per `openObject` (ab Symcon 8.2) das Visu-Fenster der Sollwert-Variable; dort stehen Wochenplan und Regler je Betriebsart als Unterobjekte (Listen-Symbol oben rechts, Uhr-Symbol öffnet den Wochenplan). Voraussetzung: Die Sollwert-Variable ist in der Visu erreichbar (z. B. per Link) und Wochenplan sowie Betriebsart-Variablen liegen darunter. Große Kacheln (ab 480 px Höhe) zeigen Wochenprogramm und Sollwerte direkt. Ohne `openObject` klappt das Symbol den Teil in der Kachel auf (Zustand wird gemerkt). Hinweis: Die maximierte Ansicht der eigenen Kachel (Vergrößern-Pfeil) zeigt HTML-SDK-Kacheln nicht an.

## Voraussetzungen

- IP-Symcon ab 8.0
- Ein Wochenplan-Ereignis, dessen Aktionen „Variable auf Wert einer Variable setzen“ verwenden
  (Aktionsparameter `VARIABLE`). So legt Symcon den Wochenplan unter einer Sollwert-Variable an, z. B. bei
  Heating Control: Sollwert → Normalbetrieb / Absenkbetrieb / Wochenplan.

## Einrichtung

1. Modulverwaltung → Repository dieser Bibliothek hinzufügen.
2. Instanz „Heizung Kachel“ anlegen und in der Kachel-Visualisierung platzieren.
3. Ist-Temperatur, Sollwert-Variable und Wochenplan auswählen, optional die Ventilposition.

## Eigenschaften

| Eigenschaft | Bedeutung |
|---|---|
| Ist-Temperatur | Variable mit der Raumtemperatur |
| Sollwert | aktueller Sollwert, den der Wochenplan setzt |
| Wochenplan | Wochenplan-Ereignis |
| Ventilposition | optional, in % |
| Luftfeuchte / Sensortemperatur | optional: relative Feuchte eines Raumsensors und dessen Temperatur. Weicht die Sensortemperatur von der Ist-Temperatur ab, wird über die absolute Feuchte die relative Feuchte bei der Ist-Temperatur berechnet. |
| Schrittweite | Raster für Sollwert und Schieberegler (Standard 1 K) |
| Minimum / Maximum | Bereich für Sollwert und Schieberegler (Standard 10–24 °C) |
| Laufende Betriebsart übernehmen | Änderung am Regler der aktiven Betriebsart sofort als Sollwert setzen |

## Farben

Die Kachel hat keine eigenen Farbeinstellungen. Farben, Layout-Bausteine und Hell/Dunkel-Erkennung kommen aus dem Modul [Visu Design](https://github.com/cippo-69/IPSymconVisuDesign) ab Version 1.7 (`W5D_GetCSS`, `W5D_GetTileCSS`, `W5D_GetTileJS`); im module.html steht nur CSS für Sonderelemente (Kopf Soll/Ist, Wochenprogramm, Sollwerte je Betriebsart) mit Farben aus Visu Design. Ein Theme-Wechsel kommt ohne Neuladen an: Die Kachel abonniert die Einstellungen der Design-Instanz und schickt das Design-CSS mit jedem Zustand mit (auch an zwischengespeicherte Kacheln). Die Farben der Betriebsarten stammen aus den Aktionen des Wochenplans.

# Heizungsanlage Kachel

Zweites Modul der Bibliothek: eine Kachel je Wärmeerzeuger (Kessel, später Wärmepumpe).

## Inhalt der Kachel

- **Betriebszustand** als Plakette: Meldung aktiv → Warmwasserladung (Kesselleistung über Schwelle) → Brenner (Leistung > 0) → Statustext → Pumpe → Bereit
- **Außentemperatur**, **Vorlauf** (mit Soll), **Rücklauf** (mit Spreizung), **Warmwasser** (mit Soll)
- **Meldungen**: aktive Alarmvariablen rot, mit Beginn
- **Verlauf**: Vorlauf, Rücklauf und Warmwasser aus dem Archiv, erkannte Warmwasserladungen als Balken, Tageswechsel gestrichelt; Gasverbrauch heute (m³ und kWh) und Zahl der Warmwasserladungen heute.
  - Zeitraum 6 h, 24 h, 3 Tage oder 7 Tage (bis 3 Tage 5-Minuten-Mittel, darüber Stundenmittel)
  - verschieben per Wischen/Ziehen im Diagramm oder mit ‹ ›; „Jetzt“ springt zurück auf live (Aktualisierung alle 5 Minuten)
  - Achse und Zeitraum mit Wochentag, Datum und Uhrzeit; Antippen bzw. Mauszeiger zeigt die Werte zum Zeitpunkt
  - jede Kachel lädt ihren Ausschnitt selbst (Anfrage-ID), Verschieben auf einem Gerät ändert die Ansicht auf anderen Geräten nicht
- **Schalten**: frei konfigurierbar
  - *Umschalten*: Boolean-Variable (RequestAction) oder Ereignis aktiv/inaktiv (zweites Ereignis wird mitgeschaltet)
  - *Taste*: setzt einen Wert per RequestAction; Tasten mit gleicher Gruppe stehen als Umschalter in einer Zeile, die Status-Variable markiert die aktive Taste
  - *Stufen*: − / + mit Schritt, Minimum und Maximum; angezeigt wird die Status-Variable (falls gesetzt)
  - *Bestätigen*: Schaltung erst nach zweitem Tippen innerhalb von 4 s
  - Jede Schaltung wird im Meldungsprotokoll unter „Heizungsanlage Kachel“ protokolliert
- **Weitere Werte**: beliebige Variablen, formatiert nach Profil

Kleine Kachel (unter 330 px Höhe): Zustand, Außentemperatur, Messwerte und aktive Meldungen.

## Eigenschaften

| Eigenschaft | Bedeutung |
|---|---|
| Außentemperatur, Vorlauf Ist/Soll, Rücklauf, Warmwasser Ist/Soll | Messwerte; nur Vorlauf ist Pflicht |
| Brennerleistung | z. B. Modulation in %; > 0 = Brenner an |
| Pumpe | Boolean |
| Statustext | String; angezeigt wird der Teil vor „ – “ |
| Leistung Kessel / Schwelle | elektrische Leistung des Kessels in W; über der Schwelle gilt Warmwasserladung (Erkennung und Zählung aus dem Archiv) |
| Gaszähler / Umrechnung | archivierte Zählervariable in m³; Umrechnung in kWh/m³ (Brennwert × Zustandszahl) |
| Meldungen | Liste: Variable, Bezeichnung; aktiv bei true bzw. ≠ 0 |
| Schaltfunktionen | Liste: Bezeichnung, Art, Objekt, Objekt 2, Status, Wert, Schritt, Min, Max, Gruppe, Bestätigen |
| Weitere Werte | Liste: Variable, Bezeichnung |

Farben wie bei der Heizung Kachel aus dem Modul Visu Design (ab 1.7); Vorlauf `--w5t-hot` (rot), Rücklauf `--w5t-cold` (blau), Warmwasser `--w5t-warm` (ocker). Im module.html steht nur CSS für Sonderelemente (Zustandsplakette, Messwerte, Verlaufsdiagramm).

## Lizenz

MIT
