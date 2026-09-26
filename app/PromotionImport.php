<?php

namespace App;

use App\Models\AcademicYear;
use App\Models\SchoolClass;
use App\Models\Student;
use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Bulk promotion from an Excel file: NIS, Nama Siswa, Kelas Asal, Kelas Tujuan / Status.
 *
 * Every row is checked against the database before anything is saved (preview first, then confirm);
 * a row that cannot be applied is reported with its reason instead of being skipped silently.
 */
final class PromotionImport
{
    /** Columns of the template, in order. */
    public const HEADERS = ['NIS', 'Nama Siswa', 'Kelas Asal', 'Kelas Tujuan / Status'];

    /** Values accepted in the last column besides a class name. */
    public const GRADUATE = 'LULUS';

    public const RETAIN = 'TINGGAL';

    /** A whole school fits well within this; it also keeps one upload bounded. */
    public const MAX_ROWS = 2000;

    /**
     * Read and validate the uploaded file.
     *
     * @return Collection<int, array{row: int, nis: string, name: string, from: string, target: string, student: Student|null, result: PromotionResult|null, toClass: SchoolClass|null, error: string|null}>
     */
    public static function read(string $path, AcademicYear $academicYear): Collection
    {
        $sheet = IOFactory::load($path)->getActiveSheet();
        $rows = collect($sheet->toArray(null, true, false, false));

        // Drop the header row when it is there, plus any completely empty line.
        $rows = $rows
            ->reject(fn (array $row, int $index): bool => $index === 0 && self::looksLikeHeader($row))
            ->map(fn (array $row, int $index): array => ['row' => $index + 1, 'cells' => $row])
            ->reject(fn (array $row): bool => collect($row['cells'])->filter(fn ($cell): bool => filled($cell))->isEmpty())
            ->take(self::MAX_ROWS)
            ->values();

        $classes = SchoolClass::orderBy('class_name')->get();
        $students = Student::query()
            ->with('schoolClass')
            ->whereIn('nis', $rows->map(fn (array $row): string => self::cell($row['cells'], 0))->filter())
            ->get()
            ->keyBy('nis');

        $processedIds = $academicYear->promotions()->pluck('student_id')->all();
        $seen = [];

        return $rows->map(function (array $row) use ($classes, $students, $processedIds, &$seen): array {
            $nis = self::cell($row['cells'], 0);
            $target = self::cell($row['cells'], 3);
            $entry = [
                'row' => $row['row'],
                'nis' => $nis,
                'name' => self::cell($row['cells'], 1),
                'from' => self::cell($row['cells'], 2),
                'target' => $target,
                'student' => $students->get($nis),
                'result' => null,
                'toClass' => null,
                'error' => null,
            ];

            $entry['error'] = self::validate($entry, $classes, $processedIds, $seen);

            if ($entry['error'] === null) {
                [$entry['result'], $entry['toClass']] = self::decide($target, $classes);
                $seen[] = $nis;
            }

            return $entry;
        });
    }

    /**
     * The decision string used by the promotion form: a class id, "stay" or "graduate".
     *
     * @param  array{result: PromotionResult|null, toClass: SchoolClass|null}  $entry
     */
    public static function decisionFor(array $entry): string
    {
        return match ($entry['result']) {
            PromotionResult::Graduated => 'graduate',
            PromotionResult::Retained => 'stay',
            default => (string) $entry['toClass']->class_id,
        };
    }

    /**
     * The .xlsx template: one sheet to fill in, one sheet listing the valid class names and statuses.
     */
    public static function template(AcademicYear $academicYear): Spreadsheet
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet()->setTitle('Kenaikan Kelas');
        $sheet->fromArray(self::HEADERS, null, 'A1');

        $classes = SchoolClass::orderBy('class_name')->get();
        $sample = $classes->first();
        $sheet->fromArray([
            ['2026000123', 'Contoh: Budi Santoso', $sample?->class_name ?? 'Kelas 1A', $classes->get(1)?->class_name ?? 'Kelas 2A'],
            ['2026000124', 'Contoh: Siti Aminah', $sample?->class_name ?? 'Kelas 1A', self::RETAIN],
            ['2026000125', 'Contoh: Andi Saputra', $classes->last()?->class_name ?? 'Kelas 6A', self::GRADUATE],
        ], null, 'A2');

        foreach (range('A', 'D') as $column) {
            $sheet->getColumnDimension($column)->setWidth(26);
        }

        $sheet->getStyle('A1:D1')->getFont()->setBold(true);
        $sheet->getStyle('A1:D1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('E2E8F0');
        $sheet->getStyle('A1:D1')->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
        $sheet->getStyle('A2:A'.($sheet->getHighestRow()))->getNumberFormat()->setFormatCode('@');
        $sheet->freezePane('A2');

        $help = $spreadsheet->createSheet()->setTitle('Petunjuk');
        $help->fromArray([
            ['Cara mengisi'],
            ['1. Hapus tiga baris contoh, lalu isi satu baris per siswa.'],
            ['2. NIS harus sama persis dengan data siswa di aplikasi.'],
            ['3. Kolom "Kelas Asal" diisi kelas siswa saat ini (dipakai untuk pengecekan).'],
            ['4. Kolom "Kelas Tujuan / Status" diisi salah satu nama kelas di bawah, atau '.self::RETAIN.' (tinggal kelas), atau '.self::GRADUATE.' (lulus).'],
            ['5. Simpan sebagai .xlsx lalu unggah di halaman Kenaikan Kelas. Data hanya tersimpan setelah Anda menekan konfirmasi.'],
            [''],
            ['Tahun ajaran aktif', $academicYear->year_name],
            [''],
            ['Nama kelas yang valid'],
            ...$classes->map(fn (SchoolClass $class): array => [$class->class_name])->all(),
            [''],
            ['Status khusus'],
            [self::RETAIN, 'Siswa tinggal kelas (tetap di kelas asal)'],
            [self::GRADUATE, 'Siswa lulus (keluar dari daftar kelas aktif)'],
        ], null, 'A1');
        $help->getColumnDimension('A')->setWidth(34);
        $help->getColumnDimension('B')->setWidth(60);
        $help->getStyle('A1')->getFont()->setBold(true);

        $spreadsheet->setActiveSheetIndex(0);

        return $spreadsheet;
    }

    public static function write(Spreadsheet $spreadsheet): void
    {
        (new Xlsx($spreadsheet))->save('php://output');
    }

    /**
     * @param  Collection<int, SchoolClass>  $classes
     * @param  list<int>  $processedIds
     * @param  list<string>  $seen
     * @param  array{nis: string, target: string, student: Student|null}  $entry
     */
    private static function validate(array $entry, Collection $classes, array $processedIds, array $seen): ?string
    {
        $student = $entry['student'];

        return match (true) {
            $entry['nis'] === '' => 'NIS kosong.',
            in_array($entry['nis'], $seen, true) => 'NIS ganda di dalam file ini.',
            $student === null => 'NIS tidak ditemukan di data siswa.',
            ! $student->status->isEnrolled() => "Status siswa {$student->status->label()}, tidak ikut kenaikan kelas.",
            in_array($student->student_id, $processedIds, true) => 'Siswa sudah diproses di tahun ajaran ini.',
            $entry['from'] !== '' && mb_strtolower($entry['from']) !== mb_strtolower($student->schoolClass->class_name) => "Kelas asal tidak cocok; siswa terdaftar di {$student->schoolClass->class_name}.",
            $entry['target'] === '' => 'Kelas tujuan / status belum diisi.',
            self::decide($entry['target'], $classes)[0] === null => 'Kelas tujuan tidak dikenal. Gunakan nama kelas, '.self::RETAIN.', atau '.self::GRADUATE.'.',
            self::decide($entry['target'], $classes)[1]?->class_id === $student->class_id => 'Kelas tujuan sama dengan kelas asal. Gunakan '.self::RETAIN.' untuk tinggal kelas.',
            default => null,
        };
    }

    /**
     * @param  Collection<int, SchoolClass>  $classes
     * @return array{0: PromotionResult|null, 1: SchoolClass|null}
     */
    private static function decide(string $target, Collection $classes): array
    {
        $normalized = mb_strtoupper($target);

        if ($normalized === self::GRADUATE) {
            return [PromotionResult::Graduated, null];
        }

        if (str_starts_with($normalized, self::RETAIN)) {
            return [PromotionResult::Retained, null];
        }

        $class = $classes->first(fn (SchoolClass $class): bool => mb_strtolower($class->class_name) === mb_strtolower($target));

        return $class ? [PromotionResult::Promoted, $class] : [null, null];
    }

    /**
     * @param  array<int, mixed>  $cells
     */
    private static function cell(array $cells, int $index): string
    {
        return trim((string) ($cells[$index] ?? ''));
    }

    /**
     * @param  array<int, mixed>  $row
     */
    private static function looksLikeHeader(array $row): bool
    {
        return str_contains(mb_strtoupper((string) ($row[0] ?? '')), 'NIS');
    }
}
