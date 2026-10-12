<?php

namespace App\Imports;

use App\Models\AcademicYear;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Bulk creation of master data from an Excel file. One subclass per module describes its columns and checks one
 * row; the flow is shared: download the template, upload it filled in, look at the preview, confirm.
 *
 * Every row is checked against the database before anything is saved. A row that cannot be created is reported
 * with its reason and left out; it never stops the rows that are fine.
 */
abstract class MasterImport
{
    /** A whole school fits well within this; it also keeps one upload bounded. */
    public const MAX_ROWS = 2000;

    /** Importers by the name used in the URL (/admin/impor/{type}). */
    public const TYPES = [
        'guru' => TeacherImport::class,
        'kelas' => SchoolClassImport::class,
        'siswa' => StudentImport::class,
        'wali-kelas' => HomeroomImport::class,
        'mapel' => SubjectImport::class,
    ];

    /** The active academic year, for the importers that need one. */
    protected ?AcademicYear $academicYear = null;

    public static function for(string $type): static
    {
        return new (self::TYPES[$type]);
    }

    public function forYear(AcademicYear $academicYear): static
    {
        $this->academicYear = $academicYear;

        return $this;
    }

    /** What is being imported, as shown to the admin: "Data Siswa". */
    abstract public function title(): string;

    /** One record in a sentence: "siswa", "akun guru". */
    abstract public function noun(): string;

    /** Route name of the page the admin returns to. */
    abstract public function indexRoute(): string;

    /**
     * Column headings of the template, in order.
     *
     * @return list<string>
     */
    abstract public function headers(): array;

    /**
     * Example rows for the template.
     *
     * @return list<list<string>>
     */
    abstract public function samples(): array;

    /**
     * How to fill in the columns, one line each.
     *
     * @return list<string>
     */
    abstract public function notes(): array;

    /**
     * Valid values the admin can copy from, per heading: ['Nama kelas yang valid' => ['Kelas 1A', ...]].
     *
     * @return array<string, list<string>>
     */
    public function references(): array
    {
        return [];
    }

    /** Homeroom teachers and subjects belong to the active academic year. */
    public function needsActiveYear(): bool
    {
        return false;
    }

    /**
     * Columns whose content must not be shown back on the preview page (passwords).
     *
     * @return list<int>
     */
    public function hiddenColumns(): array
    {
        return [];
    }

    /**
     * Load what the row checks need (existing names, classes, teachers) once per file.
     *
     * @param  Collection<int, list<string>>  $rows
     */
    protected function prepare(Collection $rows): void {}

    /**
     * Check one row. Returns the attributes to create, or the reason the row cannot be used.
     *
     * @param  list<string>  $cells
     * @return array{0: array<string, mixed>|null, 1: string|null}
     */
    abstract protected function check(array $cells): array;

    /**
     * Create one record from the attributes check() returned.
     *
     * @param  array<string, mixed>  $attributes
     */
    abstract protected function create(array $attributes): void;

    /**
     * The rows of an uploaded file as plain strings, without the heading row and empty lines.
     *
     * @return Collection<int, array{row: int, cells: list<string>}>
     */
    public function read(string $path): Collection
    {
        $columns = count($this->headers());
        $rows = collect(IOFactory::load($path)->getActiveSheet()->toArray(null, true, false, false));
        $firstHeading = mb_strtolower($this->headers()[0]);

        return $rows
            ->map(fn (array $row, int $index): array => [
                'row' => $index + 1,
                'cells' => array_map(self::text(...), array_pad(array_slice($row, 0, $columns), $columns, null)),
            ])
            ->reject(fn (array $row): bool => $row['row'] === 1 && mb_strtolower($row['cells'][0]) === $firstHeading)
            ->reject(fn (array $row): bool => implode('', $row['cells']) === '')
            ->take(self::MAX_ROWS)
            ->values();
    }

    /**
     * Check every row. Rows that pass carry the attributes to create; the others carry the reason.
     *
     * @param  Collection<int, array{row: int, cells: list<string>}>  $rows
     * @return Collection<int, array{row: int, cells: list<string>, attributes: array<string, mixed>|null, error: string|null}>
     */
    public function validate(Collection $rows): Collection
    {
        $this->prepare($rows->pluck('cells'));

        return $rows->map(function (array $row): array {
            [$attributes, $error] = $this->check($row['cells']);

            return [...$row, 'attributes' => $attributes, 'error' => $error];
        });
    }

    /**
     * Create every valid entry in one transaction and return how many were created.
     *
     * @param  Collection<int, array{attributes: array<string, mixed>|null, error: string|null}>  $entries
     */
    public function store(Collection $entries): int
    {
        $valid = $entries->whereNull('error');

        DB::transaction(fn () => $valid->each(fn (array $entry) => $this->create($entry['attributes'])));

        return $valid->count();
    }

    /**
     * The .xlsx template: one sheet to fill in, one sheet with instructions and the valid values.
     */
    public function template(): Spreadsheet
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet()->setTitle(mb_substr($this->title(), 0, 31));
        $headers = $this->headers();
        $lastColumn = chr(ord('A') + count($headers) - 1);

        $sheet->fromArray($headers, null, 'A1');
        $sheet->fromArray($this->samples(), null, 'A2');

        foreach (range('A', $lastColumn) as $column) {
            $sheet->getColumnDimension($column)->setWidth(28);
        }

        $sheet->getStyle("A1:{$lastColumn}1")->getFont()->setBold(true);
        $sheet->getStyle("A1:{$lastColumn}1")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('E2E8F0');
        $sheet->getStyle("A1:{$lastColumn}1")->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
        // Text format, so Excel keeps a NIS such as 0012345 exactly as typed.
        $sheet->getStyle("A2:{$lastColumn}".self::MAX_ROWS)->getNumberFormat()->setFormatCode('@');
        $sheet->freezePane('A2');

        $help = $spreadsheet->createSheet()->setTitle('Petunjuk');
        $lines = [['Cara mengisi'], ['1. Hapus baris contoh, lalu isi satu baris per '.$this->noun().'.']];

        foreach ($this->notes() as $index => $note) {
            $lines[] = [($index + 2).'. '.$note];
        }

        $lines[] = [(count($this->notes()) + 2).'. Simpan sebagai .xlsx lalu unggah lewat tombol Impor Excel. Data baru tersimpan setelah Anda menekan konfirmasi.'];

        foreach ($this->references() as $heading => $values) {
            $lines[] = [''];
            $lines[] = [$heading];

            foreach ($values as $value) {
                $lines[] = [$value];
            }
        }

        $help->fromArray($lines, null, 'A1');
        $help->getColumnDimension('A')->setWidth(90);
        $help->getStyle('A1')->getFont()->setBold(true);
        $spreadsheet->setActiveSheetIndex(0);

        return $spreadsheet;
    }

    public static function write(Spreadsheet $spreadsheet): void
    {
        (new Xlsx($spreadsheet))->save('php://output');
    }

    /**
     * A cell as trimmed text. Excel hands whole numbers over as floats (2026000123.0), which must not end up
     * as "2.026000123E+9" in a NIS.
     */
    private static function text(mixed $value): string
    {
        if (is_float($value) && floor($value) === $value) {
            return number_format($value, 0, '', '');
        }

        return trim((string) $value);
    }
}
