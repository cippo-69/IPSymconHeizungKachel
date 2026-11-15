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

Wochenprogramm und Sollwerte klappen automatisch nach der Kachelhöhe auf (ab 480 px) und lassen sich über das Regler-Symbol neben der Betriebsart auf- und zuklappen (wie Klang in der Sonos-Kachel). Der zuletzt gewählte Zustand wird im Modul gemerkt (gilt nach dem Neuladen und auf allen Geräten); bis dahin gilt die Automatik. Auch bei kleiner Kachel bleibt das Symbol erreichbar; aufgeklappt scrollt der Inhalt in der Kachel. Hinweis: Die maximierte Ansicht der Kachel-Visu (Vergrößern-Pfeil, openObject) zeigt HTML-SDK-Kacheln nicht an.

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
| Akzentfarbe | Farbe der Kachel |

## Lizenz

MIT
