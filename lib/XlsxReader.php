<?php
/**
 * Minimal, dependency-free .xlsx reader built on PHP's ZipArchive + SimpleXML.
 * Returns every sheet (including hidden ones) in workbook order as arrays of rows.
 */
declare(strict_types=1);

class XlsxReader
{
    /** @return array<string, array<int, array<int,string>>> sheetName => rows */
    public static function read(string $path): array
    {
        $zip = new ZipArchive();
        if ($zip->open($path) !== true) {
            throw new RuntimeException('Cannot open xlsx file: ' . basename($path));
        }

        $shared = self::loadSharedStrings($zip);
        $sheets = self::mapSheets($zip);

        $out = [];
        foreach ($sheets as $name => $target) {
            $out[$name] = self::readSheet($zip, $shared, $target);
        }
        $zip->close();
        return $out;
    }

    private static function loadSharedStrings(ZipArchive $zip): array
    {
        $raw = $zip->getFromName('xl/sharedStrings.xml');
        if ($raw === false) {
            return [];
        }
        $xml = simplexml_load_string($raw);
        $strings = [];
        foreach ($xml->si as $si) {
            $text = '';
            if (count($si->r)) {
                foreach ($si->r as $r) {
                    $text .= (string) $r->t;
                }
            } else {
                $text = (string) $si->t;
            }
            $strings[] = $text;
        }
        return $strings;
    }

    /** @return array<string,string> sheetName => internal path */
    private static function mapSheets(ZipArchive $zip): array
    {
        $wb   = $zip->getFromName('xl/workbook.xml');
        $rels = $zip->getFromName('xl/_rels/workbook.xml.rels');

        preg_match_all('/<(?:\w+:)?sheet[^>]*name="([^"]*)"[^>]*r:id="([^"]*)"/', $wb, $sm, PREG_SET_ORDER);
        preg_match_all('/<Relationship[^>]*Id="([^"]*)"[^>]*Target="([^"]*)"/', $rels, $rm, PREG_SET_ORDER);

        $relMap = [];
        foreach ($rm as $r) {
            $relMap[$r[1]] = $r[2];
        }

        $sheets = [];
        foreach ($sm as $s) {
            $target = $relMap[$s[2]] ?? '';
            if ($target === '') {
                continue;
            }
            if ($target[0] !== '/') {
                $target = 'xl/' . $target;
            }
            $sheets[html_entity_decode($s[1], ENT_QUOTES)] = ltrim($target, '/');
        }
        return $sheets;
    }

    private static function readSheet(ZipArchive $zip, array $shared, string $path): array
    {
        $raw = $zip->getFromName($path);
        if ($raw === false) {
            return [];
        }
        $xml  = simplexml_load_string($raw);
        $rows = [];
        if (!$xml || !isset($xml->sheetData)) {
            return [];
        }
        foreach ($xml->sheetData->row as $row) {
            $cells = [];
            foreach ($row->c as $c) {
                $ref = (string) $c['r'];
                $ci  = self::colIndex($ref);
                $t   = (string) $c['t'];
                if ($t === 'inlineStr') {
                    $val = (string) $c->is->t;
                } else {
                    $val = (string) $c->v;
                    if ($t === 's') {
                        $val = $shared[(int) $val] ?? '';
                    }
                }
                $cells[$ci] = $val;
            }
            if ($cells) {
                $max  = max(array_keys($cells));
                $line = [];
                for ($i = 0; $i <= $max; $i++) {
                    $line[] = $cells[$i] ?? '';
                }
                $rows[] = $line;
            } else {
                $rows[] = [];
            }
        }
        return $rows;
    }

    private static function colIndex(string $ref): int
    {
        preg_match('/^([A-Z]+)/', $ref, $m);
        $s = $m[1] ?? 'A';
        $n = 0;
        for ($i = 0, $len = strlen($s); $i < $len; $i++) {
            $n = $n * 26 + (ord($s[$i]) - 64);
        }
        return $n - 1;
    }
}
