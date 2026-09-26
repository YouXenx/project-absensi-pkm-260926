<?php

namespace App\Http\Controllers\Guru;

use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\View\View;

class DashboardController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [new Middleware('can:access-guru')];
    }

    /**
     * The signed-in guru's own account plus their assignments in the active academic year.
     * Every query goes through the guru's own relations, so other teachers' data never loads.
     */
    public function __invoke(Request $request): View
    {
        $teacher = $request->user();
        $academicYear = AcademicYear::current();

        return view('pages.guru.dashboard', [
            'teacher' => $teacher,
            'academicYear' => $academicYear,
            'subjects' => $academicYear
                ? $teacher->subjects()
                    ->with(['schoolClass' => fn ($query) => $query->withCount(['students' => fn ($query) => $query->active()])])
                    ->where('academic_year_id', $academicYear->academic_year_id)
                    ->orderBy('subject_name')
                    ->get()
                : collect(),
            'homeroomClasses' => $academicYear
                ? $teacher->homeroomAssignments()
                    ->with(['schoolClass' => fn ($query) => $query->withCount(['students' => fn ($query) => $query->active()])])
                    ->where('academic_year_id', $academicYear->academic_year_id)
                    ->get()
                    ->pluck('schoolClass')
                : collect(),
        ]);
    }
}
