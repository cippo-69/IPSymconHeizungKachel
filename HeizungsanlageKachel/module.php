<?php

declare(strict_types=1);

/**
 * Heizungsanlage-Kachel (HTML-SDK)
 *
 * Eine Kachel je Wärmeerzeuger: Betriebszustand, Außentemperatur, Vorlauf/Rücklauf mit Spreizung, Warmwasser,
 * aktive Meldungen, Verlauf der letzten 24 h, Gasverbrauch und Warmwasserladungen heute, frei konfigurierbare
 * Schaltfunktionen (Umschalten, Taste, Stufen) mit optionaler Bestätigung sowie weitere Werte.
 */
class HeizungsanlageKachel extends IPSModuleStrict
{
    private const AC_GUID = '{43192F0B-135B-4CE7-A0A7-1475603F3060}';
    private const DESIGN_GUID = '{018EEC85-8FFC-4E1F-A966-702C4BBDC2E8}';
    private const VAR_PROPS = ['OutsideID', 'FlowID', 'FlowTargetID', 'ReturnID', 'DhwID', 'DhwTargetID', 'BurnerID', 'PumpID', 'StatusID', 'ChargeID', 'GasID'];

    public function Create(): void
    {
        parent::Create();

        foreach (self::VAR_PROPS as $p) {
            $this->RegisterPropertyInteger($p, 0);
        }
        $this->RegisterPropertyFloat('ChargeThreshold', 0.0);   // W; 0 = keine Erkennung
        $this->RegisterPropertyFloat('GasFactor', 0.0);         // kWh/m³; 0 = nur m³
        $this->RegisterPropertyString('Alarms', '[]');
        $this->RegisterPropertyString('Controls', '[]');
        $this->RegisterPropertyString('Infos', '[]');

        $this->RegisterAttributeString('Watched', '[]');
        $this->RegisterAttributeString('Hist', '{}');

        $this->RegisterTimer('Refresh', 0, 'HZA_Refresh($_IPS[\'TARGET\']);');
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
        $ids = [];
        foreach (self::VAR_PROPS as $p) {
            $ids[] = $this->ReadPropertyInteger($p);
        }
        foreach ($this->Rows('Alarms') as $r) {
            $ids[] = (int) $r['ObjectID'];
        }
        foreach ($this->Rows('Infos') as $r) {
            $ids[] = (int) $r['ObjectID'];
        }
        foreach ($this->Rows('Controls') as $r) {
            $ids[] = (int) $r['ObjectID'];
            $ids[] = (int) ($r['ObjectID2'] ?? 0);
            $ids[] = (int) ($r['StateID'] ?? 0);
        }
        foreach (array_unique($ids) as $id) {
            if ($id <= 0 || !@IPS_ObjectExists($id)) {
                continue;
            }
            if (@IPS_VariableExists($id)) {
                // GasID ändert sich mit jedem Impuls; der Tageswert kommt aus dem Verlauf (Timer)
                $this->RegisterMessage($id, VM_UPDATE);
                $watch[] = [$id, VM_UPDATE];
                $this->RegisterReference($id);
            } elseif (@IPS_EventExists($id)) {
                $this->RegisterMessage($id, EM_CHANGEACTIVE);
                $watch[] = [$id, EM_CHANGEACTIVE];
                $this->RegisterReference($id);
            }
        }
        $design = $this->DesignID();
        if ($design > 0) {
            $this->RegisterMessage($design, IM_CHANGESETTINGS);
            $watch[] = [$design, IM_CHANGESETTINGS];
        }
        $this->WriteAttributeString('Watched', json_encode($watch));

        $fid = $this->ReadPropertyInteger('FlowID');
        $this->SetStatus($fid > 0 && @IPS_VariableExists($fid) ? IS_ACTIVE : 202);

        $this->SetTimerInterval('Refresh', 300 * 1000);
        if (IPS_GetKernelRunlevel() === KR_READY) {
            $this->Refresh();
        }
    }

    public function MessageSink(int $TimeStamp, int $SenderID, int $Message, array $Data): void
    {
        $this->Push();
    }

    public function GetVisualizationTile(): string
    {
        $initial = '<script>handleMessage(' . json_encode(json_encode($this->BuildState())) . ');</script>';
        return '<style id="w5d">' . $this->DesignCSS() . '</style>' . file_get_contents(__DIR__ . '/module.html') . $initial;
    }

    /** Verlauf 24 h, Gas heute und Warmwasserladungen aus dem Archiv (Timer alle 5 min) */
    public function Refresh(): void
    {
        $h = ['t' => time(), 'series' => [], 'gas' => null, 'charges' => null, 'chargeTimes' => []];
        $ac = $this->Archive();
        $to = time();
        $from = $to - 86400;
        if ($ac > 0) {
            foreach (['FlowID' => 'vl', 'ReturnID' => 'rl', 'DhwID' => 'ww'] as $p => $k) {
                $vid = $this->ReadPropertyInteger($p);
                if ($vid <= 0 || !@IPS_VariableExists($vid) || !AC_GetLoggingStatus($ac, $vid)) {
                    continue;
                }
                $pts = [];
                foreach (array_reverse((array) @AC_GetAggregatedValues($ac, $vid, 5, $from, $to, 0)) as $r) {
                    $pts[] = [(int) $r['TimeStamp'], round((float) $r['Avg'], 1)];
                }
                $h['series'][$k] = $pts;
            }
            $gid = $this->ReadPropertyInteger('GasID');
            if ($gid > 0 && @IPS_VariableExists($gid) && AC_GetLoggingStatus($ac, $gid)) {
                $d = (array) @AC_GetAggregatedValues($ac, $gid, 1, strtotime('today'), $to, 1);
                $h['gas'] = isset($d[0]) ? round((float) $d[0]['Avg'], 2) : 0.0;
            }
            $cid = $this->ReadPropertyInteger('ChargeID');
            $thr = $this->ReadPropertyFloat('ChargeThreshold');
            if ($cid > 0 && $thr > 0 && @IPS_VariableExists($cid) && AC_GetLoggingStatus($ac, $cid)) {
                $vals = array_reverse((array) @AC_GetLoggedValues($ac, $cid, $from - 3600, $to, 0));
                $above = false;
                $first = true;
                $today = strtotime('today');
                $n = 0;
                foreach ($vals as $r) {
                    $on = (float) $r['Value'] >= $thr;
                    $ts = (int) $r['TimeStamp'];
                    if ($on && !$above && !$first && $ts >= $from) {
                        $h['chargeTimes'][] = $ts;
                        if ($ts >= $today) {
                            $n++;
                        }
                    }
                    $above = $on;
                    $first = false;
                }
                $h['charges'] = $n;
            }
        }
        $this->WriteAttributeString('Hist', json_encode($h));
        $this->Push();
    }

    public function RequestAction(string $Ident, mixed $Value): void
    {
        switch ($Ident) {
            case 'Control':
                $p = is_string($Value) ? json_decode($Value, true) : null;
                if (is_array($p)) {
                    $this->Control((int) ($p['i'] ?? -1), (int) ($p['d'] ?? 0));
                }
                break;
            case 'Refresh':
                $this->Refresh();
                return;
            default:
                throw new Exception('Unbekannte Aktion: ' . $Ident);
        }
        $this->Push();
    }

    /* ---------- Schaltfunktionen ---------- */

    private function Control(int $index, int $dir): void
    {
        $rows = $this->Rows('Controls');
        if (!isset($rows[$index])) {
            return;
        }
        $r = $rows[$index];
        $obj = (int) $r['ObjectID'];
        switch ((string) ($r['Type'] ?? 'toggle')) {
            case 'toggle':
                if (@IPS_VariableExists($obj)) {
                    RequestAction($obj, !(bool) GetValue($obj));
                } elseif (@IPS_EventExists($obj)) {
                    $new = !IPS_GetEvent($obj)['EventActive'];
                    IPS_SetEventActive($obj, $new);
                    $o2 = (int) ($r['ObjectID2'] ?? 0);
                    if ($o2 > 0 && @IPS_EventExists($o2)) {
                        IPS_SetEventActive($o2, $new);
                    }
                }
                break;
            case 'button':
                if (@IPS_VariableExists($obj)) {
                    RequestAction($obj, $this->Typed($obj, (float) ($r['Value'] ?? 1)));
                }
                break;
            case 'step':
                if (@IPS_VariableExists($obj) && $dir !== 0) {
                    $sid = (int) ($r['StateID'] ?? 0);
                    $cur = (float) GetValue($sid > 0 && @IPS_VariableExists($sid) ? $sid : $obj);
                    $step = max(0.1, (float) ($r['Step'] ?? 1));
                    $v = round(($cur + $dir * $step) / $step) * $step;
                    $v = max((float) ($r['Min'] ?? 0), min((float) ($r['Max'] ?? 100), $v));
                    RequestAction($obj, $this->Typed($obj, $v));
                }
                break;
        }
        IPS_LogMessage('Heizungsanlage Kachel', IPS_GetName($this->InstanceID) . ': ' . (string) $r['Caption'] . ' (' . ($r['Type'] ?? 'toggle') . ($dir !== 0 ? ', ' . $dir : '') . ')');
    }

    private function Typed(int $vid, float $v): mixed
    {
        switch (IPS_GetVariable($vid)['VariableType']) {
            case 0: return $v != 0;
            case 1: return (int) round($v);
            case 2: return $v;
            default: return (string) $v;
        }
    }

    /* ---------- Zustand ---------- */

    private function Push(): void
    {
        $this->UpdateVisualizationValue(json_encode($this->BuildState()));
    }

    private function BuildState(): array
    {
        $s = [
            'design' => $this->DesignCSS(),
            'at' => $this->Num('OutsideID'),
            'vl' => $this->Num('FlowID'), 'vlSoll' => $this->Num('FlowTargetID'), 'rl' => $this->Num('ReturnID'),
            'ww' => $this->Num('DhwID'), 'wwSoll' => $this->Num('DhwTargetID'),
            'alarms' => [], 'controls' => [], 'infos' => [], 'state' => ['k' => 'idle', 't' => 'Bereit', 'x' => ''],
        ];

        foreach ($this->Rows('Alarms') as $r) {
            $id = (int) $r['ObjectID'];
            if ($id > 0 && @IPS_VariableExists($id) && (bool) GetValue($id)) {
                $s['alarms'][] = ['t' => (string) $r['Caption'] !== '' ? (string) $r['Caption'] : IPS_GetName($id), 'since' => IPS_GetVariable($id)['VariableChanged']];
            }
        }
        foreach ($this->Rows('Infos') as $r) {
            $id = (int) $r['ObjectID'];
            if ($id > 0 && @IPS_VariableExists($id)) {
                $s['infos'][] = ['t' => (string) $r['Caption'] !== '' ? (string) $r['Caption'] : IPS_GetName($id), 'v' => GetValueFormatted($id)];
            }
        }
        foreach ($this->Rows('Controls') as $i => $r) {
            $s['controls'][] = $this->ControlState($i, $r);
        }

        // Betriebszustand
        $burner = $this->Num('BurnerID');
        $pump = $this->Num('PumpID');
        $charge = $this->Num('ChargeID');
        $thr = $this->ReadPropertyFloat('ChargeThreshold');
        $status = '';
        $sid = $this->ReadPropertyInteger('StatusID');
        if ($sid > 0 && @IPS_VariableExists($sid)) {
            $status = trim(explode(' – ', (string) GetValue($sid))[0]);
        }
        if (count($s['alarms']) > 0) {
            $s['state'] = ['k' => 'alarm', 't' => count($s['alarms']) === 1 ? $s['alarms'][0]['t'] : count($s['alarms']) . ' Meldungen', 'x' => $status];
        } elseif ($charge !== null && $thr > 0 && $charge >= $thr) {
            $s['state'] = ['k' => 'dhw', 't' => 'Warmwasserladung', 'x' => ''];
        } elseif ($burner !== null && $burner > 0) {
            $s['state'] = ['k' => 'burn', 't' => 'Brenner ' . GetValueFormatted($this->ReadPropertyInteger('BurnerID')), 'x' => ''];
        } elseif ($status !== '') {
            $k = stripos($status, 'Brenner') === 0 ? 'burn' : (stripos($status, 'Pumpe läuft') === 0 ? 'pump' : 'idle');
            $s['state'] = ['k' => $k, 't' => $status, 'x' => ''];
        } elseif ($pump !== null && $pump > 0) {
            $s['state'] = ['k' => 'pump', 't' => 'Pumpe läuft, Brenner aus', 'x' => ''];
        }

        $h = json_decode($this->ReadAttributeString('Hist'), true);
        $s['hist'] = is_array($h) ? $h : [];
        $s['gasFactor'] = $this->ReadPropertyFloat('GasFactor');
        return $s;
    }

    private function ControlState(int $i, array $r): array
    {
        $obj = (int) $r['ObjectID'];
        $type = (string) ($r['Type'] ?? 'toggle');
        $c = ['i' => $i, 'type' => $type, 't' => (string) $r['Caption'], 'g' => (string) ($r['Group'] ?? ''), 'confirm' => (bool) ($r['Confirm'] ?? false), 'ok' => false, 'on' => false, 'v' => ''];
        if ($obj <= 0 || !@IPS_ObjectExists($obj)) {
            return $c;
        }
        $c['ok'] = true;
        $sid = (int) ($r['StateID'] ?? 0);
        $stateVar = $sid > 0 && @IPS_VariableExists($sid) ? $sid : 0;
        switch ($type) {
            case 'toggle':
                if (@IPS_EventExists($obj)) {
                    $c['on'] = (bool) IPS_GetEvent($obj)['EventActive'];
                } elseif (@IPS_VariableExists($obj)) {
                    $c['on'] = (bool) GetValue($stateVar ?: $obj);
                }
                break;
            case 'button':
                $c['on'] = $stateVar > 0 && (bool) GetValue($stateVar);
                break;
            case 'step':
                $c['v'] = GetValueFormatted($stateVar ?: $obj);
                break;
        }
        return $c;
    }

    private function Num(string $prop): ?float
    {
        $id = $this->ReadPropertyInteger($prop);
        if ($id <= 0 || !@IPS_VariableExists($id)) {
            return null;
        }
        $v = GetValue($id);
        return is_numeric($v) || is_bool($v) ? (float) $v : null;
    }

    private function Rows(string $prop): array
    {
        $r = json_decode($this->ReadPropertyString($prop), true);
        return is_array($r) ? array_values($r) : [];
    }

    private function Archive(): int
    {
        $l = IPS_GetInstanceListByModuleID(self::AC_GUID);
        return $l[0] ?? 0;
    }

    private function DesignID(): int
    {
        if (!IPS_ModuleExists(self::DESIGN_GUID)) {
            return 0;
        }
        $ids = IPS_GetInstanceListByModuleID(self::DESIGN_GUID);
        sort($ids);
        return $ids[0] ?? 0;
    }

    private function DesignCSS(): string
    {
        $id = $this->DesignID();
        return $id > 0 && function_exists('W5D_GetCSS') ? W5D_GetCSS($id) : '';
    }
}
