# Heizung Kachel

Kachel fÃ¼r die Symcon-Kachel-Visualisierung (HTML-SDK): ein Raum mit Ist- und Sollwert, Sollwertverstellung,
Wochenprogramm und einem Schieberegler je Betriebsart (z. B. Heizbetrieb und Absenkbetrieb).

## Inhalt der Kachel

- **Ist-Temperatur** und optional die Ventilposition
- **Sollwert** mit âˆ’ / +; die Verstellung gilt bis zum nÃ¤chsten Schaltpunkt des Wochenplans
- **Betriebsart** jetzt, mit nÃ¤chstem Schaltpunkt
- **Sollwerte** je Aktion des Wochenplans als Schieberegler. Ist die geÃ¤nderte Betriebsart gerade aktiv, wird der
  Sollwert sofort nachgefÃ¼hrt (abschaltbar).
- **Wochenprogramm** als Tagesbalken je Gruppe; Antippen Ã¶ffnet die Schaltpunkte (Zeit, Betriebsart, lÃ¶schen,
  hinzufÃ¼gen). Wochenplan ein/aus.

Bei kleiner Kachel werden nur Ist, Soll und Betriebsart gezeigt.

## Voraussetzungen

- IP-Symcon ab 8.0
- Ein Wochenplan-Ereignis, dessen Aktionen â€žVariable auf Wert einer Variable setzenâ€œ verwenden
  (Aktionsparameter `VARIABLE`). So legt Symcon den Wochenplan unter einer Sollwert-Variable an, z. B. bei
  Heating Control: Sollwert â†’ Normalbetrieb / Absenkbetrieb / Wochenplan.

## Einrichtung

1. Modulverwaltung â†’ Repository dieser Bibliothek hinzufÃ¼gen.
2. Instanz â€žHeizung Kachelâ€œ anlegen und in der Kachel-Visualisierung platzieren.
3. Ist-Temperatur, Sollwert-Variable und Wochenplan auswÃ¤hlen, optional die Ventilposition.

## Eigenschaften

| Eigenschaft | Bedeutung |
|---|---|
| Ist-Temperatur | Variable mit der Raumtemperatur |
| Sollwert | aktueller Sollwert, den der Wochenplan setzt |
| Wochenplan | Wochenplan-Ereignis |
| Ventilposition | optional, in % |
| Schrittweite | Raster fÃ¼r Schieberegler und âˆ’ / + (Standard 0,5 K) |
| Minimum / Maximum | Bereich für Sollwert und Schieberegler (Standard 10–24 °C) |
| Luftfeuchte / Sensortemperatur | optional: relative Feuchte eines Raumsensors und dessen Temperatur. Weicht die Sensortemperatur von der Ist-Temperatur ab, wird über die absolute Feuchte die relative Feuchte bei der Ist-Temperatur berechnet. |
| Laufende Betriebsart Ã¼bernehmen | Ã„nderung am Regler der aktiven Betriebsart sofort als Sollwert setzen |
| Akzentfarbe | Farbe der Kachel |

## Lizenz

MIT
