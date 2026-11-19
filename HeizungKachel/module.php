<?php

declare(strict_types=1);

/**
 * Heizung-Kachel (HTML-SDK)
 *
 * Eine Kachel je Raum: Ist- und Sollwert mit Sollwertverstellung, Wochenprogramm (Wochenplan-Ereignis) mit
 * Schaltpunkten und je Aktion des Wochenplans ein Schieberegler für den Sollwert dieser Betriebsart
 * (z. B. Heizbetrieb / Absenkbetrieb).
 *
 * Erwartet einen Wochenplan, dessen Aktionen eine Variable auf den Wert einer anderen Variable setzen
 * (Aktionsparameter VARIABLE), so wie ihn Symcon unter einer Sollwert-Variable anlegt.
 */
class HeizungKachel extends IPSModuleStrict
{
    private const EVENT_MESSAGES = [
        'EM_UPDATE', 'EM_CHANGEACTIVE', 'EM_ADDSCHEDULEACTION', 'EM_REMOVESCHEDULEACTION', 'EM_CHANGESCHEDULEACTION',
        'EM_ADDSCHEDULEGROUP', 'EM_REMOVESCHEDULEGROUP', 'EM_CHANGESCHEDULEGROUP',
        'EM_ADDSCHEDULEGROUPPOINT', 'EM_REMOVESCHEDULEGROUPPOINT', 'EM_CHANGESCHEDULEGROUPPOINT'
    ];

    public function Create(): void
    {
        parent::Create();

        $this->RegisterPropertyInteger('ActualID', 0);     // Ist-Temperatur
        $this->RegisterPropertyInteger('TargetID', 0);     // aktueller Sollwert (wird vom Wochenplan gesetzt)
        $this->RegisterPropertyInteger('ScheduleID', 0);   // Wochenplan
        $this->RegisterPropertyInteger('ValveID', 0);      // optional: Ventilposition / Stellwert in %
        $this->RegisterPropertyInteger('HumidityID', 0);   // optional: relative Luftfeuchte eines Raumsensors
        $this->RegisterPropertyInteger('HumidityTempID', 0); // optional: Temperatur desselben Sensors (für die Umrechnung)
        $this->RegisterPropertyFloat('StepSize', 1.0);
        $this->RegisterPropertyFloat('MinTemp', 10.0);     // Bereich für Sollwert und Schieberegler
        $this->RegisterPropertyFloat('MaxTemp', 24.0);
        $this->RegisterPropertyBoolean('ApplyToCurrent', true);
        $this->RegisterPropertyInteger('DetailID', 0);     // optional: Kategorie (Einstellungsseite), die das Regler-Symbol per openObject öffnet
        $this->RegisterPropertyBoolean('AlwaysOpen', false); // Wochenprogramm und Sollwerte immer zeigen (Instanz auf der Einstellungsseite)

        $this->RegisterAttributeString('Watched', '[]');        $this->RegisterAttributeInteger('Expanded', -1); // Wochenprogramm: -1 automatisch nach Kachelhöhe, 0 zu, 1 auf (gemerkt)

        $this->SetVisualizationType(1);
    }

    public function ApplyChanges(): void
    {
        parent::ApplyChanges();

        foreach ((array) json_decode($this->ReadAttributeString('Watched'), true) as $w) {
            $this->UnregisterMessage((int) $w[0], (int) $w[1]);
        }
        foreach ($this->GetReferenceList() as $ref) {
            $this->UnregisterReference($ref);
        }

        $watch = [];
        $vars = [
            $this->ReadPropertyInteger('ActualID'), $this->ReadPropertyInteger('TargetID'), $this->ReadPropertyInteger('ValveID'),
            $this->ReadPropertyInteger('HumidityID'), $this->ReadPropertyInteger('HumidityTempID')
        ];
        $eid = $this->ReadPropertyInteger('ScheduleID');
        $hasSchedule = $eid > 0 && @IPS_EventExists($eid) && IPS_GetEvent($eid)['EventType'] === 2;
        $ok = $eid === 0 || $hasSchedule; // ohne Wochenplan: nur Ist und Soll
        if ($hasSchedule) {
            $this->RegisterReference($eid);
            foreach (self::EVENT_MESSAGES as $c) {
                if (defined($c)) {
                    $this->RegisterMessage($eid, constant($c));
                    $watch[] = [$eid, constant($c)];
                }
            }
            foreach ($this->ModeVariables() as $vid) {
                $vars[] = $vid;
            }
        }
        foreach (array_unique($vars) as $vid) {
            if ($vid > 0 && @IPS_VariableExists($vid)) {
                $this->RegisterMessage($vid, VM_UPDATE);
                $this->RegisterReference($vid);
                $watch[] = [$vid, VM_UPDATE];
            }
        }
        // Theme-Wechsel in „Visu Design“: Zustand mit neuem Design-CSS senden (MessageSink → Push)
        $design = $this->DesignID();
        if ($design > 0) {
            $this->RegisterMessage($design, IM_CHANGESETTINGS);
            $watch[] = [$design, IM_CHANGESETTINGS];
        }
        $this->WriteAttributeString('Watched', json_encode($watch));

        if (!$ok) {
            $this->SetStatus(201);
        } elseif ($this->ReadPropertyInteger('TargetID') <= 0 || !@IPS_VariableExists($this->ReadPropertyInteger('TargetID'))) {
            $this->SetStatus(202);
        } else {
            $this->SetStatus(IS_ACTIVE);
        }
        $this->Push();
    }

    public function MessageSink(int $TimeStamp, int $SenderID, int $Message, array $Data): void
    {
        // Aktionen des Wochenplans geändert: Variablen neu abonnieren
        if (in_array($Message, [EM_ADDSCHEDULEACTION, EM_REMOVESCHEDULEACTION, EM_CHANGESCHEDULEACTION], true)) {
            $this->ApplyChanges();
            return;
        }
        $this->Push();
    }

    public function GetVisualizationTile(): string
    {
        $initial = '<script>handleMessage(' . json_encode(json_encode($this->BuildState())) . ');</script>';
        return $this->DesignStyle() . file_get_contents(__DIR__ . '/module.html') . $this->DesignScript() . $initial;
    }

    /**
     * Farben und Kachel-Bausteine aus dem Modul „Visu Design“ (ab 1.7: W5D_GetCSS + W5D_GetTileCSS).
     * Das CSS steht im Element #w5d und kommt zusätzlich mit jedem Zustand mit (Feld design), damit ein
     * Theme-Wechsel auch in zwischengespeicherten Kacheln ohne Neuladen ankommt.
     */
    private function DesignStyle(): string
    {
        return '<style id="w5d">' . $this->DesignCSS() . '</style>';
    }

    private function DesignID(): int
    {
        $guid = '{018EEC85-8FFC-4E1F-A966-702C4BBDC2E8}';
        if (!IPS_ModuleExists($guid)) {
            return 0;
        }
        $ids = IPS_GetInstanceListByModuleID($guid);
        sort($ids);
        return $ids[0] ?? 0;
    }

    private function DesignCSS(): string
    {
        $id = $this->DesignID();
        if ($id <= 0 || !function_exists('W5D_GetCSS')) {
            return '';
        }
        return W5D_GetCSS($id) . (function_exists('W5D_GetTileCSS') ? W5D_GetTileCSS($id) : '');
    }

    /** Hell/Dunkel-Erkennung (w5tTheme) aus Visu Design */
    private function DesignScript(): string
    {
        $id = $this->DesignID();
        return $id > 0 && function_exists('W5D_GetTileJS') ? '<script>' . W5D_GetTileJS($id) . '</script>' : '';
    }

    /* ---------- Verlauf (Einstellungsseite): Soll, Ist, Ventil aus dem Archiv ---------- */

    private function ArchiveID(): int
    {
        $l = IPS_GetInstanceListByModuleID('{43192F0B-135B-4CE7-A0A7-1475603F3060}');
        return $l ? (int) $l[0] : 0;
    }

    /**
     * Antwort auf eine Verlaufsanfrage der Kachel (Verschieben, Zeitraum). Geht wie jeder Zustand an alle offenen Kacheln;
     * nur die Kachel mit der passenden Anfrage-ID übernimmt sie.
     */
    private function History(string $id, int $from, int $to): void
    {
        $to = min($to, time());
        $from = max($from, $to - 31 * 86400);
        if ($to - $from < 3600) {
            $from = $to - 3600;
        }
        $ac = $this->ArchiveID();
        $se = [];
        if ($ac > 0) {
            // Soll und Ventil ändern sich selten: Einzelwerte als Stufen, mit dem Wert vor Beginn des Zeitraums
            foreach (['TargetID' => 'soll', 'ValveID' => 'ventil'] as $p => $k) {
                $vid = $this->ReadPropertyInteger($p);
                if ($vid <= 0 || !@IPS_VariableExists($vid) || !AC_GetLoggingStatus($ac, $vid)) {
                    continue;
                }
                $pts = [];
                $prev = (array) @AC_GetLoggedValues($ac, $vid, 0, $from, 1);
                if (isset($prev[0])) {
                    $pts[] = [$from, round((float) $prev[0]['Value'], 1)];
                }
                foreach (array_reverse((array) @AC_GetLoggedValues($ac, $vid, $from, $to, 5000)) as $v) {
                    $pts[] = [(int) $v['TimeStamp'], round((float) $v['Value'], 1)];
                }
                if ($pts) {
                    $pts[] = [$to, round((float) GetValue($vid), 1)];
                    $se[$k] = $pts;
                }
            }
            // Ist: bis 3 Tage 5-Minuten-Mittel, darüber Stundenmittel
            $vid = $this->ReadPropertyInteger('ActualID');
            if ($vid > 0 && @IPS_VariableExists($vid) && AC_GetLoggingStatus($ac, $vid)) {
                $pts = [];
                foreach (array_reverse((array) @AC_GetAggregatedValues($ac, $vid, ($to - $from) > 3 * 86400 ? 0 : 5, $from, $to, 0)) as $v) {
                    $pts[] = [(int) $v['TimeStamp'], round((float) $v['Avg'], 1)];
                }
                if ($pts) {
                    $se['ist'] = $pts;
                }
            }
        }
        $this->UpdateVisualizationValue(json_encode(['type' => 'hist', 'id' => $id, 'from' => $from, 'to' => $to, 'series' => $se]));
    }

    public function RequestAction(string $Ident, mixed $Value): void
    {
        $p = is_string($Value) ? json_decode($Value, true) : null;
        if ($Ident === 'History') {
            if (is_array($p)) {
                $this->History(substr((string) ($p['id'] ?? ''), 0, 16), (int) ($p['from'] ?? 0), (int) ($p['to'] ?? 0));
            }
            return;
        }
        switch ($Ident) {
            case 'SetTarget':
                $this->SetTarget((float) $Value);
                break;
            case 'SetMode':
                if (is_array($p)) {
                    $this->SetMode((int) $p['a'], (float) $p['v']);
                }
                break;
            case 'SetPoint':
                if (is_array($p)) {
                    $this->SetPoint((int) $p['g'], (int) $p['p'], (int) $p['h'], (int) $p['m'], (int) $p['a']);
                }
                break;
            case 'DeletePoint':
                if (is_array($p)) {
                    $this->DeletePoint((int) $p['g'], (int) $p['p']);
                }
                break;
            case 'SetActive':
                $eid = $this->ReadPropertyInteger('ScheduleID');
                if (@IPS_EventExists($eid)) {
                    IPS_SetEventActive($eid, (bool) $Value);
                }
                break;
            case 'SetExpanded':
                $this->WriteAttributeInteger('Expanded', $Value ? 1 : 0);
                break;
            case 'Refresh':
                break;
            default:
                throw new Exception('Unbekannte Aktion: ' . $Ident);
        }
        $this->Push();
    }

    /* ---------- Aktionen ---------- */

    private function SetTarget(float $v): void
    {
        $tid = $this->ReadPropertyInteger('TargetID');
        if (!@IPS_VariableExists($tid)) {
            return;
        }
        $r = $this->Range($tid);
        RequestAction($tid, $this->Clamp($v, $r));
    }

    private function SetMode(int $actionID, float $v): void
    {
        $sched = $this->Schedule();
        if ($sched === null) {
            return;
        }
        foreach ($sched['ScheduleActions'] as $a) {
            if ((int) $a['ID'] !== $actionID) {
                continue;
            }
            $vid = (int) ($a['ActionParameters']['VARIABLE'] ?? 0);
            if (!@IPS_VariableExists($vid)) {
                return;
            }
            $v = $this->Clamp($v, $this->Range($vid));
            RequestAction($vid, $v);
            // läuft diese Betriebsart gerade, den aktuellen Sollwert sofort nachführen
            if ($this->ReadPropertyBoolean('ApplyToCurrent') && $sched['EventActive'] && $this->CurrentAction($sched) === $actionID) {
                $this->SetTarget($v);
            }
            return;
        }
    }

    private function SetPoint(int $group, int $point, int $h, int $m, int $action): void
    {
        $sched = $this->Schedule();
        if ($sched === null || $h < 0 || $h > 23 || $m < 0 || $m > 59) {
            return;
        }
        $g = $this->Group($sched, $group);
        if ($g === null || !$this->ActionExists($sched, $action)) {
            return;
        }
        if ($point < 0) {
            $max = -1;
            foreach ($g['Points'] as $pt) {
                $max = max($max, (int) $pt['ID']);
            }
            $point = $max + 1;
        }
        try {
            IPS_SetEventScheduleGroupPoint($sched['EventID'], $group, $point, $h, $m, 0, $action);
        } catch (Throwable $e) {
            $this->Notice('Schaltpunkt konnte nicht gespeichert werden: ' . $e->getMessage(), true);
        }
    }

    private function DeletePoint(int $group, int $point): void
    {
        $sched = $this->Schedule();
        $g = $sched === null ? null : $this->Group($sched, $group);
        if ($g === null) {
            return;
        }
        $pt = null;
        foreach ($g['Points'] as $x) {
            if ((int) $x['ID'] === $point) {
                $pt = $x;
            }
        }
        if ($pt === null) {
            return;   // schon gelöscht (z. B. doppelt angetippt)
        }
        if ((int) $pt['Start']['Hour'] === 0 && (int) $pt['Start']['Minute'] === 0) {
            $this->Notice('Der Schaltpunkt um 00:00 legt den Tagesbeginn fest und kann nicht gelöscht werden. Betriebsart dort bei Bedarf ändern.', true);
            return;
        }
        try {
            // ActionID -1 löscht den Schaltpunkt
            IPS_SetEventScheduleGroupPoint($sched['EventID'], $group, $point, -1, -1, -1, -1);
            $this->Notice(sprintf('Schaltpunkt %02d:%02d gelöscht', $pt['Start']['Hour'], $pt['Start']['Minute']), false,
                ['g' => $group, 'h' => (int) $pt['Start']['Hour'], 'm' => (int) $pt['Start']['Minute'], 'a' => (int) $pt['ActionID']]);
        } catch (Throwable $e) {
            $this->Notice('Schaltpunkt konnte nicht gelöscht werden: ' . $e->getMessage(), true);
        }
    }

    /** Hinweis an die Kachel (Fehler oder „gelöscht“ mit Rückgängig-Daten) */
    private function Notice(string $text, bool $error, ?array $undo = null): void
    {
        $this->UpdateVisualizationValue(json_encode(['type' => 'notice', 'text' => $text, 'error' => $error, 'undo' => $undo]));
    }

    /* ---------- Zustand ---------- */

    private function Push(): void
    {
        $this->UpdateVisualizationValue(json_encode($this->BuildState()));
    }

    private function BuildState(): array
    {
        $step = max(0.1, $this->ReadPropertyFloat('StepSize'));
        $state = ['design' => $this->DesignCSS(), 'step' => $step, 'hasActual' => $this->ReadPropertyInteger('ActualID') > 0, 'actual' => null, 'humidity' => null, 'target' => null, 'valve' => null, 'modes' => [], 'groups' => [], 'active' => false, 'current' => 0, 'error' => ''];
        $ex = $this->ReadAttributeInteger('Expanded');
        $state['expanded'] = $ex < 0 ? null : $ex === 1;
        // Regler-Symbol öffnet per openObject das Visu-Fenster des Sollwerts (Regler je Betriebsart + Wochenplan als Unterobjekte)
        $state['targetId'] = $this->ReadPropertyInteger('TargetID');
        $did = $this->ReadPropertyInteger('DetailID');
        $state['detailId'] = ($did > 0 && @IPS_ObjectExists($did)) ? $did : 0;
        $state['alwaysOpen'] = $this->ReadPropertyBoolean('AlwaysOpen');
        // Einstellungsseite: aktives Regler-Symbol führt zurück auf die übergeordnete Seite (Raum = Eltern der Kategorie, in der die Instanz liegt)
        $state['backId'] = 0;
        if ($state['alwaysOpen']) {
            $cat = IPS_GetParent($this->InstanceID);
            $up = $cat > 0 ? IPS_GetParent($cat) : 0;
            $state['backId'] = ($up > 0 && IPS_GetObject($up)['ObjectType'] === 0) ? $up : 0;
        }

        $aid = $this->ReadPropertyInteger('ActualID');
        if ($aid > 0 && @IPS_VariableExists($aid)) {
            $state['actual'] = (float) GetValue($aid);
        }
        $state['humidity'] = $this->Humidity($state['actual']);
        $tid = $this->ReadPropertyInteger('TargetID');
        if ($tid > 0 && @IPS_VariableExists($tid)) {
            $r = $this->Range($tid);
            $state['target'] = ['v' => (float) GetValue($tid), 'min' => $r[0], 'max' => $r[1]];
        }
        $vid = $this->ReadPropertyInteger('ValveID');
        if ($vid > 0 && @IPS_VariableExists($vid)) {
            $state['valve'] = (float) GetValue($vid);
        }

        $sched = $this->Schedule();
        if ($sched === null) {
            if ($this->ReadPropertyInteger('ScheduleID') > 0) {
                $state['error'] = 'Wochenplan nicht gefunden';
            }
            return $state;
        }
        $state['active'] = (bool) $sched['EventActive'];
        $state['current'] = $this->CurrentAction($sched);
        foreach ($sched['ScheduleActions'] as $a) {
            $mvid = (int) ($a['ActionParameters']['VARIABLE'] ?? 0);
            $mode = ['id' => (int) $a['ID'], 'name' => (string) $a['Name'], 'color' => sprintf('#%06X', ((int) $a['Color']) & 0xFFFFFF), 'v' => null];
            if ($mvid > 0 && @IPS_VariableExists($mvid)) {
                $r = $this->Range($mvid);
                $mode = ['id' => $mode['id'], 'name' => $mode['name'], 'color' => $mode['color'], 'v' => (float) GetValue($mvid), 'min' => $r[0], 'max' => $r[1]];
            }
            $state['modes'][] = $mode;
        }
        foreach ($sched['ScheduleGroups'] as $g) {
            $pts = [];
            foreach ($g['Points'] as $pt) {
                $pts[] = ['id' => (int) $pt['ID'], 'h' => (int) $pt['Start']['Hour'], 'm' => (int) $pt['Start']['Minute'], 'a' => (int) $pt['ActionID']];
            }
            usort($pts, fn ($x, $y) => ($x['h'] * 60 + $x['m']) <=> ($y['h'] * 60 + $y['m']));
            $state['groups'][] = ['id' => (int) $g['ID'], 'days' => (int) $g['Days'], 'points' => $pts];
        }
        return $state;
    }

    private function Schedule(): ?array
    {
        $eid = $this->ReadPropertyInteger('ScheduleID');
        if ($eid <= 0 || !@IPS_EventExists($eid)) {
            return null;
        }
        $e = IPS_GetEvent($eid);
        return $e['EventType'] === 2 ? $e : null;
    }

    private function ModeVariables(): array
    {
        $r = [];
        $sched = $this->Schedule();
        foreach ($sched['ScheduleActions'] ?? [] as $a) {
            $vid = (int) ($a['ActionParameters']['VARIABLE'] ?? 0);
            if ($vid > 0) {
                $r[] = $vid;
            }
        }
        return $r;
    }

    private function Group(array $sched, int $id): ?array
    {
        foreach ($sched['ScheduleGroups'] as $g) {
            if ((int) $g['ID'] === $id) {
                return $g;
            }
        }
        return null;
    }

    private function ActionExists(array $sched, int $id): bool
    {
        foreach ($sched['ScheduleActions'] as $a) {
            if ((int) $a['ID'] === $id) {
                return true;
            }
        }
        return false;
    }

    /** Aktion, die laut Wochenplan jetzt gilt (letzter Schaltpunkt vor jetzt, notfalls vom Vortag). */
    private function CurrentAction(array $sched): int
    {
        $now = (int) date('G') * 60 + (int) date('i');
        $day = (int) date('N') - 1; // 0 = Montag
        for ($back = 0; $back < 7; $back++) {
            $d = ($day - $back + 7) % 7;
            $best = null;
            foreach ($sched['ScheduleGroups'] as $g) {
                if (((int) $g['Days'] & (1 << $d)) === 0) {
                    continue;
                }
                foreach ($g['Points'] as $pt) {
                    $t = (int) $pt['Start']['Hour'] * 60 + (int) $pt['Start']['Minute'];
                    if (($back > 0 || $t <= $now) && ($best === null || $t >= $best[0])) {
                        $best = [$t, (int) $pt['ActionID']];
                    }
                }
            }
            if ($best !== null) {
                return $best[1];
            }
        }
        return (int) ($sched['LastActionID'] ?? 0);
    }

    /** [min, max] für Sollwert und Schieberegler (Eigenschaften der Instanz, für alle Variablen gleich) */
    private function Range(int $vid): array
    {
        $min = $this->ReadPropertyFloat('MinTemp');
        $max = $this->ReadPropertyFloat('MaxTemp');
        return $max > $min ? [$min, $max] : [$max, $min];
    }

    /** Sättigungsdampfdruck über Wasser in hPa (Magnus-Formel) */
    private static function SaturationPressure(float $t): float
    {
        return 6.112 * exp(17.62 * $t / (243.12 + $t));
    }

    /**
     * Luftfeuchte des Raumsensors. Weicht dessen Temperatur von der Ist-Temperatur ab, wird über die absolute Feuchte
     * (g/m³ beim Sensor) die relative Feuchte bei der Ist-Temperatur berechnet.
     */
    private function Humidity(?float $actual): ?array
    {
        $hid = $this->ReadPropertyInteger('HumidityID');
        if ($hid <= 0 || !@IPS_VariableExists($hid)) {
            return null;
        }
        $rh = (float) GetValue($hid);
        if ($rh <= 0 || $rh > 100) {
            return null;
        }
        $r = ['rh' => $rh, 'raw' => $rh, 'ts' => null, 'ah' => null];
        $tid = $this->ReadPropertyInteger('HumidityTempID');
        if ($tid > 0 && @IPS_VariableExists($tid)) {
            $ts = (float) GetValue($tid);
            $ah = 216.7 * ($rh / 100 * self::SaturationPressure($ts)) / (273.15 + $ts);
            $r['ts'] = $ts;
            $r['ah'] = round($ah, 1);
            if ($actual !== null && abs($actual - $ts) >= 0.05) {
                $r['rh'] = round(min(100, $ah * (273.15 + $actual) / (216.7 * self::SaturationPressure($actual)) * 100), 1);
            }
        }
        return $r;
    }

    private function Clamp(float $v, array $r): float
    {
        $step = max(0.1, $this->ReadPropertyFloat('StepSize'));
        $v = round($v / $step) * $step;
        return round(max($r[0], min($r[1], $v)), 2);
    }
}
