# Heizung Kachel

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
| Akzentfarbe (heller Modus) | Farbe für Schalter, heutigen Tag und aktives Regler-Symbol |
| Akzentfarbe Dark Mode | eigene Farbe im Dark Mode der Visu; transparent = wie im hellen Modus |

## Lizenz

MIT
