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
        $this->RegisterPropertyFloat('StepSize', 0.5);
        $this->RegisterPropertyFloat('MinTemp', 10.0);     // Fallback, wenn die Variable keine Grenzen hat
        $this->RegisterPropertyFloat('MaxTemp', 28.0);
        $this->RegisterPropertyBoolean('ApplyToCurrent', true);
        $this->RegisterPropertyInteger('AccentColor', 0xE8590C);

        $this->RegisterAttributeString('Watched', '[]');

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
        $vars = [$this->ReadPropertyInteger('ActualID'), $this->ReadPropertyInteger('TargetID'), $this->ReadPropertyInteger('ValveID')];
        $eid = $this->ReadPropertyInteger('ScheduleID');
        $ok = $eid > 0 && @IPS_EventExists($eid) && IPS_GetEvent($eid)['EventType'] === 2;
        if ($ok) {
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
        $accent = sprintf('#%06X', $this->ReadPropertyInteger('AccentColor') & 0xFFFFFF);
        $initial = '<script>handleMessage(' . json_encode(json_encode($this->BuildState())) . ');</script>';
        return file_get_contents(__DIR__ . '/module.html') . '<style>:root{--hz-accent:' . $accent . ';}</style>' . $initial;
    }

    public function RequestAction(string $Ident, mixed $Value): void
    {
        $p = is_string($Value) ? json_decode($Value, true) : null;
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
        IPS_SetEventScheduleGroupPoint($sched['EventID'], $group, $point, $h, $m, 0, $action);
    }

    private function DeletePoint(int $group, int $point): void
    {
        $sched = $this->Schedule();
        if ($sched === null || $this->Group($sched, $group) === null) {
            return;
        }
        // ActionID -1 löscht den Schaltpunkt
        IPS_SetEventScheduleGroupPoint($sched['EventID'], $group, $point, -1, -1, -1, -1);
    }

    /* ---------- Zustand ---------- */

    private function Push(): void
    {
        $this->UpdateVisualizationValue(json_encode($this->BuildState()));
    }

    private function BuildState(): array
    {
        $step = max(0.1, $this->ReadPropertyFloat('StepSize'));
        $state = ['step' => $step, 'actual' => null, 'target' => null, 'valve' => null, 'modes' => [], 'groups' => [], 'active' => false, 'current' => 0, 'error' => ''];

        $aid = $this->ReadPropertyInteger('ActualID');
        if ($aid > 0 && @IPS_VariableExists($aid)) {
            $state['actual'] = (float) GetValue($aid);
        }
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
            $state['error'] = 'Kein Wochenplan ausgewählt';
            return $state;
        }
        $state['active'] = (bool) $sched['EventActive'];
        $state['current'] = $this->CurrentAction($sched);
        foreach ($sched['ScheduleActions'] as $a) {
            $mvid = (int) ($a['ActionParameters']['VARIABLE'] ?? 0);
            $mode = ['id' => (int) $a['ID'], 'name' => (string) $a['Name'], 'color' => sprintf('#%06X', ((int) $a['Color']) & 0xFFFFFF), 'v' => null];
            if ($mvid > 0 && @IPS_VariableExists($mvid)) {
                $r = $this->Range($mvid);
                $mode += ['v' => (float) GetValue($mvid), 'min' => $r[0], 'max' => $r[1]];
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

    /** [min, max] aus Darstellung bzw. Profil der Variable, sonst aus den Eigenschaften */
    private function Range(int $vid): array
    {
        $min = null;
        $max = null;
        if (function_exists('IPS_GetVariablePresentation')) {
            $p = @IPS_GetVariablePresentation($vid);
            if (is_array($p) && isset($p['MIN'], $p['MAX']) && (float) $p['MAX'] > (float) $p['MIN']) {
                $min = (float) $p['MIN'];
                $max = (float) $p['MAX'];
            }
        }
        if ($min === null) {
            $v = IPS_GetVariable($vid);
            $prof = $v['VariableCustomProfile'] !== '' ? $v['VariableCustomProfile'] : $v['VariableProfile'];
            if ($prof !== '' && IPS_VariableProfileExists($prof)) {
                $pp = IPS_GetVariableProfile($prof);
                if ($pp['MaxValue'] > $pp['MinValue']) {
                    $min = (float) $pp['MinValue'];
                    $max = (float) $pp['MaxValue'];
                }
            }
        }
        return [$min ?? $this->ReadPropertyFloat('MinTemp'), $max ?? $this->ReadPropertyFloat('MaxTemp')];
    }

    private function Clamp(float $v, array $r): float
    {
        $step = max(0.1, $this->ReadPropertyFloat('StepSize'));
        $v = round($v / $step) * $step;
        return round(max($r[0], min($r[1], $v)), 2);
    }
}
