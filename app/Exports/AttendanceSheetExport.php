<?php

namespace App\Exports;

use App\Models\AcademicYear;
use App\Models\Subject;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * The attendance recap as the school's own "Daftar Hadir Siswa" sheet: one worksheet per class and month, a
 * row per student, a column per date with H / S / I / A, and the totals of the month in the REKAP columns.
 *
 * Attendance is recorded per subject, the sheet has one mark per day. When a class had several subjects on a
 * day, the mark is the status recorded most often that day; a tie goes to the more serious one (A, S, I, H).
 */
final class AttendanceSheetExport
{
    /** Letter on the sheet per status, in the order of the REKAP columns. */
    private const LETTERS = ['hadir' => 'H', 'sakit' => 'S', 'izin' => 'I', 'alpha' => 'A'];

    /** Which status wins a tie, most serious first. */
    private const SEVERITY = ['alpha', 'sakit', 'izin', 'hadir'];

    private const HEADER_ROW = 7;

    public function __construct(
        private readonly AcademicYear $academicYear,
        private readonly CarbonImmutable $dateFrom,
        private readonly CarbonImmutable $dateTo,
        private readonly ?Subject $subject = null,
    ) {}

    /**
     * @param  Collection<int, object{student_id: int, nis: string, name: string, gender: string, class_id: int, class_name: string, date: string, status: string}>  $records
     */
    public function build(Collection $records): Spreadsheet
    {
        $spreadsheet = new Spreadsheet;
        $spreadsheet->removeSheetByIndex(0);
        $spreadsheet->getDefaultStyle()->getFont()->setName('Calibri')->setSize(10);

        $records
            ->sortBy('class_name', SORT_NATURAL)
            ->groupBy('class_id')
            ->each(function (Collection $classRecords) use ($spreadsheet): void {
                // The same students on every month's sheet of a class, also in a month one of them has no record.
                $students = $classRecords->unique('student_id')->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)->values();
                $byMonth = $classRecords->groupBy(fn (object $record): string => substr($record->date, 0, 7));

                foreach ($this->months() as $month) {
                    if ($byMonth->has($month->format('Y-m'))) {
                        $this->sheet($spreadsheet, $classRecords->first()->class_name, $month, $students, $byMonth->get($month->format('Y-m')));
                    }
                }
            });

        if ($spreadsheet->getSheetCount() > 0) {
            $spreadsheet->setActiveSheetIndex(0);
        }

        return $spreadsheet;
    }

    public static function write(Spreadsheet $spreadsheet): void
    {
        (new Xlsx($spreadsheet))->save('php://output');
    }

    /**
     * First day of every month the period touches.
     *
     * @return list<CarbonImmutable>
     */
    private function months(): array
    {
        $months = [];

        for ($month = $this->dateFrom->startOfMonth(); $month->lte($this->dateTo); $month = $month->addMonth()) {
            $months[] = $month;
        }

        return $months;
    }

    /**
     * @param  Collection<int, object>  $students
     * @param  Collection<int, object>  $records  the class's records of this month
     */
    private function sheet(Spreadsheet $spreadsheet, string $className, CarbonImmutable $month, Collection $students, Collection $records): void
    {
        $days = $month->daysInMonth;
        $firstDayColumn = 5;
        $firstRecapColumn = $firstDayColumn + $days;
        $lastColumn = $firstRecapColumn + count(self::LETTERS) - 1;
        $col = Coordinate::stringFromColumnIndex(...);
        $last = $col($lastColumn);
        $headerRow = self::HEADER_ROW;
        $firstDataRow = $headerRow + 2;

        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle($this->sheetTitle($spreadsheet, $className, $month));

        $this->letterhead($sheet, $last, $month);

        $sheet->setCellValue('A6', 'KELAS  : '.$className.($this->subject ? '     MAPEL  : '.$this->subject->subject_name : ''));
        $sheet->mergeCells('A6:D6');
        $bulanStart = $col(max($firstDayColumn, $lastColumn - 11));
        $sheet->setCellValue("{$bulanStart}6", 'BULAN  : '.$month->locale('id')->translatedFormat('F Y'));
        $sheet->mergeCells("{$bulanStart}6:{$last}6");
        $sheet->getStyle("{$bulanStart}6")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        $sheet->getStyle("A6:{$last}6")->getFont()->setSize(10);

        // Table heading: NOMOR (number + NIS) | NAMA SISWA | L/P | TANGGAL 1..n | REKAP H S I A.
        $next = $headerRow + 1;
        $sheet->setCellValue("A{$headerRow}", 'NOMOR')->mergeCells("A{$headerRow}:B{$next}");
        $sheet->setCellValue("C{$headerRow}", 'NAMA SISWA')->mergeCells("C{$headerRow}:C{$next}");
        $sheet->setCellValue("D{$headerRow}", 'L/P')->mergeCells("D{$headerRow}:D{$next}");
        $sheet->setCellValue($col($firstDayColumn).$headerRow, 'TANGGAL')->mergeCells($col($firstDayColumn).$headerRow.':'.$col($firstRecapColumn - 1).$headerRow);
        $sheet->setCellValue($col($firstRecapColumn).$headerRow, 'REKAP')->mergeCells($col($firstRecapColumn).$headerRow.':'.$last.$headerRow);

        for ($day = 1; $day <= $days; $day++) {
            $sheet->setCellValue($col($firstDayColumn + $day - 1).$next, $day);
        }

        foreach (array_values(self::LETTERS) as $index => $letter) {
            $sheet->setCellValue($col($firstRecapColumn + $index).$next, $letter);
        }

        $marks = $this->marks($records);

        foreach ($students as $index => $student) {
            $row = $firstDataRow + $index;
            $studentMarks = $marks[$student->student_id] ?? [];

            $sheet->setCellValue("A{$row}", $index + 1);
            $sheet->setCellValueExplicit("B{$row}", (string) $student->nis, DataType::TYPE_STRING);
            $sheet->setCellValue("C{$row}", $student->name);
            $sheet->setCellValue("D{$row}", $student->gender);

            foreach ($studentMarks as $day => $status) {
                $sheet->setCellValue($col($firstDayColumn + $day - 1).$row, self::LETTERS[$status]);
            }

            foreach (array_keys(self::LETTERS) as $position => $status) {
                $sheet->setCellValue($col($firstRecapColumn + $position).$row, count(array_keys($studentMarks, $status, true)));
            }
        }

        $lastRow = $firstDataRow + max($students->count(), 1) - 1;
        $this->style($sheet, $month, $headerRow, $firstDataRow, $lastRow, $firstDayColumn, $firstRecapColumn, $lastColumn);

        $note = $lastRow + 2;
        $sheet->setCellValue("A{$note}", 'Keterangan: H = Hadir, S = Sakit, I = Izin, A = Alpha.'.($this->subject
            ? ''
            : ' Bila dalam satu hari ada beberapa mata pelajaran, tanda diambil dari status terbanyak pada hari itu.'));
        $sheet->mergeCells("A{$note}:{$last}{$note}");
        $sheet->getStyle("A{$note}")->getFont()->setItalic(true)->setSize(9);
    }

    /**
     * The four title lines with the school's logo, as on the paper sheet.
     */
    private function letterhead(Worksheet $sheet, string $last, CarbonImmutable $month): void
    {
        [$semesterTwoStart] = $this->academicYear->semesterRange(2);
        $semester = $month->gte($semesterTwoStart->startOfMonth()) ? 2 : 1;

        $lines = [
            1 => ['DAFTAR HADIR SISWA', 14],
            2 => [mb_strtoupper((string) config('adminator.attendance_sheet.heading', config('adminator.brand.name'))), 13],
            3 => ['TAHUN PELAJARAN '.$this->academicYear->year_name, 12],
            4 => ['SEMESTER '.$semester.' ('.($semester === 1 ? 'GANJIL' : 'GENAP').')', 10],
        ];

        foreach ($lines as $row => [$text, $size]) {
            $sheet->setCellValue("A{$row}", $text)->mergeCells("A{$row}:{$last}{$row}");
            $sheet->getStyle("A{$row}")->getFont()->setBold(true)->setSize($size);
            $sheet->getStyle("A{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
            $sheet->getRowDimension($row)->setRowHeight(18);
        }

        $logo = public_path((string) config('adminator.attendance_sheet.logo'));

        if (is_file($logo)) {
            (new Drawing)
                ->setName('Logo')
                ->setPath($logo)
                ->setHeight(64)
                ->setCoordinates('B1')
                ->setOffsetX(8)
                ->setOffsetY(4)
                ->setWorksheet($sheet);
        }
    }

    /**
     * One mark per student per day of the month: [student id => [day => status]].
     *
     * @param  Collection<int, object>  $records
     * @return array<int, array<int, string>>
     */
    private function marks(Collection $records): array
    {
        $marks = [];

        foreach ($records->groupBy('student_id') as $studentId => $studentRecords) {
            foreach ($studentRecords->groupBy(fn (object $record): int => (int) substr($record->date, 8, 2)) as $day => $dayRecords) {
                $counts = $dayRecords->countBy('status')->all();
                $most = max($counts);

                // Most frequent that day; self::SEVERITY decides a tie.
                $marks[$studentId][$day] = collect(self::SEVERITY)->first(fn (string $status): bool => ($counts[$status] ?? 0) === $most);
            }
        }

        return $marks;
    }

    private function style(Worksheet $sheet, CarbonImmutable $month, int $headerRow, int $firstDataRow, int $lastRow, int $firstDayColumn, int $firstRecapColumn, int $lastColumn): void
    {
        $col = Coordinate::stringFromColumnIndex(...);
        $last = $col($lastColumn);
        $table = "A{$headerRow}:{$last}{$lastRow}";

        $sheet->getStyle($table)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        $sheet->getStyle($table)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
        $sheet->getStyle("C{$firstDataRow}:C{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT)->setIndent(1);
        $sheet->getStyle("A{$headerRow}:{$last}".($headerRow + 1))->getFont()->setBold(true);
        $sheet->getStyle("A{$headerRow}:{$last}".($headerRow + 1))->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('F1F5F9');

        // Sundays are shaded, so the weeks are easy to tell apart.
        for ($day = 1; $day <= $month->daysInMonth; $day++) {
            if ($month->setDay($day)->isSunday()) {
                $column = $col($firstDayColumn + $day - 1);
                $sheet->getStyle($column.($headerRow + 1).":{$column}{$lastRow}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('E2E8F0');
            }
        }

        $sheet->getColumnDimension('A')->setWidth(5);
        $sheet->getColumnDimension('B')->setWidth(11);
        $sheet->getColumnDimension('C')->setWidth(36);
        $sheet->getColumnDimension('D')->setWidth(5);

        for ($index = $firstDayColumn; $index <= $lastColumn; $index++) {
            $sheet->getColumnDimension($col($index))->setWidth($index < $firstRecapColumn ? 3.4 : 4.4);
        }

        for ($row = $headerRow; $row <= $lastRow; $row++) {
            $sheet->getRowDimension($row)->setRowHeight(19);
        }

        $sheet->freezePane('E'.$firstDataRow);
        $sheet->getPageSetup()
            ->setOrientation(PageSetup::ORIENTATION_LANDSCAPE)
            ->setPaperSize(PageSetup::PAPERSIZE_A4)
            ->setFitToWidth(1)
            ->setFitToHeight(0)
            ->setRowsToRepeatAtTopByStartAndEnd($headerRow, $headerRow + 1);
        $sheet->getPageMargins()->setTop(0.4)->setBottom(0.4)->setLeft(0.3)->setRight(0.3);
        $sheet->getPageSetup()->setHorizontalCentered(true);
    }

    /**
     * "XII Jul 2026": at most 31 characters, none of the characters Excel forbids, unique in the workbook.
     */
    private function sheetTitle(Spreadsheet $spreadsheet, string $className, CarbonImmutable $month): string
    {
        $suffix = ' '.$month->locale('id')->translatedFormat('M Y');
        $base = mb_substr(trim((string) preg_replace('/[\\\\\/?*\[\]:]+/', '-', $className)), 0, 31 - mb_strlen($suffix)).$suffix;
        $title = $base;

        for ($n = 2; $spreadsheet->getSheetByName($title) !== null; $n++) {
            $title = mb_substr($base, 0, 31 - strlen(" ({$n})"))." ({$n})";
        }

        return $title;
    }
}
