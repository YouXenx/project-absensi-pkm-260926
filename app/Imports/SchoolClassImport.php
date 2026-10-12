<?php

namespace App\Imports;

use App\Models\SchoolClass;
use Illuminate\Support\Collection;

/**
 * Data Kelas from Excel: one class name per row.
 */
class SchoolClassImport extends MasterImport
{
    /** @var array<string, true> Names already taken, in lower case: existing classes plus earlier rows of the file. */
    private array $taken = [];

    public function title(): string
    {
        return 'Data Kelas';
    }

    public function noun(): string
    {
        return 'kelas';
    }

    public function indexRoute(): string
    {
        return 'admin.kelas.index';
    }

    public function headers(): array
    {
        return ['Nama Kelas'];
    }

    public function samples(): array
    {
        return [['Contoh: Kelas 7A'], ['Contoh: Kelas 7B']];
    }

    public function notes(): array
    {
        return ['Nama kelas paling banyak 50 karakter dan tidak boleh sama dengan kelas yang sudah ada.'];
    }

    protected function prepare(Collection $rows): void
    {
        $this->taken = array_fill_keys(SchoolClass::pluck('class_name')->map(fn (string $name): string => mb_strtolower($name))->all(), true);
    }

    protected function check(array $cells): array
    {
        [$name] = $cells;
        $key = mb_strtolower($name);

        $error = match (true) {
            $name === '' => 'Nama kelas wajib diisi.',
            mb_strlen($name) > 50 => 'Nama kelas maksimal 50 karakter.',
            isset($this->taken[$key]) => 'Nama kelas sudah ada, di aplikasi atau di baris sebelumnya.',
            default => null,
        };

        if ($error) {
            return [null, $error];
        }

        $this->taken[$key] = true;

        return [['class_name' => $name], null];
    }

    protected function create(array $attributes): void
    {
        SchoolClass::create($attributes);
    }
}
