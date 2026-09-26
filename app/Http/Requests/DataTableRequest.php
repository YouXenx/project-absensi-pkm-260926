<?php

namespace App\Http\Requests;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\JsonResponse;

/**
 * Server-side processing for DataTables (https://datatables.net/manual/server-side).
 *
 * The browser sends draw/start/length/search/order; only one page of rows is
 * queried and returned. Column names are whitelisted by the controller.
 */
class DataTableRequest extends FormRequest
{
    public const MAX_LENGTH = 100;

    /**
     * Rows returned at most when the Excel/PDF/print buttons ask for every filtered row (?export=1&length=-1).
     */
    public const MAX_EXPORT_ROWS = 5000;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'draw' => ['nullable', 'integer', 'min:0'],
            'start' => ['nullable', 'integer', 'min:0'],
            'export' => ['nullable', 'boolean'],
            'length' => ['nullable', 'integer', $this->isExport() ? 'in:-1' : 'between:1,'.self::MAX_LENGTH],
            'search.value' => ['nullable', 'string', 'max:100'],
            'order' => ['nullable', 'array'],
            'order.*.column' => ['required_with:order', 'integer', 'min:0'],
            'order.*.dir' => ['required_with:order', 'in:asc,desc'],
            'columns' => ['nullable', 'array'],
            'columns.*.data' => ['nullable', 'string'],
        ];
    }

    /**
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @param  list<string>  $searchable  Columns matched with LIKE against the global search box.
     * @param  array<string, string>  $sortable  DataTables column "data" key => SQL column/expression.
     * @param  Closure(TModel): array<string, mixed>  $transform  Turns a model into one row. Escape anything rendered as HTML.
     * @param  array<string, string>  $fragments  CSS selector => HTML, swapped into the page on every draw (e.g. summary cards next to the table).
     */
    public function respond(Builder $query, array $searchable, array $sortable, Closure $transform, string $defaultOrder, array $fragments = []): JsonResponse
    {
        $recordsTotal = $query->toBase()->getCountForPagination();

        $search = trim((string) $this->input('search.value'));

        if ($search !== '') {
            $query->where(function (Builder $query) use ($searchable, $search): void {
                foreach ($searchable as $column) {
                    $query->orWhere($column, 'like', '%'.addcslashes($search, '%_\\').'%');
                }
            });
        }

        $recordsFiltered = $search === '' ? $recordsTotal : $query->toBase()->getCountForPagination();

        $this->applyOrder($query, $sortable, $defaultOrder);

        $rows = ($this->isExport()
            ? $query->take(self::MAX_EXPORT_ROWS)
            : $query->skip((int) $this->input('start', 0))->take((int) $this->input('length', 10)))
            ->get()
            ->map($transform)
            ->values();

        return response()->json([
            'draw' => (int) $this->input('draw', 0),
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data' => $rows,
            ...($fragments === [] ? [] : ['fragments' => $fragments]),
        ]);
    }

    /**
     * An export takes every filtered row (capped) instead of one page.
     */
    public function isExport(): bool
    {
        return $this->boolean('export');
    }

    /**
     * @param  Builder<covariant Model>  $query
     * @param  array<string, string>  $sortable
     */
    private function applyOrder(Builder $query, array $sortable, string $defaultOrder): void
    {
        $columns = $this->input('columns', []);
        $ordered = false;

        foreach ((array) $this->input('order', []) as $order) {
            $key = $columns[$order['column'] ?? -1]['data'] ?? null;

            if ($key !== null && isset($sortable[$key])) {
                $query->orderBy($sortable[$key], $order['dir'] === 'desc' ? 'desc' : 'asc');
                $ordered = true;
            }
        }

        if (! $ordered) {
            $query->orderBy($defaultOrder);
        }
    }
}
