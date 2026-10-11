<?php

namespace App\Actions\Reports;

use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportOperationalReport
{
    /** @param array<int, array<string, mixed>> $rows */
    public function download(array $rows, string $format): StreamedResponse
    {
        $columns = ['driver' => 'Driver', 'shifts' => 'Confirmed shifts', 'adjusted' => 'Boundary PHP', 'paid' => 'Allocated PHP', 'outstanding' => 'Outstanding PHP', 'collected' => 'Collections PHP', 'unallocated' => 'Unallocated PHP'];

        return response()->streamDownload(function () use ($rows, $format, $columns): void {
            if ($format === 'csv') {
                $output = fopen('php://output', 'w');
                if ($output === false) {
                    throw new \RuntimeException('Unable to open report output.');
                }
                fputcsv($output, array_values($columns), ',', '"', '');
                foreach ($rows as $row) {
                    fputcsv($output, array_map(function ($key) use ($row): string {
                        $value = (string) $row[$key];

                        return preg_match('/^[=+@\-\t\r]/', $value) ? "'".$value : $value;
                    }, array_keys($columns)), ',', '"', '');
                }
                fclose($output);

                return;
            }
            echo '<?xml version="1.0" encoding="UTF-8"?><?mso-application progid="Excel.Sheet"?>';
            echo '<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet" xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet"><Worksheet ss:Name="Operations"><Table>';
            echo '<Row>';
            foreach ($columns as $label) {
                echo '<Cell><Data ss:Type="String">'.htmlspecialchars($label, ENT_XML1 | ENT_QUOTES, 'UTF-8').'</Data></Cell>';
            }
            echo '</Row>';
            foreach ($rows as $row) {
                echo '<Row>';
                foreach (array_keys($columns) as $key) {
                    echo '<Cell><Data ss:Type="'.($key === 'driver' ? 'String' : 'Number').'">'.htmlspecialchars((string) $row[$key], ENT_XML1 | ENT_QUOTES, 'UTF-8').'</Data></Cell>';
                }
                echo '</Row>';
            }
            echo '</Table></Worksheet></Workbook>';
        }, 'PasadaTrack_Operations.'.($format === 'csv' ? 'csv' : 'xml'), [
            'Content-Type' => $format === 'csv' ? 'text/csv; charset=UTF-8' : 'application/xml; charset=UTF-8',
        ]);
    }
}
